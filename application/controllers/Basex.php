<?php

class BasexController extends Yaf_Controller_Abstract
{
    protected $appRoot; // = APP_PATH
    protected $appPath; // = APP_PATH/application
    protected $config;
    protected $view;
    protected $request;
    protected $module;
    protected $controller;
    protected $action;
    protected $dsn;

    protected $islogin;

    const R_OK = 0;
    const R_FAIL = 1;
    const R_NOLOGIN = 2;

    public function _init($notNeedLoginAction = array(), $needSessionAction = array())
    {
        $this->appRoot = APP_PATH;
        $this->appPath = Yaf_Application::app()->getAppDirectory();
        $this->config = Yaf_Registry::get("config");
        $this->view = $this->getView();
        $this->request = $this->getRequest();
        $this->module = strtolower($this->getModuleName());
        $this->controller = strtolower($this->request->getControllerName());
        $this->action = strtolower($this->request->getActionName()); // action都是小写字母
        Yaf_Dispatcher::getInstance()->autoRender(FALSE); //关闭自动渲染
        //获取数据库连接
        $this->dsn = $this->config->get("database.main");

        foreach ($notNeedLoginAction as &$action) {
            $action = strtolower($action);
        }
        unset($action);

        foreach ($needSessionAction as &$action) {
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

        if (in_array($this->action, $notNeedLoginAction)) {
            $this->islogin = null; // 不需要登录
            Gek_Session::start();
        } else if (in_array($this->action, $needSessionAction)) {
            Gek_Session::start();  // 不需要登录,但需要session
        } else {                  // 需要登录
            Gek_Session::start();
            if (Gek_Session::isLogin()) {
                $this->islogin = true;
            } else {
                $this->islogin = false;
                return $this->result('', '未登录', self::R_NOLOGIN);
            }
        }

    }

    protected function is_android()
    {
        if (isset($_SERVER['HTTP_USER_AGENT']) && !empty($_SERVER['HTTP_USER_AGENT'])) {
            $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
            if (strpos($userAgent, 'android') !== false) {
                return true;
            }
        }

        return false;
    }

    protected function is_iphone()
    {
        if (isset($_SERVER['HTTP_USER_AGENT']) && !empty($_SERVER['HTTP_USER_AGENT'])) {
            $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
            if (strpos($userAgent, 'iphone') !== false) {
                return true;
            }
        }

        return false;
    }

    protected function is_weixin()
    {
        if (isset($_SERVER['HTTP_USER_AGENT']) && !empty($_SERVER['HTTP_USER_AGENT'])) {
            $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
            if (strpos($userAgent, 'micromessenger') !== false) {
                return true;
            }
        }

        return false;
    }

    protected function result($data, $info = '', $status = self::R_OK)
    {
        $retData = array();
        $retData['data'] = $data;
        $retData['info'] = $info;
        $retData['status'] = $status;

        header('Content-Type:application/json; charset=utf-8');
        echo json_encode($retData);
    }

    /**
     * 使用http协议的301状态进行跳转
     * @param string $url 通过301跳转页面的url
     * @param string $target 跳转页面的位置
     */
    protected function set301Redirect($url, $target = 'self')
    {
        if (is_string($url) && $url != "") {
            header("http/1.1 301 moved permanently");
            header("{$target}.location: {$url}");
            exit;
        }
    }

    /**
     * 获取参数
     *
     * @param string $name 参数名称
     * @param mixed $defaultValue 参数不存在时的默认值
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
     * @param int $code 错误代码
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
        if (!empty($data)) { //注意： 不为空时不进行覆盖 data字段
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
        if (!empty($data)) { //注意： 不为空时不进行覆盖 data字段
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
