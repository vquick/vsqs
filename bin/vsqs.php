<?php
/**
 * PHP Simple Queue Service 消息队列
 * 
 * 运行要求：
 * Linux2.6内核 + PHP>=5.3 + Cli模式 + Socket扩展 + Pcntl扩展
 * 
 * @version 1.0
 * @author V哥
 */

// 系统版本号
define('VSQS_VERSION', '1.0');

// 系统根目录
define('VSQS_ROOT', realpath(dirname(__FILE__).'/../'));

// 设置不超时
set_time_limit(0);

// 引入配置文件
$conf = parse_ini_file(VSQS_ROOT.'/conf/vsqs.ini',true);

// 设置php错误
$error = $conf['debug'] ? E_ALL : E_COMPILE_ERROR|E_ERROR|E_CORE_ERROR;
error_reporting($error);

// 将打开绝对（隐式）刷送
ob_implicit_flush(); 

// 必要要设置 declare 机制，因为 pcntl_signal 是基于它实现的
declare(ticks = 1);

// 设置时区
date_default_timezone_set($conf['timezone']);

// 引入系统类
require VSQS_ROOT.'/bin/lib/sys.php';

// HTTP 协议解析类
require VSQS_ROOT.'/bin/lib/http.php';

// SOCKET 套接字操作
require VSQS_ROOT.'/bin/lib/socket.php';

// 队列操作
require VSQS_ROOT.'/bin/lib/queue.php';

// 服务程序
require VSQS_ROOT.'/bin/lib/sqs.php';

// 载入配置文件
vsqs_sys::loadConf($conf);

// 检查系统运行的必备条件,不成立则直接退出
vsqs_sys::checkSystem();

// 运行服务
vsqs_sqs::run();
