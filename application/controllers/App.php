<?php

class AppController extends BasexController
{
    private $logger = '';

    public function init()
    {
        $notNeedLoginAction = [
            'download',
            'updateAndroid',
            'updateIOS',
            'getConfig',
            'getUpdateInfo',
            'getUpdateShow'
        ];

        $needSessionAction = [];

        $this->_init($notNeedLoginAction, $needSessionAction);

        $this->logger = Gek_Logger::get_logger('app_con');

    }

    public function downloadAction()
    {
        if ($this->is_android()) {
            $downUrl = 'https://wx.firstwisdom.cn/firstwisdom_android.apk';
        } else if ($this->is_iphone()) {
            $downUrl = 'https://itunes.apple.com/cn/app/%E4%B8%80%E6%99%BA%E8%85%BE%E9%A3%9E/id1361588220?mt=8';
        } else if ($this->is_weixin()) {
            $downUrl = 'https://wx.firstwisdom.cn/';
        } else {
            $downUrl = 'http://www.firstwisdom.cn/';
        }

        $this->redirect($downUrl);
    }


    public function updateAndroidAction()
    {
        /**
         * name APP名字
         * version 是最新版本
         * isForceUpdate 是否强制更新
         * packageUrl 包地址
         * packageSize 包大小
         * intro 更新说明
         */

        //版本控制
        $appVersion = (string)$this->getParam('appVersion');
        //来源
        $platform = strtolower((string)$this->getParam('platform', 'miniapp'));

        if (empty($platform) || $platform != 'android') {
            return $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '平台错误');
        }

        $versionModel = new VersionUpdateInfoModel($this->dsn);
        $field = 'platform, name, version, isForceUpdate, build, packageUrl, packageSize, time, intro';
        $ret = $versionModel->get_version_info($field, array('platform' => $platform));

        return $this->successOutput($ret);
    }

    public function updateIOSAction()
    {
        /**
         * name APP名字
         * version 是最新版本
         * isForceUpdate 是否强制更新
         * packageUrl 包地址
         * packageSize 包大小
         * intro 更新说明
         */
        //版本控制
        $appVersion = (string)$this->getParam('appVersion');
        //来源
        $platform = (string)$this->getParam('platform', 'miniapp');

        if (empty($platform) || $platform != 'ios') {
            return $this->errorOutput(ErrorCode::ERR_PARAM_NEW, '平台错误');
        }

        $versionModel = new VersionUpdateInfoModel($this->dsn);
        $field = 'platform, name, version, packageUrl, isForceUpdate, time, intro';
        $ret = $versionModel->get_version_info($field, array('platform' => $platform));

        if ($appVersion == '1.6.0') {
            $ret['isForceUpdate'] = 1;
        }

        return $this->successOutput($ret);
    }


    public function getConfigAction()
    {
        $ret = array(
            'data' => array(),
            'info' => 'success',
            'status' => 0,
        );
        //ios android miniapp
        $platform = $this->request->getRequest('platform');
        if (empty($platform) || !in_array($platform, array('ios', 'android', 'miniapp'))) {
            $ret['info'] = '来源错误';
            $ret['status'] = -2;
            $ret['data'] = array();
        }
        $appVersion = $this->request->getRequest('appVersion');
        $config = [];
        if ($platform == 'ios') {
            $config = $this->getIosConfig($appVersion);
        } elseif ($platform == 'android') {
            $config = $this->getAndroidConfig($appVersion);
        } elseif ($platform == 'miniapp') {
            $config = $this->getMiniConfig($appVersion);
        }

        $ret['data'] = $config;

        return $this->result($ret['data'], $ret['info'], $ret['status']);
    }

    private function getIosConfig($appVersion)
    {
        $urlScheme = $this->config->get("url.scheme");
        $urlDomain = $this->config->get("url.domain");
        $host = $urlScheme . '://' . $urlDomain;
        $config = array(
            'config_version' => !empty($appVersion) ? $appVersion : '1.0.0',
            'api_version' => 'v1.2.0',
            'api_host' => 'https://wx.firstwisdom.cn',
            'update_interval' => '3',
            'pay_type' => array('weixin'),
            'webview_urls' => array(
                'yzjg' => $host . '/?code=1&state=do_login/#/module_product?type=xnjg&from=app',
                'ztds' => $host . '/?code=1&state=do_login/#/module_product?type=ztds&from=app',
                't0' => $host . '/#/module_product?type=t0&from=app',
                'dk' => $host . '/#/module_product?type=dk&from=app',
                'user_agreement' => $host . '/#/agreement?0=u&1=s&2=e&3=r&from=app',
                'buy_agreement' => $host . '/#/agreement?from=app',
                'use_explain' => $host . '/infei-guide.html',
                'grade_url' => $host . '/company-intro.html?type=cwpj', //财务评级
            ),
            'check_status' => '2',//1 提交审核 2审核通过
        );
        //1 提交审核 2审核通过
        $MiniappCheckStatusModel = new MiniappCheckStatusModel($this->dsn);
        $param['platform'] = 'ios';
        $param['version'] = $appVersion;
        $info = $MiniappCheckStatusModel->get_check_info('check_status', $param);
        if (empty($info)) {
            $config['check_status'] = '2';
        } else {
            $config['check_status'] = (string)$info['check_status'];
        }
        if ($config['check_status'] == '1') {
            $config['pay_type'] = [];
        }
        return $config;
    }

    private function getAndroidConfig($appVersion)
    {
        $urlScheme = $this->config->get("url.scheme");
        $urlDomain = $this->config->get("url.domain");
        $baseUri = $this->config->get("application.baseUri");
        $host = $urlScheme . '://' . $urlDomain;
        $config = array(
            'config_version' => !empty($appVersion) ? $appVersion : '1.0.0',
            'api_version' => 'v1.2.0',
            'api_host' => 'https://wx.firstwisdom.cn',
            'update_interval' => '3',
            'pay_type' => array('alipay', 'weixin'),
            'webview_urls' => array(
                'yzjg' => $host . '/?code=1&state=do_login/#/module_product?type=xnjg&from=app',
                'ztds' => $host . '/?code=1&state=do_login/#/module_product?type=ztds&from=app',
                't0' => $host . '/#/module_product?type=t0&from=app',
                'dk' => $host . '/#/module_product?type=dk&from=app',
                'user_agreement' => $host . '/#/agreement?0=u&1=s&2=e&3=r&from=app',
                'buy_agreement' => $host . '/#/agreement?from=app',
                'use_explain' => $host . '/infei-guide.html',
                'grade_url' => $host . '/company-intro.html?type=cwpj', //财务评级
            ),
            'check_status' => '2',//1 提交审核 2审核通过
        );
        //1 提交审核 2审核通过
        $MiniappCheckStatusModel = new MiniappCheckStatusModel($this->dsn);
        $param['platform'] = 'android';
        $param['version'] = $appVersion;
        $info = $MiniappCheckStatusModel->get_check_info('check_status', $param);
        if (empty($info)) {
            $config['check_status'] = '2';
        } else {
            $config['check_status'] = (string)$info['check_status'];
        }
        if ($config['check_status'] == '1') {
            $config['pay_type'] = [];
        }
        return $config;
    }

    private function getMiniConfig($appVersion)
    {
        $urlScheme = $this->config->get("url.scheme");
        $urlDomain = $this->config->get("url.domain");
        $baseUri = $this->config->get("application.baseUri");
        $host = $urlScheme . '://' . $urlDomain;
        $config = array(
            'config_version' => !empty($appVersion) ? $appVersion : '1.0.0',
            'api_version' => 'v1.2.0',
            'api_host' => 'https://wx.firstwisdom.cn',
            'update_interval' => '3',
            'pay_type' => array('weixin'),
            'webview_urls' => array(
                'yzjg' => $host . '/?code=1&state=do_login/#/module_product?type=xnjg&from=app',
                'ztds' => $host . '/?code=1&state=do_login/#/module_product?type=ztds&from=app',
                't0' => $host . '/#/module_product?type=t0&from=app',
                'dk' => $host . '/#/module_product?type=dk&from=app',
                'user_agreement' => $host . '/#/agreement?0=u&1=s&2=e&3=r&from=app',
                'buy_agreement' => $host . '/#/agreement?from=app',
                'use_explain' => $host . '/infei-guide.html',
                'grade_url' => $host . '/company-intro.html?type=cwpj', //财务评级
            ),
            'start' => 2, //1 提交审核 2审核通过
            'pay_way' => 1, //1 微信支付，2 客服通知
        );
        return $config;
    }

    public function getUpdateInfoAction()
    {
        $ret = array(
            'data' => array(),
            'info' => 'success',
            'status' => 0,
        );
        //ios android miniapp
        $platform = $this->request->getRequest('platform');
        if (empty($platform) || !in_array($platform, array('ios', 'android', 'miniapp'))) {
            $ret['info'] = '来源错误';
            $ret['status'] = -2;
            $ret['data'] = array();
            return $this->result($ret['data'], $ret['info'], $ret['status']);
        }

        $appVersion = $this->request->getRequest('appVersion');
        if (empty($appVersion)) {
            $ret['info'] = '版本为空';
            $ret['status'] = -2;
            $ret['data'] = array();
            return $this->result($ret['data'], $ret['info'], $ret['status']);
        }

        $versionInfoModel = new VersionTextModel($this->dsn);

        $param['platform'] = $platform;

        $list = $versionInfoModel->get_version_list('id, version, title, time', $param);

        if (empty($list)) {
            $ret['info'] = '数据为空';
            $ret['status'] = -1;
            $ret['data'] = array();
            return $this->result($ret['data'], $ret['info'], $ret['status']);
        }

        $last_version = $list[0]['version'];
        $ret['data']['lastVersion'] = $last_version;
        if ($last_version > $appVersion) {
            if ($platform == 'ios') {
                $ret['data']['url'] = 'itms-apps://itunes.apple.com/us/app/apple-store/id1361588220?mt=8';
            } elseif ($platform == 'android') {
                $ret['data']['url'] = 'https://wx.firstwisdom.cn/firstwisdom_android.apk';
            } else {
                $ret['data']['url'] = '';
            }
        } else {
            $ret['data']['url'] = '';
        }

        foreach ($list as $key => $value) {
            $list[$key]['publish_time'] = date('m月d日', strtotime($value['time']));
            $list[$key]['text'] = $value['version'] . '新功能介绍';
            $host = getDomainUrl(false);
            if ($platform == 'miniapp') {
                $url_par = $host . '/version-intro.html?type=0&id=' . $value['id'] . '&platform=' . $platform . '&lastVersion=' . $last_version;
                $url_par = urlencode($url_par);
                $list[$key]['url'] = '/pages/page-embed?url=' . $url_par;
            } else {
                $list[$key]['url'] = $host . '/version-intro.html?type=0&id=' . $value['id'] . '&platform=' . $platform . '&lastVersion=' . $last_version;
            }
        }

        $ret['data']['list'] = $list;

        return $this->result($ret['data'], $ret['info'], $ret['status']);
    }

    public function getUpdateShowAction()
    {
        $ret = array(
            'data' => array(),
            'info' => 'success',
            'status' => 0,
        );

        //ios android miniapp
        $platform = $this->request->getRequest('platform');
        if (empty($platform) || !in_array($platform, array('ios', 'android', 'miniapp'))) {
            $ret['info'] = '来源错误';
            $ret['status'] = -2;
            $ret['data'] = array();
            return $this->result($ret['data'], $ret['info'], $ret['status']);
        }

        $appVersion = (string)$this->request->getRequest('appVersion');
        $lastVersion = (string)$this->request->getRequest('lastVersion');
        $id = (string)$this->request->getRequest('id');

        $type = (int)$this->request->getRequest('type');

        if (empty($appVersion)) {
            $appVersion = '0.0.0';
        }
        if ($type && $type == 1) {
            if ($lastVersion > $appVersion) {
                $ret['data']['update_info']['status'] = '1';
                $ret['data']['update_info']['desc'] = '立即更新';
                if ($platform == 'ios') {
                    $ret['data']['update_info']['url'] = 'https://itunes.apple.com/us/app/apple-store/id1361588220?mt=8';
                } elseif ($platform == 'android') {
                    $ret['data']['update_info']['url'] = 'https://wx.firstwisdom.cn/firstwisdom_android.apk';
                } else {
                    $ret['data']['update_info']['url'] = '';
                }
            } else {
                $ret['data']['update_info']['status'] = '0';
                $ret['data']['update_info']['desc'] = '立即体验';
                $ret['data']['update_info']['url'] = array(
                    'type' => JUMP_SY,
                    'needlogin' => "0",
                    'param' => "",
                );
                if ($platform == 'miniapp') {
                    $ret['data']['update_info']['url'] = '/pages/analysis';
                }
            }

        } else {
            $ret['data']['update_info']['status'] = '2';
            $ret['data']['update_info']['desc'] = '不显示';
            $ret['data']['update_info']['url'] = '';
        }

        $versionInfoModel = new VersionTextModel($this->dsn);
        $param['platform'] = $platform;
        $param['id'] = $id;
        $ret['data']['text'] = $versionInfoModel->get_version_text('content', $param);

        return $this->result($ret['data'], $ret['info'], $ret['status']);

    }

}