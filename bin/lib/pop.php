<?php
/**
 * VSQS 出队处理
 * 
 * @version 1.0
 * @author V哥
 */
class vsqs_pop
{
	/**
	 * 出队错误定义
	 *
	 * @var unknown_type
	 */
	static private $_popError =  array('VSQS_POP_END','VSQS_POP_ERROR');
	
	/**
	 * 是否停止出队服务
	 *
	 * @var unknown_type
	 */
	static private $_isStopServer = false;
	
	/**
	 * 开始运行
	 *
	 */
	static public function run()
	{
		// 得到系统配置
		$conf = vsqs_sys::getConf();
		
		// 如果没有定义自动出队配置
		if($conf['pop_heartbeat'] <= 1){
			vsqs_sys::log('VPOP SERVER STOP: [pop_heartbeat] define 0');
			exit(0);
		}
		
		// 将服务转为守护进程模式
		$pid = vsqs_sys::becomeDaemon();
				
		// 记录主进程PID，用于停止服务
		file_put_contents(VSQS_ROOT.'/logs/vpop.pid', $pid);
				
		// 信号管理
		self::_signalManage();
		
		// 定时执行出队
		while(true)
		{
			// 如果没有接收到终止信号
			if(! self::$_isStopServer){
				sleep($conf['pop_heartbeat']);
				self::_popQueue($conf);
			}
		}
	}
	
	/**
	 * 信号管理
	 *
	 */
	static private function _signalManage()
	{
		// 通知内核，对子进程的结束不感兴趣，那么子进程结束后，内核会回收
		pcntl_signal(SIGCHLD, SIG_IGN);
		
		// 必要要设置 declare 机制，因为 pcntl_signal 是基于它实现的
		declare(ticks = 1);
		
		// 安装信号回调
		if (! pcntl_signal(SIGTERM, array(__CLASS__, '_signal'))){
			vsqs_sqs::halt('Bind Signal Handler for SIGTERM failed');
		}
	}
	
	/**
	 * 信号处理句柄
	 *
	 */
	static public function _signal()
	{
		// 终止服务
		self::$_isStopServer = true;
		
		// 等待出队通知运行完(这里不是等级子进程，而是等待出队的过程可以结束)
		sleep(1);
		
		// 安全退出
		$conf = vsqs_sys::getConf();
		if($conf['debug']){
			vsqs_sys::log('server stop');
		}
		exit(0);
	}
	
	/**
	 * 执行出队
	 *
	 */
	static private function _popQueue($conf)
	{
		// 遍历所有要出队的队列
		foreach($conf['pop_exec'] as $qname=>$cmd)
		{
			$ret = vsqs_sys::execQueue(array(
				'auth'=>$conf['auth'],
				'opt'=>'pop',
				'name'=>$qname,
			));
			// 如果出队正常
			if(! in_array($ret, self::$_popError)){
				$pid = pcntl_fork();
				if($pid == -1){
					// fork 失败则继续
					vsqs_sys::log('VPOP SERVER: fork child fail', 1);
					continue;
				}elseif ($pid == 0){
					// 由子进程来处理执行出队程序，它的死活不用关心
					$shellCmd = sprintf($cmd, $ret);
					if($conf['debug']){
						vsqs_sys::log("VPOP SERVER: pop run=>$shellCmd");
					}
					shell_exec(sprintf($cmd, $ret));
					exit(0);
				}
			}
		}
	}
}
