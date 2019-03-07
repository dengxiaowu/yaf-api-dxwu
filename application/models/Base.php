<?php
/**
 * User: ben
 * Date: 2017/6/14
 * Time: 14:02
 */

use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\TSocket;
use Thrift\Transport\THttpClient;
use Thrift\Transport\TBufferedTransport;
use Thrift\Exception\TException;

class BaseModel
{
    protected $dsn;
    protected $db;
    protected $online_source = 'redis';

    public $date_line = array();

    public $trade_day = array();

    public $holiday_tbl = 'holiday';
    private $logger = '';

    public function __construct($dsn)
    {
        $this->dsn = $dsn;
        $this->db = Gek_Db::getInstance($dsn);
        if (Yaf_Registry::get("config")->get("online_source") == 'aliyun') {
            $this->online_source = 'aliyun';
        }
        $this->logger = Gek_Logger::get_logger('base_model');
    }

    public function init()
    {
    }

    public function sys_time()
    {
        do {
            if (!empty($this->sys_times)) {
                break;
            }
            $this->sys_times = array(
                'time' => time(), //strtotime('2017-09-01 09:00:00')
                'trading_day' => 1,
            );
            if (!$this->is_trade_day(time())) {
                $this->sys_times['trading_day'] = 0;
            }
        } while (0);

        return $this->sys_times;
    }

    public function is_trade_day($time)
    {
        do {
            if (isset($this->trade_day[$time])) {
                break;
            }
            if ((date('w', $time) == 6) || (date('w', $time) == 0)) {
                $this->trade_day[$time] = false;
                break;
            }
            $date = date('Ymd', $time);
            $sql = "SELECT * FROM `holiday` WHERE date = '{$date}'";
            if ($this->db->query($sql, array(), 'row')) {
                $this->trade_day[$time] = false;
                break;
            }
            $this->trade_day[$time] = true;
            break;
        } while (0);
        return $this->trade_day[$time];
    }

    public function before_trade_day($much = 0)
    {
        $i = 0;
        do {
            $time = strtotime("-$i day");
            if ($this->is_trade_day($time)) {
                if ($much == 0) {
                    return $time;
                }
                $much--;
            }
            $i++;
        } while (1);
    }

    protected function logger($msg, $level = "ERROR")
    {
        $msg = '[' . get_class($this) . '] - ' . $msg;
        Gek_Log::write($msg, $level);
        return true;
    }

    public function redis_instance()
    {
        if (!empty($this->redis)) {
            return $this->redis;
        }
        $this->redis = new \Redis();
        if (!$this->redis->connect(Yaf_Registry::get("config")->get("redis.host"), Yaf_Registry::get("config")->get("redis.port"))) {
            $this->logger("connect to redis failed， check host and port first");
            return false;
        } else {
            return $this->redis;
        }
    }

    //测试环境的redis
    public function redis_instance_test()
    {
        $redis = new \Redis();
        if (!$redis->connect('192.168.1.245', '6379')) {
            $this->logger("connect to redis failed， check host and port first");
            return false;
        } else {
            return $redis;
        }
    }

    public function aliyun_instance()
    {
        if (!empty($this->aliyun)) {
            return $this->aliyun;
        }
        Yaf_loader::import('misc/standbySource.php');
        $this->aliyun = new standbySource(Yaf_Registry::get("config")->get("online_source_code"));
        return $this->aliyun;
    }

    protected function tryGetDateLine($limit = 5)
    {
        if (!empty($this->date_line)) {
            return array_slice($this->date_line, 0, $limit);
        }
        $this->date_line = $this->redis_instance()->ZREVRANGEBYSCORE("Stock_Date", 201706290, 0);
        return array_slice($this->date_line, 0, $limit);
    }

    protected function hash_hgetAll_ByDay($stock_id, $key_pre_fix = 'Stock_Minute_', $day = 1)
    {
        if ($this->online_source == 'aliyun') {
            $tmp = $this->aliyun_instance()->switch_module_by_day($stock_id, $key_pre_fix, $day);
            if (!empty($tmp)) {
                return $tmp;
            }
        }

        $ret = array();
        $date_line = $this->tryGetDateLine($day);
        foreach ($date_line as $dl) {
            $key = "{$key_pre_fix}{$stock_id}_{$dl}";
            $ret[$dl] = array(
                'time' => $dl,
                'data' => $this->redis_instance()->HGETALL($key),
            );
        }
        return $ret;
    }

    /*获取分时数据*/
    protected function hash_hgetAll($stock_id, $key_pre_fix = 'Stock_Minute_')
    {
        if ($this->online_source == 'aliyun') {
            $tmp = $this->aliyun_instance()->switch_module($stock_id, $key_pre_fix);
            if (!empty($tmp)) {
                return $tmp;
            }
        }
        //date 格式20180410
        $date_line = $this->tryGetDateLine(1);
        $ret = array(
            'data' => array(),
            'time' => date("Ymd"),
        );

        do {
            if (empty($date_line[0])) {
                break;
            }
            $key = "{$key_pre_fix}{$stock_id}_{$date_line[0]}";
            $ret['time'] = $date_line[0];
            $ret['data'] = $this->redis_instance()->HGETALL($key);
        } while (0);
        ksort($ret['data']);
        return $ret;
    }

    //获取分时数据
    //可以根据时间返回
    protected function hash_hgetAll_new($stock_id, $key_pre_fix = 'Stock_Minute_', $date = '')
    {
        //date 格式20180410
        if (empty($date)){
            $date_line = $this->tryGetDateLine(1);
        }else{
            $date_line[0] = date("Ymd", strtotime($date));
        }
        $ret = array(
            'data' => array(),
            'time' => date("Ymd"),
        );

        do {
            if (empty($date_line[0])) {
                break;
            }
            $key = "{$key_pre_fix}{$stock_id}_{$date_line[0]}";
            $ret['time'] = $date_line[0];
            $ret['data'] = $this->redis_instance()->HGETALL($key);
        } while (0);
        ksort($ret['data']);
        return $ret;
    }

    /*获取分时数据*/
    protected function hash_hgetAll_test($stock_id, $key_pre_fix = 'Stock_Minute_')
    {
        //date 格式20180410
        $date_line = $this->tryGetDateLine(1);
        $ret = array(
            'data' => array(),
            'time' => date("Ymd"),
        );

        do {
            if (empty($date_line[0])) {
                break;
            }
            $key = "{$key_pre_fix}{$stock_id}_{$date_line[0]}";
            $ret['time'] = $date_line[0];
            $ret['data'] = $this->redis_instance_test()->HGETALL($key);
        } while (0);
        ksort($ret['data']);
        return $ret;
    }

    protected function zset_ZRANGEBYSCORE($key, $order, $min = -1, $max = 1, $limit = array('limit' => array(0, 15), 'withscores' => true))
    {
        $date_line = $this->tryGetDateLine(1);
        $key = "{$key}{$date_line[0]}";
        if ($order == 'asc') {
            return $this->redis_instance()->ZRANGEBYSCORE($key, $min, $max, $limit);
        } else {
            return $this->redis_instance()->ZREVRANGEBYSCORE($key, $max, $min, $limit);
        }
    }

    protected function zset_SCORE($key, $field)
    {
        $date_line = $this->tryGetDateLine(1);
        $key = "{$key}{$date_line[0]}";
        return $this->redis_instance()->ZSCORE($key, $field);
    }

    /**
     * TODO::生成分时k线的数据
     * @param $data 来自hash_hgetAll的数据
     * @param $pre_close_price  昨天的收盘价
     * @param $ret  返回
     * @param int $each 横轴多少秒分割
     * @param array $field 每一个分时图取哪些字段
     * @return bool
     */
    protected function buildSecondsKLine($data, $pre_close_price, &$ret, $each = NULL, $fields = NULL)
    {

        if (empty($fields)) {
            $fields = array('last_price', 'avg_price', 'total_volume', 'change_percent');
        }
        if (empty($each)) {
            $each = 60;
        }

        $ret['time_line'] = $this->getSecondsKLineTimeLine($data['time'], $each);

        $this->last_min = array('last_price' => 0, 'avg_price' => 0, 'total_volume' => 0, 'change_percent' => 0, 'all_volume' => 0,);

        $end_data_time = key(array_slice($data['data'], -1, 1, true));
        $end_data_time = strtotime($data['time'] . $end_data_time . '01');

        $last_data = $this->caculateData(json_decode(current($data['data']), true), $pre_close_price, $fields);
        $now_time = time();
        foreach ($ret['time_line'] as $time) {
            foreach ($data['data'] as $k => $d) {
                $d = json_decode($d, true);
                $d_time = strtotime($data['time'] . $k . '01');
                if (($d_time <= ($time + $each)) AND ($d_time > $time)) {
                    $tmp = $this->caculateData($d, $pre_close_price, $fields);
                    $last_data = $tmp;
                    $ret['data'][$time] = $last_data;
                    break;
                }
                if ($time < $end_data_time) {
                    $ret['data'][$time] = $last_data;
                }
            }
            if (empty($ret['data'][$time]) AND $time < $now_time) {
                $ret['data'][$time] = $last_data;
            }
            $this->last_min = $last_data;
            if (!empty($data['data'])) {
                $this->last_min['all_volume'] = $d['total_volume'];
            }
        }
        unset($ret['time_line']);
        return true;
    }

    protected function caculateData($d, $pre_close_price, $fields)
    {
        $last_price = $d['last_price'];
        foreach ($fields as $fi) {
            switch ($fi) {
                case 'last_price' :
                    $ret['last_price'] = number_format_float($last_price);
                    break;
                case 'avg_price' :
                    if (!empty($d['avg_price'])) {
                        $ret['avg_price'] = number_format_float($d['avg_price']);
                        break;
                    }
                    if ($d['total_volume'] == 0) {
                        $ret['avg_price'] = $this->last_min['avg_price'];
                    } else {
                        $ret['avg_price'] = number_format_float($d['total_amount'] / $d['total_volume']);
                    }
                    break;
                case 'total_volume' :
                    $total_volume = (int)($d['total_volume'] - $this->last_min['all_volume']);
                    if ($total_volume < 0) {
                        $total_volume = 0;
                    }
                    $ret['total_volume'] = $total_volume;
                    break;
                case 'change_percent' :
                    if (empty($last_price) OR empty($pre_close_price)) {
                        $ret['change_percent'] = 0;
                    } else {
                        $ret['change_percent'] = number_format_float((($last_price - $pre_close_price) / $pre_close_price) * 100);
                    }
                    break;
                default :
                    ;
            }
        }
        return $ret;
    }

    private function getSecondsKLineTimeLine($time, $each)
    {
        $date = strtotime($time);

        $m_open_time = open_close_time(MORNING_OPEN_TIME, date("Y-m-d", $date));
        $m_close_time = open_close_time(MORNING_CLOSE_TIME, date("Y-m-d", $date));
        $a_open_time = open_close_time(AFTERNOON_OPEN_TIME, date("Y-m-d", $date));
        $a_close_time = open_close_time(AFTERNOON_CLOSE_TIME, date("Y-m-d", $date));

        $m_time_line = seconds_range($m_open_time, $m_close_time, $each);
        $a_time_line = seconds_range($a_open_time, $a_close_time, $each);
        return array_merge($m_time_line, $a_time_line);
    }
    /*TODO::生成分时k线的数据-end*/

    /*TODO::生成日k数据*/
    protected function buildKLine($data, &$ret)
    {
        $format = array(
            'open_price' => 0.0,
            'high_price' => 0.0,
            'low_price' => 0.0,
            'close_price' => 0.0,
            'change_percent' => 0.0,
            'volume' => 0.0,
            'date' => "",
            'ma5' => 0.00,
            'ma10' => 0.00,
            'ma20' => 0.00,
            'signal' => "0"
        );

        foreach ($data as $k => $v) {
            $tmp = filterIssetAndType($format, $v);
            $time_line = strtotime($tmp['date']);
            $ret['data'][$time_line] = $tmp;
        }
        return true;
    }

    /*TODO::生成日k数据-end*/

    protected function marketInit($dsn = NULL)
    {
        $tmp = $dsn ? $dsn : $this->dsn;
        if (empty($this->marketObj)) {
            $this->marketObj = new MarketModel($tmp);
        }
        return $this->marketObj;
    }

    protected function selectInit($dsn = NULL)
    {
        $tmp = $dsn ? $dsn : $this->dsn;
        if (empty($this->selectObj)) {
            $this->selectObj = new SelectModel($tmp);
        }
        return $this->selectObj;
    }

    protected function userInit($dsn = NULL)
    {
        $tmp = $dsn ? $dsn : $this->dsn;
        if (empty($this->userObj)) {
            $this->userObj = new UserModel($tmp);
        }
        return $this->userObj;
    }

    protected function stocksInit($dsn = NULL)
    {
        $tmp = $dsn ? $dsn : $this->dsn;
        if (empty($this->stocksObj)) {
            $this->stocksObj = new StocksModel($tmp);
        }
        return $this->stocksObj;
    }

    protected function miscInit($dsn = NULL)
    {
        $tmp = $dsn ? $dsn : $this->dsn;
        if (empty($this->miscObj)) {
            $this->miscObj = new MiscModel($tmp);
        }
        return $this->miscObj;
    }

    //判断是否是新股
    public function isNewStock($date)
    {
        return (date('Y-m-d', time()) == $date) ? true : false;
    }


    /**
     * 判断交易日
     * @param $time 时间戳
     * @return mixed
     */
    public function is_trade_day_new($time)
    {
        do {
            if (isset($this->trade_day[$time])) {
                break;
            }
            if ((date('w', $time) == 6) || (date('w', $time) == 0)) {
                $this->trade_day[$time] = false;
                break;
            }
            $date = date('Ymd', $time);
            $holidayModel = new HolidayModel($this->dsn);
            if ($holidayModel->getHoliday($date)) {
                $this->trade_day[$time] = false;
                break;
            }
            $this->trade_day[$time] = true;
            break;
        } while (0);
        return $this->trade_day[$time];
    }

    /**
     * 获取上一个交易日
     * @return false|int 时间戳
     */
    public function getLastTraceDay()
    {
        for ($i = 1; ; $i++) {
            $time = strtotime("-$i day");
            if ($this->is_trade_day($time)) {
                return $time;
            }
        }
    }

    /**
     * 获取下一个交易日
     * @return false|int 时间戳
     */
    public function getNestTraceDay()
    {
        for ($i = 1; ; $i++) {
            $time = strtotime("+$i day");
            if ($this->is_trade_day($time)) {
                return $time;
            }
        }
    }

    /**
     * 获取股票涨幅保留两位
     * 交易日：
     * 1. 9：00 显示上一个交易日的
     * 2. 9：00-9：25 显示--
     * 3. 都显示 今天的
     * 非交易日：显示上一个交易日的
     */
    public function getStockChangePercentAll($symbol)
    {
        if ($this->getStockStopStatus($symbol) == 1) {
            return '--';
        }

        list($now, $time_900, $time_925) = $this->getTimeList_900_925();

        //交易日的 9：00-9：25 显示--
        if ($this->is_trade_day(time()) && ($time_900 <= $now && $now <= $time_925)) {
            return '--';
        }

        if (!$this->is_trade_day(time())) {
            $date = date('Ymd', $this->getLastTraceDay());
        } else {
            if (($now < $time_900) || ($time_900 < $now && $now < $time_925)) {
                $date = date('Ymd', $this->getLastTraceDay());
            } else {
                $date = date('Ymd', time());
            }
        }
        //这里获取redis 还要乘以100
        $change_percent = Gek_Redis::factory()->ZSCORE("Stock_Change_{$date}", $symbol);
        return $change_percent ? round_position($change_percent * 100) : 0;
    }

    /**
     * 价格显示说明
     * 交易日：
     * 1. 9：00 显示上一个交易日的收盘价
     * 2. 9：00-9：25 显示--
     * 3. 9：25 -9：30 显示开盘价
     * 4. 9:30-15:00 显示最新价格
     * 5. 15：00- 9：00 显示收盘价
     * 非交易日：显示上一个交易日的收盘价
     */
    public function get_stock_priceAll($symbol)
    {
        $stockData = Gek_Redis::factory()->HGETALL("Stock_Quotation_{$symbol}");
        $price = 0;
        if (empty($stockData)) return $price;
        if (!$this->is_trade_day(time())) {
            $price = ($this->getStockStopStatus($symbol) == 0) ? (isset($stockData['close_price']) ? round_position($stockData['close_price']) : 0) : '--';
        } else {
            $time_930 = date('Y-m-d', time()) . ' 09:30:00';
            $time_150 = date('Y-m-d', time()) . ' 15:00:00';

            list($now, $time_900, $time_925) = $this->getTimeList_900_925();

            if ($now < $time_900) {
                $price = ($this->getStockStopStatus($symbol) == 0) ? (isset($stockData['close_price']) ? round_position($stockData['close_price']) : 0) : '--';
            } elseif ($time_900 <= $now && $now <= $time_925) {
                $price = '--';
            } elseif ($time_925 < $now && $now <= $time_930) {
                $price = ($this->getStockStopStatus($symbol) == 0) ? (isset($stockData['open_price']) ? round_position($stockData['open_price']) : 0) : "--";
            } elseif ($time_930 < $now && $now <= $time_150) {
                $price = ($this->getStockStopStatus($symbol) == 0) ? (isset($stockData['last_price']) ? round_position($stockData['last_price']) : 0) : '--';
            } else {
                if ($this->getStockStopStatus($symbol) == 0) {
                    if (isset($stockData['close_price']) && ($stockData['close_price'] != '0.00')) {
                        $price = round_position($stockData['close_price']);
                    } else {
                        $price = round_position($stockData['last_price']);
                    }
                } else {
                    $price = '--';
                }
            }
        }
        return $price;
    }

    /**
     * 获取股票的停牌状态
     * @param $symbol
     * @return int
     */
    public function getStockStopStatus($symbol)
    {
        $date = date('Ymd', time());
        $stockStop = Gek_Redis::factory()->HGET("Stock_SUSP_{$date}", $symbol);
        return ($stockStop == 1) ? 1 : 0;
    }

    /**
     * 获取竞价阶段时间
     */
    public function getTimeList()
    {
        $time_915 = date('Y-m-d', time()) . ' 09:15:00';
        $time_925 = date('Y-m-d', time()) . ' 09:25:00';
        $now = date('Y-m-d H:i:s', time());

        return array($now, $time_915, $time_925);
    }

    /**
     * 金额单元换算
     * @param $money 元
     * @param $format
     * @return string
     */

    public function money_format($money, $format)
    {
        if (empty($money)) return '0.00';

        switch ($format) {
            case 1:
                return (string)round_position($money / 100000000, 2);
        }

    }


    /**
     * @param $price
     * @return array
     */
    public function getPriceColor($price)
    {
        if (empty($price) || ($price === '--')) {
            return array('text' => '--', 'color' => 'ffffff');
        }

        $price = number_format_float($price);
        if ($price > 0) {
            $price = '+' . $price . '%';
            $color = 'eb333b';

        } elseif ($price < 0) {
            $price = $price . '%';
            $color = '1dad51';
        } else {
            $price = $price . '%';
            $color = 'ffffff';
        }
        return array('text' => $price, 'color' => $color);
    }


    /**
     * 9:00-9:25数据展示情况同集合竞价规则一致
     * 改成26：因为大数据25：12秒才才完成计算，导致部分数据问题。
     */
    public function getTimeList_900_925()
    {
        $time_900 = date('Y-m-d', time()) . ' 09:00:00';
        $time_925 = date('Y-m-d', time()) . ' 09:26:00';
        $now = date('Y-m-d H:i:s', time());

        return array($now, $time_900, $time_925);
    }

    /**
     * 不带+ - 和百分号
     * @param $price
     * @return array
     */
    public function getPriceColorNotFomart($price)
    {
        if (($price === '--')) {
            return array('text' => '--', 'color' => 'ffffff');
        }

        $price = number_format_float($price);
        if ($price > 0) {
            $color = 'eb333b';
        } elseif ($price < 0) {
            $color = '1dad51';
        } else {
            $color = 'ffffff';
        }
        return array('text' => $price, 'color' => $color);
    }


    /**
     * 只带+ -
     * @param $price
     * @return array
     */
    public function getPriceColorSign($price)
    {
        if (empty($price) || ($price == '--')) {
            return array('text' => '--', 'color' => 'ffffff');
        }

        $price = number_format_float($price);
        if ($price > 0) {
            $price = '+' . $price;
            $color = 'eb333b';
        } elseif ($price < 0) {
            $color = '1dad51';
        } else {
            $color = 'ffffff';
        }
        return array('text' => $price, 'color' => $color);
    }

    //股票停牌 1 停牌 0 正常
    public function checkAllStop($stock_id)
    {
        //获取停牌状态
        //股票停牌 1 停牌 0 正常
        $stockData = Gek_Redis::factory()->HGETALL("Stock_Quotation_{$stock_id}");
        if (empty($stockData)) {
            $status = 1;
        } else {
            $status = $this->getStockStatus($stockData["Status"]);
        }

        return $status;
    }


    //股票停牌 1 停牌 0 正常
    public function getStockStatus($status)
    {
        if ($status == 'P' OR $status == 'D' OR $status == 'Z' OR $status == 'H' OR $status === '') {
            return 1;
        }
        return 0;
    }

    /**
     * 股票收盘价
     * @param string $symbol
     * @return int
     */
    public function get_stock_close_price($symbol)
    {
        $stockData = Gek_Redis::factory()->HGETALL("Stock_Quotation_{$symbol}");
        if (empty($stockData)) return 0;
        if (!$this->is_trade_day(time())) {
            $price = ($this->getStockStatus($stockData["Status"]) == 0) ? (isset($stockData['pre_close_price']) ? ($stockData['pre_close_price']) : 0) : 0;
        } else {
            if (($this->getStockStatus($stockData["Status"]) == 0)) {
                $price = ($stockData['close_price'] == 0) ? $stockData['last_price'] : $stockData['close_price'];
            } else {
                $price = $stockData['pre_close_price'];
            }
        }
        return $price;
    }

}
