<?php
/**
 *
 * Created by PhpStorm.
 * User: dengxiaowu@innofi.cn
 * Date: 2018/9/30
 * Time: 16:18
 */
//微信推送近期策略收益
require '/data/webapps/stock/gframework/gek/Db.php';
require '/data/webapps/stock/gframework/gek/Log.php';
require '/data/webapps/stock/gframework/gek/Exception.php';
require '/data/webapps/stock/gframework/gek/Error.php';
require '../../lib/Curl.php';
require '../../lib/Wx_proxy.php';
require '../../conf/wechat_conf.php';

$miniAppid = 'wx736c1885a774c978';

date_default_timezone_set('Asia/Shanghai');
ini_set('default_socket_timeout', -1);
Gek_Log::init("/var/log/process_push_wx_pool.log");

Gek_Log::write('Init ...');

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

//清除access_token
$access_token_key = $wx_conf['access_token_redis_key'];
$redis->delete($access_token_key);

$curl = new Curl();
$wx_proxy = new Wx_proxy($curl, $redis, $wx_conf);

Gek_Log::write(date("Y-m-d H:i:s") . ' Start ...');

if (!is_trade_day($db, time())) {
    gek_log::write("iam not work on weekend");
    exit;
}

domain($mobile);

function domain($mobile)
{
    global $db;
    global $miniAppid;
    global $wx_proxy;

    //获取微信推送用户
    $wx_users = getDaliyUserListForWX($db, $mobile);

    $data = build_tmpl();

    //推送微信
    foreach ($wx_users as $v) {

        if (empty($v['openid'])) {
            continue;
        }

        $url = '/pages/page-embed?url='. urlencode('https://preview.firstwisdom.cn/page-notice.html?id=1&platform=miniapp');

        $miniprogram = array(
            'appid' => $miniAppid,
            'pagepath' => $url
        );

        if (!$wx_proxy->pushMsgToWxUser($v['openid'], $data['tmp_id'], $data['data'], '', $miniprogram)) {
            Gek_Log::write("failed send message to openID : {$v['openid']} ,tmp_id: {$data['tmp_id']} ,data: " . json_encode($data));
        }
        unset($data, $miniprogram, $url);
    }

    Gek_Log::write(date("Y-m-d H:i:s") . ' push wx over ...');
    return true;
}


function build_tmpl()
{
    $tmpl = array(
        'tmp_id' => '6bZuZjdLGV50aywXqXI1QNzarBF929kAopndxa2Neow',
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
            'remark' => array( // 备注
                'value' => "%s",
                "color" => "#000000",
            ),
        ),
    );
    $data = [];
    $data[] = "近期策略收益通知\\n";
    $data[] = "近期策略收益通知";
    $data[] = "9月16日—9月27日";
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
    $valid_users = [];
    if ($users) {
        foreach ($users as $user) {
            //1:金股 5:涨停
            if ($user['stockpool_type'] == 1 || $user['stockpool_type'] == 5) {
                if (!checkServiceExpire($db, $user['user_id'], $user['stockpool_type'])) {
                    continue;
                }
                $valid_users[] = $user;
            } else {
                $valid_users[] = $user;
            }
        }
    }
    return $valid_users;
}


//判断服务是否过期
function checkServiceExpire($db, $user_id, $service_id)
{
    //service_id :1=金股 2=涨停
    if ($service_id == 5) {
        $service_id = 2;
    }
    $date = date('Y-m-d');
    $sql = "SELECT * FROM user_services WHERE status = 0 AND user_id = '{$user_id}' AND service_id = '{$service_id}' AND end_time >= '{$date}'";
    $ret = $db->query($sql, array(), 'row');
    if ($ret) {
        return true;
    } else {
        return false;
    }
}

function debug($data)
{
    var_dump($data);
    exit();
}