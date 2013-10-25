<?php
/**
 * PHP Simple Queue Service 服务
 * 
 * @author V哥
 */
class vsqs_sqs
{
	/**
	 * 保存系统配置
	 *
	 * @var unknown_type
	 */
	static private $_conf = array();
	
	/**
	 * 子进程的PID
	 *
	 */
	static private $_childPidArr = array();
	
	/**
	 * 当前服务是否要终止(服务接收到了要终止的信号)
	 *
	 * @var unknown_type
	 */
	static private $_serverIsStop = false;
	
	/**
	 * 当前服务端socket连接对象
	 *
	 * @var socket fd
	 */
	static private $_connect = null;
	
	/**
	 * 启动守护进程网关
	 * 
	 * @return void
	 */
	static public function run()
	{
		// 读出系统配置
		self::$_conf = vsqs_sys::getConf();
		
		// 将服务转为守护进程模式
		$pid = vsqs_sys::becomeDaemon();
				
		// 记录主进程PID，用于停止服务(因为它会生成很多的子进程)
		file_put_contents(VSQS_ROOT.'/logs/vsqs.pid', $pid);
				
		// 安装进程管理器
		self::_processManage();
		
		// 连接起监听
		self::$_connect = new vsqs_socket();
		self::$_connect->init(self::$_conf['bind_host'], self::$_conf['bind_port']);
		
		// 先预先启动多个进程用于处理监听
		for($i=1; $i<=self::$_conf['server_num']; ++$i){
			self::$_childPidArr[] = self::_fork();
		}
		
		// 父进程开始临视子进程，防止子进程意外挂掉了
		while(true)
		{
			sleep(1);
			// 如果当前父进程是正常服务中(没有收到终止信号)
			if(! self::$_serverIsStop){
				// 如果有子进程被意外终止了则重新 fork 出子进程
				$num = self::$_conf['server_num'] - count(self::$_childPidArr);
				for($i=1; $i<=$num; ++$i){
					$pid = self::_fork();
					self::$_childPidArr[] = $pid;
					vsqs_sys::log("reset fork child proccess {$pid}",1);
				}
			}
		}
		
		// 如果异常则终止整个服务(一般不会执行到这里来)
		exit(0);
	}
	
	/**
	 * fork() 子进程处理 socket
	 * 
	 * @return  int PID 子进程PID
	 */
	static private function _fork()
	{
		// fork()后子进程返回 0,父进程则返回子进程的 pid
		$pid = pcntl_fork();
		if($pid == 0){			
			// 子进程开始监听连接
			self::$_connect->loop(array(__CLASS__,'server'));
			
			// 防止子进程影响到父进程的上下文（正常情况下不会执行到这里，防止异常）
			exit(0);
		}elseif ($pid == -1){
			// fork 子进程失败
			self::halt('fork child process fail');
		}
		// 在父进程中则继续子进程的PID
		return $pid;
	}
		
	/**
	 * 执行请求答应
	 * 
	 * @param string $recvData :请求的原始报文
	 * @param string $clientIp :客户端IP地址
	 * 
	 * @return string
	 */
	static public function server($recvData, $clientIp='')
	{
		// 记录原始日志
		if(self::$_conf['debug']){
			file_put_contents(VSQS_ROOT.'/logs/recvdata.log', $recvData.PHP_EOL.PHP_EOL, FILE_APPEND);
		}
		
		// 得到 http 的实例
		$http = vsqs_http::getInstance();
		
		// 解析 HTTP 头,得到 $_GET/$_POST 
		$http->reset()->parse($recvData);
		
		// 合并 $_GET/$_POST 的数据
		$params = array_merge($_GET, $_POST);
		
		// 得到队列执行后的状态消息
		$body = vsqs_sys::execQueue($params);
		
		// 写日志
		self::_accessLog($clientIp, $http, $body, $params);	
			
		// 解析请求对应的响应内容
		return $http->getResponse(array(
			'status'=>200,
			'message'=>'OK',
			'body'=>$body,
		));
	}
	
	/**
	 * 记录访问日志
	 *
	 * @param string $ip 
	 * @param object $http
	 * @param array $body
	 * @param array $result
	 */
	static private function _accessLog($ip, $http, $body, $params)
	{
		// 日志文件名
		$logFile = self::$_conf['log_file'];
		
		// 如果没有定义日志文件则即出
		if($logFile == ''){
			return false;
		}
		
		// 得到日志格式:127.0.0.1 [2013-12-09 12:12:23] "GET /?name=q1&opt=pop" name=queue&opt=push VSQS_PUSH_OK
		$header = $http->getHead();
		$log = sprintf('%s [%s] "%s %s" %s %s', $ip, date('Y-m-d H:i:s'), $header['method'], $header['url'], 
		http_build_query($params), $body);
		
		// 得到完整的日志文件名
		$search = array('%Y','%m','%d','%H','%i');
		$replace = array(date('Y'),date('m'),date('d'),date('H'),date('i'));
		$file = VSQS_ROOT.'/logs/'.str_replace($search, $replace, $logFile);
		
		// 写日志
		if(self::$_conf['debug']){
			echo $log.PHP_EOL;
		}		
		file_put_contents($file, $log.PHP_EOL, FILE_APPEND);
		return true;
	}
	
	/**
	 * 进程管理,安装进程信号处理
	 *
	 */
	static private function _processManage()
	{
		/* 注册信号处理句柄 */
		$signalArr = array(
			SIGALRM => 'SIGALRM', // 闹钟信号,类似 setTimeout
			SIGCHLD => 'SIGCHLD', // 子进程结束时
			SIGCLD	=> 'SIGCLD',  // 子进程结束时
			SIGINT  => 'SIGINT',  // 程序终断(Ctrl+C)
			SIGHUP  => 'SIGHUP',  // 终端关闭
			SIGQUIT => 'SIGQUIT', // 常是(Ctrl-\)来控制错误退出. 进程在因收到SIGQUIT退出时会产生core文件
			SIGTERM => 'SIGTERM', // 一般是 kill 命令时的终止信号
		);
		foreach ($signalArr as $signo=>$signame){
			if (! pcntl_signal($signo, array(__CLASS__, "_signalHandler"))){
				vsqs_sqs::halt("Bind Signal Handler for $signame failed");
			}
		}
	}
	
	/**
	 * 信号处理句柄
	 *
	 * @param unknown_type $signo
	 */
	static public function _signalHandler($signo)
	{
		/**
		 * 避免僵尸进程常用的有以下几种方法：
		 * 1：父进程通过wait和waitpid等函数等待子进程结束，但这会导致父进程挂起，不利于大并发的要求。
		 * 2：父进程可以用signal函数为SIGCHLD安装handler，因为子进程结束后， 父进程会收到该信号，可以在handler中调用wait回收。
		 * 3：父进程可以用signal(SIGCHLD, SIG_IGN)通知内核，自己对子进程的结束不感兴趣，那么子进程结束后，内核会回收， 
		 * 并不再给父进程发送信号。但这种方法只适合Linux系统，Unix系统中则一定要调用 wait()。
		 * 4：fork两次，父进程fork一个子进程，然后继续工作，子进程fork一 个孙进程后退出，那么孙进程被init接管，孙进程结束后，init会回收。
		 */
		switch(intval($signo))
		{
			/* 子进程结束时信号 */
			case SIGCLD:
			case SIGCHLD:
				// 由于在并发状态下SIGCHLD信号到达服务器时，UNIX往往是不会排队，所以这里推荐采用 waitpid() 而不用 wait()
				// WNOHANG:即使没有子进程退出，也会立即返回
				// WUNTRACED:如果子进程进入暂停执行情况则马上返回,但结束状态不予以理会
				while( ($pid = pcntl_waitpid(-1, $status, WNOHANG|WUNTRACED)) > 0 ){
					vsqs_sys::log("child proccess {$pid} exited", 1);
					// 从保存的所有子进程PID的数组中去除,表示该了进程已经结束了
					if(in_array($pid, self::$_childPidArr)){
						unset(self::$_childPidArr[array_search($pid, self::$_childPidArr)]);
					}
				}
				break;
			
			/* 父进程结束的信号 */
			case SIGINT:
			case SIGQUIT:		
			case SIGTERM: 
			case SIGHUP:
				// 安全退出
				self::_exitServer();
				break;
		}
	}
	
	/**
	 * 安全即出整个服务
	 *
	 */
	static private function _exitServer()
	{
		// 标记当前服务要被终止了(不用自动修复子进程了)
		self::$_serverIsStop = true;
		
		// 父进程终止前要先终止所有的子进程，防止产生僵尸进程
		foreach (self::$_childPidArr as $pid){
			vsqs_sys::log("kill child proccess {$pid}");
			// SIGKILL:等同 kill -9 
			posix_kill($pid, SIGKILL);
		}
		
		// 关闭 socket 服务
		self::$_connect->close();
		
		// 等待所有的子进程全结束后终止整个服务
		sleep(1);
		file_put_contents(VSQS_ROOT.'/logs/vsqs.pid', '');
		exit(0);		
	}
}