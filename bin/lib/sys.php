<?php
/**
 * PHP Simple Queue Service 系统功能类
 * 
 * @author V哥
 */
class vsqs_sys
{
	/**
	 * 系统配置
	 *
	 */
	static private $_conf = null;
	
	/**
	 * 载入配置
	 *
	 * @param array $conf
	 * @return void
	 */
	static public function loadConf($conf)
	{
		// 如果是内存型DB，只能启动一个子工作进程
		if($conf['db_type'] == 'memory'){
			$conf['server_num'] = 1;
		}
		// 保存
		self::$_conf = $conf;
	}
	
	/**
	 * 得到系统配置
	 *
	 * @return array
	 */
	static public function getConf()
	{
		return self::$_conf;
	}
	
	/**
	 * 检测系统，如果不符和运行条件将会终止程序
	 *
	 * @return void
	 */
	static public function checkSystem()
	{
		// PHP必需是 >=5.3
		if(!version_compare(PHP_VERSION, '5.3','>=')){
			self::halt('PHP Version mast >=5.3');
		}
		// 必要安装的扩展
		$exts = array('sockets','posix','pcntl','sqlite3');
		foreach ($exts as $name){
			if(!extension_loaded($name)){
				self::halt(sprintf('PHP Extensions %s not find', strtoupper($name)));
			}
		}
	}

	/**
	 * 将当前进程设置为守护进程模式
	 *
	 * @return int
	 */
	static public function becomeDaemon()
	{
		// 如果是调度模式
		if(self::$_conf['debug']){
			return posix_getpid();
		}
		
		// 生成子进程
		$pid = pcntl_fork(); 
		if($pid == -1){
			// fork 子进程失败
			self::halt('become daemon failure');
			exit(1); 
		}elseif($pid > 0){ 
			// 结束父进程，使子进程成为新会话的进程组首进程
			exit(0); 
		}else{ 
			// 子进程成为会话的进程组长
			posix_setsid(); 
			chdir('/'); 
			umask(0); 
			return posix_getpid();
		}	
	}
		
	/**
	 * 执行队列操作
	 *
	 * @param array $params 执行队列所需要的参数
	 */
	static public function execQueue($params=array())
	{	
		// 如果系统需要验证
		if(self::$_conf['auth'] != ''){
			// 没有设置访问密码或密码不对
			if(!isset($params['auth']) || $params['auth']!=self::$_conf['auth']){
				return 'VSQS_AUTH_FAIL';				
			}
		}
		
		// 必要要有 &opt 参数
		if(!isset($params['opt'])){
			return 'VSQS_PARAM_ERROR';	
		}
		$opt = $params['opt'];
		
		// &opt 是否是合法的动作	
		if(!in_array($opt, array('push','pop','clear','status','clearall'))){
			return 'VSQS_OPT_ERROR';
		}
		
		// 有些操作必要要有 &name 操作
		if(in_array($opt, array('push','pop','clear')) && !isset($params['name'])){
			return 'VSQS_PARAM_ERROR';
		}		
		
		// 如果是入队则要有 &data 参数
		if($opt=='push' && !isset($params['data'])){
			return 'VSQS_PARAM_ERROR';
		}
		
		// 判断队列名是否合法(只能是以字母开头)
		if(isset($params['name']) && ! preg_match('/^[a-zA-Z]\w+$/', $params['name'])){
			return 'VSQS_PARAM_ERROR';
		}
		
		// 执行指定的动作	
		$queue = vqsq_queue::getInstance();
		$result = $queue->{$opt}($params);
		
		// 返回值对应的状态码
		$retMap = array(
			'push'=>array(
				'true'=>'VSQS_PUSH_OK',
				'false'=>'VSQS_PUSH_ERROR',
			),
			'pop'=>array(
				'NULL'=>'VSQS_POP_END',
				'false'=>'VSQS_POP_ERROR',
			),	
			'clear'=>array(
				'true'=>'VSQS_CLEAR_OK',
				'false'=>'VSQS_CLEAR_ERROR',
			),			
			'clearall'=>array(
				'true'=>'VSQS_CLEARALL_OK',
				'false'=>'VSQS_CLEARALL_ERROR',
			),			
		);
		$ret = var_export($result,true);
		return isset($retMap[$opt], $retMap[$opt][$ret]) ? $retMap[$opt][$ret] : $result;
	}
	
	/**
	 * 终止程序，并记录日志
	 *
	 * @param string $error
	 */
	static public function halt($error)
	{
		self::log($error,1);
		exit(1);
	}
	
	/**
	 * 写日志
	 *
	 * @param string $log
	 * @param int $type 日志类型 0:运行日志 1:错误日志
	 */
	static public function log($log, $type=0)
	{
		$logFileMap = array(
			0=>'run.log',
			1=>'error.log',
		);
		// 如果是调度模式
		if(self::$_conf['debug']){
			echo $logFileMap[$type].'=>'.$log.PHP_EOL;
		}
		file_put_contents(VSQS_ROOT.'/logs/'.$logFileMap[$type], $log.PHP_EOL, FILE_APPEND);
	}
}
