<?php

/**
 * User: ben
 * Date: 2017/7/5
 * Time: 10:47
 */
class WxModel extends BaseModel
{
    public $appid = NULL;
    public $secret = NULL;

    public $access_token = NULL;
    public $jsapi_ticket = NULL;
    public $wx_proxy = NULL;
    public $ACCESS_TOKEN_KEY = NULL;
    public $JSAPI_TICKET_KEY = NULL;
    public $sessionKey = NULL;
    public $logger = NULL;

    public function __construct($dsn, $appid, $secret)
    {
        parent::__construct($dsn);
        $this->appid = $appid;
        $this->secret = $secret;
        Yaf_loader::import('misc/WxProxy.php');
        $this->wx_proxy = new WxProxy($this->appid, $this->secret);
        $this->ACCESS_TOKEN_KEY = Yaf_Registry::get("config")->get('wx.ACCESS_TOKEN_redis_key');
        $this->JSAPI_TICKET_KEY = Yaf_Registry::get("config")->get('wx.JSAPI_TICKET_redis_key');
        $this->tryGetAccessToken();

        $this->logger = Gek_Logger::get_logger('wechat');


    }

    public function tryGetAccessToken()
    {
        do {
            if (!empty($this->access_token)) {
                break;
            }

            $this->access_token = $this->redis_instance()->GET($this->ACCESS_TOKEN_KEY);
            if (empty($this->access_token)) {
                $this->access_token = $this->wx_proxy->getToken();
                $this->redis_instance()->SET($this->ACCESS_TOKEN_KEY, $this->access_token);
                $this->redis_instance()->EXPIRE($this->ACCESS_TOKEN_KEY, 6000);
                $this->logger('refresh wx access_token : ' . print_r($this->access_token, true));
            }
        } while (0);
        return $this->access_token;
    }

    public function getUserInfo($openid)
    {
        $info = $this->wx_proxy->getUserInfo($this->access_token, $openid);
        if (isset($info['errcode']) AND $info['errcode'] != 0) {
            $this->logger("try get user failed, openid:{$openid}, data:" . json_encode($info));
            return false;
        }
        return $info;
    }

    public function getAuthRedirectUrl($replace_url = 'REDIRECT_URI', $state)
    {
        $replace_url = urlencode($replace_url);
        return $this->wx_proxy->getAuthRedirectUrl($replace_url, $state);
    }

    public function getAuthUserInfo($code)
    {
        $ret = $this->wx_proxy->getAuthAccessToken($code);
        if (empty($ret['access_token']) OR empty($ret['openid'])) {
            $this->logger('getAuthUserInfo failed' . print_r($ret, true));
            return false;
        }
        $ret = $this->wx_proxy->getAuthUserInfo($ret['access_token'], $ret['openid']);
        if (empty($ret['nickname']) OR empty($ret['headimgurl'])) {
            $this->logger('getAuthUserInfo failed' . print_r($ret, true));
            return false;
        }
        return $ret;
    }

    public function userIsSubscribe($openid)
    {
        $ret = $this->wx_proxy->getUserSubscribe($this->access_token, $openid);
        if (empty($ret) OR empty($ret['subscribe'])) {
            return false;
        }
        return $ret;
    }

    public function tryGetJsapiTicket()
    {
        do {
            if (!empty($this->jsapi_ticket)) {
                break;
            }

            $this->jsapi_ticket = $this->redis_instance()->GET($this->JSAPI_TICKET_KEY);
            if (empty($this->jsapi_ticket)) {
                $this->jsapi_ticket = $this->wx_proxy->getJsApiTicket($this->access_token);
                $this->redis_instance()->SET($this->JSAPI_TICKET_KEY, $this->jsapi_ticket);
                $this->redis_instance()->EXPIRE($this->JSAPI_TICKET_KEY, 6000);
                $this->logger('refresh wx JsapiTicket : ' . print_r($this->jsapi_ticket, true));
            }
        } while (0);
        return $this->jsapi_ticket;
    }

    public function getJssdkConf($jsapiTicket, $url)
    {
        return $this->wx_proxy->getSignPackage($jsapiTicket, $url);
    }

    public function tryCapturePic($url, $path)
    {
        $img = $this->wx_proxy->capture_pic($url, $path);
        if ($img) {
            return $img;
        }
        $this->logger('try get headimg failed' . $url);
        return false;
    }

    //获取微信小程序code换取 用户唯一标识openid 和 会话密钥session_key
    public function getWxLoginInfo($code, $appid, $secret)
    {
        $url = 'https://api.weixin.qq.com/sns/jscode2session?';
        $param = "appid={$appid}&secret={$secret}&js_code={$code}&grant_type=authorization_code";
        $url = $url . $param;
        $result = Gek_Http::httpGet($url);
        if (isset($result['openid']) && !empty($result['openid'])) {
            return $result;
        }else{
            $this->logger->info("code: ".$code." | code result:".json_encode($result));
            return [];
        }
    }

    //解密获取小程序用户的手机号
    public function getWxPhone($encryptedData, $iv, $sessionKey)
    {
        $this->sessionKey = $sessionKey;
        $errCode = $this->decryptData($encryptedData, $iv, $data);
        if ($errCode == 0) {
            return $data;
        } else {
            $this->logger->warn('data:' . $encryptedData . '|' . 'iv:' . $iv . '|' . 'sessionKey:' . $sessionKey);
            $this->logger->warn('err_code :' . $errCode);
            return [];
        }
    }

    /**
     * 检验数据的真实性，并且获取解密后的明文.
     * @param $encryptedData string 加密的用户数据
     * @param $iv string 与用户数据一同返回的初始向量
     * @param $data array 解密后的原文
     *
     * @return int 成功0，失败返回对应的错误码
     */
    private function decryptData($encryptedData, $iv, &$data)
    {
        if (strlen($this->sessionKey) != 24) {
            return ErrorCode::IllegalAesKey;
        }
        $aesKey = base64_decode($this->sessionKey);

        if (strlen($iv) != 24) {
            return ErrorCode::IllegalIv;
        }

        $aesIV = base64_decode($iv);

        $aesCipher = base64_decode($encryptedData);

        $result = openssl_decrypt($aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);
        $dataObj = json_decode($result);
        if ($dataObj == NULL) {
            return ErrorCode::IllegalBuffer;
        }
        if ($dataObj->watermark->appid != $this->appid) {
            return ErrorCode::IllegalBuffer;
        }
        $data = json_decode($result, true);
        return ErrorCode::OK;
    }

    /**
     * APP 获取access_token
     * @param string $code
     * @return array
     */
    public function getAppAccessToken($code)
    {

        $key_pre = get_redis_pre();
        $redis_key = $key_pre.'_'.$code;

        $data = $this->redis_instance()->get($redis_key);
        if ($data){
            return unserialize($data);
        }

        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?";
        $param = "appid=" . $this->appid . "&secret=" . $this->secret . "&code=" . $code . "&grant_type=authorization_code";

        $get_url = $url . $param;
        $result = Gek_Http::httpGet($get_url);
        if (isset($result['access_token']) && !empty($result['access_token']) && isset($result['unionid']) && !empty($result['unionid'])) {
            $this->redis_instance()->set($redis_key, serialize($result));
            return $result;
        } elseif (isset($result['access_token']) && !empty($result['access_token']) && empty($result['unionid'])) {
            $data = $this->getAppUserInfo($result['access_token'], $result['openid']);
            $this->redis_instance()->set($redis_key, serialize($data));
            return $data;
        }
        $this->logger->warn('err get token :' . json_encode($result));
        return [];
    }

    /**
     * app 获取用户信息
     */
    public function getAppUserInfo($access_token, $openid)
    {
        $url = "https://api.weixin.qq.com/sns/userinfo?";
        $param = "access_token=".$access_token."&openid=".$openid;

        $get_url = $url . $param;
        $result = Gek_Http::httpGet($get_url);
        if (isset($result['unionid']) && !empty($result['unionid'])) {
            return $result;
        }
        $this->logger->warn('err get info :' . json_encode($result));
        return [];
    }
}