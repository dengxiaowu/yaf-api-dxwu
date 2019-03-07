<?php
/**
 * Redis
 * @author: dengxiaowu
 * @date: 2018-04-03
 */

class Gek_Redis
{
    private static $_instance = null;   //所有数据库连接句柄，可以区分不同的库

    public static function factory($server = 'master')
    {
        //基本验证
        $server = trim($server);
        $redisConfig = Yaf_Registry::get("config")->get("redis");

        if (empty($server) || !isset($redisConfig[$server])) {
            throw new Exception('Sorry, cache server is busy now, please try again later (001)');
        }

        //已存在资源句柄
        $existInstance = isset(self::$_instance[$server]) ? self::$_instance[$server] : '';
        if (!empty($existInstance) && ($existInstance instanceof Redis)) {
            return $existInstance;
        }

        //资源句柄初始化
        self::$_instance[$server] = null;

        //server配置
        $config = $redisConfig[$server];

        //参数
        $host = empty($config['host']) ? '' : trim($config['host']);
        $port = empty($config['port']) ? '' : trim($config['port']);
        $timeout = empty($config['timeout']) ? '' : trim($config['timeout']);
        $database = isset($config['database']) ? trim($config['database']) : '';
        $persistent = isset($config['persistent']) ? intval($config['persistent']) : 0;
        $password = isset($config['password']) ? $config['password'] : '';

        //如果host包含端口号则提取出来
        if (stripos($host, ':') !== false) {
            $tmpArr = explode(':', $host);
            $host = isset($tmpArr[0]) ? trim($tmpArr[0]) : '';
            $port = isset($tmpArr[1]) ? trim($tmpArr[1]) : '';
        }

        //基本检查
        if (empty($host)) {
            throw new Exception('Sorry, cache server is busy now, please try again later (002)');
        }

        //初始化连接
        try {
            $instance = new Redis();
            if ($persistent) {
                $instance->pconnect($host, $port, $timeout);    //长连接
            } else {
                $instance->connect($host, $port, $timeout);
            }
        } catch (Exception $e) {

            $logArr = array(
                'server' => $server,
                'Exception' => $e->getMessage(),
            );

            throw new Exception('Sorry, cache server is busy now, please try again later (003)');
        }

        if ($instance !== null && $password) {
            $instance->auth($password);
        }
        //保存资源句柄
        self::$_instance[$server] = $instance;

        return $instance;
    }

    public static function close($server = null)
    {
        if (is_array(self::$_instance) && !empty(self::$_instance)) {

            if (empty($server)) {

                foreach (self::$_instance as $key => $instance) {
                    if (self::$_instance[$key] instanceof Redis) self::$_instance[$key]->close();
                    self::$_instance[$key] = null;
                }

            } elseif (!empty(self::$_instance[$server])) {

                if (self::$_instance[$server] instanceof Redis) self::$_instance[$server]->close();

                self::$_instance[$server] = null;
            }

            return true;
        } else {
            return false;
        }
    }
}