#!/bin/bash
#-------------------------------------------
# PHP Simple Queue Service
#
# @version 1.0
# @author V哥
#-------------------------------------------

PHP_CMD="/data/soft/php/bin/php";
VSQS_PATH="/data/soft/vsqs";

function start_server(){
	cd $VSQS_PATH
	$PHP_CMD ./bin/vsqs.php &
	$PHP_CMD ./bin/vpop.php &
}
function stop_server(){
	cd $VSQS_PATH
	kill `cat ./logs/vsqs.pid` >/dev/null 2>&1
	kill `cat ./logs/vpop.pid` >/dev/null 2>&1
}
function restart_server(){
	stop_server;
	start_server;
}
# 检测参数
case $1 in
	"start")
		start_server;
		;;
	"stop")
		stop_server;
		;;
	"restart")
		restart_server;
		;;
	*)
		echo $0 "(start|stop|restart)";
		;;
esac