<?php
class SmsProxy
{
	public function __construct(){
            if(!isset($_SESSION)){ Gek_Session::start(); }
        }

        const TIMES_LIMIT_TIME = 10;
        const EXPIRE_TIME = 60;
	//【已弃用】老接口地址
	/*
	public function send_sms_code_old($phone,$msg_type, $token)
	{
		if ($msg_type == 'reg') {
			//$msg = "您的验证码：".$token."，您正在注册乐智能平台帐号，10分钟内输入有效，请勿泄露。咨询电话0755-86537663【阿尔妮塔】";
			//$msg = "您的验证码：".$token."，您正在注册乐智能平台帐号，10分钟内输入有效，请勿泄露。咨询电话0755-86537663【鹿投】";
			$msg = "尊敬的用户，您的注册验证码为".$token.",请您在10分钟内输入。如非本人操作请忽略本短信【阿尔妮塔】";

		} else if($msg_type == 'find') {
			//$msg = "您的验证码：".$token."，您正在乐智能平台使用密码找回功能，10分钟内输入有效，请勿泄露。咨询电话0755-86537663【阿尔妮塔】";
			//$msg = "您的验证码：".$token."，您正在乐智能平台使用密码找回功能，10分钟内输入有效，请勿泄露。咨询电话0755-86537663【鹿投】";
			$msg = "尊敬的用户，您的找回密码验证码为".$token."，请您在10分钟内输入。如非本人操作请忽略本短信【阿尔妮塔】";
		} else {
			return false;
		}

		$msg = iconv("UTF-8","gb2312//IGNORE",$msg);
		$msg = rawurlencode($msg);

		$uid = 'sdk2862';
		$pwd = '123456';
		$gateway = "http://api.bjszrk.com/sdk/BatchSend2.aspx?CorpID={$uid}&Pwd={$pwd}&Mobile={$phone}&Content={$msg}&Cell=&SendTime=";

		$result = file_get_contents($gateway);

		if ($result <= 0) {
			log_message('ERROR', 'type: '.$msg_type.' phone:'.$phone.' send sms fail');
			return false;
		}

		log_message('ERROR', 'type: '.$msg_type.' phone:'.$phone.' send sms succ');
		return true;
	}
	*/

	public function send_sms_code($phone,$msg_type, $token, &$ret_msg)
	{
		if ($msg_type == 'reg') {
                        $msg = "【小妮选股】验证码：{$token}，您正在进行手机号注册操作，有效时间为15分钟，请尽快验证。";

		} else if($msg_type == 'find') {
			$msg = "【小妮选股】验证码：{$token}，您正在进行手机号验证修改密码操作，有效时间为15分钟，请尽快验证。";
		} else {
                        $ret_msg = 'invalid type';
			return false;
		}

                if (!$this->limit_check($phone, $ret_msg)) { return false; }

		$curl_data = array(
				'account' => 'SDK2862',
				'pswd' => 'SDK2862123@11',
				'mobile' => $phone,
				'msg' => $msg,
				'needstatus' => false,
				'product' => '',
				'extno' => '',
				'resptype' => 'json',
				);
		$o = "";
		foreach ($curl_data as $k => $v) {
			if ($k == 'msg')
				$o .= "$k=" . urlencode($v) . "&";
			else
				$o .= "$k=" . ($v) . "&";
		}
		$curl_data = substr($o, 0, -1);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "http://api.esoftsms.com/msg/HttpBatchSendSM");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $curl_data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $ret = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode != 200)  {
                    Gek_Log::write('type: '.$msg_type.' phone:'.$phone.' send sms fail, sms server is down , contact wechat: 13020096824', 'ERROR');
                    return false;
                }

                $ret = json_decode($ret, true);

		if (!empty($ret['result'])) {
                    Gek_Log::write('type: '.$msg_type.' phone:'.$phone.' send sms fail'.print_r($ret, true), 'ERROR');
                    return false;
		}

                Gek_Log::write('【科大讯飞】type: '.$msg_type.' phone:'.$phone.' send sms succ', 'ERROR');
		return true;
	}

	private function limit_check($phone, &$ret_msg)
        {
            $ret = true;
            $sms_60s_limit = 'sms_60s_limit' . $phone;
            $sms_day_limit = 'sms_day_limit' . $phone;
            $last_send_time = Gek_Session::get($sms_60s_limit);
            $sms_day_times = Gek_Session::get($sms_day_limit);
            do {
                if (!empty($last_send_time) AND time() < ($last_send_time + self::EXPIRE_TIME)) {
                    Gek_Log::write(' phone:'.$phone.' send sms failed , 60s limit', 'ERROR');
                    $ret_msg = "请60秒后再试";
                    $ret = false;
                    break;
                }

                if (!empty($sms_day_times) AND  $sms_day_times > self::TIMES_LIMIT_TIME) {
                    Gek_Log::write(' phone:'.$phone.' send sms failed , 10 times limit', 'ERROR');
                    $ret_msg = "今日已达到发送上限";
                    $ret = false;
                    break;
                }

            }while(0);

            if ($ret) {
                Gek_Session::set($sms_60s_limit, time());
                Gek_Session::set($sms_day_limit, $sms_day_times + 1);
            }

            return $ret;
        }
}
