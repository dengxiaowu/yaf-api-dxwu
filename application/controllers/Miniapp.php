<?php

/**
 *
 * Created by PhpStorm.
 * User: dengxiaowu@innofi.cn
 * Date: 2018/4/23
 * Time: 15:37
 */
class MiniappController extends BasexController
{
    private $appid = '';
    private $secret = '';
    private $logger = '';

    public $wxappid = '';
    public $wxsecret = '';

    public function init()
    {
        $notNeedLoginAction = [
            'wxLogin',
            'getWxPhone',
            'saveWxInfo',
            'getStart',
            'getAccessToken',
            'getQrcode',
            'getUnionInfo',
            'shareNews',
        ];

        $needSessionAction = [];

        $this->_init($notNeedLoginAction, $needSessionAction);
        //微信小程序的配置
        $this->appid = $this->config->get("miniapp.appid");
        $this->secret = $this->config->get("miniapp.secret");

        //web wechat
        $this->wxappid = $this->config->get("wx.appid");
        $this->wxsecret = $this->config->get("wx.secret");

        $this->logger = Gek_Logger::get_logger('miniapp_con');
    }

    /**
     * 小程序审核接口
     */
    public function getStartAction()
    {
        //版本控制
        $appVersion = (string)$this->getParam('appVersion', '');
        //来源
        $platform = (string)$this->getParam('platform', 'miniapp');
        if (empty($platform) || $platform != 'miniapp') {
            return $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '平台错误');
        }
        if (empty($appVersion)) {
            return $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '版本号为空');
        }
        //1 提交审核 2审核通过
        $MiniappCheckStatusModel = new MiniappCheckStatusModel($this->dsn);
        $param['platform'] = $platform;
        $param['version'] = $appVersion;
        $info = $MiniappCheckStatusModel->get_check_info('check_status', $param);
        if (empty($info)) {
            $data['start'] = 2;
        } else {
            $data['start'] = (int)$info['check_status'];
        }

        $this->successOutput($data);
    }

    /**
     * 1.3.2 微信unionID 绑定 注册 登录
     */
    public function getWxPhoneAction()
    {
        $encryptedData = $this->getParam('encryptedData', '');
        $iv = $this->getParam('iv', '');
        $code = $this->getParam('code', '');
        $inviter = $this->getParam('inviter', '');

        if (empty($encryptedData)) {
            $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '参数data为空');
        }
        if (empty($iv)) {
            $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '参数iv为空');
        }
        if (empty($code)) {
            $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '参数code为空');
        }

        //平台
        $platform = (string)$this->getParam('platform', 'miniapp');
        //活动类型 code, like
        $activity_type = (string)$this->getParam('activity_type', '');

        $pointModel = new BuriedPointModel($this->dsn);
        $a_type_num = $pointModel->get_activity_type_num($activity_type);
        //埋点类型
        $type = (string)$this->getParam('type', '');
        $type_num = $pointModel->get_type_num($type);

        $userModel = new UserModel($this->dsn);
        $wxModel = new MiniappModel($this->dsn, $this->appid, $this->secret);

        //获取session_key
        $sessionInfo = $wxModel->getWxLoginInfo($code, $this->appid, $this->secret);
        if (empty($sessionInfo)) {
            return $this->errorOutput(ErrorCode::ERR_WX_ANALYSIS, 'code解析数据失败');
        }
        $miniopenid = $sessionInfo['openid'];
        $unionid = isset($sessionInfo['unionid']) ? $sessionInfo['unionid'] : "";
        $wxInfo = $wxModel->getWxPhone($encryptedData, $iv, $sessionInfo['session_key']);

        if (empty($wxInfo)) {
            return $this->errorOutput(ErrorCode::ERR_WX_ANALYSIS, '解析数据失败');
        }
        $mobile = $wxInfo['purePhoneNumber'];
        // 验证用户名是否已经存在
        $has = $userModel->mobileIsRegister($mobile);

        //如果login中解密不到unionID，则去个人信息中解密
        if (empty($unionid)) {
            $unionid = (string)$this->getParam('unionid', '');
        }
        //检查unionID 绑定情况
        $userModel->delUnionid($unionid);

        // 检查用户的邀请人是否存在
        if (!empty($inviter)) {
            $r = $userModel->mobileIsRegister($inviter);
            if (empty($r)) {
                $inviter = '';
            }
        }

        if ($has) {
            $isNew = 0;
            $user = array(
                'miniopenid' => $miniopenid,
                'unionid' => $unionid
            );
            $res = $userModel->updateUserInfo($mobile, $user);
        } else {
            $isNew = 1;
            $user = array(
                'miniopenid' => $miniopenid,
                'unionid' => $unionid,
                'mobile' => $mobile,
                'realname' => $mobile,
                'inviter' => $inviter,
                'password' => '',
                'reg_ip' => Gek_Request::getUserIP(),
                'reg_time' => date("Y-m-d H:i:s"),
                'login_device' => 0,
                'login_deviceId' => '',
                'type' => $a_type_num, //用户类型
                'point' => $type_num,  //埋点类型
            );
            $res = $userModel->put($user);
        }
        if ($res < 0) {
            return $this->errorOutput(ErrorCode::ERR_INVALID_PARAMETER, '登录1失败');
        }
        // 清除之前的登录状态
        Gek_Session::destroyAll();
        $user = $userModel->getByMobile($mobile);
        if (empty($user)) {
            return $this->errorOutput(ErrorCode::ERR_INVALID_PARAMETER, '登录2失败');
        }
        $user = $userModel->_do_login(true, $user['id']);
        if ($isNew) {
            //注册送14天的使用期
            $services = array();
            $service = array();
            $service['user_id'] = $user['id'];
            $service['service_id'] = 1;
            $service['start_time'] = date('Y-m-d');
            $service['end_time'] = date('Y-m-d', strtotime("{$service['start_time']} +14 days"));
            $services[] = $service;
            $service['service_id'] = 2;
            $services[] = $service;
            $db = Gek_Db::getInstance($this->dsn);
            $db->insert('user_services', $services);
            //======================== 新的订阅方式 start =============
            //订阅股票池 小程序
            $selectObj = new SelectModel($this->dsn);
            //1-一智金股、2-zlld、5-涨停大师
            $selectObj->updateSubscribeMini($user['id'], 1, 1);
            $selectObj->updateSubscribeMini($user['id'], 5, 1);
            $selectObj->updateSubscribeMini($user['id'], 2, 1);
            //======================== 新的订阅方式 end =============
            $user['rewards']['title'] = '恭喜';
            $user['rewards']['content']['first'] = '您已成功获取价值';
            $user['rewards']['content']['price'] = '999';
            $user['rewards']['content']['second'] = '元注册大礼包—';
            $user['rewards']['content']['award'] = '免费试用付费股票池一智金股和涨停大师14天！';
            if (get_api_env() == 'online') {
                $user['rewards_img'] = 'https://wx.firstwisdom.cn/public/images/reward_999.png';
            } else {
                $user['rewards_img'] = 'https://wxtest.firstwisdom.cn/public/images/reward_999.png';
            }
        }
        //返回临时头像昵称
        $key_pre = get_redis_pre();
        $redis_key = $key_pre . '_' . $unionid;
        $redis_data = Gek_Redis::factory()->get($redis_key);
        if ($redis_data) {
            $redis_info = json_decode($redis_data, true);
            $user['avatar'] = $redis_info['headimgurl'];
            $user['nickname'] = $redis_info['nickname'];
            //异步处理
            $param['unionid'] = $unionid;
            $get_url = getDomainUrl() . '/User/saveWxInfo';
            AsyncRequest($get_url, $param); //下载更新头像
        }

        //活动新用户注册返回数据 like活动
        $user['show_new'] = "0";
        if ($isNew && $a_type_num == 2) {
            $user['show_new'] = "1";
        }

        return $this->successOutput($user, '登录成功');
    }

    /**
     * 保存微信用户信息
     *
     */
    public function saveWxInfoAction()
    {
        $userInfo = $this->getParam('userInfo', '');
        $rawData = $this->getParam('rawData', '');
        $signature = $this->getParam('signature', '');
        $encryptedData = $this->getParam('encryptedData', '');
        $iv = $this->getParam('iv', '');
        $code = $this->getParam('code', '');
        if (empty($rawData) || empty($userInfo)) {
            $this->errorOutput(ErrorCode::ERR_PARAM, '参数为空');
        }
        $wxInfo = json_decode($rawData, true);
        if (empty($wxInfo)) {
            $wxInfo = json_decode($userInfo, true);
            if (empty($wxInfo)) {
                $this->errorOutput(ErrorCode::ERR_PARAM, 'json解析错误');
            }
        }

        $wxModel = new WxModel($this->dsn, $this->appid, $this->secret);

        $wxInfo['avatar'] = isset($wxInfo['avatarUrl']) ? $wxModel->tryCapturePic($wxInfo['avatarUrl'], $this->config->get('upload.path')) : '';

        $wxInfo['encryptedData'] = $encryptedData;
        $wxInfo['iv'] = $iv;

        Gek_Session::set('wx_user_info', $wxInfo);
        $this->successOutput();
    }


    //小程序获取token
    public function getAccessTokenAction()
    {
        $miniappModel = new MiniWxModel($this->dsn, $this->appid, $this->secret);

        $data['access_token'] = $miniappModel->GetMiniAppAccessToken();

        $this->successOutput($data);
    }

    /**
     * 小程序二维码
     */
    public function getQrcodeAction()
    {
        //版本控制
        $appVersion = (string)$this->getParam('appVersion', '');
        //来源
        $platform = (string)$this->getParam('platform', 'miniapp');

        $scene = (string)$this->getParam('scene', 'test');
        $page = (string)$this->getParam('page', 'pages/firstWisdom');
        $width = (int)$this->getParam('width', 430);
        $filename = (string)$this->getParam('filename', 'test');

        if (empty($filename)) {
            $filename = getRandChar(5);
        }

        $miniappModel = new MiniWxModel($this->dsn, $this->appid, $this->secret);
        $data = $miniappModel->create_qrcode($scene, $page, $width, $filename);
        if ($data['status'] == 0) {
            $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '生成二维码失败');
        }
        $this->successOutput($data['info']);

    }

    /**
     * 小程序授权获取用户信息，获取unionID
     */
    public function getUnionInfoAction()
    {
        $userInfo = $this->getParam('userInfo', '');
        $encryptedData = $this->getParam('encryptedData', '');
        $iv = $this->getParam('iv', '');
        $code = $this->getParam('code', '');

        if (empty($encryptedData) || empty($userInfo) || empty($iv) || empty($code)) {
            $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '参数为空');
        }

        $wxInfo = json_decode($userInfo, true);
        if (empty($wxInfo)) {
            $this->errorOutput(ErrorCode::ERR_PARAM_NEW, 'json解析错误');
        }

        //获取微信手机号
        $wxModel = new MiniappModel($this->dsn, $this->appid, $this->secret);
        //获取session_key
        $sessionInfo = $wxModel->getWxLoginInfo($code, $this->appid, $this->secret);
        if (empty($sessionInfo)) {
            return $this->errorOutput(ErrorCode::ERR_WX_ANALYSIS, 'code解析数据失败');
        }

        $openid = isset($sessionInfo['openid']) ? $sessionInfo['openid'] : "";
        $unionid = isset($sessionInfo['unionid']) ? $sessionInfo['unionid'] : "";

        //如果login中解密不到unionID，则去个人信息中解密
        if (empty($unionid)) {
            $sessionWxInfo_jiemi = $wxModel->getWxPhone($encryptedData, $iv, $sessionInfo['session_key']);
            if (isset($sessionWxInfo_jiemi['unionId'])) {
                $unionid = $sessionWxInfo_jiemi['unionId'];
            } else {
                $this->logger->warn('get unionid fail.');
            }
        }
        if (empty($unionid)) {
            return $this->errorOutput(ErrorCode::ERR_WX_ANALYSIS, 'unionid获取失败');
        }

        //获取用户微信信息缓存
        if ($wxInfo) {
            $key_pre = get_redis_pre();
            $redis_key = $key_pre . '_' . $unionid;
            $saveInfo['headimgurl'] = isset($wxInfo['avatarUrl']) ? $wxInfo['avatarUrl'] : '';
            $saveInfo['nickname'] = isset($wxInfo['nickName']) ? $wxInfo['nickName'] : '';
            Gek_Redis::factory()->set($redis_key, json_encode($saveInfo), 3600); //1小时
        }
        //检查unionID 绑定情况
        $userModel = new UserModel($this->dsn);
        $u_data = $userModel->get_unionid($unionid);

        if (empty($u_data)) {
            $info_ret['unionid'] = $unionid;
            return $this->result($info_ret, $info = '无关联', ErrorCode::ERR_UNIONID_NOT);
        }

        //更新openid
        $u_data['miniopenid'] = $openid;
        $userModel->UpdateAvatar($unionid, $u_data);

        //登录，返回用户信息
        Gek_Session::destroyAll();
        $user = $userModel->_do_login(true, $u_data['id']);
        if ($wxInfo) {
            $user['avatar'] = isset($wxInfo['avatarUrl']) ? $wxInfo['avatarUrl'] : '';
            $user['nickname'] = isset($wxInfo['nickName']) ? $wxInfo['nickName'] : '';
            $param['unionid'] = $unionid;
            $url = getDomainUrl();
            $get_url = $url . '/User/saveWxInfo';
            AsyncRequest($get_url, $param); //下载更新头像
        }

        //活动新用户注册返回数据 like活动
        $user['show_new'] = "0";

        return $this->successOutput($user, '登录成功');
    }

    /**
     * 小程序分享图片新闻
     */
    public function shareNewsAction()
    {
        $new_id = (int)$this->getParam('id', 0);

        if (empty($new_id)) {
            $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '参数为空');
        }

        $newsObj = new NewsModel($this->dsn);
        $field = 'id, title, content, pubtime, source, weight';
        $news = $newsObj->getNewsById($new_id, $field);
        if (empty($news)) {
            $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '参数错误');
        }

        $news['weight'] = ($news['weight'] == 101) ? '1' : '0';
        $news['time_line'] = strtotime($news['pubtime']);

        $date = date('Y-m-d H:i', $news['time_line']);

        $gData['image_date'] = $date;
        $gData['image_content'] = $news['content'];

        $fileName = 'public/images/share_' . $new_id . '.png';

        $news_b = 'public/images/news_b.png';

        $imageModel = new ImageModel($this->dsn);
        $fileName = '';
        $image_url = $imageModel->createSharePng($gData, $news_b, $fileName);
        $ret_data['image'] = $image_url;
        $this->successOutput($ret_data);

    }

    public function isSubscribeWxAction()
    {
        //检查用户是否登录,并从session中读取用户ID
        if (!$this->islogin) return;
        $userId = Gek_Session::get('user_id');
        //判断用户是否关注公众号
        $userModel = new UserModel($this->dsn);
        $info = $userModel->get($userId);
        if (empty($info) || empty($info['openid'])) {
            return $this->errorOutput(ErrorCode::ERR_PARAM_FOLLOW, '需要关注公众号');
        } else {
            return $this->successOutput();
        }
    }

}