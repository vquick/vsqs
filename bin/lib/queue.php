<?php
/**
 * 队列操作
 * 
 * @author V哥
 */
// 队列依赖 SQLite3
require VSQS_ROOT.'/bin/lib/sqlite.php';
class vqsq_queue
{
	/**
	 * SQLite3 连接句柄
	 *
	 * @var unknown_type
	 */
	private $_db = null;
	
	/**
	 * 单例模式
	 *
	 * @var unknown_type
	 */
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
	private function __construct()
	{
		// 得到DB的配置
		$conf = vsqs_sys::getConf();
		if($conf['db_type'] == 'memory'){
			$dbfile = ':memory:';
		}else{
			$dbfile = $conf['db_file']=='' ? VSQS_ROOT.'/db/vsqs.db' : $conf['db_file'];
		}
		$this->_db = vqsq_sqlite::getInstance($dbfile);
	}
	
	/**
	 * 得到所有的队列名
	 *
	 */
	private function _allQueue()
	{
		$ret = $this->_db->allTables();
		return $ret ? $ret : array();
	}
	
	/**
	 * 创建队列（如果不存在）
	 *
	 * @param string $queueName 队列名
	 */
	private function _useQueue($queueName)
	{
		$this->_db->beginTransaction();
		$allQueueName = $this->_allQueue();
		// 如果不存在就新建表，一个队列名就是一个单独的数据表
		if(! in_array($queueName, $allQueueName)){
			if($this->_db->execute(sprintf('CREATE TABLE "%s" ("queue" TEXT)', $queueName)) === false){
				$this->_db->rollBack();
				return false;
			}
		}
		$this->_db->commit();
		return true;
	}
	
	/**
	 * 入队
	 *
	 * @param array $params 请求参数
	 * 
	 * @return boolean
	 */
	public function push($params=array())
	{
		if(!$this->_useQueue($params['name'])){
			return false;
		}
		
		$sql = sprintf("insert into \"%s\" values('%s')", $params['name'], $this->_db->escape($params['data']));
		$this->_db->beginTransaction();
		if($this->_db->execute($sql) === false){
			$this->_db->rollBack();
			return false;
		}
		$this->_db->commit();
		return true;
	}
	
	/**
	 * 出队
	 *
	 * @param array $params 请求参数
	 * 
	 * @return string | false(失败) | null (队列是空)
	 */
	public function pop($params=array())
	{
		if(!$this->_useQueue($params['name'])){
			return false;
		}
		
		// 是否为空队列
		if($this->_db->fetchOne(sprintf("select count(*) from \"%s\"", $params['name'])) < 1){
			return null;
		}
		
		// 队数据
		$sql = sprintf("select rowid as rid,queue from \"%s\" order by rid asc limit 1", $params['name']);
		$this->_db->beginTransaction();
		$row = $this->_db->fetchRow($sql);
		// 如果队列是空了
		if(! $row){
			$this->_db->rollBack();
			return false;			
		}
		// 删除这一行
		$sql = sprintf("delete from \"%s\" where rowid=%d", $params['name'], $row['rid']);
		if($this->_db->execute($sql) === false){
			$this->_db->rollBack();
			return false;
		}		
		$this->_db->commit();
		return $row['queue'];
	}
	
	/**
	 * 清空指定队列
	 *
	 * @param array $params 请求参数
	 * 
	 * @return boolean
	 */	
	public function clear($params=array())
	{
		// 如果队列不存在
		$allQueueName = $this->_allQueue();	
		if(! in_array($params['name'], $allQueueName)){
			return false;
		}
		// 清除队列
		$sql = sprintf("DROP TABLE \"%s\"", $params['name']);
		$this->_db->beginTransaction();
		if($this->_db->execute($sql) === false){
			$this->_db->rollBack();
			return false;
		}		
		$this->_db->commit();
		// 重新整理数据库文件
		$this->_db->execute('vacuum');
		return true;
	}
	
	/**
	 * 清空所有队列
	 *
	 * @param array $params 请求参数
	 * 
	 * @return boolean
	 */	
	public function clearall($params=array())
	{
		// 如果没有队列
		$allQueueName = $this->_allQueue();	
		if(! $allQueueName){
			return true;
		}
		
		// 清除所有
		$this->_db->beginTransaction();
		foreach ($allQueueName as $qname){
			$sql = sprintf("DROP TABLE \"%s\"", $qname);
			if($this->_db->execute($sql) === false){
				$this->_db->rollBack();
				return false;
			}
		}
		$this->_db->commit();
		// 重新整理数据库文件
		$this->_db->execute('vacuum');
		return true;
	}	
	
	/**
	 * 得到队列的状态
	 *
	 * @param array $params 请求参数
	 * 
	 * @return string
	 */	
	public function status($params=array())
	{
		// 如果没有队列
		$allQueueName = $this->_allQueue();
		if(count($allQueueName) < 1){
			return 'queues=';
		}
		
		$ret = array('queues='.implode(',', $allQueueName));
		// 分别得到队列的记录数		
		$this->_db->beginTransaction();
		foreach ($allQueueName as $qname){
			$ret[] = $qname.'='.$this->_db->fetchOne(sprintf("select count(*) from \"%s\"", $qname));
		}
		$this->_db->commit();
		return implode('&', $ret);
	}

}