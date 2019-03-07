<?php
/**
 * User: ben
 * Date: 2017/8/31
 * Time: 15:34
 */
include_once("Curl.php");
class standbySource
{
    public $curl = NULL;
    public $appCode = NULL;

    public function __construct($appCode = "2243b5026ed1445097d5d042a74508ca")
    {
        $this->appCode = $appCode;
    }

    public function init_curl()
    {
        if (empty($this->curl)) { $this->curl = new Curl(); }
        return $this->curl;
    }

    private function do_query($url, $param = array() )
    {
        $full_url = $url . '?' . http_build_query($param);
        $headers = array("Authorization:APPCODE {$this->appCode}");
        $data = $this->init_curl()->rapid($full_url, 'GET', 'null', $headers);
        $data = json_decode($data, true);
        if (empty($data)) { Gek_Log::write("欠费了吧？" . $full_url); }
        return $data;
    }

    //TODO::当天数据获取
    public function switch_module($stock_id, $key_pre_fix)
    {
        switch ($key_pre_fix)
        {
            case 'Stock_Minute_': $ret = current($this->Stock_Minute($stock_id));break;
            case 'Index_Minute_': $ret = current($this->Index_Minute($stock_id));break;
            case 'Stock_Probability_': $ret = array('data' => array()); break;
            case 'Stock_BS_': $ret = $this->Stock_BS($stock_id); break;
            default: $ret = array();
        }

        if (!empty($ret)) { return $ret; }
        return $ret;
    }

    //TODO::近几天数据获取
    public function switch_module_by_day($stock_id, $key_pre_fix, $day = 1)
    {
        switch ($key_pre_fix)
        {
            case 'Stock_Minute_': $ret = $this->Stock_Minute($stock_id, $day);break;
            case 'Index_Minute_': $ret = $this->Index_Minute($stock_id, $day);break;
            default: $ret = array();
        }

        if (!empty($ret)) { return $ret; }
        return $ret;
    }

    /*股票行情单支查询*/
    public function real_stockinfo($stock_id)
    {
        $url = "https://ali-stock.showapi.com/real-stockinfo";
        $param = array( 'code'=> $stock_id );
        $data = $this->do_query($url, $param);
        if (empty($data['showapi_res_body']) OR empty($data['showapi_res_body']['stockMarket'])) { return array(); }
        return $data['showapi_res_body']['stockMarket'];
    }

    /*股票行情_批量查询*/
    public function batch_real_stockinfo($stock_ids)
    {
        $url = "https://ali-stock.showapi.com/batch-real-stockinfo";
        $id_str = array();
        foreach ($stock_ids as $v)
        {
            if (preg_match("/^6/i", $v)){ $id_str[] = 'sh' . $v;}
            else{ $id_str[] = 'sz' . $v;}
        }
        $id_str = implode(',', $id_str);

        $param = array( 'stocks'=> $id_str );
        $data = $this->do_query($url, $param);
        if (empty($data['showapi_res_body']) OR empty($data['showapi_res_body']['list'])) { return array(); }
        return $data['showapi_res_body']['list'];
    }

    /*股票实时分时线数据 day 1-5*/
    public function timeline($stock_id, $day = 1)
    {
        $url = "https://ali-stock.showapi.com/timeline";
        $param = array( 'code'=> $stock_id, 'day' => $day );
        $data = $this->do_query($url, $param);
        if (empty($data['showapi_res_body']) OR empty($data['showapi_res_body']['dataList'])) { return array(); }
        return $data['showapi_res_body']['dataList'];
    }

    /*沪深股市Level2实时行情*/
    public function hs_level2($stock_id)
    {
        $url = "http://ali.api.intdata.cn/stock/hs_level2/real";
        $prefix = 'sz';
        if (preg_match("/^6/i", $stock_id)) { $prefix = 'sh'; }
        $param = array( 'code'=> $prefix . $stock_id );
        $data = $this->do_query($url, $param);
        if ($data['state'] != 0 OR empty($data['data']) OR empty($data['data']['rows'])) { return array(); }
        return $data['data']['rows'];
    }

    /*沪深股票内外盘数据*/
    public function in_out_data($stock_id)
    {
        $url = "https://ali-stock.showapi.com/in-out-data";
        $param = array( 'code'=> $stock_id );
        $data = $this->do_query($url, $param);
        if (empty($data['showapi_res_body']) OR empty($data['showapi_res_body'] )) { return array(); }
        return $data['showapi_res_body'] ;
    }

    /*大盘股指行情_批量查询*/
    public function stock_index()
    {
        $url = "https://ali-stock.showapi.com/stockIndex";
        $param = array( 'stocks'=> 'sh000001,sz399001,sz399006' );
        $data = $this->do_query($url, $param);
        if (empty($data['showapi_res_body']) OR empty($data['showapi_res_body']['indexList'])) { return array(); }
        return $data['showapi_res_body']['indexList'];
    }

    /*大盘股指分时线 day 1-5*/
    public function index_timeline($stock_id, $day = 1)
    {
        $url = "https://ali-stock.showapi.com/index-timeline";
        $param = array( 'code'=> $stock_id, 'day' => $day );
        $data = $this->do_query($url, $param);
        if (empty($data['showapi_res_body']) OR empty($data['showapi_res_body']['dataList'])) { return array(); }
        return $data['showapi_res_body']['dataList'];
    }


    public function Stock_BS($stock_id)
    {
        $data = $this->in_out_data($stock_id);
        $ret = array(
            'data' => array(
                'b' => !empty($data['inTradeNum']) ? $data['inTradeNum'] : 0,
                's' => !empty($data['outTradeNum']) ? $data['outTradeNum'] : 0,
            ),
            'time' => date('ymd'),
        );
        return $ret;
    }

    public function Index_Minute($stock_id, $day = 1)
    {
        $data = $this->index_timeline($stock_id, $day);
        return $this->build_Minute($data);
    }

    public function Stock_Minute($stock_id, $day = 1)
    {
        $data = $this->timeline($stock_id, $day);
        return $this->build_Minute($data);
    }

    private function build_Minute($data)
    {
        $ret = array();
        foreach ($data as $one_day)
        {
            $tmp = array('time' => $one_day['date'], 'data' => array());
            foreach ($one_day['minuteList'] as $v){
                $tmp['data'][$v['time']] =
                    '{"total_volume":"'.$v['volume'].'",'
                    .'"last_price":"'.$v['nowPrice'].'",'
                    .'"avg_price":"'.$v['avgPrice'].'",'
                    .'"total_amount":"0"}';
            }
            $ret[$one_day['date']] = $tmp;
        }
        if (empty($ret)) {
            $today = date('ymd');
            $ret[$today] = array();
        }
        return $ret;
    }

    public function getSHIndexs()
    {
        $ret = array();
        $data = $this->stock_index();
        foreach ($data as $da)
        {
            $ret[] = $this->getSHIndexsBuild($da);
        }
        return $ret;
    }

    public function returnProbScope($stock_id)
    {
        $data = $this->real_stockinfo($stock_id);
        return $this->returnProbScopeBuild($stock_id, $data, true);
    }

    public function returnProbScopes($stock_ids)
    {
        $data = $this->batch_real_stockinfo($stock_ids);
        $ret = array();
        foreach ($data as $v)
        {
            $ret[] = $this->returnProbScopeBuild($v['code'], $v, false);
        }
        return $ret;
    }

    private function getSHIndexsBuild($data)
    {

        $ret = array(
                'pre_close_index' => issetOrZero($data, 'yestodayClosePrice'),
                'last_index' => issetOrZero($data, 'nowPrice'),
                'time' => !empty($data['time']) ? strtotime($data['time']) : time(),
                'open_index' => issetOrZero($data, 'todayOpenPrice'),
                'total_volume' => issetOrZero($data, 'tradeNum'),
                'close_index' => issetOrZero($data, 'nowPrice'),
                'low_index' => issetOrZero($data, 'minPrice'),
                'high_index' => issetOrZero($data, 'maxPrice'),
                'total_amount' =>  issetOrZero($data, 'tradeAmount'),
        );
        return $ret;
    }

    private function returnProbScopeBuild($stock_id, $data, $level2 = false)
    {
        $ret = array(
            'code' => $stock_id,
            'time' => date('YmdHis'). '000',
            'pre_close_price' => issetOrZero($data, 'yestodayClosePrice'),
            'total_volume' => issetOrZero($data, 'tradeNum'),
            'total_amount' => issetOrZero($data,'tradeAmount'),
            'Status' => (!empty($data['remark']) AND $data['remark']) == '停牌' ? 'Z' : '',
            'low_price' => issetOrZero($data, 'todayMin'),
            'high_price' => issetOrZero($data, 'todayMax'),
            'last_price' => issetOrZero($data, 'nowPrice'),
            'close_price' => 0,
            'open_price' => issetOrZero($data, 'openPrice'),
            'auction_leave_volume' => '',
            'sell_level_queue' => '',
            'buy_level_queue' => '',
            'auction_open_price' => '',
            'TradeStatus' => '',
            'SecurityPhaseTag' => '',
            'auction_volume' => '',
            'total_no' => '',
            'action_side' => '',
        );


        if ($level2) {
            $hs_level2 = $this->hs_level2($stock_id);
            foreach ($hs_level2 as $hs)
            {
                $abs_pos = abs($hs['pos']);
                if ($hs['pos'] < 0) {
                    $ret["buy_price0{$abs_pos}"] = issetOrZero($hs, 'price');
                    $ret["buy_volume0{$abs_pos}"] = issetOrZero($hs, 'amount');
                } else {
                    $ret["sell_price0{$abs_pos}"] = issetOrZero($hs, 'price');
                    $ret["sell_volume0{$abs_pos}"] = issetOrZero($hs, 'amount');
                }
            }
        }
        return $ret;
    }
}