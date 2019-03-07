<?php
/**
 *
 * Created by PhpStorm.
 * User: dengxiaowu@innofi.cn
 * Date: 2018/4/17
 * Time: 19:29
 */

class UploadController extends BasexController
{

    public function init()
    {
        $notNeedLoginAction = array(
            'index',
            'upload',
        );

        $needSessionAction = array();

        $this->_init($notNeedLoginAction, $needSessionAction);

    }

    public function indexAction()
    {
        Yaf_Dispatcher::getInstance()->autoRender(TRUE);
        $this->getView()->assign();
    }

    public function uploadAction()
    {
        //discern
        $name = (string)$this->getParam('name', '');
        if (empty($name)) {
            return $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '文件名为空');
        }

        $upload_path = '/data/webapps/stock_dev/public/upload';

        $imageModel = new ImageModel($this->dsn);

        $r = $imageModel->uploadImage($name, $upload_path);

        if ($r['status'] == 0 && $r['data']){
            $urlScheme = Yaf_Registry::get("config")->get("url.scheme");
            $urlDomain = Yaf_Registry::get("config")->get("url.domain");
            $r['data'] = "{$urlScheme}://{$urlDomain}/".$r['data'];
            $ret['url'] = $r['data'] ? $r['data'] : "";
            return $this->successOutput($ret['url']);
        }else {
            return $this->errorOutput(ErrorCode::ERR_UPLOAD, $r['info']);
        }
    }

}