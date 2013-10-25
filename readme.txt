
[说明]

vsqs 是一个用PHP开发的简单队列服务程序，它支持基于文件系统或纯内存的工作方式，
适合与各种场景，它除了有常规的出队/入队功能外，同时还支持自动出队操作（不需要
你额外开发出队功能），从而让你的开发变得更简单。


[软件协议]

本软件完全开源使用，使用个人可以二次修改后使用。


[系统要求]

1：必须是 Linux 类系统
2：必须要安装 PHP 版本 >= 5.3
3：必须要安装 PHP 扩展有：sockets,posix,pcntl,sqlite3,json


[安装步骤]

1：安装好 PHP ，例如安装在: /data/soft/php

2：将 vsqs 整个目录复制到服务器，例如：/data/soft/vsqs

3: 修改 vsqs/vsqs.sh 中的PHP和VSQS的路径定义:
------------ vi vsqs.sh ---------------
PHP_CMD="/data/soft/php/bin/php"; # 安装的 php 可执行文件的绝对路径
VSQS_PATH="/data/soft/vsqs";      # vsqs 的安装根目录
---------------------------------------

4: 执行以下命令启动服务
chmod a+x vsqs.sh
./vsqs.sh start

5: 检验是否启动成功,执行: netstat -nlpt | grep '8099' 有类似如下即代表成功:
vlinux:~ # netstat -nlpt | grep '8099'
tcp        0      0 0.0.0.0:8099            0.0.0.0:*               LISTEN      3518/php 

6: 测试,在服务器上执行以下命令后会有类似输出（也可以用浏览器打开对应URL，只要将其中的 localhost 改为服务器的IP）
vlinux:~ # curl "http://localhost:8099/?opt=push&name=test&data=100"
VSQS_PUSH_OK

7: 其它详细配置请仔细阅读: conf/vsqs.ini




========================= [详细使用说明] ======================

[使用协议]

基于标准的 HTTP 1.1 协议，所有的操作都同时支持: GET / POST


[全局错误返回码]

VSQS_AUTH_FAIL   : 认证失败
VSQS_PARAM_ERROR : 缺少必要的参数 &name|&opt|&data
VSQS_OPT_ERROR   : &opt 参数不合法


[入队操作]
http://host:port/?name=queue_name&opt=push&data=经过URL编码的文本消息[&auth=mypass]

返回值:
VSQS_PUSH_OK    :成功
VSQS_PUSH_ERROR :失败

[出队操作]
http://host:port/?name=queue_name&opt=pop[&auth=mypass]

返回值:
VSQS_POP_ERROR :失败
VSQS_POP_END   :队列为空

[清空指定队列操作]
http://host:port/?name=queue_name&opt=clear[&auth=mypass]

返回值:
VSQS_CLEAR_OK    :成功
VSQS_CLEAR_ERROR :清空失败,一般是队列不存在

[清空所有队列操作]
http://host:port/?opt=clearall[&auth=mypass]

返回值:
VSQS_CLEARALL_OK    :成功
VSQS_CLEARALL_ERROR :清空失败

[得到队列状态操作]
http://host:port/?opt=status[&auth=mypass]

返回值:
queues=                   :当前没有队列
queues=q1,q2&q1=100&q2=10 :有两个队列名: q1,q2 ,其中 q1 的队列元素:100个，q2 的队列元素：10个



========================= [客户端开发SDK示例] ======================

[PHP CLIENT DEMO]

<?php
require "vsqs/sdk/vsqs_client.php";
$queue = new vsqs_client('192.168.0.250', 8099);

// 入队
var_dump($queue->push('aa1', array('id'=>1,'name'=>'V哥')));

// 出队
print_r($queue->pop('aa1'));

// 查看服务的状态
print_r($queue->status());

// 清空指定队列
var_dump($queue->clear('aa1'));

// 清空所有队列
var_dump($queue->clearAll());


[关于软件]

作者：V哥
QQ群: 2995220
