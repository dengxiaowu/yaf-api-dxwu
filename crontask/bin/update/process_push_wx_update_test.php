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
Gek_Log::init("/var/log/process_push_wx_update_test.log");

Gek_Log::write(' Init ...');

$ven = $argv[1]; //用户环境
$mobile = $argv[2]; //手机

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

main($mobile);


function main($mobile)
{
    global $db;

    $users = getDaliyUserListForWX($db, $mobile);
    //============================= APP 推送 start ==========================================
    foreach ($users as $user) {

    }

    //============================= APP 推送 end ==========================================
    Gek_Log::write(date("Y-m-d H:i:s") . ' insert redis Over ...');
    return true;
}

//版本更新通知
function build_tmpl()
{
    $tmpl = array(
        'tmp_id' => 'sFmwB4me0YnEHkIm2qyn2JZwkZNC1Syj_fsKHZIGiBE',
        'url' => '',
        'data' => array(
            'first' => array(
                'value' => "%s",
                "color" => '#eb333b',
            ),
            'keyword1' => array(
                'value' => "%s",
                "color" => '#000000',
            ),
            'keyword2' => array(
                'value' => "%s",
                "color" => '#000000',
            ),
            'keyword3' => array(
                'value' => "%s",
                "color" => "#000000",
            ),
            'keyword4' => array(
                'value' => "%s",
                "color" => "#000000",
            ),
            'remark' => array( // 备注
                'value' => "%s",
                "color" => "#000000",
            ),
        ),
    );
    $data = [];
    $data[] = "版本更新通知\\n";
    $data[] = "一智滕飞更新版本2.4啦";
    $data[] = "最新版2.4";
    $data[] = "\\n 1、实时快讯关联相关，一键跳转直达个股行情\\n";
    $data[] = "\\n 1、主力雷达涨幅前3名：海正药业（+6.65%）、恒瑞药业（+2.34%）、航天电子（+0.28%）2、涨停大师1个涨停：科隆药业（+9.90%）\\n";
    $remark = '如需退订消息，请回复：退订';

    $data[] = $remark;

    $tmpl = json_encode($tmpl);
    $tmpl = vsprintf($tmpl, $data);
    $tmpl = json_decode($tmpl, true);
    return $tmpl;
}

//微信用户
function getDaliyUserListForWX($db, $mobile)
{
    $where = " and user.mobile = '$mobile' ";

    $sql = "SELECT stockpool_type, openid, user_id
            FROM `stock_pool_subscribe`, user 
            where user.id = stock_pool_subscribe.user_id and stock_pool_subscribe.mini_status = 1 and stock_pool_subscribe.stockpool_type = 6 and user.openid != ''" . $where;
    $users = $db->query($sql, array(), 'all');

    return $users;
}

function debug($data)
{
    var_dump($data);
    exit;
}