<?php
//今期收益推送
require '/data/webapps/stock/gframework/gek/Db.php';
require '/data/webapps/stock/gframework/gek/Log.php';
require '/data/webapps/stock/gframework/gek/Exception.php';
require '/data/webapps/stock/gframework/gek/Error.php';
require '../../lib/Curl.php';
require '../../lib/Wx_proxy.php';
require '../../conf/wechat_conf.php';
require '../AppPush.php';

date_default_timezone_set('Asia/Shanghai');
ini_set('default_socket_timeout', -1);
Gek_Log::init("/var/log/process_push_app_income_test.log");

Gek_Log::write(' Init ...');

$ven = $argv[1]; //用户环境
$type = $argv[2]; //推送环境
$mobile = $argv[3]; //手机

$dsn = $redis_conf = [];

if ($ven == 'test') {
    $dsn = array(
        'host' => 'bigdata5',
        'port' => 3306,
        'user' => 'root',
        'password' => 'BDdata5+!(@255',
        'db' => 'stock',
    );

    $redis_conf = array(
        'host' => '192.168.1.244',
        'port' => '6379',
        'event_key' => 'Stock_Events',
        'event_processing_key' => 'Stock_Events_Processing',
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

    $redis_conf = array(
        'host' => '192.168.1.244',
        'port' => '6379',
        'event_key' => 'Stock_Events',
        'event_processing_key' => 'Stock_Events_Processing',
    );
}

$db = new Gek_Db($dsn);
$redis = new \Redis();
if (!$redis->connect($redis_conf['host'], $redis_conf['port'])) {
    Gek_Log::write("connect to redis failed， check host and port first");
    exit;
}

Gek_Log::write(date("Y-m-d H:i:s") . ' Start ...');

if (!is_trade_day($db, time())) {
    gek_log::write("iam not work on weekend");
    exit;
}

$ret = handleData($mobile);

if ($ret) {
    pushApp($type);
    insertMysql();
    return true;
}

//处理数据放入redis
function handleData($mobile)
{
    global $db;
    global $redis;

    $task_key = 'task_list_income_app';
    $mysql_key = 'mysql_income_app';

    $redis->del($task_key);
    $redis->del($mysql_key);

    //获取APP推送用户
    $app_users = getUserListForApp($db, $mobile);
    //============================= APP 推送 start ==========================================
    foreach ($app_users as $v) {

        //处理推送消息到redis队列
        $push_data['mobile'] = $v['mobile'];
        $push_data['login_device'] = $v['login_device'];
        $push_data['title'] = '近期策略收益';
        $push_data['body'] = '10月份的近期策略收益近期策略收益近期策略收益';
        $push_data['type'] = 1;
        $push_data['action'] = array(
            'type' => '1',
            'needlogin' => "0",
            'param' => json_encode(array('url' => "https://preview.firstwisdom.cn/page-notice.html?id=1", 'title' => '近期策略收益')),
        );

        $redis->lPush($task_key, serialize($push_data));

        //mysql
        $mysql_data['user_id'] = $v['user_id'];
        $mysql_data['type'] = $v['stockpool_type'];
        $mysql_data['time'] = date('Y-m-d H:i:s');
        $mysql_data['msg'] = $push_data['body'];
        $mysql_data['url'] = 'https://preview.firstwisdom.cn/page-notice.html?id=1';

        $redis->lPush($mysql_key, serialize($mysql_data));

    }

    //============================= APP 推送 end ==========================================
    Gek_Log::write(date("Y-m-d H:i:s") . ' insert redis Over ...');
    return true;
}


//推送app
function pushApp($type)
{
    global $redis;
    //获取redis的数据
    $task_key = 'task_list_income_app';

    Gek_Log::write("push app start:" . time());

    while ($redis->lLen($task_key) > 0) {
        $redis_data = $redis->rPop($task_key);

        $data = unserialize($redis_data);
        $push_data['mobile'] = $data['mobile'];
        $push_data['login_device'] = $data['login_device'];
        $push_data['title'] = $data['title'];
        $push_data['body'] = $data['body'];
        $push_data['param']['type'] = $data['type'];
        $push_data['param']['action'] = $data['action'];

        if ($push_data['login_device'] == 1) {
            AppPush::get_pusher($type)->pushNoticeToiOS($push_data['mobile'], $push_data['title'], $push_data['body'], $push_data['param']);
        } else if ($push_data['login_device'] == 2) {
            AppPush::get_pusher($type)->pushNoticeToAndroid($push_data['mobile'], $push_data['title'], $push_data['body'], $push_data['param']);
        }
        unset($push_data);
    }

    Gek_Log::write("push app done:" . time());
}

//插入数据库
function insertMysql()
{
    global $redis;
    global $db;

    //获取redis的数据

    $mysql_key = 'mysql_income_app';

    Gek_Log::write("insert MySQL start:" . time());

    while ($redis->lLen($mysql_key) > 0) {
        $redis_data = $redis->rPop($mysql_key);

        $data = unserialize($redis_data);
        $insert_data['time'] = ($data['time']);
        $insert_data['type'] = intval($data['type']);
        $insert_data['msg'] = $data['msg'];
        $insert_data['user_id'] = intval($data['user_id']);
        $insert_data['url'] = $data['url'];

        $db->insert('user_messages', $insert_data);
    }

    Gek_Log::write("insert MySQL done:" . time());
}

//APP用户
function getUserListForApp($db,$mobile)
{
    $where = '';

    $where = " and user.mobile = '$mobile' ";

    $sql = "SELECT stockpool_type, mobile, user_id, login_device,login_deviceId 
            FROM `stock_pool_subscribe`,user 
            where user.id = stock_pool_subscribe.user_id and stock_pool_subscribe.app_status = 1 AND stock_pool_subscribe.stockpool_type = 6 ". $where;
    $users = $db->query($sql, array(), 'all');
    $valid_users = [];
    if ($users) {
        foreach ($users as $user) {
            if (!isPhone($user['mobile'])) {
                continue;
            }
            $valid_users[] = $user;
        }
    }
    return $valid_users;
}

function isPhone($phone)
{
    if (strlen($phone) <= 11) {
        return true;
    } else {
        return false;
    }
}

function debug($data)
{
    var_dump($data);
    exit;
}