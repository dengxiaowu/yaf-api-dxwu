<?php

// These constants may be changed without breaking existing hashes.
 define("PBKDF2_HASH_ALGORITHM", "sha256");
 define("PBKDF2_ITERATIONS", 1000);
 define("PBKDF2_SALT_BYTE_SIZE", 24);
 define("PBKDF2_HASH_BYTE_SIZE", 24);

 define("HASH_SECTIONS", 4);
 define("HASH_ALGORITHM_INDEX", 0);
 define("HASH_ITERATION_INDEX", 1);
 define("HASH_SALT_INDEX", 2);
 define("HASH_PBKDF2_INDEX", 3);
    
class Gek_Security {
    
    const TOKEN = '__GEK_TOKEN';
    
    public static function html_clean($str, $allowed_html=array('default'))
    {
        $disallowd_tags = array(
            'style', 'script', 'iframe','frame', 'frameset', 'embed','applet','object',
            'base',  'basefont', 'bgsound', 'blink',    
            'html', 'head', 'meta', 'title', 'body', 'link',
            'ilayer',  'layer'
        );
        
        $default_allowed_tags = array(
		'address' => array(),
		'a' => array(
			'href' => true,
			'rel' => true,
			'rev' => true,
			'name' => true,
			'target' => true,
		),
		'abbr' => array(),
		'acronym' => array(),
		'area' => array(
			'alt' => true,
			'coords' => true,
			'href' => true,
			'nohref' => true,
			'shape' => true,
			'target' => true,
		),
		'article' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'aside' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'b' => array(),
		'big' => array(),
		'blockquote' => array(
			'cite' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'br' => array(),
		'button' => array(
			'disabled' => true,
			'name' => true,
			'type' => true,
			'value' => true,
		),
		'caption' => array(
			'align' => true,
		),
		'cite' => array(
			'dir' => true,
			'lang' => true,
		),
		'code' => array(),
		'col' => array(
			'align' => true,
			'char' => true,
			'charoff' => true,
			'span' => true,
			'dir' => true,
			'valign' => true,
			'width' => true,
		),
		'del' => array(
			'datetime' => true,
		),
		'dd' => array(),
		'dfn' => array(),
		'details' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'open' => true,
			'xml:lang' => true,
		),
		'div' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'dl' => array(),
		'dt' => array(),
		'em' => array(),
		'fieldset' => array(),
		'figure' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'figcaption' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'font' => array(
			'color' => true,
			'face' => true,
			'size' => true,
		),
		'footer' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'form' => array(
			'action' => true,
			'accept' => true,
			'accept-charset' => true,
			'enctype' => true,
			'method' => true,
			'name' => true,
			'target' => true,
		),
		'h1' => array(
			'align' => true,
		),
		'h2' => array(
			'align' => true,
		),
		'h3' => array(
			'align' => true,
		),
		'h4' => array(
			'align' => true,
		),
		'h5' => array(
			'align' => true,
		),
		'h6' => array(
			'align' => true,
		),
		'header' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'hgroup' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'hr' => array(
			'align' => true,
			'noshade' => true,
			'size' => true,
			'width' => true,
		),
		'i' => array(),
		'img' => array(
			'alt' => true,
			'align' => true,
			'border' => true,
			'height' => true,
			'hspace' => true,
			'longdesc' => true,
			'vspace' => true,
			'src' => true,
			'usemap' => true,
			'width' => true,
		),
		'ins' => array(
			'datetime' => true,
			'cite' => true,
		),
		'kbd' => array(),
		'label' => array(
			'for' => true,
		),
		'legend' => array(
			'align' => true,
		),
		'li' => array(
			'align' => true,
			'value' => true,
		),
		'map' => array(
			'name' => true,
		),
		'mark' => array(),
		'menu' => array(
			'type' => true,
		),
		'nav' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'p' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'pre' => array(
			'width' => true,
		),
		'q' => array(
			'cite' => true,
		),
		's' => array(),
		'samp' => array(),
		'span' => array(
			'dir' => true,
			'align' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'section' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'small' => array(),
		'strike' => array(),
		'strong' => array(),
		'sub' => array(),
		'summary' => array(
			'align' => true,
			'dir' => true,
			'lang' => true,
			'xml:lang' => true,
		),
		'sup' => array(),
		'table' => array(
			'align' => true,
			'bgcolor' => true,
			'border' => true,
			'cellpadding' => true,
			'cellspacing' => true,
			'dir' => true,
			'rules' => true,
			'summary' => true,
			'width' => true,
		),
		'tbody' => array(
			'align' => true,
			'char' => true,
			'charoff' => true,
			'valign' => true,
		),
		'td' => array(
			'abbr' => true,
			'align' => true,
			'axis' => true,
			'bgcolor' => true,
			'char' => true,
			'charoff' => true,
			'colspan' => true,
			'dir' => true,
			'headers' => true,
			'height' => true,
			'nowrap' => true,
			'rowspan' => true,
			'scope' => true,
			'valign' => true,
			'width' => true,
		),
		'textarea' => array(
			'cols' => true,
			'rows' => true,
			'disabled' => true,
			'name' => true,
			'readonly' => true,
		),
		'tfoot' => array(
			'align' => true,
			'char' => true,
			'charoff' => true,
			'valign' => true,
		),
		'th' => array(
			'abbr' => true,
			'align' => true,
			'axis' => true,
			'bgcolor' => true,
			'char' => true,
			'charoff' => true,
			'colspan' => true,
			'headers' => true,
			'height' => true,
			'nowrap' => true,
			'rowspan' => true,
			'scope' => true,
			'valign' => true,
			'width' => true,
		),
		'thead' => array(
			'align' => true,
			'char' => true,
			'charoff' => true,
			'valign' => true,
		),
		'title' => array(),
		'tr' => array(
			'align' => true,
			'bgcolor' => true,
			'char' => true,
			'charoff' => true,
			'valign' => true,
		),
		'tt' => array(),
		'u' => array(),
		'ul' => array(
			'type' => true,
		),
		'ol' => array(
			'start' => true,
			'type' => true,
		),
		'var' => array(),
        );
        
        $kses = new Gek_Utils_Kses5();
        
        if(empty($allowed_html)) { // 删除所有标签和属性。
            return $kses->Parse($str);
        } else if(count($allowed_html) == 1 && $allowed_html[0] == 'default') {
            $kses->setAllowedHtml($default_allowed_tags);
            return $kses->Parse($str);
        } else {
            foreach($allowed_html as $tag => $attr) {
                $tag = strtolower($tag);
                
                if(isset($disallowd_tags[$tag])) { // 标签是不允许的则过滤掉。
                    continue;
                }
                
                if(count($attr) == 1 && $attr['default'] == 1) { //标签采用默认属性
                    if(isset($default_allowed_tags[$tag])) {
                        $kses->AddHTML($tag, $default_allowed_tags[$tag]);
                    } else {
                        $kses->AddHTML($tag,array());
                    }
                } else {
                    $kses->AddHTML($tag,$attr);
                }
            }
            return $kses->Parse($str);
        }
    }
    
    /**
     * TOKEN机制：
     * （1）根据请求的操作生成一个token，并存放到session中，包括其expire时间，并通过表单隐藏元素返回。
     * （2）用户提交请求，需要对token进行验证，包括过期时间。如果验证不通过则重新生成一个token。
     * 
     * 参数：$action：指明该token适用操作，必须是唯一的，最好是_MODULE_CONTROLLER_ACTION格式。
     *      $lifetime:token的生存时间，单位s,不同的操作最好指定不同的时间，特别是大表单。
     */
    public static function token_get($action, $lifetime=600)
    {
        $token = md5(uniqid(mt_rand(), TRUE));
        $_SESSION[self::TOKEN][$action]['value'] = $token;
        $_SESSION[self::TOKEN][$action]['expire'] = time() + $lifetime;
        return $token;
    }
    
    // 对token进行验证，如果验证失败则重新生成一个新的token并返回，验证成功则返回true。
    public static function token_verify($token, $action, $lifetime=600)
    {
        if(empty($token)) {
            return self::token_get($action, $lifetime);
        } 
        
        // token是否在session里存在
        if(!isset($_SESSION[self::TOKEN][$action]['value']) || !isset($_SESSION[self::TOKEN][$action]['expire']) ) {
            return self::token_get($action, $lifetime);
        } else if($_SESSION[self::TOKEN][$action]['expire'] < time()) { // token是否过期
            return self::token_get($action, $lifetime);
        } else if($_SESSION[self::TOKEN][$action]['value'] != $token) { // token是否正确
            return self::token_get($action, $lifetime);
        } else {
            unset($_SESSION[self::TOKEN][$action]); // 释放token
            return true;
        }
    }
    
    /**
     * 一般的跳转逻辑： A->B->C->A
     * （1）用户请求a.php，a.php检测用户未登录，根据自身URL+salt生成key，302重定向到show_login.php?from=url&key=xxx
     *     （URL需要encode）
     * （2）show_login.php对from url和key进行HTML编码（避免XSS攻击）后当作登录表单的两个隐藏元素返回。
     * （3）用户post登录请求给login.php,login.php检查登录成功，并验证from url+salt=key（需要先HTML解码），
     *     如果key匹配则302重定向from url。（这里还可以检查一下from url的域名是否符合要求）
     * http://www.2cto.com/News/200803/24346.html
     * 
     */
    public static function redirect_goto($to_url, $salt, $self_host='')
    {
        $req_uri = $_SERVER['REQUEST_URI'];
        
        // 用户通过代理服务器上网的，则HTTP_HOST值可能是代理服务器的IP或HOST。
        // HTTP_X_FORWARDED_HOST/HTTP_POST头是可以伪造的，用户也可以加入这样的header。
        if($self_host == '') {
            $host = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? 
                    $_SERVER['HTTP_X_FORWARDED_HOST'] : 
                    (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
            if($host == '') {
                $host = $_SERVER["SERVER_NAME"];
            }
        } else {
            $host = $self_host;
        }
        
        $port = '';
        if(isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) {
            $port = (int)$_SERVER['SERVER_PORT'];
            $port = ':' . $port;
        }
        
        $scheme = 'http://';
        if(isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS'])) {
            $scheme = 'https://';
        }
        
        $self_url = $scheme . $host . $port . $req_uri;
        $key = sha1($self_url.$salt);
        $self_url = Gek_Utils::base64url_encode($self_url); //采用base64编码
        if(strpos($to_url, '?') === false) {
            $to_url .= '?';
        } else {
            $to_url .= '&';
        }
        
        $to_url .= "from={$self_url}&key={$key}";
        header("Location: $to_url"); // 302
        return true;
    }
    
    public static function redirect_comeback($salt, $domain_list=array())
    {
        // 支持GET POST方法
        if(!isset($_REQUEST['from']) || !isset($_REQUEST['key']) || 
            empty($_REQUEST['from']) || empty($_REQUEST['key'])) {
            return false;
        }
        
        // 输出到表单隐藏元素时为来避免xss，都会进行HTML转码。
        $from = Gek_Utils::base64url_decode($_REQUEST['from']);
        $key = $_REQUEST['key'];
        
        // 验证是不是合法的来源URL
        if(sha1($from.$salt) != $key) {
            return false;
        }
 
        // 如果存在跳转域名白名单，检查跳转URL的域名是否在白名单中。
        if(!empty($domain_list)) {
            $host = parse_url($from, PHP_URL_HOST);
            if($host === false) return false;
            trim($host, '.');
            $host = '.' . $host; // host.com -> .host.com
            $host_len = strlen($host);
            $is_in = false;
            foreach($domain_list as $domain) {
                trim($domain,'.');
                $domain = '.' . $domain; // domain.com -> .domain.com
                $domain_len = strlen($domain);
                if($host_len >= $domain_len) {
                    $dom = substr($host, $host_len - $domain_len);
                    if(strtolower($dom) == strtolower($domain)) {
                        $is_in = true;
                        break;
                    }
                }
            }
            
            if(!$is_in) {
                return false;
            }
        }
        
        header("Location: $from"); // 302
        return true;
    }
    
    /**
    * 重定向到指定的 URL
    *
    * @param string $url 要重定向的 url
    * @param int $delay 等待多少秒以后跳转
    * @param bool $js 指示是否返回用于跳转的 JavaScript 代码
    * @param bool $jsWrapped 指示返回 JavaScript 代码时是否使用 <script> 标签进行包装
    * @param bool $return 指示是否返回生成的 JavaScript 代码
    */
   public static function redirect_delay($url, $delay = 0, $js = false, $jsWrapped = true, $return = false)
   {
       $delay = (int)$delay;
        if (!$js) {
           if (headers_sent() && $delay > 0) {
               echo <<<EOT
<html>
<head>
<meta http-equiv="refresh" content="{$delay};URL={$url}" />
</head>
</html>
EOT;
                exit;
            } else {
                header("Location: {$url}");
                exit;
            }
        }

       $out = '';
       if ($jsWrapped) {
           $out .= '<script language="JavaScript" type="text/javascript">';
       }
       $url = rawurlencode($url);
       if ($delay > 0) {
           $out .= "window.setTimeOut(function () { document.location='{$url}'; }, {$delay});";
       } else {
           $out .= "document.location='{$url}';";
       }
       if ($jsWrapped) {
           $out .= '</script>';
       }

       if ($return) {
           return $out;
       }

       echo $out;
       exit;
   }
   
   /**
    * Password Hashing With PBKDF2 (http://crackstation.net/hashing-security.htm).
    * Copyright (c) 2013, Taylor Hornby
    * All rights reserved.
    * 
    * 重要：保护密码的安全就是不能猜出密码的明文。
    * 方法：hash(salt.password)
    * salt:
    *     每个密码加入不同的salt，用户注册或者修改密码，都应该使用新的salt。
    *     salt的长度不能太短，最好和密码的hash值一样长。
    *     salt的最好采用mcrypt_create_iv() /dev/random or /dev/urandom来获得随机字符串。
    *     salt需要存放到数据库中（可以分字段存储，也可以和密码hash存在一起），用于校验密码是否正确。
    *     salt可以不需要保密。
    * 密码hash函数：可以采用sha256(32Byte),最好不要用md5 sha1. php中可以使用hash('sha256',...)
    * 建议密码长度至少为12个字符的密码，并且其中至少包括两个字母、两个数字和两个符号。
    */
    
    /**
     * 对密码进行hash，返回密码的hash值和salt，格式如下：
     * return format: algorithm:iterations:salt:hash
     * @param type $password
     * @return type
     */
    public static function password_hash($password)
    {
        $salt = base64_encode(mcrypt_create_iv(PBKDF2_SALT_BYTE_SIZE, MCRYPT_DEV_URANDOM));
        return PBKDF2_HASH_ALGORITHM . ":" . PBKDF2_ITERATIONS . ":" .  $salt . ":" . 
            base64_encode(self::pbkdf2(
                PBKDF2_HASH_ALGORITHM,
                $password,
                $salt,
                PBKDF2_ITERATIONS,
                PBKDF2_HASH_BYTE_SIZE,
                true
            ));
    }
    
    /**
     * 对用户输入的密码进行验证。
     * @param type $password     用户输入密码
     * @param type $correct_hash 数据库里存放的正确密码
     * @return boolean
     */
    public static function password_verify($password, $correct_hash)
    {
        $params = explode(":", $correct_hash);
        if(count($params) < HASH_SECTIONS)
           return false; 
        $pbkdf2 = base64_decode($params[HASH_PBKDF2_INDEX]);
        return self::slow_equals(
            $pbkdf2,
          self::pbkdf2(
                $params[HASH_ALGORITHM_INDEX],
                $password,
                $params[HASH_SALT_INDEX],
                (int)$params[HASH_ITERATION_INDEX],
                strlen($pbkdf2),
                true
            )
        );
    }

    // Compares two strings $a and $b in length-constant time.
    // 大概5～10ms
    public static function slow_equals($a, $b)
    {
        $diff = strlen($a) ^ strlen($b);
        for($i = 0; $i < strlen($a) && $i < strlen($b); $i++)
        {
            $diff |= ord($a[$i]) ^ ord($b[$i]);
        }
        return $diff === 0; 
    }

    /*
     * PBKDF2 key derivation function as defined by RSA's PKCS #5: https://www.ietf.org/rfc/rfc2898.txt
     * $algorithm - The hash algorithm to use. Recommended: SHA256
     * $password - The password.
     * $salt - A salt that is unique to the password.
     * $count - Iteration count. Higher is better, but slower. Recommended: At least 1000.
     * $key_length - The length of the derived key in bytes.
     * $raw_output - If true, the key is returned in raw binary format. Hex encoded otherwise.
     * Returns: A $key_length-byte key derived from the password and salt.
     *
     * Test vectors can be found here: https://www.ietf.org/rfc/rfc6070.txt
     *
     * This implementation of PBKDF2 was originally created by https://defuse.ca
     * With improvements by http://www.variations-of-shadow.com
     */
    public static function pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output = false)
    {
        $algorithm = strtolower($algorithm);
        if(!in_array($algorithm, hash_algos(), true))
            trigger_error('PBKDF2 ERROR: Invalid hash algorithm.', E_USER_ERROR);
        if($count <= 0 || $key_length <= 0)
            trigger_error('PBKDF2 ERROR: Invalid parameters.', E_USER_ERROR);

        if (function_exists("hash_pbkdf2")) {
            // The output length is in NIBBLES (4-bits) if $raw_output is false!
            if (!$raw_output) {
                $key_length = $key_length * 2;
            }
            return hash_pbkdf2($algorithm, $password, $salt, $count, $key_length, $raw_output);
        }

        $hash_length = strlen(hash($algorithm, "", true));
        $block_count = ceil($key_length / $hash_length);

        $output = "";
        for($i = 1; $i <= $block_count; $i++) {
            // $i encoded as 4 bytes, big endian.
            $last = $salt . pack("N", $i);
            // first iteration
            $last = $xorsum = hash_hmac($algorithm, $last, $password, true);
            // perform the other $count - 1 iterations
            for ($j = 1; $j < $count; $j++) {
                $xorsum ^= ($last = hash_hmac($algorithm, $last, $password, true));
            }
            $output .= $xorsum;
        }

        if($raw_output)
            return substr($output, 0, $key_length);
        else
            return bin2hex(substr($output, 0, $key_length));
    }
}
