<?php

class Bootstrap extends Yaf_Bootstrap_Abstract
{
    private $config;

    public function _initConfig()
    {
        $this->config = Yaf_Application::app()->getConfig();
        Yaf_Registry::set("config", $this->config);
    }

    public function _initLog()
    {
        $logpath = $this->config->get("log.path");
        $level = $this->config->get("log.level");
        if (!empty($logpath) && !empty($level)) {
            Gek_Log::init($logpath, $level);
        }
    }

    public function _initSession()
    {
        if ($this->config->session) {
            $items = $this->config->session->toArray();
            Gek_Session::config($items);
        }
    }

    public function _initCommon()
    {
        Yaf_Loader::import(APP_PATH . '/application/helpers/common.php');
        //引入actionmap
        Yaf_Loader::import(APP_PATH . '/application/helpers/actionmap.php');
        //引入公共错误码类
        Yaf_Loader::import(APP_PATH . '/application/helpers/ErrorCode.php');
        ini_set('session.lazy_write', 0);
        //ini_set('memcached.sess_lock_wait_max', 30000);
    }
}
