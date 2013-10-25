<?php
/**
 * VSQS 队列系统 PHP 客户端开发 SDK
 * 
 * 依赖扩展：curl+json
 * 
 * @author V哥
 */
class vsqs_client
{
	/**
	 * 客户端认证密码
	 *
	 * @var string
	 */
	private $_auth = '';
	
	/**
	 * CURL对象
	 *
	 * @var curl object
	 */
	private $_curl = null;
	
	/**
	 * 构造函数
	 *
	 * @param string $host VSQS服务的IP或域名
	 * @param int $port VSQS服务的端口号
	 * @param int $timeout 连接服务器的超时时间
	 * @param string $auth 客户端验证的密码
	 */
	public function __construct($host='localhost', $port=8099, $timeout=2, $auth='')
	{
		$this->_auth = $auth;
		$url = "http://{$host}:{$port}/";
		$this->_curl = curl_init();
		curl_setopt($this->_curl, CURLOPT_URL, $url);
		curl_setopt($this->_curl, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($this->_curl, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($this->_curl, CURLOPT_HEADER, false);
		curl_setopt($this->_curl, CURLOPT_RETURNTRANSFER, true) ;
		curl_setopt($this->_curl, CURLOPT_BINARYTRANSFER, true) ;		
		curl_setopt($this->_curl, CURLOPT_PORT, $port);
		curl_setopt($this->_curl, CURLOPT_POST, true) ;
	}
	
	/**
	 * 数据入队
	 *
	 * @param string $queueName 队列名
	 * @param mixed $data 数据，可以是数组
	 * 
	 * @return true:成功 false:失败 null:异常
	 */
	public function push($queueName, $data)
	{
		$ret = $this->_callServer(array(
			'opt'=>'push',
			'name'=>$queueName,
			'data'=>json_encode($data),
		));
		if($ret == 'VSQS_PUSH_OK'){
			return true;
		}elseif ($ret == 'VSQS_PUSH_ERROR'){
			return false;
		}else{
			return null;
		}
	}
	
	/**
	 * 数据出队
	 *
	 * @param string $queueName 队列名
	 * 
	 * @return <data>出队的数据 false:失败 null:队列为空
	 */
	public function pop($queueName)
	{
		$ret = $this->_callServer(array(
			'opt'=>'pop',
			'name'=>$queueName,
		));
		if($ret == 'VSQS_POP_ERROR'){
			return false;
		}elseif ($ret == 'VSQS_POP_END'){
			return null;
		}else{
			return json_decode($ret, true);
		}		
	}
	
	/**
	 * 清空指定的队列
	 *
	 * @param string $queueName 队列名
	 * 
	 * @return true:成功 false:失败 null:异常
	 */
	public function clear($queueName)
	{
		$ret = $this->_callServer(array(
			'opt'=>'clear',
			'name'=>$queueName,
		));
		if($ret == 'VSQS_CLEAR_OK'){
			return true;
		}elseif ($ret == 'VSQS_CLEAR_ERROR'){
			return false;
		}else{
			return null;
		}	
	}	
	
	/**
	 * 清空所有的队列
	 *
	 * @return true:成功 false:失败 null:异常
	 */
	public function clearAll()
	{
		$ret = $this->_callServer(array(
			'opt'=>'clearall',
		));
		if($ret == 'VSQS_CLEARALL_OK'){
			return true;
		}elseif ($ret == 'VSQS_CLEARALL_ERROR'){
			return false;
		}else{
			return null;
		}
	}	
	
	/**
	 * 队列当前的状态
	 *
	 * @return 
	 * 
	 * false:异常 
	 * array():队列为空
	 * 
	 * 当队列有数据时，返回以下数据，其中队列名对应是数值是代表队列元素的个数
	 * array(
	 * 	'queue_name1'=>20,
	 * 	'queue_name2'=>20,
	 * )
	 */
	public function status()
	{
		$ret = $this->_callServer(array(
			'opt'=>'status',
		));
		parse_str($ret, $result);
		if(!is_array($result) || !isset($result['queues'])){
			return false;
		}
		// 队列是空
		if($result['queues'] == ''){
			return array();
		}else{
			unset($result['queues']);
			return $result;
		}
	}	
	
	/**
	 * 私有方法：请服务器发请求并返回结果
	 *
	 * @param array $request 请求数组
	 * @return string
	 */
	private function _callServer($request=array())
	{
		$request['auth'] = $this->_auth;
		curl_setopt($this->_curl, CURLOPT_POSTFIELDS, http_build_query($request));
		return trim(curl_exec($this->_curl));
	}
}
