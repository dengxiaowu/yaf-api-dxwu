<?php

/**
 * exception
 *
 * @author liuxingzhi @2014.6
 */

class Gek_Exception extends Exception {

    public function __construct($errno, $message = '', $code = 0) 
    {
        if(isset(Gek_Error::$MSG[$errno])) {
            parent::__construct(Gek_Error::$MSG[$errno], $errno, NULL);
        } else if(!empty($message)) {
            parent::__construct($message, (int)$code, NULL);
        } else {
            parent::__construct('unknow exception', (int)$code, NULL);
        }
    }
    
    public function writeLog() 
    {
        $msg = '[' . $this->getCode() . ']-' . $this->getMessage();
        Gek_Log::write($msg);
    }
}
