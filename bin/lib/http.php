<?php
/**
 * HTTP 协议解析操作(只解析GET和POST)
 *
 * @author V哥
 */
class vsqs_http
{
	// 行分隔符
	const ENTER = "\r\n";
	
	// 协议与实体分隔符
	const ENTER2 = "\r\n\r\n";
	const ENTER2_LEN = 4;
	
	// 所有请求头行
	private $_header = array();
	
	// 应答属性，暂只支持以下属性值
	private $_response = array(
		'type'=>'text/html', // 文档类型
		'_other'=>array(),// 其它自定义头部
	);
	
	// 单例模式
	static private $_instance = null;
	
	/**
	 * 得到单个实例
	 *
	 * @return Request object
	 */
	static public function getInstance()
	{
		if (null === self::$_instance){
			self::$_instance = new self();
		}
		return self::$_instance;
	}	
	
	/**
	 * 构造函数
	 *
	 */
	private function __construct(){}
	
	/**
	 * 重置超全局变量（这里是因为采用的多进程的守护进程方式,所以在每次请求时都在初始华这些值）
	 *
	 * @return this
	 */
	public function reset()
	{
		// 初始化超全局变量
		$_GET = $_POST = array();
		// 初始化响应头
		$this->_response = array(
			'type'=>'text/html',
			'_other'=>array(),
		);
		return $this;
	}
	
	/**
	 * 返回解析后的协议头
	 *
	 * @return array
	 */
	public function getHead()
	{
		return $this->_header;
	}
	
	/**
	 * 解析请求头
	 *
	 * @param string $requestHeader
	 */
	public function parse($requestHeader)
	{
		// 协议头和实体是以 "\r\n\r\n" 来分隔的
		// (普通的post和get协议是这样的,但如果是扩展的post协议则只有第一个才算分隔符)
		list($header,$body) = explode(self::ENTER2,$requestHeader,2);
		
		// 解析头部
		$this->_parseHeader($header);
		
		// 解析URL得到 GET 全局变量
		$this->_parseGet();
		
		// 如果是POST协议才继续解决实体内容
		if($this->_header['method'] == 'POST'){
			$this->_parseBody($body);
		}
	}
	
	/**
	 * 设置响应头
	 *
	 * @param string $name
	 * @param string $value
	 */
	public function setResponse($name,$value)
	{
		if(array_key_exists($name, $this->_response)){
			$this->_response[$name] = $value;
		}else{
			$this->_response['_other'][$name] = $value;
		}
	}
	
	/**
	 * 返回要输出的响应内容
	 *
	 * @param array $result
	 * @return string
	 */
	public function getResponse($result)
	{
		// 如果有设置文件扩展名则要自动设置对应的 Content-Type
		if(isset($result['location'])){
			$this->setResponse('Location',$result['location']);
		}
		// 生成响应头
		$headerArr[] = sprintf("HTTP/1.1 %d %s",$result['status'],$result['message']);
		foreach ($this->_response['_other'] as $key=>$val){
			$headerArr[] = sprintf("%s: %s",$key,$val);
		}
		$headerArr[] = 'Server: PHP Simple Queue Servic '.VSQS_VERSION.' for Linux';
		$headerArr[] = 'Author: V哥';
		$headerArr[] = sprintf("Content-Length: %d",strlen($result['body']));
		$headerArr[] = 'Date: '.gmdate("D, d M Y H:i:s T");
		$headerArr[] = 'KeepAlive: off';
		$headerArr[] = 'Pragma: no-cache';
		$headerArr[] = 'Cache-Control: no-cache';
		$headerArr[] = 'Connection: close';
		$headerArr[] = sprintf("Content-Type: %s", $this->_response['type']);
		// 头和主休内容一起组成响应内容
		return implode(self::ENTER,$headerArr) . self::ENTER2 . $result['body'];
	}
	
	/**
	 * 解析http头
	 *
	 * @param string $headStr
	 */
	private function _parseHeader($headStr)
	{
		$header = array();
		// 得到第一行和其它所有行
		list($oneLine,$otherLine) = explode(self::ENTER,$headStr,2);
		// 解析第一行，因为第一行是状态行，它包括了请求方法，URL，协议版本.如:"GET /index.html HTTP/1.1"
		list($header['method'],$header['url'],$header['ver']) = explode(' ',$oneLine);
		// 合并其它行的解释
		$header = array_merge($header, $this->_parseLine($otherLine));
		// 得到主机名和端口
		$header['hostname'] = $header['host'];
		$header['port'] = 80;
		if(strpos($header['host'], ':') !== false){
			list($header['hostname'],$header['port']) = explode(':',$header['host']);
		}		
		// 得到的请求的文件和查询字符串 (path|query)
		$header = array_merge($header, parse_url($header['url']));
		$this->_header = $header;
	}
	
	/**
	 * 解析 GET 全局变量
	 *
	 */
	private function _parseGet()
	{
		// 解析 GET
		if(isset($this->_header['query'])){
			parse_str($this->_header['query'],$_GET);
		}
	}
	
	/**
	 * 解析实体(post协议才有)
	 *
	 * @param string $body
	 */
	private function _parseBody($body)
	{
		/**
		 * POST 协议又分为两种
		 * 
		 * 一种的简单key/value的形式，如下：
		 * -------------------------------------------------------------------------
		 * Content-Type: application/x-www-form-urlencoded
		 * Content-Length: 76
		 * 
		 * u=chenmx&p=aae499e566b72ff48d71294dd8830c41&verifycode=%21N6R&login_enable=1
		 * -------------------------------------------------------------------------
		 * 
		 * 一种是扩展的 RFC1867协议主要是在HTTP协议的基础上为INPUT标签增加了file属性，
		 * 同时限定了Form的method必须为POST，ENCTYPE 必须为multipart/form-data,如下：
		 * ======================================================================================
		 * Content-Type: multipart/form-data; boundary=---------------------------217342584625820 
		 * Content-Length: 3035
		 * 
		 * -----------------------------217342584625820 (注意:这里的 '-' 比上面boundary字段值多2个)
		 * Content-Disposition: form-data; name="title"
		 * 
		 * title1
		 * -----------------------------217342584625820
		 * Content-Disposition: form-data; name="file"; filename="a.js"
		 * Content-Type: application/x-js
		 * 
		 * var fun = function(){alert('ok')};
		 * -----------------------------217342584625820-- (注意:这里会有2个 '-')
		 * ======================================================================================
		 */
		// 只取协议中规定的长度内容
		$body = substr($body,0,$this->_header['content-length']);
		// 普通形式
		$pos = strpos($this->_header['content-type'], 'boundary=');
		if($pos === false){
			parse_str($body,$_POST);
		}else{
			$this->_parseBoundary($body);
		}
	}
	
	/**
	 * 解析HTTP RFC1867 扩展协议的POST内容
	 * 
	 * @param string $body 整个 body 内容
	 */
	private function _parseBoundary($body)
	{
		// 得到数据段的“分割线”标识
		list($tmp, $boundayVal) = explode('boundary=', $this->_header['content-type']);
		$boundary = '--'.$boundayVal;
		// 所有所有的数据段
		foreach (explode($boundary,$body) as $dataStr){
			// 如果是最后2个'-'则表示整个数据段都结束了
			if(strlen(trim($dataStr)) <= 2){
				continue;
			}
			
			// 得到数据段中字段与内容的分隔符(第一个"\r\n\r\n")
			$pos = strpos($dataStr,self::ENTER2);
			
			// 分离出名称和对应的POST值,此时 $disposition 的值可能是如下形式的数据头(类似请求头)：
			// -----------------------------------------------------------
			// Content-Disposition: form-data; name="imgfile"; filename="a.jpg"
			// Content-Type: application/octet-stream
			// -----------------------------------------------------------
			//echo '$dataStr=';var_dump($dataStr);
			//echo '$pos=';var_dump($pos);
			$disposition = substr($dataStr,0,$pos);
			
			// 此时 $value 为提交的内容，可以是表单中 input 的值，也可以是上传文件的内容
			$value = substr($dataStr,$pos+self::ENTER2_LEN);
			
			//echo '$disposition=';var_dump($disposition);
			
			// 解析数据头
			$dateHead = $this->_parseLine($disposition);
			//echo '$dateHead=';var_dump($dateHead);
			// 解析 Content-Disposition 的数组段内容
			$dispField = $this->_parseField($dateHead['content-disposition'],true);
			// 如果是上传的文件
			if(isset($dateHead['content-type']) && isset($dispField['filename'])){
				// (待续)
			}else{
				// 直接的 POST 数据
				$str = $dispField['name'].'='.$value;
				parse_str($str,$ret);
				if(is_array($ret)){
					$_POST = array_merge($_POST,$ret);
				}
			}
		}
	}

	/**
	 * 解析协议行，类似以下格式的输入：
	 *
	 * @param string $lines 协议行(可以是多行)，类似以下格式：
	 * 
	 * Keep-Alive: timeout=5, max=100
	 * Connection: Keep-Alive
	 * Content-Type: text/html
	 * 
	 * @return array
	 */
	private function _parseLine($lines)
	{
		$result = array();
		$lineArr = explode(self::ENTER, $lines);
		foreach ($lineArr as $line){
			if(trim($line) == ''){
				continue;
			}
			list($key,$value) = explode(':',$line);
			$result[strtolower($key)] = ltrim($value);
		}
		return $result;
	}
	
	/**
	 * 解析字段，得到对应的值。
	 *
	 * @param string $fieldValue 多字段值，格式如下：
	 * 
	 * 'form-data; name="imgfile[1]"; filename="a.js"' 或 _xltj=1; a23=2; PHPSESSID=1ac6
	 * 
	 * @param boolean $isRemoveQou
	 * @return string
	 */
	private function _parseField($fieldValue,$isRemoveQou=false)
	{
		$result = array();
		foreach (explode(';',$fieldValue) as $field){
			if(strpos($field,'=') === false){
				continue;
			}else{
				list($key,$val) = explode('=',$field);
				// 是否要去掉值两边的双引号(一般用在解析扩展POST数据头时用到)
				if($isRemoveQou && substr($val,0,1)=='"'){
					$val = substr($val,1,strlen($val)-2);
				}
				$result[trim($key)] = $val;
			}
		}
		return $result;
	}
}
