<?php
/**
 * SOCKET
 *
 * @author V哥
 */
class vsqs_socket
{
	// scoket 句柄
	private $_socket = null;
	
	/**
	 * 构造函数
	 *
	 */
	public function __construct(){}
	
	/**
	 * 初始化
	 *
	 * @param unknown_type $host
	 * @param unknown_type $port
	 * @return unknown
	 */
	public function init($host,$port)
	{
		// 创建 socket
		$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if(! $sock){
			$this->_halt('socket create fail');
		}
		// 绑定到端口
		if(! socket_bind($sock, $host, $port)){
			$this->_halt('socket bind fail');
		}
		// 开始监听
		if(! socket_listen($sock)){
			$this->_halt('socket listen fail');
		}
		// 设置为非阻塞模式
///*		if(! socket_set_nonblock($sock)){
//			$this->_halt('socket set nonblock fail');
//		}*/
		
		$this->_socket = $sock;
		return $this;
	}

	/**
	 * 开始监听
	 *
	 * @param function $responseCallback 临听后的回调函数
	 */
	public function loop($responseCallback=null)
	{
		while(true)
		{
			// 创建客户端连接
			$connection = socket_accept($this->_socket);
			if($connection === false || $connection < 1){
				usleep(100);
				continue;
			}
		
			// 读内容
			$data = $this->read($connection);
			if($data){
				// 得到客户端连接的IP地址
				socket_getpeername($connection, $ip);		
				// 回调内容处理
				$response = call_user_func_array($responseCallback, array($data,$ip));
				// 写内容
				$this->write($connection, $response);
			}
			
			// 关闭连接
			$this->close($connection);
		}
	}
	
	/**
	 * 写 scoket
	 *
	 * @param unknown_type $socket
	 * @param unknown_type $data
	 * @return unknown
	 */
	public function write($socket,$data)
	{
		$length = strlen($data);
		while(true){
			$sentLen = socket_write($socket, $data, $length);
			if($sentLen === false) return false;
			if($sentLen < $length){
				$data = substr($data, $sentLen);
				$length -= $sentLen;
			}else{
				break;
			}
		}
		return true;
	}
	
	/**
	 * 读 scoket
	 *
	 * @param unknown_type $socket
	 * @param unknown_type $length
	 * @return unknown
	 */
	public function read($socket,$length=1024)
	{
		$data = '';
		while($buf = socket_read($socket,$length)){
			if($buf === false) break;
			$data .= $buf;
			if($buf === '' || strlen($buf) < $length) break;
		}
		return $data;
	}

	/**
	 * 关闭连接
	 *
	 * @param unknown_type $socket
	 */
	public function close($socket=null)
	{
		if(null === $socket){
			$socket = $this->_socket;
		}
		socket_shutdown($socket);  
		socket_close($socket);
	}
	
	/**
	 * 错误处理
	 *
	 * @param unknown_type $msgpre
	 */
	private function _halt($msgpre)
	{
		vsqs_sys::halt($msgpre.' socketerr:'.socket_strerror(socket_last_error()));
	} 
	
}
