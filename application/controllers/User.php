<?php

class UserController extends BaseController
{
    const RegUrl = '/#/auth';
    const IndexUrl = '/';

    const MSG_TYPE_JG = 1;
    const MSG_TYPE_ZD = 2;
    const MSG_TYPE_T0 = 3;
    const MSG_TYPE_DK = 4;

    public $miniappid = '';
    public $minisecret = '';

    public $wxappid = '';
    public $wxsecret = '';

    public $appappid = '';
    public $appsecret = '';

    public $logger = '';

    public $needLoginAction = array(
        'get',
        'uploadavatar',
        'editprofile',
        'getqrcode',
        'getmsgcenter',
        'getsubscribemsg',
        'setreadedmsg',
        'checkBind',
        'bindWechat'
    );


    public function init()
    {
        parent::init();

        //微信小程序的配置
        $this->miniappid = $this->config->get("miniapp.appid");
        $this->minisecret = $this->config->get("miniapp.secret");

        //app
        $this->appappid = $this->config->get("app.appid");
        $this->appsecret = $this->config->get("app.secret");

        //web wechat
        $this->wxappid = $this->config->get("wx.appid");
        $this->wxsecret = $this->config->get("wx.secret");

        $this->logger = Gek_Logger::get_logger('user_cont');

    }

    public function login_webAction()
    {
        $account = $this->request->getRequest('account');
        $password = (string)$this->request->getRequest('password');

        //code作为换取access_token的票据，每次用户授权带上的code将不一样，code只能使用一次，5分钟未被使用自动过期。
        $code = $this->request->getRequest('code');

        if (empty($code)) {

            if (empty($account) || strlen($password) <= 0) {
                return $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '账号或者密码为空');
            }

            if ($this->is_weixin()) {
                $state = base64_encode($account . '-' . $password);
                $urlDomain = getDomainUrl();
                $redirectUrl = $urlDomain . "/user/login_web";
                $redirectUrl = strtolower(urlencode($redirectUrl));
                $indexUrl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$this->wxappid}&redirect_uri={$redirectUrl}&response_type=code&scope=snsapi_base&state={$state}#wechat_redirect";
                $this->redirect($indexUrl);
                return;
            }
        }

        $state = $this->request->getRequest('state');
        if (empty($state)) {
            return $this->errorOutput(ErrorCode::ERR_PARAM_NEW, 'state参数为空');
        }
        list($account, $password) = explode('-', base64_decode($state));
        // 验证用户名和密码
        $userModel = new UserModel($this->dsn);
        $r = $userModel->getByMobile($account);
        if (empty($r)) {
            return $this->errorOutput(ErrorCode::ERR_NOT_PHONE, '手机号未注册');
        }

        if (!Gek_Security::password_verify($password, $r['password']) != 0) {
            return $this->errorOutput(ErrorCode::ERR_NOT_PASS, '密码错误');
        }

        $user = $userModel->_do_login(true, $r['id']);
        //获取openID
        $wxModel = new WxModel($this->dsn, $this->wxappid, $this->wxsecret);
        $wxInfo = $wxModel->getAuthUserInfo($code);
        $openid = '';
        if ($wxInfo && isset($wxInfo['openid'])) {
            $openid = $wxInfo['openid'];
        }
        $userModel->updateUserInfo($user['mobile'], array('openid' => $openid));

        return $this->successOutput($user);
    }

    private function getWxUserInfoUrl($state)
    {
        $appId = $this->config->get("wx.appid");
        $secret = $this->config->get("wx.secret");
        $urlScheme = $this->config->get("url.scheme");
        $urlDomain = $this->config->get("url.domain");
        $redirectUrl = "{$urlScheme}%3a%2f%2f{$urlDomain}%2fuser%2fgetWxUserInfo";
        return "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$appId}&redirect_uri={$redirectUrl}&response_type=code&scope=snsapi_userinfo&state={$state}#wechat_redirect";
    }

    // 用于微信回调，获取微信用户信息
    public function getWxUserInfoAction()
    {
        $urlScheme = $this->config->get("url.scheme");
        $urlDomain = $this->config->get("url.domain");
        $indexUrl = "$urlScheme://$urlDomain" . self::IndexUrl;

        $state = $this->request->getRequest('state');
        if (empty($state)) {
            $this->redirect($indexUrl);
            return;
        }

        //code作为换取access_token的票据，每次用户授权带上的code将不一样，code只能使用一次，5分钟未被使用自动过期。 
        $code = $this->request->getRequest('code');
        if (empty($code)) {
            $this->redirect($indexUrl);
            return;
        }

        $pos = strpos($state, '-');
        if ($pos === fasle) {
            $this->redirect($indexUrl);
            return;
        }

        $userId = (int)substr($state, 0, $pos);
        if ($userId <= 0) {
            $this->redirect($indexUrl);
            return;
        }
        $gotoUrl = substr($state, $pos + 1);
        if (!empty($gotoUrl)) {
            $gotoUrl = base64_decode($gotoUrl);
        }

        //服务器拉取用户信息
        $ret = $this->WxInit()->getAuthUserInfo($code);
        if (empty($ret)) {
            if (empty($gotoUrl)) $gotoUrl = $indexUrl;
            $this->redirect($gotoUrl);
            return;
        }

        // 更新用户信息和下载用户头像
        $profile = array(
            'nickname' => emoji2str($ret['nickname']),
            'name' => emoji2str($ret['nickname']),
            'avatar' => $ret['headimgurl'],
            'openid' => $ret['openid'],
        );

        //下载头像
        $img = $this->WxInit()->tryCapturePic($ret['headimgurl'], $this->config->get('upload.path'));
        if ($img) {
            $profile['avatar'] = $img;
        }

        $this->userInit()->editProfile($userId, $profile);

        // 跳转到指定的页面
        if (empty($gotoUrl)) {
            $gotoUrl = $indexUrl;
        }
        $this->redirect($gotoUrl);
    }


    public function feedbackAction()
    {
        $ret = array(
            'data' => array(),
            'info' => 'success',
            'status' => 0,
        );
        $feedback = $this->request->getRequest('feedback');
        $contact = (string)$this->request->getRequest('contact');//联系方式
        $user_ip = (string)Gek_Request::getUserIP();

        do {
            if (empty($feedback)) {
                $ret['info'] = "请输入反馈内容";
                $ret['status'] = 100;
                break;
            }
            $uid = $this->u_id ? $this->u_id : "100000";
            if (!$this->userInit()->pushUserFeedBack($feedback, $uid, $contact, $user_ip)) {
                $ret['info'] = "意见反馈提交失败，请稍后再试";
                $ret['status'] = 200;
                break;
            }
        } while (0);

        $this->ajax_return($ret['data'], $ret['info'], $ret['status']);
    }


    public function indexAction()
    {
    }

    public function getAction()
    {
        $user = $this->userInit()->do_get();
        return $this->ajax_return($user, '获取用户信息成功', 0);
    }

    public function mobileIsExistAction()
    {
        $mobile = $this->request->getRequest('mobile');
        if (empty($mobile)) {
            return $this->ajax_return('', 'missing mobile', 1);
        }

        $this->userInit($this->dsn);
        $r = $this->userInit()->mobileIsRegister($mobile);
        if (!empty($r)) {
            return $this->ajax_return(1, 'already exist', 0);
        } else {
            return $this->ajax_return(0, 'not exist', 0);
        }
    }

    public function sendSmsAction()
    {
        $ret = array(
            'data' => array(),
            'info' => "",
            'status' => 0,
        );

        $mobile = $this->request->getRequest('mobile');
        if (empty($mobile) OR !is_numeric($mobile)) {
            $ret['info'] = '手机号格式不正确';
            $ret['status'] = 1;
            return $this->ajax_return($ret['data'], $ret['info'], $ret['status']);
        }

        $type = $this->request->getRequest('type');

        if ($type && !in_array($type, array('find', 'reg', 'bind'))) {
            $ret['info'] = "类型错误";
            $ret['status'] = 100;
            return $this->ajax_return($ret['data'], $ret['info'], $ret['status']);
        }
        if ($type == 'find' AND !$this->userInit()->getByMobile($mobile)) {
            $ret['info'] = "该手机号并未注册，请先注册";
            $ret['status'] = 100;
            return $this->ajax_return($ret['data'], $ret['info'], $ret['status']);

        } else if ($type == 'reg' AND $this->userInit()->getByMobile($mobile)) {
            $ret['info'] = '手机号已注册，请尝试忘记密码';
            $ret['status'] = 1;
            return $this->ajax_return($ret['data'], $ret['info'], $ret['status']);
        }

        $code = mt_rand(100000, 999999);
        $sms_using = $this->config->get('sms');
        switch ($sms_using) {
            case 'sms_dy':
                Yaf_Loader::import('misc/SmsProxyDy.php');
                $sms = new SmsProxyDy();
                break;
            default :
                Yaf_Loader::import('misc/SmsProxy.php');
                $sms = new SmsProxy();
        }
        if ($type == 'bind') {
            $type = 'reg';
        }
        if (!$sms->send_sms_code($mobile, $type, $code, $ret['info'])) {
            $ret['status'] = 200;
            return $this->ajax_return($ret['data'], $ret['info'], $ret['status']);
        }

        //写入redis
        $redis_pre = get_redis_pre();
        $redis_key = $redis_pre . '_sms_code' . $mobile;
        $regcode['mobile'] = $mobile;
        $regcode['value'] = $code;
        $regcode['expire'] = time() + 900;
        Gek_Redis::factory()->set($redis_key, json_encode($regcode));

        return $this->ajax_return($ret['data'], $ret['info'], $ret['status']);
    }

    //手机账号注册
    public function registerAction()
    {
        Gek_Session::start();
        $mobile = $this->request->getRequest('mobile');
        $smscode = $this->request->getRequest('smscode');
        $password = $this->request->getRequest('password');
        $inviter = $this->request->getRequest('inviter');
        $loginDevice = (int)$this->request->getRequest('login_device');
        $loginDeviceId = $this->request->getRequest('login_deviceId');

        //平台
        $platform = (string)$this->getParam('platform', 'miniapp');
        //活动类型 code, like
        $activity_type = (string)$this->getParam('activity_type', '');

        $pointModel = new BuriedPointModel($this->dsn);
        $a_type_num = $pointModel->get_activity_type_num($activity_type);
        //埋点类型
        $type = (string)$this->getParam('type', '');
        $type_num = $pointModel->get_type_num($type);

        if (empty($mobile) || empty($smscode) || empty($password)) {
            return $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '参数为空');
        }

        if ($password && strlen($password) < 6) {
            return $this->errorOutput(ErrorCode::ERR_PHONE_SIX, '密码不能小于六位');
        }

        //检查短信验证码
        $redis_pre = get_redis_pre();
        $redis_key = $redis_pre . '_sms_code' . $mobile;
        $data_code = json_decode(Gek_Redis::factory()->get($redis_key), true);
        if (!isset($data_code['value']) || !isset($data_code['expire']) || !isset($data_code['mobile'])) {
            return $this->errorOutput(ErrorCode::ERR_NOT_SMSCODE, '验证码不存在');
        } else if ($data_code['expire'] < time()) { // 是否过期
            return $this->errorOutput(ErrorCode::ERR_SMSCODE_EXP, '验证码已过期');
        } else if ($data_code['value'] != $smscode) { // 是否正确
            return $this->errorOutput(ErrorCode::ERR_SMSCODE_ERROR, '验证码错误');
        } else if ($data_code['mobile'] != $mobile) { // 是否正确
            return $this->errorOutput(ErrorCode::ERR_SMSCODE_PHONE, '注册手机号与接收短信手机号不一致');
        } else {
            Gek_Redis::factory()->del($redis_key); // 释放
        }

        // 验证用户名是否已经存在
        $this->userInit($this->dsn);
        $r = $this->userInit()->mobileIsRegister($mobile);
        if (!empty($r)) {
            return $this->errorOutput(ErrorCode::ERR_PHONE_EXIST, '该手机已经存在');
        }

        // 检查用户的邀请人是否存在
        if (!empty($inviter)) {
            $r = $this->userInit()->mobileIsRegister($inviter);
            if (empty($r)) {
                $inviter = '';
            }
        } else {
            $inviter = '';
        }

        $user_ip = Gek_Request::getUserIP();
        $password = Gek_Security::password_hash($password);
        $nickName = getRandChar(8);
        $user = array(
            'name' => $nickName,
            'nickname' => $nickName,
            'mobile' => $mobile,
            'realname' => $mobile,
            'password' => $password,
            'reg_ip' => $user_ip,
            'reg_time' => date("Y-m-d H:i:s"),
            'inviter' => $inviter,
            'login_device' => empty($loginDevice) ? 0 : $loginDevice,
            'login_deviceId' => empty($loginDeviceId) ? '' : $loginDeviceId,
            'type' => $a_type_num, //用户类型
            'point' => $type_num,  //埋点类型
        );
        $this->userInit()->put($user);

        // 清除之前的登录状态
        Gek_Session::start();
        Gek_Session::destroyAll();

        $user = $this->userInit()->getByMobile($mobile);
        if (empty($user)) {
            return $this->errorOutput(ErrorCode::ERR_REG_FAIL, '注册失败');
        }

        $user = $this->userInit()->_do_login(false, $user['id']);

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
        //兼容旧版本APP，空是APP。小程序已经更新版本了
        //订阅股票池
        $selectObj = new SelectModel($this->dsn);
        if ($platform == 'miniapp') {
            //小程序
            $selectObj->updateSubscribeMini($user['id'], 1, 1);
            $selectObj->updateSubscribeMini($user['id'], 5, 1);
            $selectObj->updateSubscribeMini($user['id'], 2, 1);
        } else {
            //新版APP
            $selectObj->updateSubscribeAPP($user['id'], 1, 1);
            $selectObj->updateSubscribeAPP($user['id'], 5, 1);
            $selectObj->updateSubscribeAPP($user['id'], 2, 1);
        }
        //======================== 新的订阅方式 end =============

        //返回新用户的奖品
        if (get_api_env() == 'online') {
            $user['rewards_img'] = 'https://wx.firstwisdom.cn/public/images/reward_999.png';
        } else {
            $user['rewards_img'] = 'https://wxtest.firstwisdom.cn/public/images/reward_999.png';
        }

        return $this->successOutput($user);
    }

    public function getQrcodeAction()
    {
        if (!$this->islogin) return;
        $userId = Gek_Session::get('user_id');
        $user = $this->userInit()->get($userId);
        $urlScheme = $this->config->get("url.scheme");
        $urlDomain = $this->config->get("url.domain");
        $regUrl = "$urlScheme://$urlDomain" . self::RegUrl;
        $inviter = $user['mobile'];
        Yaf_Loader::import('phpqrcode/phpqrcode.php');
        QRcode::png($regUrl . '?type=1&inviter=' . $inviter);
    }

    public function isLoginAction()
    {
        if (Gek_Session::isLogin()) {
            return $this->ajax_return(1, '已登录', 0);
        } else {
            return $this->ajax_return(0, '未登录', 0);
        }
    }

    //手机账号登录
    public function loginAction()
    {
        $account = $this->request->getRequest('account');
        $password = (string)$this->request->getRequest('password');
        $autologin = (int)$this->request->getRequest('autologin');
        $loginDevice = (int)$this->request->getRequest('login_device');
        $loginDeviceId = $this->request->getRequest('login_deviceId');
        //版本控制
        $appVersion = (string)$this->getParam('appVersion', '');
        //来源
        $platform = (string)$this->getParam('platform', 'miniapp');

        if (empty($account) || strlen($password) <= 0) {
            return $this->ajax_return('', 'missing arg', 1);
        }

        // 验证用户名和密码
        $this->userInit($this->dsn);
        $r = $this->userInit()->getByMobile($account);
        if (empty($r)) {
            return $this->errorOutput(ErrorCode::ERR_NOT_PHONE, '手机号未注册');
        }

        if (!Gek_Security::password_verify($password, $r['password']) != 0) {
            return $this->errorOutput(ErrorCode::ERR_NOT_PASS, '密码错误');
        }

        $user = $this->userInit()->_do_login($autologin, $r['id']);

        if ($loginDevice > 0) {
            $this->userInit()->updateUserLoginDevice($user['id'], $loginDevice, $loginDeviceId);
        }

        return $this->successOutput($user);
    }

    public function logoutAction()
    {
        $this->userInit()->_do_loginout();
        return $this->ajax_return('', 'login out', 0);
    }

    //修改密码
    public function changePasswordAction()
    {
        Gek_Session::start();
        $password = (string)$this->request->getRequest('password');
        $mobile = $this->request->getRequest('mobile');
        $smscode = $this->request->getRequest('smscode');

        if (empty($password) || empty($smscode) || empty($mobile) || !is_numeric($mobile)) {
            return $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '参数为空');
        }

        if ($password && strlen($password) < 6) {
            return $this->errorOutput(ErrorCode::ERR_PHONE_SIX, '密码不能小于六位');
        }

        //验证用户的密码
        $this->userInit($this->dsn);
        $user = $this->userInit()->getByMobile($mobile);
        if (empty($user) || $mobile != $user['mobile']) {
            return $this->errorOutput(ErrorCode::ERR_NOT_PHONE, '手机号未注册');
        }

        //检查短信验证码
        $redis_pre = get_redis_pre();
        $redis_key = $redis_pre . '_sms_code' . $mobile;
        $data_code = json_decode(Gek_Redis::factory()->get($redis_key), true);
        if (!isset($data_code['value']) || !isset($data_code['expire']) || !isset($data_code['mobile'])) {
            return $this->errorOutput(ErrorCode::ERR_NOT_SMSCODE, '验证码不存在');
        } else if ($data_code['expire'] < time()) { // 是否过期
            return $this->errorOutput(ErrorCode::ERR_SMSCODE_EXP, '验证码已过期');
        } else if ($data_code['value'] != $smscode) { // 是否正确
            return $this->errorOutput(ErrorCode::ERR_SMSCODE_ERROR, '验证码错误');
        } else if ($data_code['mobile'] != $user['mobile']) { // 是否正确
            return $this->errorOutput(ErrorCode::ERR_SMSCODE_PHONE, '注册手机号与接收短信手机号不一致');
        } else {
            Gek_Redis::factory()->del($redis_key); // 释放
        }

        // 更新用户的密码
        $password = Gek_Security::password_hash($password);
        $this->userInit()->modifyPassword($user['id'], $password);

        $this->userInit()->_do_login(true, $user['id']);

        return $this->successOutput();
    }

    public function uploadAvatarAction()
    {
        $userId = Gek_Session::get('user_id');

        $r = $this->uploadImage('avatar');

        return $this->ajax_return($r['data'], $r['info'], $r['status']);
    }

    public function editProfileAction()
    {

        return $this->ajax_return('', '', 0);
    }

    private function uploadImage($name)
    {
        $config = array();
        $config['allowed_types'] = "jpg|png|gif|jpeg";
        $config['max_size'] = 8192;   //8M
        $result = array();

        if (isset($_FILES[$name]) && $_FILES[$name]['error'] == UPLOAD_ERR_OK && $_FILES[$name]['size'] > 0) { // 文件上传字段
            $upload_path = $this->config->get("upload.path");
            if (empty($upload_path)) {
                $result['status'] = 1;
                $result['info'] = "没有指定上传文件的存放路径。";
                $result['data'] = "";
                return $result;
            }
            $config['upload_path'] = $upload_path;

            $config['overwrite'] = false;
            $config['encrypt_name'] = true;
            // 加密上传文件名，同时增加上传目录，防止一个目录下上传文件过多。
            $today = date("Ymd");
            $config['upload_path'] .= ('/' . $today);
            if (!file_exists($config['upload_path'])) {
                mkdir($config['upload_path'], 0777, true);
            }

            // 上传处理
            $upload = new Gek_Upload($config);
            if ($upload->do_upload($name) === false) {
                $result['status'] = 1;
                $result['info'] = $upload->display_errors();
                return $result;
            } else {
                $r = $upload->data();
                $result['status'] = 0;
                $result['info'] = 'ok';
                $result['data'] = $r['file_name'];
                return $result;
            }
        }

        $result['status'] = 1;
        $result['info'] = "未上传文件或上传错误。";
        $result['data'] = "";
        return $result;
    }

    public function getMsgCenterAction()
    {

        if (!$this->islogin) return;
        $userId = Gek_Session::get('user_id');

        $r = $this->userInit()->getMsgCenter($userId);

        foreach ($r as &$msg) {
            if ($msg['type'] == self::MSG_TYPE_T0 && $msg['new_msg'][0]['di'] == 1) {
                $time = date("H:i:s", strtotime($msg['new_msg'][0]['time']));
                $msg['new_msg'][0]['msg'] = "B点信号 | $time {$msg['new_msg'][0]['stock']}（{$msg['new_msg'][0]['symbol']}）出现买入信号。";
            } else if ($msg['type'] == self::MSG_TYPE_T0 && $msg['new_msg'][0]['di'] == -1) {
                $time = date("H:i:s", strtotime($msg['new_msg'][0]['time']));
                $msg['new_msg'][0]['msg'] = "S点信号 | $time {$msg['new_msg'][0]['stock']}（{$msg['new_msg'][0]['symbol']}）出现卖出信号。";
            } else if ($msg['type'] == self::MSG_TYPE_DK && $msg['new_msg'][0]['di'] == 1) {
                $time = date("Y-m-d", strtotime($msg['new_msg'][0]['time']));
                $msg['new_msg'][0]['msg'] = "D点信号 | $time {$msg['new_msg'][0]['stock']}（{$msg['new_msg'][0]['symbol']}）出现多头信号。";
            } else if ($msg['type'] == self::MSG_TYPE_DK && $msg['new_msg'][0]['di'] == -1) {
                $time = date("Y-m-d", strtotime($msg['new_msg'][0]['time']));
                $msg['new_msg'][0]['msg'] = "K点信号 | $time {$msg['new_msg'][0]['stock']}（{$msg['new_msg'][0]['symbol']}）出现空头信号。";
            }
        }

        return $this->ajax_return($r, 'success', 0);
    }

    public function getSubscribeMsgAction()
    {
        if (!$this->islogin) return;
        $userId = Gek_Session::get('user_id');

        //1=金股，2=涨停，5=主力雷达，6=收益通知
        $type = (int)$this->request->getRequest('type');
        $page = (int)$this->request->getRequest('page');
        $pageSize = (int)$this->request->getRequest('page_size');
        if ($page < 0) $page = 1;
        if ($pageSize < 0) $pageSize = 20;

        $msgs = $this->userInit()->getMessages($userId, $type, $page, $pageSize);
        foreach ($msgs['result'] as &$msg) {
            if ($type == self::MSG_TYPE_JG || $type == self::MSG_TYPE_ZD) {
                unset($msg['stock']);
                unset($msg['symbol']);
                unset($msg['di']);
            } else if ($type == self::MSG_TYPE_T0 && $msg['di'] == 1) {
                $time = date("H:i:s", strtotime($msg['time']));
                $msg['msg'] = "B点信号 | $time {$msg['stock']}（{$msg['symbol']}）出现买入信号。";
            } else if ($type == self::MSG_TYPE_T0 && $msg['di'] == -1) {
                $time = date("H:i:s", strtotime($msg['time']));
                $msg['msg'] = "S点信号 | $time {$msg['stock']}（{$msg['symbol']}）出现卖出信号。";
            } else if ($type == self::MSG_TYPE_DK && $msg['di'] == 1) {
                $time = date("Y-m-d", strtotime($msg['time']));
                $msg['msg'] = "D点信号 | $time {$msg['stock']}（{$msg['symbol']}）出现多头信号。";
            } else if ($type == self::MSG_TYPE_DK && $msg['di'] == -1) {
                $time = date("Y-m-d", strtotime($msg['time']));
                $msg['msg'] = "K点信号 | $time {$msg['stock']}（{$msg['symbol']}）出现空头信号。";
            }
            unset($msg['id']);
            unset($msg['user_id']);
            unset($msg['type']);
        }

        return $this->ajax_return($msgs['result'], 'success', 0);
    }

    public function setReadedMsgAction()
    {
        if (!$this->islogin) return;
        $userId = Gek_Session::get('user_id');

        $type = (int)$this->request->getRequest('type');
        $this->userInit()->updateMessageReaded($userId, $type);

        return $this->ajax_return(1, 'success', 0);
    }

    //APP 微信登录
    public function wxLoginAction()
    {
        $appVersion = (string)$this->getParam('appVersion', '');
        $platform = (string)$this->getParam('platform', 'miniapp');

        $loginDeviceId = (string)$this->getParam('login_deviceId', '');

        $code = (string)$this->getParam('code', '');

        if (empty($code)) {
            return $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '参数为空');
        }

        $wxModel = new WxModel($this->dsn, $this->appappid, $this->appsecret);

        $info = $wxModel->getAppAccessToken($code);

        if (empty($info) || empty($info['unionid'])) {
            return $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '参数错误2');
        }

        //获取用户微信信息缓存
        $user_info = $wxModel->getAppUserInfo($info['access_token'], $info['openid']);
        if ($user_info) {
            $key_pre = get_redis_pre();
            $redis_key = $key_pre . '_' . $info['unionid'];
            $saveInfo['headimgurl'] = isset($user_info['headimgurl']) ? $user_info['headimgurl'] : '';
            $saveInfo['nickname'] = isset($user_info['nickname']) ? $user_info['nickname'] : '';
            Gek_Redis::factory()->set($redis_key, json_encode($saveInfo), 3600); //1小时
        }
        //检查unionID 绑定情况
        $userModel = new UserModel($this->dsn);
        $u_data = $userModel->get_unionid($info['unionid']);

        if (empty($u_data)) {
            $info_ret['openid'] = $info['openid'];
            $info_ret['unionid'] = $info['unionid'];
            return $this->ajax_return($info_ret, '无关联', ErrorCode::ERR_UNIONID_NOT);
        }
        //登录，返回用户信息
        // 清除之前的登录状态
        Gek_Session::destroyAll();
        $user = $userModel->_do_login(true, $u_data['id']);

        //更新登录设备
        if ($platform == 'ios' || $platform == 'android') {
            $loginDevice = $platform == 'ios' ? 1 : 2;
            $this->userInit()->updateUserLoginDevice($u_data['id'], $loginDevice, $loginDeviceId);
        }
        if ($user_info) {
            $user['avatar'] = isset($user_info['headimgurl']) ? $user_info['headimgurl'] : '';
            $user['nickname'] = isset($user_info['nickname']) ? $user_info['nickname'] : '';
            //异步处理
            $param['unionid'] = $info['unionid'];
            $url = getDomainUrl();
            $get_url = $url . '/User/saveWxInfo';
            AsyncRequest($get_url, $param); //下载更新头像
        }
        return $this->successOutput($user, '登录成功');

    }

    public function saveWxInfoAction()
    {
        $appVersion = (string)$this->getParam('appVersion', '');
        $platform = (string)$this->getParam('platform', 'miniapp');

        $unionid = (string)$this->getParam('unionid', '');

        if (empty($unionid)) {
            return $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '参数为空');
        }
        $key_pre = get_redis_pre();
        $redis_key = $key_pre . '_' . $unionid;
        $redis_data = Gek_Redis::factory()->get($redis_key);
        if ($redis_data) {
            $user_info = json_decode($redis_data, true);
            $wxModel = new WxModel($this->dsn, $this->appappid, $this->appsecret);

            $wxInfo['avatar'] = isset($user_info['headimgurl']) ? $wxModel->tryCapturePic($user_info['headimgurl'], $this->config->get('upload.path')) : '';
            $wxInfo['nickname'] = isset($user_info['nickname']) ? emoji2str($user_info['nickname']) : '';
            $wxInfo['name'] = isset($user_info['nickname']) ? emoji2str($user_info['nickname']) : '';
            //更新用户头像
            $userModel = new UserModel($this->dsn);
            $ret = $userModel->UpdateAvatar($unionid, $wxInfo);
        }

        return $this->successOutput();
    }

    //APP 微信phone
    public function wxPhoneAction()
    {
        $appVersion = (string)$this->getParam('appVersion', '');
        $platform = (string)$this->getParam('platform', 'miniapp');

        $unionid = (string)$this->getParam('unionid', '');
        $mobile = $this->request->getRequest('mobile');
        $smscode = $this->request->getRequest('smscode');

        if (empty($unionid) || empty($smscode) || empty($mobile) || !is_numeric($mobile)) {
            return $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '参数为空');
        }

        //检查短信验证码
        $redis_pre = get_redis_pre();
        $redis_key = $redis_pre . '_sms_code' . $mobile;
        $data_code = json_decode(Gek_Redis::factory()->get($redis_key), true);
        if (!isset($data_code['value']) || !isset($data_code['expire']) || !isset($data_code['mobile'])) {
            return $this->errorOutput(ErrorCode::ERR_NOT_SMSCODE, '验证码不存在');
        } else if ($data_code['expire'] < time()) { // 是否过期
            return $this->errorOutput(ErrorCode::ERR_SMSCODE_EXP, '验证码已过期');
        } else if ($data_code['value'] != $smscode) { // 是否正确
            return $this->errorOutput(ErrorCode::ERR_SMSCODE_ERROR, '验证码错误');
        } else if ($data_code['mobile'] != $mobile) { // 是否正确
            return $this->errorOutput(ErrorCode::ERR_SMSCODE_PHONE, '注册手机号与接收短信手机号不一致');
        } else {
            Gek_Redis::factory()->del($redis_key); // 释放
        }

        //验证用户
        $this->userInit($this->dsn);
        $user = $this->userInit()->getByMobile($mobile);
        if (empty($user)) {
            return $this->errorOutput(ErrorCode::ERR_NOT_PHONE, '手机号未注册');
        }

        //检查unionID 绑定情况
        $userModel = new UserModel($this->dsn);

        //手机号已经注册，则绑定微信
        if (!empty($user['unionid']) && $user['unionid'] != $unionid) {
            //解绑微信对应的手机号
            $userModel->delUnionid($unionid);
        }

        //绑定微信
        $userModel->bindUnionid($user['id'], $unionid);

        Gek_Session::start();
        Gek_Session::destroyAll();
        $user = $userModel->_do_login(true, $user['id']);

        //更新登录设备
        $loginDeviceId = (string)$this->getParam('login_deviceId', '');
        if ($platform == 'ios' || $platform == 'android') {
            $loginDevice = $platform == 'ios' ? 1 : 2;
            $this->userInit()->updateUserLoginDevice($user['id'], $loginDevice, $loginDeviceId);
        }

        //临时头像
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
        return $this->successOutput($user, '登录成功');

    }

    //设置密码
    public function setPasswordAction()
    {
        $appVersion = (string)$this->getParam('appVersion', '');
        $platform = (string)$this->getParam('platform', 'miniapp');

        $unionid = (string)$this->getParam('unionid', '');
        $mobile = $this->getParam('mobile', 0);
        $password = (string)$this->getParam('password', '');
        $loginDevice = (int)$this->request->getRequest('login_device');
        $loginDeviceId = $this->request->getRequest('login_deviceId');

        if (empty($unionid) || empty($password) || empty($mobile) || !is_numeric($mobile)) {
            return $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '参数为空');
        }

        if ($password && strlen($password) < 6) {
            return $this->errorOutput(ErrorCode::ERR_PHONE_SIX, '密码不能小于六位');
        }

        //验证用户
        $this->userInit($this->dsn);
        $user = $this->userInit()->getByMobile($mobile);
        if ($user) {
            return $this->errorOutput(ErrorCode::ERR_PHONE_EXIST, '手机号已注册');
        }
        //获取缓存的微信用户信息
        $wx_info = [];
        if ($unionid) {
            $key_pre = get_redis_pre();
            $redis_key = $key_pre . '_' . $unionid;
            $redis_data = Gek_Redis::factory()->get($redis_key);
            if ($redis_data) {
                $wx_info = json_decode($redis_data, true);
            }
        }

        //注册
        $user_ip = Gek_Request::getUserIP();
        $password = Gek_Security::password_hash($password);
        $nickName = isset($wx_info['nickname']) ? $wx_info['nickname'] : getRandChar(8);
        $avatar = "";
        $user = array(
            'name' => $nickName,
            'nickname' => $nickName,
            'mobile' => $mobile,
            'realname' => $mobile,
            'password' => $password,
            'avatar' => $avatar,
            'reg_ip' => $user_ip,
            'reg_time' => date("Y-m-d H:i:s"),
            'inviter' => '',
            'unionid' => $unionid,
            'login_device' => empty($loginDevice) ? 0 : $loginDevice,
            'login_deviceId' => empty($loginDeviceId) ? '' : $loginDeviceId
        );
        $this->userInit()->put($user);

        // 清除之前的登录状态
        Gek_Session::start();
        Gek_Session::destroyAll();

        $user = $this->userInit()->getByMobile($mobile);
        if (empty($user)) {
            return $this->errorOutput(ErrorCode::ERR_REG_FAIL, '注册失败');
        }

        $user = $this->userInit()->_do_login(false, $user['id']);

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
        //兼容旧版本APP，空是APP。小程序已经更新版本了
        //订阅股票池
        $selectObj = new SelectModel($this->dsn);
        if ($platform == 'miniapp') {
            //小程序
            $selectObj->updateSubscribeMini($user['id'], 1, 1);
            $selectObj->updateSubscribeMini($user['id'], 5, 1);
            $selectObj->updateSubscribeMini($user['id'], 2, 1);
        } else {
            //新版APP
            $selectObj->updateSubscribeAPP($user['id'], 1, 1);
            $selectObj->updateSubscribeAPP($user['id'], 5, 1);
            $selectObj->updateSubscribeAPP($user['id'], 2, 1);
        }
        //======================== 新的订阅方式 end =============
        //返回新用户的奖品
        if (get_api_env() == 'online') {
            $user['rewards_img'] = 'https://wx.firstwisdom.cn/public/images/reward_999.png';
        } else {
            $user['rewards_img'] = 'https://wxtest.firstwisdom.cn/public/images/reward_999.png';
        }

        //返回临时头像
        if (isset($wx_info['headimgurl']) && !empty($wx_info['headimgurl'])) {
            $user['avatar'] = $wx_info['headimgurl'];
            $user['nickname'] = $wx_info['nickname'];
            $param['unionid'] = $unionid;
            $get_url = getDomainUrl() . '/User/saveWxInfo';
            AsyncRequest($get_url, $param); //下载更新头像
        }

        return $this->successOutput($user);
    }

    //检查绑定状态
    public function checkBindAction()
    {
        //版本控制
        $appVersion = (string)$this->getParam('appVersion', '');
        //来源
        $platform = (string)$this->getParam('platform', 'miniapp');

        if (!Gek_Session::isLogin()) {
            return $this->errorOutput(ErrorCode::ERR_NOT_LOGIN, '请登录');
        }

        $userModel = new UserModel($this->dsn);
        $data = $userModel->check_bind(Gek_Session::get('user_id'));
        return $this->successOutput($data);
    }

    //绑定
    public function bindWechatAction()
    {
        $platform = (string)$this->getParam('platform', 'miniapp');

        if (!Gek_Session::isLogin()) {
            return $this->errorOutput(ErrorCode::ERR_NOT_LOGIN, '请登录');
        }

        $code = $this->getParam('code', '');
        if (empty($code)) {
            $this->errorOutput(ErrorCode::ERR_PARAM, '参数code为空');
        }

        $userModel = new UserModel($this->dsn);

        //获取unionid
        if ($platform == 'miniapp') {
            $wxModel = new MiniappModel($this->dsn, $this->miniappid, $this->minisecret);
            //获取session_key
            $sessionInfo = $wxModel->getWxLoginInfo($code, $this->miniappid, $this->minisecret);
            if (empty($sessionInfo)) {
                return $this->errorOutput(ErrorCode::ERR_WX_ANALYSIS, 'code解析数据失败');
            }
            $openid = isset($sessionInfo['openid']) ? $sessionInfo['openid'] : "";
            $unionid = isset($sessionInfo['unionid']) ? $sessionInfo['unionid'] : "";
            $encryptedData = $this->getParam('encryptedData', '');
            $iv = $this->getParam('iv', '');
            if (empty($encryptedData) || empty($iv)) {
                return $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '参数为空');
            }
            $sessionWxInfo_jiemi = $wxModel->getWxPhone($encryptedData, $iv, $sessionInfo['session_key']);
            if (isset($sessionWxInfo_jiemi['unionId'])) {
                $unionid_jiemi = $sessionWxInfo_jiemi['unionId'];
            } else {
                $this->logger->warn('get unionid fail');
                $this->errorOutput(ErrorCode::ERR_PARAM_NEW, 'unionid 解析失败');
            }
            if (empty($unionid)) {
                $unionid = $unionid_jiemi;
            }

            //解绑微信对应的手机号
            $userModel->delUnionid($unionid);

            $userModel->bindUnionid(Gek_Session::get('user_id'), $unionid);
            //更新openid
            $u_data['miniopenid'] = $openid;
            $userModel->UpdateAvatar($unionid, $u_data);
            $wxInfo = [];
            $key_pre = get_redis_pre();
            $redis_key = $key_pre . '_' . $unionid;
            if ($sessionWxInfo_jiemi) {
                $wxInfo['headimgurl'] = isset($sessionWxInfo_jiemi['avatarUrl']) ? $sessionWxInfo_jiemi['avatarUrl'] : '';
                $wxInfo['nickname'] = isset($sessionWxInfo_jiemi['nickName']) ? $sessionWxInfo_jiemi['nickName'] : '';
                Gek_Redis::factory()->set($redis_key, json_encode($wxInfo), 3600); //1小时
                $param['unionid'] = $unionid;
                $url = getDomainUrl();
                $get_url = $url . '/User/saveWxInfo';
                AsyncRequest($get_url, $param); //下载更新头像
            }

            $wxInfo['avatar'] = $wxInfo['headimgurl'] ? $wxInfo['headimgurl'] : "";
            unset($wxInfo['headimgurl']);
            return $this->successOutput($wxInfo);

        } else {
            $wxModel = new WxModel($this->dsn, $this->appappid, $this->appsecret);
            $info = $wxModel->getAppAccessToken($code);
            if (empty($info) || empty($info['unionid'])) {
                return $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '参数错误2');
            }
            $unionid = $info['unionid'];

            //解绑微信对应的手机号
            $userModel->delUnionid($unionid);

            $wxInfo = [];
            //获取微信信息，存到redis，异步调用保存接口。
            $user_info = $wxModel->getAppUserInfo($info['access_token'], $info['openid']);

            $userModel->bindUnionid(Gek_Session::get('user_id'), $unionid);

            $key_pre = get_redis_pre();
            $redis_key = $key_pre . '_' . $unionid;
            if ($user_info) {
                $wxInfo['headimgurl'] = isset($user_info['headimgurl']) ? $user_info['headimgurl'] : '';
                $wxInfo['nickname'] = isset($user_info['nickname']) ? $user_info['nickname'] : '';
                Gek_Redis::factory()->set($redis_key, json_encode($wxInfo), 3600); //1小时
                $param['unionid'] = $info['unionid'];
                $url = getDomainUrl();
                $get_url = $url . '/User/saveWxInfo';
                AsyncRequest($get_url, $param); //下载更新头像
            }

            $wxInfo['avatar'] = $wxInfo['headimgurl'];
            unset($wxInfo['headimgurl']);
            return $this->successOutput($wxInfo);
        }
    }

    //================================= 拉取服务号粉丝列表 start ====================================================
    public function getWxUserInfoUrlAction()
    {
        #重定向后会带上state参数，开发者可以填写a-zA-Z0-9的参数值，最多128字节
        $state = '111';
        $appId = $this->config->get("wx.appid");
        $secret = $this->config->get("wx.secret");
        $urlScheme = $this->config->get("url.scheme");
        $urlDomain = $this->config->get("url.domain");
        $baseUri = $this->config->get("application.baseUri");
        $urlDomain = $urlDomain . $baseUri . '%2f';
        $redirectUrl = "{$urlScheme}%3a%2f%2f{$urlDomain}%2fuser%2fgetWxUserInfo2";
        $redirectUrl = "https%3a%2f%2fwxtest.firstwisdom.cn%2fv2.1.0%2fuser%2fgetWxUserInfo2";
        $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$appId}&redirect_uri={$redirectUrl}&response_type=code&scope=snsapi_userinfo&state={$state}#wechat_redirect";
        $this->logger->info('url :' . $url);
        $this->redirect($url);
        exit;
    }

    // 用于微信回调，获取微信用户信息
    public function getWxUserInfo2Action()
    {
        $urlScheme = $this->config->get("url.scheme");
        $urlDomain = $this->config->get("url.domain");
        $baseUri = $this->config->get("application.baseUri");
        $urlDomain = $urlDomain . $baseUri;
        $indexUrl = "$urlScheme://$urlDomain" . self::IndexUrl;

        //code作为换取access_token的票据，每次用户授权带上的code将不一样，code只能使用一次，5分钟未被使用自动过期。
        $code = $this->request->getRequest('code');
        if (empty($code)) {
            $this->redirect($indexUrl);
            return;
        }

        //拉取粉丝列表
        $token = $this->WxInit()->tryGetAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/user/get?access_token={$token}&next_openid=";

        $list = Gek_Http::httpGet($url);
        if ($list) {
            $openlist = $list['data']['openid'];
            foreach ($openlist as $openid) {
                $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token={$token}&openid={$openid}&lang=zh_CN";
                $info = Gek_Http::httpGet($url);
                $unionid = isset($info['unionid']) ? $info['unionid'] : "";
                if ($unionid) {
                    //更新用户信息
                    $profile = array(
                        'openid' => $info['openid'],
                    );
                    $ret = $this->userInit()->UpdateAvatar($unionid, $profile);
                    if ($ret) {
                        $this->logger->info('fuwuhao $unionid:' . $unionid . ' | openid:' . $openid);
                    } else {
                        $this->logger->info('fuwuhao nickname:' . $info['nickname'] . ' | openid:' . $openid);
                    }
                }
            }
        }
        echo "执行成功";
        exit;
    }

    //================================= 拉取服务号粉丝列表 end ====================================================

    //用户服务过期状态和订阅状态
    //1-一智金股、2-主力雷达、5-涨停大师 ，6-收益通知，7-版本更新 ==订阅
    //1-一智金股、2-涨停大师 ==服务
    public function servicesAction()
    {
        //来源
        $platform = (string)$this->getParam('platform', 'miniapp');

        if (!Gek_Session::isLogin()) {
            return $this->errorOutput(ErrorCode::ERR_NOT_LOGIN, '请登录');
        }
        $user_id = Gek_Session::get('user_id');
        if (empty($user_id)) {
            return $this->errorOutput(ErrorCode::ERR_NOT_LOGIN, '请登录');
        }
        $servicesModel = new UserServicesModel($this->dsn);

        //服务过期
        $service_yzjg = $servicesModel->getUserServices($user_id, 1, date('Y-m-d'));
        $service_ztds = $servicesModel->getUserServices($user_id, 2, date('Y-m-d'));
        //过期关闭订阅
        if ($platform == 'miniapp') {
            if (!$service_yzjg) {
                $this->selectInit()->updateSubscribeMini($user_id, 1, 0);
            }
            if (!$service_ztds) {
                $this->selectInit()->updateSubscribeMini($user_id, 5, 0);
            }
        } else {
            if (!$service_yzjg) {
                $this->selectInit()->updateSubscribeAPP($user_id, 1, 0);
            }
            if (!$service_ztds) {
                $this->selectInit()->updateSubscribeAPP($user_id, 5, 0);
            }
        }
        //是否订阅
        if ($platform == 'miniapp') {
            $subservice_yzjg = $this->selectInit()->isSubscribeMini($user_id, 1);
            $subservice_ztds = $this->selectInit()->isSubscribeMini($user_id, 5);
            $subservice_zlld = $this->selectInit()->isSubscribeMini($user_id, 2);
            $subservice_sy = $this->selectInit()->isSubscribeMini($user_id, 6);
            $subservice_update = $this->selectInit()->isSubscribeMini($user_id, 7);
        } else {
            $subservice_yzjg = $this->selectInit()->isSubscribeApp($user_id, 1);
            $subservice_ztds = $this->selectInit()->isSubscribeApp($user_id, 5);
            $subservice_zlld = $this->selectInit()->isSubscribeApp($user_id, 2);
            $subservice_sy = $this->selectInit()->isSubscribeApp($user_id, 6);
            $subservice_update = $this->selectInit()->isSubscribeApp($user_id, 7);
        }

        $data = array(
            array(
                'service_id' => "1",
                'subscribe_id' => "1",
                'name' => '一智金股每日调仓通知',
                'service_status' => "1",
                'subscribe_status' => "0",
                'subscribe_text' => "关闭消息推送，无法第一时间接收股票池变动消息",
            ),
            array(
                'service_id' => "2",
                'subscribe_id' => "5",
                'name' => '涨停大师每日调仓通知',
                'service_status' => "1",
                'subscribe_status' => "0",
                'subscribe_text' => "关闭消息推送，无法第一时间接收股票池变动消息",
            ),
            array(
                'service_id' => "0",
                'subscribe_id' => "2",
                'name' => '主力雷达每日调仓通知',
                'service_status' => "1",
                'subscribe_status' => "0",
                'subscribe_text' => "关闭消息推送，无法第一时间接收股票池变动消息",
            ),
            array(
                'service_id' => "0",
                'subscribe_id' => "6",
                'name' => '策略近期收益通知',
                'service_status' => "1",
                'subscribe_status' => "0",
                'subscribe_text' => "是否确认关闭策略近期收益消息推送？",
            ),
            array(
                'service_id' => "0",
                'subscribe_id' => "7",
                'name' => '新版本功能介绍',
                'service_status' => "1",
                'subscribe_status' => "0",
                'subscribe_text' => "是否确认关闭新版本功能介绍消息推送？",
            ),
        );
        $data[0]['service_status'] = $service_yzjg ? "1" : "0";
        $data[1]['service_status'] = $service_ztds ? "1" : "0";

        $data[0]['subscribe_status'] = $subservice_yzjg ? "1" : "0";
        $data[1]['subscribe_status'] = $subservice_ztds ? "1" : "0";
        $data[2]['subscribe_status'] = $subservice_zlld ? "1" : "0";
        //添加策略收益通知和版本更新通知
        $data[3]['subscribe_status'] = $subservice_sy ? "1" : "0";
        $data[4]['subscribe_status'] = $subservice_update ? "1" : "0";


        return $this->successOutput($data);
    }

    //个人中心获取用户全部的信息
    public function infosAction()
    {
        //来源
        $platform = (string)$this->getParam('platform', 'miniapp');

        if (!Gek_Session::isLogin()) {
            return $this->errorOutput(ErrorCode::ERR_NOT_LOGIN, '请登录');
        }
        $userId = Gek_Session::get('user_id');
        if (empty($userId)) {
            return $this->errorOutput(ErrorCode::ERR_NOT_LOGIN, '请登录');
        }

        $type = trim((string)$this->getParam('type', ''));

        if (empty($type)) {
            $orderObj = new OrderModel($this->dsn);
            $data = $orderObj->getUserServices($userId);
            $data_all['service'] = $data;

            $userModel = new UserModel($this->dsn);
            $user_info = $userModel->getInfoByUserId($userId);
            //用户类型 0-普通用户，1-code，2-like，5-代理,9-管理员
            if ($user_info && intval($user_info['type']) >= 5) {
                if (intval($user_info['type']) == 5) { //代理商
                    $data_all['extend_info'] = $userModel->getUserQrcode($userId, $platform);
                } elseif (intval($user_info['type']) == 9) { //管理员
                    $data_all['extend_info'] = $userModel->getAdminInfo($userId, $platform);
                }
            }else {
                $data_all['extend_info'] = [];
            }

            return $this->successOutput($data_all);

        } else {
            $data = [];
            $userModel = new UserModel($this->dsn);
            $user_info = $userModel->getInfoByUserId($userId);
            if (intval($user_info['type']) == 9) { //管理员
                $data = $userModel->getAdminInfo($userId, $platform,'more');
            }
            return $this->successOutput($data);
        }
    }
}
