<?php
/**
 * User: ben
 * Date: 2017/5/26
 * Time: 17:02
 */
define('AUTO_LOGIN_EXPIRE', 604800);
define('LOGIN_EXPIRE', 604800);
define('AUTO_LOGIN_KEY', 'IS_LOGIN');
define('AUTO_LOGIN_STATUS', 'AUTO_LOGIN_STATUS');

define('IMG_FIX', '.jpg');

define('K_LINE_TYPE_DAY', 1);
define('K_LINE_TYPE_WEEK', 2);
define('K_LINE_TYPE_MONTH', 3);
define('K_LINE_TYPE_SECOND', 4);

define('INDEX_SH', "000001");
define('INDEX_SZ', "399001");
define('INDEX_CY', "399006"); //创业板

define('SELECT_SMART', "1"); //智能
define('SELECT_TECH', "2"); //技术
define('SELECT_HOT', "3");  //舆情
define('SELECT_ALL', "4");  //综合
define('SELECT_TOP', "5");  //涨停
define('SELECT_THEME', "6"); //板块选股

define('ADJ_TYPE_NO', "0");  //不复权
define('ADJ_TYPE_YES', "1");  //前复权

define('STRATEGY_TYPE_BUY', "buy_price_type");  //买入策略
define('STRATEGY_TYPE_SELL', "sell_price_type");  //卖出策略

define('WX_AUTH_ACTION_GET_AVATAR', "get_info");  //拉取头像，出现授权按钮
define('WX_AUTH_ACTION_TRY_LOGIN', "do_login");  //尝试登陆，不出先授权按钮
define('WX_AUTH_ACTION_WX_SHARE', "wx_share");  //尝试登陆，不出先授权按钮

define('STOCK_STATUS_STOP', 0);  //停牌
define('STOCK_STATUS_NOON', 1);  //午间休市
define('STOCK_STATUS_CLOSE', 2);  //收盘
define('STOCK_STATUS_OPEN', 3);  //开盘
define('STOCK_STATUS_AUCTION_15', 4);  //集合竞价15-25
define('STOCK_STATUS_AUCTION_25', 5);  //集合竞价25-30
define('STOCK_STATUS_AUCTION_00', 6);  //集合竞价前 00-15

define('TOKEN_KEY', 'innifo');
define('TOKEN_EXPIRE', 86400);

//实时信号开关 1 关， 0 开
define('REAL_OFF', 0);

//大盘异动板块的涨速配置
define('SPEED_ONE', 1.9);
define('SPEED_SIX', 1.5);
define('SPEED_LOSE_ONE', -1.9);
define('SPEED_LOSE_SIX', -1.5);

//颜色
define('RED', 'eb333b');
define('GREEN', '1dad51');
define('WHITE', 'ffffff');
define('YELLOW', 'FEC709');

//平台来源
define('IOS', 'ios');
define('ANDROID', 'android');
define('MINIAPP', 'miniapp');

//埋点的活动类型
define('ACTIVITY_CODE', 'code');//扫描
define('ACTIVITY_LIKE', 'like');//集赞
define('ACTIVITY_DD1', 'ddztds0724');//渠道
define('ACTIVITY_DD2', 'ddfxy0724');//渠道

//埋点的类型
define('POINT_FX', 'fx');//发现页
define('POINT_YZJG', 'yzjg');//一智金股
define('POINT_ZTDS', 'ztds');//涨停大师
define('POINT_ZLLD', 'zlld');//主力雷达

//活动人数
define('MAX_COUNT', 15);//15人

//跳转协议
define('JUMP_URL', '1'); //跳转网页
define('JUMP_LOGIN', '2'); //登录
define('JUMP_SELECT', '3'); //策略选股
define('JUMP_THEME', '4'); //概念选股
define('JUMP_DK', '5'); //趋势看多
define('JUMP_MARKET', '6'); //大盘行情
define('JUMP_GRADE', '7'); //财务评级
define('JUMP_ZTDS', '8'); //涨停大师
define('JUMP_ZNDP', '9'); //智能盯盘
define('JUMP_SY', '10'); //首页
define('JUMP_YZJG', '11'); //一智金股
define('JUMP_ZLLD', '12'); //主力雷达


function switch_status_by_time($time)
{
    $date = date('Y-m-d', $time);
    //redis 数据时间与系统时间不一致
    if ($date != date('Y-m-d')) {
        return STOCK_STATUS_CLOSE;
    }
    if ($time > strtotime($date . '09:00:00') AND $time < strtotime($date . '09:14:59')) {
        return STOCK_STATUS_AUCTION_00;
    }
    if ($time > strtotime($date . '09:15:00') AND $time < strtotime($date . '09:24:59')) {
        return STOCK_STATUS_AUCTION_15;
    }
    if ($time > strtotime($date . '09:25:00') AND $time < strtotime($date . '09:30:00')) {
        return STOCK_STATUS_AUCTION_25;
    }
    if ($time >= strtotime($date . '09:30:00') AND $time < strtotime($date . '11:30:00')) {
        return STOCK_STATUS_OPEN;
    }
    if ($time >= strtotime($date . '11:30:00') AND $time < strtotime($date . '13:00:00')) {
        return STOCK_STATUS_NOON;
    }
    if ($time >= strtotime($date . '13:00:00') AND $time < strtotime($date . '15:00:00')) {
        return STOCK_STATUS_OPEN;
    }
    return STOCK_STATUS_CLOSE;
}

//开盘-3 收盘-2 午休-1
function switch_open_status_by_time($time)
{
    $date = date('Y-m-d', $time);
    //redis 数据时间与系统时间不一致
    if ($date != date('Y-m-d')) {
        return STOCK_STATUS_CLOSE;
    }

    if ($time < strtotime($date . '09:30:00')) {
        return STOCK_STATUS_CLOSE;
    }
    if ($time >= strtotime($date . '09:30:00') AND $time < strtotime($date . '11:30:00')) {
        return STOCK_STATUS_OPEN;
    }
    if ($time >= strtotime($date . '11:30:00') AND $time < strtotime($date . '13:00:00')) {
        return STOCK_STATUS_NOON;
    }
    if ($time >= strtotime($date . '13:00:00') AND $time < strtotime($date . '15:00:00')) {
        return STOCK_STATUS_OPEN;
    }
    return STOCK_STATUS_CLOSE;
}

function select2name($se)
{
    switch ($se) {
        case SELECT_SMART :
            return "小妮金股";
            break;
        case SELECT_TECH :
            return "技术掘金";
            break;
        case SELECT_HOT :
            return "热点追击";
            break;
        case SELECT_TOP :
            return "涨停大师";
            break;
        default :
            return "综合";
            break;
    }
}


function filterIssetAndType($format, $souce)
{
    $ret = array();
    if (!is_array($format) OR !is_array($souce)) {
        return array();
    }
    foreach ($format as $k => $v) {
        if (is_string($v)) {
            if (isset($souce[$k])) {
                $ret[$k] = $souce[$k];
            } else {
                $ret[$k] = $v;
            }
        } else if (is_numeric($v)) {
            if (isset($souce[$k]) AND is_numeric($souce[$k])) {
                if (is_float($v)) {
                    $ret[$k] = (float)$souce[$k];
                } else {
                    $ret[$k] = (int)$souce[$k];
                }
            } else {
                if (is_float($v)) {
                    $ret[$k] = (float)$v;
                } else {
                    $ret[$k] = (int)$v;
                }
            }
        } else if (is_array($v) OR is_object($v)) {
            if (isset($souce[$k]) AND is_array($souce[$k])) {
                if (!empty($v)) {
                    $ret[$k] = filterIssetAndType($v, $souce[$k]);
                } else {
                    $ret[$k] = (array)$souce[$k];
                }
            } else {
                $ret[$k] = $v;
            }
        } else {
            $ret[$k] = $v;
        }
    }
    return $ret;
}

function check_var($var, $valid_list = array())
{
    if (empty($var)) {
        return false;
    }
    if (empty($valid_list)) {
        return true;
    }
    foreach ($valid_list as $v) {
        if (strcasecmp($var, $v) == 0) {
            return true;
        }
    }
    return false;
}

function sort_array_by_field($list, $field, $sort_type = SORT_ASC)
{
    $sort = array();
    foreach ($list as $v) {
        $sort[] = $v[$field];
    }
    array_multisort($sort, $sort_type, $list);
    return $list;
}

function sort_array_by_field_double($list, $field, $sort_type = SORT_ASC, $field2, $sort_type2 = SORT_ASC)
{
    $sort = array();
    $sort2 = array();
    foreach ($list as $v) {
        if (!array_key_exists($field, $v) OR !array_key_exists($field2, $v)) {
            return array();
        }
        $sort[] = $v[$field];
        $sort2[] = $v[$field2];
    }

    if (count($sort) != count($sort2) OR count($sort2) != count($list)) {
        return array();
    }

    array_multisort($sort, $sort_type, $sort2, $sort_type2, $list);

    return $list;
}

function my_array_column_int($arr, $column)
{
    $ret = array();
    foreach ($arr as $v) {
        $ret[] = (int)$v[$column];
    }
    return $ret;
}

//保留2位小数
function number_format_float($val, $dec = 2)
{
    //return round($val, $dec);
    return sprintf("%." . $dec . "f", $val);
}

function check_multi_key_set($keys, &$arr)
{
    foreach ($keys as $v) {
        if (!array_key_exists($v, $arr)) {
            $arr[$v] = 0;
        }
    }
    return true;
}

function seconds_range($start, $end, $each = 60)
{
    $ret = array();
    do {
        $ret[] = $start;
        $start = $start + $each;
        if ($start > $end) {
            return $ret;
        }
    } while (1);
}

//开盘时间、收盘时间
define('MORNING_OPEN_TIME', 1);
define('MORNING_CLOSE_TIME', 2);
define('AFTERNOON_OPEN_TIME', 3);
define('AFTERNOON_CLOSE_TIME', 4);

function open_close_time($type, $today = NULL)
{
    if (empty($today)) {
        $today = date("Y-m-d");
    }
    $time = 0;
    switch ($type) {
        case MORNING_OPEN_TIME :
            $time = $today . " 09:30:00";
            break;
        case MORNING_CLOSE_TIME :
            $time = $today . " 11:30:00";
            break;
        case AFTERNOON_OPEN_TIME :
            $time = $today . " 13:00:00";
            break;
        case AFTERNOON_CLOSE_TIME :
            $time = $today . " 15:00:00";
            break;
        default:
            ;
    }
    return strtotime($time);
}

function is_open_time($time)
{
    $date = date("Y-m-d", $time);
    if (($time > open_close_time(MORNING_OPEN_TIME, $date) AND $time < open_close_time(MORNING_CLOSE_TIME, $date))
        OR ($time > open_close_time(AFTERNOON_OPEN_TIME, $date) AND $time < open_close_time(AFTERNOON_CLOSE_TIME, $date))) {
        return 0;//正常交易时间
    }
    if ($time > open_close_time(MORNING_CLOSE_TIME, $date) AND $time < open_close_time(AFTERNOON_OPEN_TIME, $date)) {
        return 1; //午间休市
    }

    return 2; //收盘

}

function sub_time($time, $s = 0, $e = -3)
{
    return strtotime(substr($time, $s, $e));
}

function yesterday($date)
{
    return date("Y-m-d", strtotime($date) - 86400);
}

function media_file_name()
{
    return uniqid();
}

function media_file_path($path, $image_name)
{
    return $path . '/' . $image_name . IMG_FIX;
}

function pic_url($filename)
{
    return 'https://' . $_SERVER['HTTP_HOST'] . '/Misc/d?sign=' . $filename;
}

function is_wx_client()
{
    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
        return true;
    }
    return false;
}

function emoji2str($str)
{
    $strEncode = '';

    $length = mb_strlen($str, 'utf-8');

    for ($i = 0; $i < $length; $i++) {
        $_tmpStr = mb_substr($str, $i, 1, 'utf-8');
        if (strlen($_tmpStr) >= 4) {
            $strEncode .= '[[EMOJI:' . rawurlencode($_tmpStr) . ']]';
        } else {
            $strEncode .= $_tmpStr;
        }
    }
    return $strEncode;
}

function str2emoji($str)
{
    $strDecode = preg_replace_callback("/\[\[EMOJI:(.*?)\]\]/", function ($matches) {
        return rawurldecode($matches[1]);
    }, $str);
    return $strDecode;
}

function issetOrZero($var, $key, $default = 0)
{
    if (!empty($var[$key])) {
        return number_format_float($var[$key]);
    }
    return $default;
}

function issetOrString($var, $key, $default = "")
{
    if (!empty($var[$key])) {
        return $var[$key];
    }
    return $default;
}

function debug($data)
{
    var_dump($data);
    exit;
}

function initRes()
{
    $ret = array(
        'data' => array(),
        'info' => 'success',
        'status' => 0,
    );
    return $ret;
}

//二维数组排序
function array_sort($arr, $keys, $type = 'asc')
{
    $keysvalue = $new_array = array();
    foreach ($arr as $k => $v) {
        $keysvalue[$k] = $v[$keys];
    }
    if ($type == 'asc') {
        asort($keysvalue);
    } else {
        arsort($keysvalue);
    }
    reset($keysvalue);
    foreach ($keysvalue as $k => $v) {
        $new_array[$k] = $arr[$k];
    }
    return $new_array;
}

//保留小数位数
function round_position($num, $position = 2)
{
    //return round(floatval($num), $position);
    return sprintf("%." . $position . "f", $num);
}

//获取目录下文件，不包括子目录
function getfiles($dir)
{
    // 获取某目录下所有文件、目录名（不包括子目录下文件、目录名）
    $handler = opendir($dir);
    while (($filename = readdir($handler)) !== false) {
        // 务必使用!==，防止目录下出现类似文件名“0”等情况
        if ($filename !== "." && $filename !== "..") {
            $files[] = $filename;
        }
    }

    closedir($handler);

    return $files;
}

//获取redis 前缀
function get_redis_pre()
{
    return Yaf_Registry::get("config")->get("env") . Yaf_Registry::get("config")->get("application.baseUri");
}

//获取api 环境
function get_api_env()
{
    return Yaf_Registry::get("config")->get("env");
}

//版本转化
function change_version($version)
{
    if (empty($version)) return 0;
    $arr = explode('.', $version);
    if (count($arr) != 3) return 0;

    $first = $arr[0] * 100;
    $second = $arr[1] * 10;
    $third = $arr[2];
    return $first + $second + $third;
}

//根据值 获取百分号和对应的颜色 带正负号
function getColor($value)
{
    if ($value === '--') {
        return array('text' => '--', 'color' => WHITE);
    }
    $text = number_format_float($value);
    if ($text > 0) {
        $text = '+' . $text . '%';
        $color = RED;
    } elseif ($text < 0) {
        $text = $text . '%';
        $color = GREEN;
    } else {
        $text = $text . '%';
        $color = WHITE;
    }
    return array('text' => $text, 'color' => $color);
}

//请求方法
function request_get($req_url, $req_args, $retry_count = 1)
{
    $req_url = $req_url . "?" . $req_args;
    for ($i = 1; $i <= $retry_count; $i++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $req_url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        if ($res === false) {
            curl_close($ch);
            continue;
        }
        $cc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($cc != 200) {
            continue;
        }
        return $res;
    }

    return false;
}

/**
 * http post
 * @param string $req_url
 * @param array $reg_args
 * @param int $retry_count
 * @return object | josn
 */
function request_post($req_url, $req_args, $retry_count = 1)
{
    for ($i = 1; $i <= $retry_count; $i++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $req_url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $req_args);
        $res = curl_exec($ch);
        if ($res === false) {
            curl_close($ch);
            continue;
        }
        $cc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($cc != 200) {
            continue;
        }
        return $res;
    }

    return false;
}

/**
 * 数组 转 对象
 *
 * @param array $arr 数组
 * @return object
 */
function array_to_object($arr)
{
    if (gettype($arr) != 'array') {
        return;
    }
    foreach ($arr as $k => $v) {
        if (gettype($v) == 'array' || getType($v) == 'object') {
            $arr[$k] = (object)array_to_object($v);
        }
    }

    return (object)$arr;
}

/**
 * 对象 转 数组
 *
 * @param object $obj 对象
 * @return array
 */
function object_to_array($obj)
{
    $obj = (array)$obj;
    foreach ($obj as $k => $v) {
        if (gettype($v) == 'resource') {
            return;
        }
        if (gettype($v) == 'object' || gettype($v) == 'array') {
            $obj[$k] = (array)object_to_array($v);
        }
    }

    return $obj;
}

//随机生成字符串
function getRandChar($length)
{
    $str = null;
    $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
    $max = strlen($strPol) - 1;

    for ($i = 0; $i < $length; $i++) {
        $str .= $strPol[rand(0, $max)];//rand($min,$max)生成介于min和max两个数之间的一个随机整数
    }

    return $str;
}

function retObject()
{
    return new stdClass();
}

//异步请求方法
function AsyncRequest($url, $param = array())
{

    $urlinfo = parse_url($url);
    $host = $urlinfo['host'];
    $path = $urlinfo['path'];
    $query = isset($param) ? http_build_query($param) : '';

    $port = 80;
    $errno = 0;
    $errstr = '';
    $timeout = 10;

    $fp = fsockopen($host, $port, $errno, $errstr, $timeout);

    $out = "POST " . $path . " HTTP/1.1\r\n";
    $out .= "host:" . $host . "\r\n";
    $out .= "content-length:" . strlen($query) . "\r\n";
    $out .= "content-type:application/x-www-form-urlencoded\r\n";
    $out .= "connection:close\r\n\r\n";
    $out .= $query;

    fputs($fp, $out);
    Gek_Logger::get_logger('common')->error($errno);
    Gek_Logger::get_logger('common')->error($errstr);
    fclose($fp);
}


function getDomainUrl($version = true)
{
    $urlScheme = Yaf_Registry::get("config")->get("url.scheme");
    $urlDomain = Yaf_Registry::get("config")->get("url.domain");
    $baseUri = Yaf_Registry::get("config")->get("application.baseUri");
    $urlDomain = $urlDomain . $baseUri;

    $url = "{$urlScheme}://{$urlDomain}";
    if (!$version) {
        return $urlScheme . '://' . Yaf_Registry::get("config")->get("url.domain");
    }
    return $url;
}

//小程序类型 0-online,2-preview
function getMiniType()
{
    return Yaf_Registry::get("config")->get('miniapp.type');
}

//根据时间戳返回星期几
function weekday($time)
{
    if (is_numeric($time)) {
        $weekday = array('星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六');
        return $weekday[date('w', $time)];
    }
    return false;
}

//活动专用-活动类型
function get_activity_num($name)
{
    if (empty($name)) {
        return 0;
    }
    $data = array(
        'like' => 1,
    );
    return isset($data[$name]) ? $data[$name] : 0;
}

//活动专用-活动的或者类型
function get_activity_type_num($name)
{
    if (empty($name)) {
        return 0;
    }
    $data = array(
        'yzjg' => 1,
        'ztds' => 2,
    );
    return isset($data[$name]) ? $data[$name] : 0;
}

//因为某一键名的值不能重复，删除重复项 二维数组
function assoc_unique($arr, $key)
{
    $tmp_arr = array();
    foreach ($arr as $k => $v) {
        //搜索$v[$key]是否在$tmp_arr数组中存在，若存在返回true
        if (in_array($v[$key], $tmp_arr)) {
            unset($arr[$k]);
        } else {
            $tmp_arr[] = $v[$key];
        }
    }

    return $arr;
}


//这个星期的星期一
// @$timestamp ，某个星期的某一个时间戳，默认为当前时间
// @is_return_timestamp ,是否返回时间戳，否则返回时间格式
function this_monday($timestamp = 0, $is_return_timestamp = true)
{
    static $cache;
    $id = $timestamp . $is_return_timestamp;
    if (!isset($cache[$id])) {
        if (!$timestamp) $timestamp = time();
        $monday_date = date('Y-m-d', $timestamp - 86400 * date('w', $timestamp) + (date('w', $timestamp) > 0 ? 86400 : - 6*86400));
        if ($is_return_timestamp) {
            $cache[$id] = strtotime($monday_date);
        } else {
            $cache[$id] = $monday_date;
        }
    }
    return $cache[$id];

}


//这个月的第一天
// @$timestamp ，某个月的某一个时间戳，默认为当前时间
// @is_return_timestamp ,是否返回时间戳，否则返回时间格式
function month_firstday($timestamp = 0, $is_return_timestamp = true)
{
    static $cache;
    $id = $timestamp . $is_return_timestamp;
    if (!isset($cache[$id])) {
        if (!$timestamp) $timestamp = time();
        $firstday = date('Y-m-d', mktime(0, 0, 0, date('m', $timestamp), 1, date('Y', $timestamp)));
        if ($is_return_timestamp) {
            $cache[$id] = strtotime($firstday);
        } else {
            $cache[$id] = $firstday;
        }
    }
    return $cache[$id];
}
