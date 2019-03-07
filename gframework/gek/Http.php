<?php

/**
 * Http请求类
 * @author dengxiaowu
 */
class Gek_Http
{

    /**
     * http get请求
     *
     * @param string $url
     * @param string $log
     * @return array
     */
    public static function httpGet($url, $log = '')
    {
        $ci = curl_init();
        curl_setopt($ci, CURLOPT_URL, $url);
        curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 30); // 连接超时
        curl_setopt($ci, CURLOPT_TIMEOUT, 30); // 执行超时
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, true); // 文件流的形式返回，而不是直接输出
        curl_setopt($ci, CURLOPT_HEADER, FALSE);
        curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ci);
        curl_close($ci);
        return self::response($response, $log);
    }

    /**
     * http get请求
     *
     * @param string $url
     * @param string $postfields
     * @param string $log
     * @return array
     */
    public static function httpPost($url, $postfields, $log = '')
    {
        $ci = curl_init();
        curl_setopt($ci, CURLOPT_URL, $url);
        curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 30); // 连接超时
        curl_setopt($ci, CURLOPT_TIMEOUT, 30); // 执行超时
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, true); // 文件流的形式返回，而不是直接输出
        curl_setopt($ci, CURLOPT_HEADER, FALSE);
        curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ci, CURLOPT_POST, true);
        curl_setopt($ci, CURLOPT_POSTFIELDS, $postfields); // post数据 可为数组、连接字串

        $response = curl_exec($ci);
        curl_close($ci);

        return self::response($response, $log);
    }

    /**
     * 统一处理返回和写log
     *
     * @param  $response
     * @param $log
     * @return array
     */
    public static function response($response, $log = '')
    {
        /* 解码返回的json串 */
        $response = trim($response);
        if (!empty($response) && is_string($response) && in_array($response [0], array('[', '{'))) {
            $response = json_decode($response, true);
        }
        if ($log != '') {
            //write log
            Gek_Log::write($log, 'INFO');
        }
        return $response;
    }

    //获取远程url的header信息  用以保存生成二维码
    public static function httpHeader($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_NOBODY, 0);    //只取body头
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $package = curl_exec($ch);
        $httpinfo = curl_getinfo($ch);
        curl_close($ch);
        return array_merge(array('body' => $package), array('header' => $httpinfo));
    }

    /**
     * http get请求
     *
     * @param string $url
     * @return array
     */
    public static function httpCurlGet($url)
    {
        $ci = curl_init();
        curl_setopt($ci, CURLOPT_URL, $url);
        curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 30); // 连接超时
        curl_setopt($ci, CURLOPT_TIMEOUT, 30); // 执行超时
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, true); // 文件流的形式返回，而不是直接输出
        curl_setopt($ci, CURLOPT_HEADER, FALSE);
        curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ci);
        curl_close($ci);
        return $response;
    }

}