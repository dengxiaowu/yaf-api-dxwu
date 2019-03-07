<?php

/**
 * Class TokenModel
 *
 */
class TokenModel extends BaseModel
{
    public function __construct($dsn)
    {
        parent::__construct($dsn);

        Yaf_Loader::import('jwt/JWT.php');
    }

    /**
     * 获取token
     * @param string $user_id
     * @return string
     */
    public function getAccessToken($user_id)
    {
        $payload = array(
            "token" => $user_id,
            "exp" => time() + JWT::$leeway + TOKEN_EXPIRE
        );
        $encoded = JWT::encode($payload, TOKEN_KEY);
        return $encoded;
    }

    /**
     * 刷新token 有效期
     * @param string $token
     * @return string
     */
    public function freshAccessToken($token)
    {
        $tokenArr = $this->verifyAccessToken($token);
        if (!empty($tokenArr) && $this->verifyAccessTokenExpire($token)){
            $payload = array(
                "token" => $tokenArr['token'],
                "exp" => time() + JWT::$leeway + TOKEN_EXPIRE
            );
            return JWT::encode($payload, TOKEN_KEY);
        }
        return '';
    }

    /**
     * 验证token
     * @param string $token
     * @return string
     */
    public function verifyAccessToken($token)
    {
        //返回对象object
        $decoded = JWT::decode($token, TOKEN_KEY, array('HS256'));
        if (!empty($decoded)){
            return json_decode(json_encode($decoded),true);
        }
        return [];
    }

    /**
     * 验证token 有效期
     * @param string $token
     * @return string
     */
    public function verifyAccessTokenExpire($token)
    {
        //返回对象object
        $decoded = JWT::decode($token, TOKEN_KEY, array('HS256'));
        if (!empty($decoded)){
            $tokenArr = json_decode(json_encode($decoded),true);
            return (isset($tokenArr['exp']) && $tokenArr['exp'] > time()) ? true : false;
        }
        return false;
    }
}

