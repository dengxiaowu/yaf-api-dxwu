<?php

/**
 * User: dengxiaowu
 * Date: 2018/7/12
 * Time: 18:36
 */
class WxController extends BasexController
{
    public $logger = "";
    public $appid = "";
    public $secret = "";

    public function init()
    {
        $notNeedLoginAction = [
            'createMenu',
            'index',
        ];

        $needSessionAction = [];

        $this->_init($notNeedLoginAction, $needSessionAction);
        //微信配置
        $this->appid = $this->config->get("wx.appid");
        $this->secret = $this->config->get("wx.secret");
        $this->logger = Gek_Logger::get_logger('wx_con');
    }

    public function indexAction()
    {
        $token = 'firstwisdom';
        $EncodingAESKey = "vinoum6QN5TzffBhZOW5S4CqHiRQyPOy7ED59CM3poN";

        $timestamp = $_GET['timestamp'];
        $nonce = $_GET['nonce'];
        $signature = $_GET['signature'];
        $array = array($timestamp, $nonce, $token);
        sort($array);

        //2.将排序后的三个参数拼接后用sha1加密
        $tmpstr = implode('', $array);
        $tmpstr = sha1($tmpstr);

        //3. 将加密后的字符串�� signature 进行对比, 判断该请求是否来自微��
        if ($tmpstr == $signature && $_GET['echostr']) {
            ob_clean();
            $this->logger->info('echostr:' . $_GET['echostr']);
            exit;
        } else {
            $this->reponseMsg();
        }
    }

    public function reponseMsg()
    {
        //1.获取到微信推送过来post数据（xml格式）
        $xml = file_get_contents("php://input");

        //2.处理消息类型，并设置回复类型和内容
        $postArr = $this->xml2data($xml);

        //判断该数据包是否是订阅的事件推送
        if (strtolower($postArr['MsgType']) == 'event') {
            $wechatModel = new WxModel($this->dsn, $this->appid, $this->secret);
            $userModel = new UserModel($this->dsn);
            //如果是关注 subscribe 事件
            if (strtolower($postArr['Event'] == 'subscribe')) {
                $openid = $postArr['FromUserName'];
            }
            //如果是关注 subscribe 事件
            if (strtolower($postArr['Event'] == 'unsubscribe')) {
                $openid = $postArr['FromUserName'];
            }
            $this->logger->info('infos:' . json_encode($postArr));
            $this->logger->info('openid:' . $openid);

            $info = $wechatModel->getUserInfo($openid);
            if ($info) {
                $unionid = isset($info['unionid']) ? $info['unionid'] : "";
                $update['openid'] = $openid;
                $ret = $userModel->UpdateAvatar($unionid, $update);
                if (!$ret) {
                    //unionID 没有绑定，存到redis
                    $list_key = 'subscribe_user';
                    $list_data['unionid'] = $unionid;
                    $list_data['openid'] = $openid;
                    $list_json = json_encode($list_data);
                    Gek_Redis::factory()->lPush($list_key, $list_json);
                }else {
                    $this->logger->info('update info ok: $unionid:' .$unionid.'| openid:'. $openid);
                }

            } else {
                $this->logger->info('get user info fail:' . $openid);
            }
        }
    }

    /**
     * XML数据解码
     * @param  string $xml 原始XML字符串
     * @return array       解码后的数组
     */
    private function xml2data($xml)
    {
        $xml = new \SimpleXMLElement($xml);

        if (!$xml) {
            throw new \Exception('非法XXML');
        }

        $data = array();
        foreach ($xml as $key => $value) {
            $data[$key] = strval($value);
        }

        return $data;
    }
}