<?php
/**
 *
 * Created by PhpStorm.
 * User: dengxiaowu@innofi.cn
 * Date: 2018/4/17
 * Time: 19:49
 */

class TokenController extends BasexController
{
    public function init()
    {
        $notNeedLoginAction = [
            'getAccessToken',
            'freshAccessToken'
        ];

        $needSessionAction = [];

        $this->_init($notNeedLoginAction, $needSessionAction);
    }

    /**
     * token 规划
     * 1.获取token
     * 2.刷新token
     * 3.验证token
     */

    /**
     * 获取token
     */
    public function getAccessTokenAction()
    {
        $device_id = $this->getParam('deviceId', '');

        $tokenModel = new TokenModel($this->dsn);

        $data['token'] = $tokenModel->getAccessToken($device_id);

        $this->successOutput($data);

    }

    /**
     * 刷新token
     */
    public function freshAccessTokenAction()
    {
        $token = $this->getParam('token','');
        if (empty($token)){
            $this->errorOutput(ErrorCode::ERR_NO_PARAMETERS,'token 为空');
        }

        $tokenModel = new TokenModel($this->dsn);
        $data['token'] = $tokenModel->freshAccessToken($token);
        if (empty($data['token'])){
            $this->errorOutput(ErrorCode::ERR_CHECK_SIGN, 'token 无效');
        }
        $this->successOutput($data);

    }

}