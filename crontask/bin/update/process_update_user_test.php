<?php
//修改用户的权限
require '/data/webapps/stock/gframework/gek/Db.php';
require '/data/webapps/stock/gframework/gek/Log.php';

date_default_timezone_set('Asia/Shanghai');
ini_set('default_socket_timeout', -1);
Gek_Log::init("/var/log/process_update_user_test.log");


$ven = $argv[1]; //用户环境
$mobile = $argv[2]; //手机
$type = $argv[3]; //用户类型

$dsn = [];

if ($ven == 'test') {
    $dsn = array(
        'host' => 'bigdata5',
        'port' => 3306,
        'user' => 'root',
        'password' => 'BDdata5+!(@255',
        'db' => 'stock',
    );
} else {
    //online
    $dsn = array(
        'host' => 'bigdata6',
        'port' => 3306,
        'user' => 'root',
        'password' => 'BDdata4+!(@254yf',
        'db' => 'stock',
    );
}

$db = new Gek_Db($dsn);

main($mobile, $type);


function main($mobile, $type)
{
    global $db;

    $sql = "SELECT id, mobile FROM `user` WHERE status = 0 and mobile = '$mobile'";
    $info = $db->query($sql, array(), 'row');

    if ($info) {
        $id = $info['id'];
        $sql = "update `user` set type = $type WHERE id = $id";
        $db->query($sql);
    }

    return true;
}
