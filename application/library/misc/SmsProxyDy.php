<?php
/**
 * User: ben
 * Date: 2017/7/19
 * Time: 10:48
 */

class SmsProxyDy
{

    public $accessKeyId = NULL;
    public $accessKeySecret = NULL;
    public $templateCodeReg = NULL;
    public $templateCodeForget = NULL;

    const TIMES_LIMIT_TIME = 10;
    const EXPIRE_TIME = 60;

    public function __construct()
    {
        $this->config = Yaf_Registry::get("config");
        if (empty($this->accessKeyId)) { $this->accessKeyId = $this->config->get('sms_dy.appid'); }
        if (empty($this->accessKeySecret)) { $this->accessKeySecret = $this->config->get('sms_dy.secret'); }
        if (empty($this->templateCodeReg)) { $this->templateCodeReg = $this->config->get('sms_dy.reg'); }
        if (empty($this->templateCodeForget)) { $this->templateCodeForget = $this->config->get('sms_dy.forget'); }
    }

    public function send_sms_code($phone, $msg_type, $token, &$ret_msg)
    {
        if (!$this->limit_check($phone, $ret_msg)) { return false; }

        Yaf_Loader::import('sms_dy/aliyun-php-sdk-core/Config.php');

        /*此处固定*/
        $product = "Dysmsapi";
        $domain = "dysmsapi.aliyuncs.com";
        $region = "cn-hangzhou";
        /*此处固定*/

        //初始化访问的acsCleint
        $profile = DefaultProfile::getProfile($region, $this->accessKeyId, $this->accessKeySecret);
        DefaultProfile::addEndpoint($region, $region, $product, $domain);
        $acsClient= new DefaultAcsClient($profile);

        $request = new Dysmsapi\Request\V20170525\SendSmsRequest;
        $request->setPhoneNumbers($phone);
        $request->setSignName("一智腾飞");

        if ($msg_type == 'reg') {
            $request->setTemplateCode($this->templateCodeReg);
        } else if($msg_type == 'find') {
            $request->setTemplateCode($this->templateCodeForget);
        } else {
            $ret_msg = 'invalid type';
            return false;
        }

        $request->setTemplateParam(json_encode(array('code' => $token)));
        $acsResponse = $acsClient->getAcsResponse($request);

        $ret = true;
        if (empty($acsResponse) OR strtolower($acsResponse->Code) !== 'ok') {
            Gek_Log::write('短信发送错误 dy_rsp:'.print_r($acsResponse, true));
            $ret = false;
        }
        Gek_Log::write('【阿里通讯】type: '.$msg_type.' phone:'.$phone.' send sms succ', 'ERROR');
        return $ret;

    }

    private function limit_check($phone, &$ret_msg)
    {
        if(!isset($_SESSION)){ Gek_Session::start(); }
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