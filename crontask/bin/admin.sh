#!/bin/bash
#process="wechat_message_process.php"
path=$(dirname $0)
php_path='/usr/local/bin/php'
cd $path

usage(){
	echo "usage:<restart|start|stop>"
}

if [ -n "$2" ]
then
	process=$2
fi


if [ ! -n "$1" ]
then
	usage;
	exit;
fi

log="../log/$process.log"

#接受信号
if [ $1 == "stop" ]
then
	 echo "$process is stop" >> $log
	`kill -9 $(ps -ef|grep $process|grep -v "grep"|awk '{print $2}')`
	 echo "stop success"
elif [ $1 == "start" ]
then
	echo "$process is start" >> $log
	`$php_path $path/$process >> $log 2>&1&`
	echo "start success"
elif [ $1 == "restart" ]
then 
	echo "$process is restart" >> $log
	`kill -9 $(ps -ef|grep $process|grep -v "grep"|awk '{print $2}')`
	`$php_path $path/$process >> $log 2>&1&`
	echo "restart success"
elif [ $1 == "status" ]
then 
	ps -ef|grep $process
else
	usage;
fi
