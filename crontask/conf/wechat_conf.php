<?php
//判断是否是交易日
function is_trade_day($db, $time)
{
	if((date('w', $time)==6) || (date('w', $time) == 0)) { return false; }
	$date = date('Ymd', $time);
	$sql = "select * from `holiday` where date = '{$date}'";
	if ($db->query($sql, array(), 'row')) { return false; }
	return true;
}

//获取当天前一个交易日 参数much=1
function before_trade_day($db, $much = 0){
    $i = 0;
    do{
        $time = strtotime("-$i day");
        if (is_trade_day($db, $time)) 
        {
                if ($much == 0) { return $time;}
                $much-- ;
        }
        $i++;
    }while(1);
}

//$dsn = array(
//    'host'      =>  'bigdata5',
//    'port'      =>  3306,
//    'user'      =>  'root',
//    'password'  =>  'BDdata5+!(@255',
//    'db'        =>  'stock',
//);

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

$wx_conf = array(
	'access_token_redis_key' => 'wx_access_token',
	'appid' => 'wx607adf2598a22a33',
	'secret' => '8dddfca6ce68142b8b105a0cf0e072c5',
);

$tmpl_conf = array(
	'index_9_pm' =>
		array( 
			'tmp_id' => 'jPsWgI1sbq6agGErM1F0GZ-IyBUEOsx2EC1f6qDhZYw',
			'url' => 'https://wx.firstwisdom.cn/',
			'data' => array(
				'first' => array(
					'value' => "%s",
					"color" => '#ff0000',
					),
				'keyword1' => array(
					'value' => "%s",
					"color" => '#000000',
					),	
				'keyword2' => array(
					'value' => "%s",
					"color"=> "#000000",				
					),
				'remark' => array(
					'value' => "%s",
					"color"=> "#000000",
					),								
			),
		),
	'B_S_point' =>
	array( 
			'tmp_id' => 'naFDx4PalbL2EoSBmFRU03gGqcduXE5-OAcbIf8Y0g4',
			'url' => 'https://wx.firstwisdom.cn/#/stock/',
			'data' => array(
				'first' => array( //第一行
					'value' => "%s",
					"color" => '#ff0000',
					),
				'keyword1' => array(//股票名称
					'value' => "%s",
					"color" => '#459ae9',
					),
                                'keyword2' => array(//股票代码
					'value' => "%s",
					"color" => '#459ae9',
					),
				'keyword3' => array(// 时间
					'value' => "%s",
					"color"=> "#000000",				
					),
                                'keyword4' => array( // 内容
					'value' => "%s",
					"color"=> "#000000",
					),	
				'remark' => array( // 备注
					'value' => "%s",
					"color"=> "#000000",
					),								
				),
	),
	'sys_op' =>
	array( 
			'tmp_id' => '',
			'url' => '',
			'data' => array(
				'first' => array(
					'value' => "%s",
					"color" => '#ff0000',
					),
				'keyword1' => array(
					'value' => "%s",
					"color" => '#459ae9',
					),	
				'keyword2' => array(
					'value' => "%s",
					"color"=> "#000000",				
					),
				'remark' => array(
					'value' => "%s",
					"color"=> "#000000",
					),								
				),
	)


);

