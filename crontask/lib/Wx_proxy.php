<?php

class Wx_proxy
{
    public $curl = null;
    public $redis = null;
    public $wx_conf = array();
    public $access_token = null;

    public function __construct($curl, $redis, $wx_conf)
    {
        $this->curl = $curl;
        $this->redis = $redis;
        $this->wx_conf = $wx_conf;
        $this->access_token = '';
    }

    public function pushMsgToWxUser($openid, $template_id, $data, $url = "", $miniprogram)
    {
        $this->tryGetAccessToken();
        $totalData = array('touser' => $openid, 'template_id' => $template_id, 'url' => $url, 'data' => $data, 'miniprogram' => $miniprogram);
        $totalData = json_encode($totalData);
        $ret = $this->curl->rapid("https://api.weixin.qq.com/cgi-bin/message/template/send?access_token={$this->access_token}", 'POST', $totalData);
        $ret = json_decode($ret, true);
        if (!isset($ret['errcode']) OR $ret['errcode'] != 0) {
            if ($ret['errcode'] == 40001) {
                $this->tryGetAccessToken(true);
                //Gek_Log::write('force refresh access_token : ' . print_r($this->access_token, true));
                return $this->pushMsgToWxUser($openid, $template_id, $data, $url, $miniprogram);
            }
            //Gek_Log::write("push to : {$openid} failed, wx result:" . print_r($ret, true) . 'post data : ' . print_r($totalData, true) . 'mini info:' . print_r($miniprogram, true));
            return false;
        }
        return true;
    }


    public function tryGetAccessToken($force = false)
    {
        $this->access_token = $this->redis->GET($this->wx_conf['access_token_redis_key']);
        if (empty($this->access_token) OR $force) {
            $this->access_token = $this->getToken();
            $this->redis->SET($this->wx_conf['access_token_redis_key'], $this->access_token);
            $this->redis->EXPIRE($this->wx_conf['access_token_redis_key'], 6000);
            //Gek_Log::write('refresh wx access_token : ' . print_r($this->access_token, true));
        }
        return $this->access_token;
    }

    public function getToken($all = false)
    {
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->wx_conf['appid']}&secret={$this->wx_conf['secret']}";
        $result = $this->curl->rapid($url);
        $result = json_decode($result, true);

        if (empty($result['access_token'])) {
            //Gek_Log::write('get access_token failed' . print_r($result, true));
        }

        if ($all == true) {
            return $result;
        }
        return $result['access_token'];
    }
}
