<?php
/**
 * User: ben
 * Date: 2017/5/26
 * Time: 16:07
 */
class BaseController extends Yaf_Controller_Abstract
{
    protected $config;
    protected $view;
    protected $request;
    protected $controller;
    protected $action;
    protected $islogin;
    protected $dsn;

    protected $u_id;


    const R_OK       = 0;


    public function init($notNeedLoginAction = array())
    {
        $this->config = Yaf_Registry::get("config");
        $this->view = $this->getView();
        $this->request = $this->getRequest();
        $this->controller = strtolower($this->request->getControllerName());
        $this->action = strtolower($this->request->getActionName());
        Yaf_Dispatcher::getInstance()->autoRender(FALSE);
        $this->dsn = $this->config->get("database.main");

        foreach ($notNeedLoginAction as &$action) {
            $action = strtolower($action);
        }
        unset($action);

        /**
         * 接口请求埋点，存入redis
         */
        //start
        $redis_list_key = 'action_list_'.get_api_env();
        $descrip = get_action_name($this->controller . '_' . $this->action);
        $redis_action = $this->config->get("application.baseUri") . '_' . $this->controller . '_' . $this->action;
        $source = $this->getParam('platform', 'miniapp');
        $data_redis = json_encode(array('action' => $redis_action, 'source' => $source, 'descrip' => $descrip));
        if (get_api_env() == 'online'){
            Gek_Redis::factory()->lPush($redis_list_key, $data_redis);
        }
        //end
        if (!in_array($this->controller, $notNeedLoginAction)){
            if (!$this->auto_login()) {
                $this->logger("The requested action needs to be logged in");
                exit;
            }
        }
    }

    //在继承类中配置$needLoginAction，哪些接口需要登录权限
    //也可以直接使用，将$auto_return设置为false，将不会自动输出，而是返回true和false，用于判断用户登录与否
    protected function auto_login($needLoginAction = null, $auto_return = true)
    {
        $list = array();
        if (!empty($this->needLoginAction)) { $list = $this->needLoginAction; }
        if (!empty($needLoginAction)) { $list = $needLoginAction; }
        
        Gek_Session::start();

        if(!in_array($this->action, $list, false)) { 
            return true; 
        } else {
            if($this->action == 'sendsms') { return true; }
            if(Gek_Session::isLogin()) { 
                $this->u_id = Gek_Session::get('user_id');
                $this->islogin = true;
                return true; 
            } else {
                $this->islogin = false;
                setcookie(AUTO_LOGIN_KEY, 'null', time() + AUTO_LOGIN_EXPIRE,  '/');
                //setcookie('GEKSESSID', 'deleted', strtotime('1982-01-06'), '/', '.firstwisdom.cn', 0, 0);
                if ($auto_return) { $this->ajax_return(array(), 'need login', 2); }
                return false;
            }
        }
    }

    public function tryGetUserAvatar($code, $state, &$subscribe)
    {
        //服务器拉取用户信息
        $ret = $this->WxInit()->getAuthUserInfo($code);
        if (!empty($ret)) {

            if ($state == WX_AUTH_ACTION_WX_SHARE) {
                if ($this->WxInit()->userIsSubscribe($ret['openid'])) { $subscribe = 1; }
                else { $subscribe = 0; }
            }

            $profile = array(
                'nickname' => emoji2str($ret['nickname']),
                'name' => emoji2str($ret['nickname']),
                'avatar' => $ret['headimgurl'],
                'openid' => $ret['openid'],
            );

            $user = $this->userInit()->getUserByOpenid($ret['openid']);

            //openid未注册, 并且没有登录
            if (empty($user) AND !$this->is_login()) {
                    $this->logger("openid : {$profile['openid']} are not registered and are not logged in, code: {$code}, state: {$state}");
                    return false;
            }

            //登录了(注册、登录流程)
            if ($this->is_login()) {
                $user = $this->userInit()->do_get();

                //【被解绑】手机号的openid与当前微信的openid不一致，并且还是自动登陆进入的，显然是被其他账号解绑的微信号。
                if ($user['openid'] != $profile['openid'] AND $state != WX_AUTH_ACTION_GET_AVATAR) {
                    $this->logger("openid is not registered, login out...");
                    $this->userInit()->_do_loginout();
                    return true;
                }

                //【解绑】原有Openid存在库中，但与当前openid不同，重新绑定openid
                if ($user['openid'] != $profile['openid']) {
                    if ($state == WX_AUTH_ACTION_GET_AVATAR) {
                        $this->userInit()->cleanOpenidByOpenid($profile['openid'], $user['mobile']);
                        $this->logger("unbind with {$profile['openid']} and mobile neq {$user['mobile']}");
                    }
                }
                $this->logger("bind openid : {$profile['openid']} with {$user['mobile']}");
            }else {
                //如果用户未登录，即做登录操作
                $this->userInit()->_do_login(true, $user['id'], false);
            }

            //state不为下载用户信息
            if ($state != WX_AUTH_ACTION_GET_AVATAR) { return true; }

            //下载头像
            $img = $this->WxInit()->tryCapturePic($ret['headimgurl'], $this->config->get('upload.path'));
            if ($img) { $profile['avatar'] = $img; }

            return $this->userInit()->editProfile($user['id'], $profile);

        }else { $this->logger("get user_info failed"); }

        return false;
    }

    protected function is_login()
    {
        if (!$this->auto_login(array($this->action), false)) { return false; }
        return true;
    }
    
    protected function is_weixin() {
        if(isset($_SERVER['HTTP_USER_AGENT']) && !empty($_SERVER['HTTP_USER_AGENT'])) {
            $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
            if(strpos($userAgent, 'micromessenger') !== false) {
                return true;
            }
        } 
        
        return false;
    }
    
    protected function is_android() {
        if(isset($_SERVER['HTTP_USER_AGENT']) && !empty($_SERVER['HTTP_USER_AGENT'])) {
            $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
            if(strpos($userAgent, 'android') !== false) {
                return true;
            }
        } 
        
        return false;
    }
    
    protected function is_iphone() {
        if(isset($_SERVER['HTTP_USER_AGENT']) && !empty($_SERVER['HTTP_USER_AGENT'])) {
            $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
            if(strpos($userAgent, 'iphone') !== false) {
                return true;
            }
        } 
        
        return false;
    }

    protected function ajax_return($data, $info = '', $status = 0)
    {
        $retData = array();
        $retData['data'] = $data;
        $retData['info'] = $info;
        $retData['status'] = $status;

        if ($retData['status'] !== 0 ) {
            $this->logger("[{$retData['status']}]".$retData['info'].' - req : '.json_encode($this->request->getRequest()));
        }

        header('Content-Type:application/json; charset=utf-8');
        echo json_encode($retData);
        return true;
    }

    protected function logger($msg, $level = "ERROR")
    {
        $msg = '['.$this->controller.']['.$this->action .'] - '. $msg;
        Gek_Log::write($msg, $level);
        return true;
    }

    protected function defaultReturn(&$ret)
    {
        $ret['data'] = array();
        $ret['info'] = 'system error';
        $ret['status'] = 999;
    }

    protected function marketInit($dsn = NULL)
    {
        $tmp = $dsn ? $dsn : $this->dsn;
        if (empty($this->marketObj)) { $this->marketObj = new MarketModel($tmp); }
        return $this->marketObj;
    }

    protected function selectInit($dsn = NULL)
    {
        $tmp = $dsn ? $dsn : $this->dsn;
        if (empty($this->selectObj)) { $this->selectObj = new SelectModel($tmp); }
        return $this->selectObj;
    }

    protected function userInit($dsn = NULL)
    {
        $tmp = $dsn ? $dsn : $this->dsn;
        if (empty($this->userObj)) { $this->userObj = new UserModel($tmp); }
        return $this->userObj;
    }

    protected function stocksInit($dsn = NULL)
    {
        $tmp = $dsn ? $dsn : $this->dsn;
        if (empty($this->stocksObj)) { $this->stocksObj = new StocksModel($tmp); }
        return $this->stocksObj;
    }

    protected function miscInit($dsn = NULL)
    {
        $tmp = $dsn ? $dsn : $this->dsn;
        if (empty($this->miscObj)) { $this-> miscObj = new MiscModel($tmp); }
        return $this->miscObj;
    }

    protected function WxInit($dsn = NULL)
    {
        $tmp = $dsn ? $dsn : $this->dsn;
        if (empty($this->WxObj)) { $this->WxObj = new WxModel($tmp, $this->config->get("wx.appid"), $this->config->get("wx.secret")); }
        return $this->WxObj;
    }
    
    protected function result($data, $info='', $status=0) {
        $retData = array();
        $retData['data'] = $data;
        $retData['info'] = $info;
        $retData['status'] = $status;
        
        header('Content-Type:application/json; charset=utf-8');
        echo json_encode($retData);
    }

    /**
     * 获取参数
     *
     * @param string $name         参数名称
     * @param mixed  $defaultValue 参数不存在时的默认值
     * @return mixed
     */
    public function getParam($name, $defaultValue = null)
    {
        if (isset($this->request->getRequest()[$name])) {
            return $this->request->getRequest()[$name];
        }
        return $defaultValue;
    }

    /**
     * 获取所有参数
     *
     * @return array
     * @author xiaoyanchun
     */
    public function getParams()
    {
        return $this->request->getRequest();
    }

    /**
     * 初始化接口返回结构
     * @return array
     */
    public static function initRes()
    {
        $res = [];
        $res['status'] = self::R_OK;
        $res['info'] = 'success';
        $res['data'] = [];
        return $res;
    }


    /**
     * 错误时josn输出
     *
     * @param int    $code 错误代码
     * @param string info  错误信息
     * @return string
     */
    public function errorOutput($code, $info = '')
    {
        $res = self::initRes();

        $res['status'] = $code;

        if ($info) {
            $res['info'] = $info;
        } else {
            $res['info'] = ErrorCode::msg($code);
        }

        $this->renderJSON($res);
    }

    /**
     * 成功时的返回
     *
     * @param mixed $data 返回的数据
     * @return string
     */
    public function successOutput($data = array())
    {
        $res = self::initRes();
        if ( !empty($data) ) { //注意： 不为空时不进行覆盖 data字段
            $res['data'] = $data;
        }
        $this->renderJSON($res);
    }

    public function renderJSON($res)
    {
        header('Content-type: application/json');

        if (empty($res)) {
            $res = self::initRes();
        }
        echo json_encode($res);
        exit;
    }

    /**
     * 成功时的返回对象
     *
     * @param mixed $data 返回的数据 一维数组
     * @return string
     */
    public function successOutputObject($data = array())
    {
        $res = self::initResObject();
        if ( !empty($data) ) { //注意： 不为空时不进行覆盖 data字段
            $res['data'] = $data;
        }
        $this->renderObjectJSON($res);
    }

    /**
     * 初始化接口返回结构 object
     * @return array
     */
    public static function initResObject()
    {
        $res = [];
        $res['status'] = self::R_OK;
        $res['info'] = 'success';
        $res['data'] = new stdClass();
        return $res;
    }

    public function renderObjectJSON($res)
    {
        header('Content-type: application/json');
        if (empty($res)) {
            $res = self::initResObject();
        }
        echo json_encode($res);
        exit;
    }



}