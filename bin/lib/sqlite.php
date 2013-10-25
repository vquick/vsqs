<?php
/**
 * SQLite3 操作
 * 
 * @author V哥
 */
class vqsq_sqlite
{
	/**
	 * DB连接句柄
	 *
	 * @var unknown_type
	 */
	private $_link = null;
	
	/**
	 * 查询句柄
	 *
	 * @var unknown_type
	 */
	private $_query = null;
	
	/**
	 * 单例模式
	 *
	 * @var unknown_type
	 */
	static private $_instance = null;
	
	/**
	 * 得到单个实例
	 *
	 * @param string $dbfile 数据库文件
	 * @return Request object
	 */
	static public function getInstance($dbfile)
	{
		if (null === self::$_instance){
			self::$_instance = new self($dbfile);
		}
		return self::$_instance;
	}
		
	/**
	 * 构造函数
	 * 
	 * @param string $dbfile 数据库文件
	 */
	private function __construct($dbfile)
	{
		// 碰盘文件： /opt/databases/mydb.sq3 
		// 内存文件: :memory: 
		$this->_link = new SQLite3($dbfile);
		if(! $this->_link){
			$this->_error('connection '.$dbfile.' fail');
		}		
	}
	
	/**
	 * 格式化用于数据库的字符串
	 *
	 * 
	 * @param string $str
	 * @return string
	 */
	public function escape($str)
	{
		return $this->_link->escapeString($str);
	}
	
	/**
	 * 执行没有结果集的SQL，一般是删除、更新，创建等
	 *
	 * @param unknown_type $sql
	 */
	public function execute($sql)
	{
		//echo $sql.PHP_EOL;
		return $this->_link->exec($sql);
	}
	
	/**
	 * 执行有结果集的SQL
	 *
	 * @param unknown_type $sql
	 */
	public function query($sql)
	{
		//echo $sql.PHP_EOL;
		$this->_query = @$this->_link->query($sql);
		if(! $this->_query){
			$this->_query = null;
			$this->_error('SQL Error:'.$sql);
		}
		return $this->_query;
	}
	
	/**
	 * 关闭查询结果集
	 *
	 */
	private function _closeQuery()
	{
		if($this->_query){
			$this->_query->finalize();
		}
	}
	
	/**
	 * 得到数据库中所有的表
	 *
	 */
	public function allTables()
	{
		$sql = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'";
		return $this->fetchCol($sql);
	}
	
	/**
	 * 执行SQL并返回所有结果集
	 * 
	 * @param string $sql
	 * @return array
	 */
	public function fetchAll($sql)
	{
		$ret = array();
		if(!$this->query($sql)){
			return $ret;
		}
		while($row = $this->_query->fetchArray(SQLITE3_ASSOC)){
			$ret[] = $row;
		}
		$this->_closeQuery();
		return $ret;
	}
	
	/**
	 * 执行SQL并返回结果集第一行的所有值
	 *
	 * @param string $sql
	 * @return array
	 */
	public function fetchRow($sql)
	{
		if(!$this->query($sql)){
			return array();
		}
		$ret = $this->_query->fetchArray(SQLITE3_ASSOC);
		$this->_closeQuery();
		return $ret ? $ret : array();			
	}	
		
	/**
	 * 执行SQL并返回结果集第一列的所有值
	 *
	 * @param string $sql
	 * @return array
	 */
	public function fetchCol($sql)
	{
		$ret = array();
		if(!$this->query($sql)){
			return $ret;
		}
		while($row = $this->_query->fetchArray(SQLITE3_ASSOC)){
			$ret[] = current($row);
		}
		$this->_closeQuery();
		return $ret;
	}

	/**
	 * 执行SQL并返回结果集第一行第一列的值
	 *
	 * @param string $sql
	 * @return mixed 
	 */
	public function fetchOne($sql)
	{
		$ret = $this->fetchRow($sql);
		return $ret ? current($ret) : false;
	}
		
	/**
	 * 开始事务
	 * 
	 * @return bool
	 */
	public function beginTransaction()
	{
		return $this->execute('begin');
	}

	/**
	 * 提交事务
	 * 
	 * @return bool
	 */
	public function commit()
	{
		return $this->execute('commit');
	}

	/**
	 * 事务回滚
	 * 
	 * @return bool
	 */
	public function rollBack()
	{
		return $this->execute('rollback');
	}
		
	/**
	 * 输出错误并退出
	 *
	 * @param unknown_type $error
	 */
	private function _error($error)
	{
		$msg = 'Errno:'.$this->_link->lastErrorCode().' Error:'.$this->_link->lastErrorMsg().' Sql:'.$error;
		vsqs_sys::log($msg, 1);
	}
}

/*
$db = vqsq_sqlite::getInstance('test.db');

echo $db->escape("a'er\"'");
exit;

// 新建一个表
$db->execute('CREATE TABLE "aaa1" ("name" TEXT)');
 // 插入数据
$db->execute("insert into aaa1 values('aa')");
$db->execute("insert into aaa1 values('bb')");

$sql = "select name from aaa";
print_r($db->fetchAll($sql));
print_r($db->fetchCol($sql));
print_r($db->fetchRow($sql));
print_r($db->fetchOne($sql));

print_r($db->allTables());

// print_r($db->allTables());
//*/