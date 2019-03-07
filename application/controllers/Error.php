<?php

class ErrorController extends Yaf_Controller_Abstract 
{ 
    public function init() {
        Yaf_Dispatcher::getInstance()->autoRender(TRUE);
    }
    
    public function errorAction($exception) {
        // write log
        $msg = '[' . $exception->getCode() . ']-' . $exception->getMessage();
        Gek_Log::write($msg);

        // output error page
        switch($exception->getCode()) {
            case YAF_ERR_NOTFOUND_MODULE:      //找不到指定的模块, 值为515
            case YAF_ERR_NOTFOUND_CONTROLLER:  //指定的Controller, 值为516
            case YAF_ERR_NOTFOUND_ACTION:      //找不到指定的Action, 值为517
            case YAF_ERR_NOTFOUND_VIEW:        //找不到指定的视图文件, 值为518
                $this->getView()->assign("title", "404错误");
                $this->getView()->assign("content", "抱歉，您请求的页面不存在。");
                $this->getResponse()->setHeader('HTTP/1.1', '404 Not Found'); 
                $this->getResponse()->response();
                break;
            case YAF_ERR_STARTUP_FAILED:       //启动失败, 值为512
            case YAF_ERR_ROUTE_FAILED:         //路由失败, 值为513
            case YAF_ERR_DISPATCH_FAILED:      //分发失败, 值为514
            case YAF_ERR_CALL_FAILED:          //调用失败, 值为519
            case YAF_ERR_AUTOLOAD_FAILED:      //自动加载类失败, 值为520
            case YAF_ERR_TYPE_ERROR:           //关键逻辑的参数错误, 值为521
            default:
                $this->getView()->assign("title", "503错误");
                $this->getView()->assign("content", "抱歉，请稍后再试。");
                $this->getResponse()->setHeader('HTTP/1.1', '503 Service Unavailable'); 
                $this->getResponse()->response();
                break;
        }
    }
}
