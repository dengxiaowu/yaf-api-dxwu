<?php
//app 更新推送
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
Gek_Log::init("/var/log/process_push_app_update_test.log");

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
    return true;
}

//处理数据放入redis
function handleData($mobile)
{
    global $db;
    global $redis;

    $task_key = 'task_list_update_app';

    $redis->del($task_key);

    //获取APP推送用户
    $app_users = getUpdateUserListForApp($db, $mobile);
    //============================= APP 推送 start ==========================================
    foreach ($app_users as $v) {
        //处理推送消息到redis队列
        $push_data['mobile'] = $v['mobile'];
        $push_data['login_device'] = $v['login_device'];
        $push_data['title'] = '版本更新提示';
        $push_data['body'] = "一智腾飞版本更新啦！市场行情新增板块轮动，提前布局上升板块；板块详情页新增板块分析，板块情况一目了然；个股新增财务评级报告，挖掘中长线好公司";
        $push_data['type'] = 1;

        if ($push_data['login_device'] == 1) {
            $push_data['action'] = array(
                'type' => '1',
                'needlogin' => "0",
                'param' => json_encode(array('url' => "https://wx.firstwisdom.cn/version-intro.html?type=1&platform=ios&lastVersion=2.3.0", 'title' => '版本更新提示')),
            );
        } else if ($push_data['login_device'] == 2) {
            $push_data['action'] = array(
                'type' => '1',
                'needlogin' => "0",
                'param' => json_encode(array('url' => "https://wx.firstwisdom.cn/version-intro.html?type=1&platform=android&lastVersion=2.3.0", 'title' => '版本更新提示')),
            );
        }

        $redis->lPush($task_key, serialize($push_data));
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
    $task_key = 'task_list_update_app';

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
            AppPush::get_pusher()->pushNoticeToAndroid($push_data['mobile'], $push_data['title'], $push_data['body'], $push_data['param']);
        }
        unset($push_data);
    }

    Gek_Log::write("push app done:" . time());
}

//APP用户
function getUpdateUserListForApp($db,$mobile)
{
    $where = '';
    $where = " and user.mobile = '$mobile' ";

    $sql = "SELECT stockpool_type, mobile, user_id, login_device,login_deviceId 
            FROM `stock_pool_subscribe`,user 
            where user.id = stock_pool_subscribe.user_id and stock_pool_subscribe.app_status = 1 and stock_pool_subscribe.stockpool_type = 7 " . $where;
    $users = $db->query($sql, array(), 'all');
    $valid_users = [];
    if ($users) {
        foreach ($users as $user) {
            if (!isPhone($users['mobile'])) {
                continue;
            }
            $valid_users[] = $user;
        }
    }
    return $valid_users;
}

function isPhone($phoneNumber)
{
    if (strlen($phoneNumber) <= 11) {
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