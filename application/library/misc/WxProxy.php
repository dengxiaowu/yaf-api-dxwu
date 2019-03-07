<?php
/**
 * User: ben
 * Date: 2017/7/4
 * Time: 18:33
 */
include_once("Curl.php");
class WxProxy
{
    public $appid;
    public $secret;
    function __construct($appid = "", $secret = "") {
        $this->appid = $appid;
        $this->secret = $secret;
    }

    public function getToken($all = false){
        $curl = new Curl();
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->appid}&secret={$this->secret}";
        $result = $curl->rapid($url);
        $result = json_decode($result,true);

        if (empty($result['access_token'])) { Gek_Log::write('get access_token failed' . print_r($result, true)); }

        if($all == true){ return $result; }
        else{ return $result['access_token']; }
    }

    public function getUserInfo($access_token, $open_id)
    {
        $curl = new Curl();
        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token={$access_token}&openid={$open_id}&lang=zh_CN";
        $result = $curl->rapid($url);
        $result = json_decode($result, true);
        return $result;
    }

    public function getAuthRedirectUrl($r_url, $state = WX_AUTH_ACTION_GET_AVATAR)
    {
        if ($state == WX_AUTH_ACTION_GET_AVATAR) {
            return  "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$this->appid}&redirect_uri={$r_url}&response_type=code&scope=snsapi_userinfo&state={$state}#wechat_redirect";
        }else if ($state == WX_AUTH_ACTION_TRY_LOGIN) {
            return  "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$this->appid}&redirect_uri={$r_url}&response_type=code&scope=snsapi_base&state={$state}#wechat_redirect";
        }else if ($state == WX_AUTH_ACTION_WX_SHARE) {
            return  "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$this->appid}&redirect_uri={$r_url}&response_type=code&scope=snsapi_base&state={$state}#wechat_redirect";
        }
        return 'http://'.$_SERVER['HTTP_HOST'].'/?code=1&state=do_login';
    }

    public function getAuthAccessToken($code)
    {
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$this->appid}&secret={$this->secret}&code={$code}&grant_type=authorization_code";
        $curl = new Curl();
        $result = $curl->rapid($url);
        $result = json_decode($result,true);
        return $result;
    }

    public function getAuthUserInfo($auth_access_token, $openid)
    {
        $url = "https://api.weixin.qq.com/sns/userinfo?access_token={$auth_access_token}&openid={$openid}&lang=zh_CN";
        $curl = new Curl();
        $result = $curl->rapid($url);
        $result = json_decode($result, true);
        return $result;
    }

    public function getUserSubscribe($auth_access_token, $openid)
    {
        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token={$auth_access_token}&openid={$openid}&lang=zh_CN";
        $curl = new Curl();
        $result = $curl->rapid($url);
        $result = json_decode($result, true);
        return $result;
    }

    public function getSignPackage($jsapiTicket, $url)
    {
        $timestamp = time();
        $nonceStr = $this->createNonceStr();

        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        $signature = sha1($string);

        $signPackage = array(
            "appId"     => $this->appid,
            "nonceStr"  => $nonceStr,
            "timestamp" => $timestamp,
//            "url"       => $url,
            "signature" => $signature,
//            "rawString" => $string,
//         'jsapiTicket'=> $jsapiTicket,
        );
        return $signPackage;
    }

    public function getJsApiTicket($accessToken) {
        $curl = new Curl();
        $newTicket = $curl->rapid("https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken");
        $newTicket = json_decode($newTicket,true);
        if (!empty($newTicket['ticket'])) { return $newTicket['ticket']; }
        Gek_Log::write('get access_token failed' . print_r($newTicket, true));
        return false;
    }

    private function createNonceStr($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) { $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1); }
        return $str;
    }

    public function capture_pic($url, $path)
    {
        $curl  = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $file = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpCode != 200)  { return false; }

        $image_name = media_file_name();
        $full_path = media_file_path($path, $image_name);
        file_put_contents($full_path, $file);

        return $image_name;
    }
}