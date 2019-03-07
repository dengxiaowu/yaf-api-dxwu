<?php

class PayModel
{

    private $dsn;
    private $db;
    private $logger;

    private $payDetailTable = 'order_pay_detail';

    const WxPayNotifyUrl = 'http://wx.firstwisdom.cn/order/wxPayNotifyProcess';
    const WxAppPayNotifyUrl = 'http://wx.firstwisdom.cn/order/wxappPayNotifyProcess';
    const AliPayNotifyUrl = 'http://wx.firstwisdom.cn/order/aliPayNotifyProcess';

    const PAY_TYPE_WX = 1;
    const PAY_TYPE_ALI = 2;
    const PAY_TYPE_GZH = 3;
    const PAY_TYPE_MINI = 4;
    const PAY_TYPE_STORE = 6;

    const PAY_STATUS_UNPAY = 0;
    const PAY_STATUS_OK = 1;
    const PAY_STATUS_FAIL = 2;

    const AlipayAppId = '2018030502320605';
    //开发者私钥
    const AlipayRsaPrivateKey = '';
    //支付宝公钥
    const AlipayRsaPublicKey = '';

    public function __construct($dsn, $app = false)
    {
        $this->logger = Gek_Logger::get_logger('pay');
        if ($app && ($app !== 4)) { //微信app支付
            Yaf_Loader::import('wxapppay/WxPay.Config.php');
            Yaf_Loader::import('wxapppay/WxPay.Api.php');
            Yaf_Loader::import('wxapppay/WxPay.Data.php');
            Yaf_Loader::import('wxapppay/WxPay.Exception.php');
            Yaf_Loader::import('wxapppay/WxPay.Notify.php');
        } else if ($app === false) { //公众号
            Yaf_Loader::import('wxpay/WxPay.Config.php');
            Yaf_Loader::import('wxpay/WxPay.Api.php');
            Yaf_Loader::import('wxpay/WxPay.Data.php');
            Yaf_Loader::import('wxpay/WxPay.Exception.php');
            Yaf_Loader::import('wxpay/WxPay.Notify.php');
        } else if ($app && ($app == 4)) { //小程序
            Yaf_Loader::import('miniwxpay/WxPay.Config.php');
            Yaf_Loader::import('miniwxpay/WxPay.Api.php');
            Yaf_Loader::import('miniwxpay/WxPay.Data.php');
            Yaf_Loader::import('miniwxpay/WxPay.Exception.php');
            Yaf_Loader::import('miniwxpay/WxPay.Notify.php');
        }

        $this->dsn = $dsn;
        $this->db = Gek_Db::getInstance($dsn);
    }

    public function aliPayUnifiedOrder($notifyUrl, $totalFee, $body, $outTradeNo)
    {
        Yaf_Loader::import('alipay/aop/AopClient.php');
        Yaf_Loader::import('alipay/aop/request/AlipayTradeAppPayRequest.php');

        $aop = new AopClient;
        $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
        $aop->appId = self::AlipayAppId;
        $aop->rsaPrivateKey = self::AlipayRsaPrivateKey;
        $aop->format = "JSON";
        $aop->charset = "UTF-8";
        $aop->signType = "RSA2";
        $aop->alipayrsaPublicKey = self::AlipayRsaPublicKey;
        //实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
        $request = new AlipayTradeAppPayRequest();
        //SDK已经封装掉了公共参数，这里只需要传入业务参数
        $bizcontent = "{"
            . "\"subject\": \"$body\","
            . "\"out_trade_no\": \"$outTradeNo\","
            . "\"total_amount\": \"$totalFee\","
            . "\"product_code\":\"QUICK_MSECURITY_PAY\""
            . "}";

        $request->setNotifyUrl($notifyUrl);
        $request->setBizContent($bizcontent);
        //这里和普通的接口调用不同，使用的是sdkExecute
        $response = $aop->sdkExecute($request);
        //htmlspecialchars($response)是为了输出到页面时防止被浏览器将关键参数html转义，实际打印到日志以及http传输不会有这个问题
        //在支付时提示交易订单处理失败，请稍后再试。（ALI38173） 把htmlspecialchars($response);  去掉 ，直接输出 $response给客户端调用即可。
        return $response;//就是orderString 可以直接给客户端请求，无需再做处理。
    }

    public function aliPayNotifyProcess()
    {
        Yaf_Loader::import('alipay/aop/AopClient.php');

        $aop = new AopClient;
        $aop->alipayrsaPublicKey = self::AlipayRsaPublicKey;
        $flag = $aop->rsaCheckV1($_POST, NULL, "RSA2"); //签名验证
        if ($flag) {
            //验签成功后处理业务，并从$_POST中提取需要的参数内容  
            if ($_POST['trade_status'] == 'TRADE_SUCCESS' || $_POST['trade_status'] == 'TRADE_FINISHED') {//处理交易完成或者支付成功的通知
                $outTradeNo = $_POST['out_trade_no'];
                $tradeNo = $_POST['trade_no'];   //交易号  
                $totalFee = $_POST['total_amount']; //订单金额 单位元
                $tradeTime = $_POST['gmt_payment']; //交易付款时间  
                $appId = $_POST['app_id'];

                if ($appId != self::AlipayAppId) {
                    echo "fail";
                    return false;
                }

                $this->db->beginTransaction();
                try {
                    $order = $this->updateOrderPayInfo($outTradeNo, $totalFee, $tradeNo, $tradeTime, self::PAY_STATUS_OK);
                    if (empty($order)) {
                        $this->db->rollBack();
                        echo "fail";
                        return false;
                    }

                    // 更新订单状态:已支付
                    $orderObj = new OrderModel($this->dsn);
                    $orderObj->updateStatusBySN($outTradeNo, OrderModel::ORDER_STATUS_PAID);
                    $orderObj->addUserService($outTradeNo);

                    $this->db->commit();
                    echo 'success';
                    return true;
                } catch (Gek_Exception $ex) {
                    $this->db->rollBack();
                    echo "fail";
                    return false;
                }
            } else { // 交易状态未完成或失败
                echo "fail";
                return false;
            }
        } else {
            //  验签失败
            echo "fail";
            return false;
        }
    }

    //微信支付
    public function wxPayUnifiedOrder($notifyUrl, $totalFee, $body, $outTradeNo, $openId, $attach = '')
    {
        $totalFee = (int)($totalFee * 100);             //单位为分
        $nonce_str = WxPayApi::getNonceStr();
        $input = new WxPayUnifiedOrder();

        $input->SetAppid(WxPayConfig::APPID);         //公众账号ID
        $input->SetMch_id(WxPayConfig::MCHID);        //商户号

        $input->SetNonce_str($nonce_str);             //随机字符串 长度要求在32位以内
        $input->SetBody($body);                       //商品描述
        $input->SetAttach($attach);                   //附加数据，在查询API和支付通知中原样返回，可作为自定义参数使用。
        $input->SetOut_trade_no($outTradeNo);         //商户订单号 商户订单号保持唯一性（建议根据当前系统时间加随机序列来生成订单号）
        $input->SetFee_type("CNY");                   //标价币种
        $input->SetTotal_fee($totalFee);              //订单总金额，单位为分
        $input->SetNotify_url($notifyUrl);            //通知url，不能携带参数。

        if (!empty($openId)) { // 公众号支付
            $tradeType = "JSAPI";
            $input->SetOpenid($openId); //用户标识, trade_type=JSAPI时（即公众号支付），此参数必传，此参数为微信用户在商户对应appid下的唯一标识。
        } else { // app 支付
            $tradeType = "APP";
        }
        $input->SetTrade_type($tradeType); //交易类型: JSAPI--公众号支付、NATIVE--原生扫码支付、APP--app支付 MICROPAY--刷卡支付，刷卡支付有单独的支付接口，不调用统一下单接口

        try {
            $result = WxPayApi::unifiedOrder($input);
        } catch (WxPayException $ex) {
            return false;
        }

        if ($result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS') {
            $retData = array();
            if (!empty($openId)) {
                $retData['appId'] = WxPayConfig::APPID;
                $retData['timeStamp'] = "" . time();
                $retData['nonceStr'] = WxPayApi::getNonceStr();
                $retData['package'] = 'prepay_id=' . $result['prepay_id'];
                $retData['signType'] = 'MD5';
                $retData['paySign'] = $this->MakeSign($retData);
            } else { // app支付
                $retData['appid'] = WxPayConfig::APPID;
                $retData['partnerid'] = WxPayConfig::MCHID;
                $retData['prepayid'] = $result['prepay_id'];
                $retData['package'] = 'Sign=WXPay';
                $retData['noncestr'] = WxPayApi::getNonceStr();
                $retData['timestamp'] = "" . time();
                $retData['sign'] = $this->MakeSign($retData);
            }

            return $retData;
        }

        return false;
    }

    //小程序支付
    public function miniPayUnifiedOrder($notifyUrl, $totalFee, $body, $outTradeNo, $openId, $attach = '')
    {
        $totalFee = (int)($totalFee * 100);             //单位为分
        $nonce_str = WxPayApi::getNonceStr();
        $input = new WxPayUnifiedOrder();

        $input->SetAppid(WxPayConfig::APPID);         //小程序账号ID
        $input->SetMch_id(WxPayConfig::MCHID);        //小程序商户号

        $input->SetNonce_str($nonce_str);             //随机字符串 长度要求在32位以内
        $input->SetBody($body);                       //商品描述
        $input->SetAttach($attach);                   //附加数据，在查询API和支付通知中原样返回，可作为自定义参数使用。
        $input->SetOut_trade_no($outTradeNo);         //商户订单号 商户订单号保持唯一性（建议根据当前系统时间加随机序列来生成订单号）
        $input->SetFee_type("CNY");                   //标价币种
        $input->SetTotal_fee($totalFee);              //订单总金额，单位为分
        $input->SetNotify_url($notifyUrl);            //通知url，不能携带参数。

        if (!empty($openId)) { // 小程序支付
            $tradeType = "JSAPI";
            $input->SetOpenid($openId); //用户标识, trade_type=JSAPI时（即公众号支付），此参数必传，此参数为微信用户在商户对应appid下的唯一标识。
        }
        $input->SetTrade_type($tradeType); //交易类型: JSAPI--公众号支付、NATIVE--原生扫码支付、APP--app支付 MICROPAY--刷卡支付，刷卡支付有单独的支付接口，不调用统一下单接口

        try {
            $result = WxPayApi::unifiedOrder($input);
        } catch (WxPayException $ex) {
            return false;
        }

        if ($result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS') {
            $retData = array();
            if (!empty($openId)) {
                $retData['appId'] = WxPayConfig::APPID;
                $retData['timeStamp'] = "" . time();
                $retData['nonceStr'] = WxPayApi::getNonceStr();
                $retData['package'] = 'prepay_id=' . $result['prepay_id'];
                $retData['signType'] = 'MD5';
                $retData['paySign'] = $this->MakeSign($retData);
            }
            return $retData;
        }

        return false;
    }

    //iOS 内购支付状态验证

    /**
     * 验证AppStore内付
     * @param  string $receipt_data 付款后凭证
     * @return array                验证是否成功
     */
    function validate_apple_pay($receipt_data)
    {
        // 验证参数
        if (strlen($receipt_data) < 20) {
            $result = array(
                'status' => false,
                'message' => '非法参数'
            );
            return $result;
        }
        // 请求验证
        $html = $this->acurl($receipt_data);
        $data = json_decode($html, true);
        // 如果是沙盒数据 则验证沙盒模式
        if ($data['status'] == '21007') {
            // 请求验证
            $html = $this->acurl($receipt_data, 1);
            $data = json_decode($html, true);
            $data['sandbox'] = '1';
        }

        // 判断是否购买成功
        if (intval($data['status']) === 0) {
            $result = array(
                'status' => true,
                'message' => '购买成功'
            );
        } else {
            $result = array(
                'status' => false,
                'message' => '购买失败 status:' . $data['status']
            );
        }
        return $result;
    }

    /**
     * 21000 App Store不能读取你提供的JSON对象
     * 21002 receipt-data域的数据有问题
     * 21003 receipt无法通过验证
     * 21004 提供的shared secret不匹配你账号中的shared secret
     * 21005 receipt服务器当前不可用
     * 21006 receipt合法，但是订阅已过期。服务器接收到这个状态码时，receipt数据仍然会解码并一起发送
     * 21007 receipt是Sandbox receipt，但却发送至生产系统的验证服务
     * 21008 receipt是生产receipt，但却发送至Sandbox环境的验证服务
     */
    private function acurl($receipt_data, $sandbox = 0)
    {
        //小票信息
        $POSTFIELDS = array("receipt-data" => $receipt_data);
        $POSTFIELDS = json_encode($POSTFIELDS);

        //正式购买地址 沙盒购买地址
        $url_buy = "https://buy.itunes.apple.com/verifyReceipt";
        $url_sandbox = "https://sandbox.itunes.apple.com/verifyReceipt";
        $url = $sandbox ? $url_sandbox : $url_buy;

        //简单的curl
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $POSTFIELDS);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    //ios 内购
    public function appStoreNotifyProcessCallback($result)
    {
        if ($result['status']) {
            $transactionId = '';
            $outTradeNo = $result['out_trade_no'];
            $timeEnd = date('Y-m-d H:i:s');
            $totalFee = $result['total_fee'];

            $this->db->beginTransaction();
            try {
                $amount = $totalFee / 100.0;
                $order = $this->updateOrderPayInfo($outTradeNo, $amount, $transactionId, $timeEnd, self::PAY_STATUS_OK);
                if (empty($order)) {
                    $this->db->rollBack();
                    return false;
                }

                // 更新订单状态:已支付
                $orderObj = new OrderModel($this->dsn);
                $orderObj->updateStatusBySN($outTradeNo, OrderModel::ORDER_STATUS_PAID);
                $orderObj->addUserService($outTradeNo);

                $this->db->commit();
            } catch (Gek_Exception $ex) {
                $this->db->rollBack();
                return false;
            }

            return true;
        }
        return false;
    }

    public function wxPayNotifyProcess()
    {
        $wxPayNotify = new WxPayNotify();
        $msg = "OK";
        $result = WxpayApi::notify(array($this, 'wxPayNotifyProcessCallback'), $msg);
        if ($result == false) {
            //当返回false的时候，表示notify中调用NotifyCallBack回调失败  获取签名校验失败，此时直接回复失败
            $wxPayNotify->SetReturn_code("FAIL");
            $wxPayNotify->SetReturn_msg("OK");
            $wxPayNotify->ReplyNotify(false);
            return;
        } else {
            //该分支在成功回调到NotifyCallBack方法，处理完成之后流程
            $wxPayNotify->SetReturn_code("SUCCESS");
            $wxPayNotify->SetReturn_msg("OK");
            $wxPayNotify->ReplyNotify(false);
        }
    }

    public function wxPayNotifyProcessCallback($result)
    {
        if ($result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS') {
            $totalFee = $result['total_fee'];
            $transactionId = $result['transaction_id'];
            $outTradeNo = $result['out_trade_no'];
            $timeEnd = $result['time_end'];
            //$this->writelog("wxPayNotifyProcessCallback ok:" . $totalFee ."||". $transactionId ."||".$outTradeNo."||".$timeEnd);
            $attach = '';
            if (isset($result['attach'])) {
                $attach = $result['attach'];
            }

            $this->db->beginTransaction();
            try {
                $amount = $totalFee / 100.0;
                $order = $this->updateOrderPayInfo($outTradeNo, $amount, $transactionId, $timeEnd, self::PAY_STATUS_OK);
                if (empty($order)) {
                    $this->db->rollBack();
                    return false;
                }

                // 更新订单状态:已支付
                $orderObj = new OrderModel($this->dsn);
                $orderObj->updateStatusBySN($outTradeNo, OrderModel::ORDER_STATUS_PAID);
                $orderObj->addUserService($outTradeNo);

                $this->db->commit();
            } catch (Gek_Exception $ex) {
                $this->db->rollBack();
                return false;
            }

            return true;
        }
        return false;
    }

    // 创建支付订单
    public function createPayOrder($out_trade_no, $userId, $amount, $payType)
    {
        // 创建订单 
        $order = array();
        $order['user_id'] = $userId;
        $order['time'] = date("Y-m-d H:i:s");
        $order['amount'] = $amount;
        $order['out_trade_no'] = $out_trade_no;
        $order['pay_type'] = $payType;

        try {
            $this->db->insert($this->payDetailTable, $order);
            $retData = array('out_trade_no' => $out_trade_no, 'total_fee' => $amount);
            return $retData;
        } catch (Gek_Exception $ex) {
            //$ex->writeLog();
            return false;
        }
    }

    //商户系统对于支付结果通知的内容一定要做签名验证,并校验返回的订单金额是否与商户侧的订单金额一致，防止数据泄漏导致出现“假通知”，造成资金损失。
    public function updateOrderPayInfo($out_trade_no, $amount, $trade_no, $pay_time, $pay_status)
    {
        $r = $this->db->get($this->payDetailTable, 'out_trade_no', $out_trade_no, 'id, user_id, amount, pay_status');
        if (empty($r)) { // 订单不存在
            //$this->writelog("订单不存在");
            return false;
        }
        if ($r['pay_status'] == self::PAY_STATUS_OK) { // 订单已支付
            //$this->writelog("订单已支付");
            return false;
        }
        if ((int)((float)$r['amount'] * 100) !== (int)((float)$amount * 100)) { // 支付金额和订单金额不一致
            //$this->writelog("支付金额和订单金额不一致");
            return false;
        }
        $order = $r;
        $now = date("Y-m-d H:i:s");
        $cols = array();
        $cols['pay_status'] = $pay_status;
        $cols['trade_no'] = $trade_no;
        $cols['pay_time'] = $pay_time;
        $cols['pay_notify_time'] = $now;
        try {
            $rr = $this->db->update($this->payDetailTable, $cols, array('out_trade_no =' => $out_trade_no));
            return $order;
        } catch (Gek_Exception $ex) {
            return false;
        }
    }

    ////////////////////////////////////////////////////////////////////

    private function writelog($msg)
    {
        $logpath = '/tmp/test.log';

        if (!$fp = @fopen($logpath, 'ab')) {
            return false;
        }

        $level = '[INFO] ';
        $message = '';
        $message .= $level . ' ' . (($level == 'INFO' || $level == 'WARN') ? ' -' : '-') . ' ' . date('Y-m-d H:i:s') . ' --> ' . $msg . "\n";

        flock($fp, LOCK_EX);
        fwrite($fp, $message);
        flock($fp, LOCK_UN);
        fclose($fp);

        @chmod($logpath, 0666);
        return false;
    }


    /**
     * 格式化参数格式化成url参数
     */
    private function ToUrlParams($values)
    {
        $buff = "";
        foreach ($values as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    /**
     * 生成签名
     * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
     */
    private function MakeSign($values)
    {
        //签名步骤一：按字典序排序参数
        ksort($values);
        $string = $this->ToUrlParams($values);
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=" . WxPayConfig::KEY;
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    /**
     * 输出xml字符
     **/
    public function ToXml($values)
    {
        $xml = "<xml>";
        foreach ($values as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     * 将xml转为array
     * @param string $xml
     */
    public function FromXml($xml)
    {
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $this->values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $this->values;
    }

    /**
     * 以post方式提交xml到对应的接口url
     *
     * @param string $xml 需要post的xml数据
     * @param string $url url
     * @param bool $useCert 是否需要证书，默认不需要
     * @param int $second url执行超时时间，默认30s
     * @throws WxPayException
     */
    public function postXmlCurl($xml, $url, $useCert = false, $second = 30)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);

        //如果有配置代理这里就设置代理
        if (WxPayConfig::CURL_PROXY_HOST != "0.0.0.0"
            && WxPayConfig::CURL_PROXY_PORT != 0) {
            curl_setopt($ch, CURLOPT_PROXY, WxPayConfig::CURL_PROXY_HOST);
            curl_setopt($ch, CURLOPT_PROXYPORT, WxPayConfig::CURL_PROXY_PORT);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);//严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        if ($useCert == true) {
            //设置证书
            //使用证书：cert 与 key 分别属于两个.pem文件
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLCERT, WxPayConfig::SSLCERT_PATH);
            curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLKEY, WxPayConfig::SSLKEY_PATH);
        }
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            throw new WxPayException("curl出错，错误码:$error");
        }
    }


}



