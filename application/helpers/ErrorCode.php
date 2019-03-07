<?php
/**
 * 错误代码类
 * 
 * @date 2018-04-13
 */
class ErrorCode
{
    const OK = '0';  //处理成功

    /*** 关于系统底层错误代码  小于100 ***/
    const ERR_SYTEM = -1; //系统错误
    const ERR_PARAM = -2; //参数错误
    const ERR_EMPTY_RESULT = -3; //请求接口无返回
    const ERR_INVALID_PARAMETER = -4; //请求参数错误
    const ERR_CHECK_SIGN = -5; //签名验证错误
    const ERR_NO_PARAMETERS = -6; //参数缺失
    const ERR_SERVICE_NOT_FOUND = -7; //方法未找到
    const ERR_VER_NOTEXISTS = -8; //版本号错误
    const ERR_DB_ERROR = -9; //数据库操作错误
    const ERR_UPLOAD = -10; //上传失败
    const ERR_WX_ANALYSIS = -11; //微信登录解析失败
    const ERR_NOT_SERVICENAME = -12; //没有服务别名
    const ERR_SYTEM_BUSY = -13; //系统繁忙


    const ERR_PARAM_NEW = 1; //参数错误
    const ERR_NOT_LOGIN = 2; //请登录
    const ERR_NOT_PHONE = 3; //手机号未注册
    const ERR_NOT_PASS = 4; //密码错误

    const ERR_NOT_SMSCODE = 5; //验证码为空
    const ERR_SMSCODE_ERROR = 6; //验证码错误
    const ERR_SMSCODE_EXP = 7; //验证码过期
    const ERR_SMSCODE_PHONE = 8; //注册手机号与接收短信手机号不一致
    const ERR_PHONE_EXIST = 9; //手机已存在
    const ERR_REG_FAIL = 10; //手机已存在
    const ERR_UNIONID_NOT = 11; //unionid 没关联
    const ERR_PHONE_SIX = 12; //密码不能小于六位

    const ERR_ADD_ERROR = 13; //添加失败

    const ERR_ADD_HAD = 14; //活动已经存在
    const ERR_AC_NOT = 15; //活动不存在
    const ERR_LIKE_HAD = 16; //已经点赞过

    const ERR_AIP_NO = 17; //百度识别结果为空

    const ERR_PARAM_FOLLOW = 100; //未关注公众号

    const ERR_OTHER = 9001; // 其他错误
    const ERR_UNKNOWN = 9002; // 未知错误

    const IllegalAesKey = -41001; //encodingAesKey 非法
    const IllegalIv = -41002;   //iv 非法
    const IllegalBuffer = -41003; //41003: aes 解密失败
    const DecodeBase64Error = -41004; //41004: 解密后得到的buffer非法



    /**
     * 错误代码与消息的对应数组
     * 
     * @var array
     */
    static $msg = array(
            self::OK         => '处理成功',
            self::ERR_SYTEM => '系统错误',
            self::ERR_PARAM  => '参数错误',
            self::ERR_EMPTY_RESULT => '请求接口无返回',
            self::ERR_INVALID_PARAMETER => '请求参数错误',
            self::ERR_CHECK_SIGN        => '签名错误',
            self::ERR_NO_PARAMETERS => '参数缺失',
            self::ERR_SERVICE_NOT_FOUND  => '方法未找到',
            self::ERR_VER_NOTEXISTS  => '版本号错误',
            self::ERR_DB_ERROR  => '数据库操作错误',
            self::ERR_UPLOAD  => '上传失败',
            self::ERR_NOT_LOGIN  => '请登录',
            self::ERR_NOT_SERVICENAME  => '没有服务别名',
            self::ERR_SYTEM_BUSY  => '系统繁忙',
            self::ERR_UNKNOWN => '未知错误',
    );
    
    /**
     * 返回错误代码的描述信息
     * 
     * @param int    $code        错误代码
     * @param string $otherErrMsg 其他错误时的错误描述
     * @return string 错误代码的描述信息
     */
    public static function msg($code, $otherErrMsg = '')
    {
        if ($code == self::ERR_UNKNOWN) {
            return $otherErrMsg;
        }
        
        if (isset(self::$msg[$code])) {
            return self::$msg[$code];
        }
        
        return $otherErrMsg;
    }
    
}