<?php

class OrderModel {

    private $dsn;
    private $db;

    private $serviceTable     = 'service';
    private $skuTable         = 'service_sku';
    private $orderTable       = 'order';
    private $userServicesTable = 'user_services';

    const STATUS_UP = 0;     // 上架
    const STATUS_DOWN = 1;   // 下架 
    const STATUS_DELETE = 9; // 删除

    const ORDER_STATUS_UNPAY   = 0;    // 未支付
    const ORDER_STATUS_PAID    = 1;    // 已支付
    const ORDER_STATUS_INVALID = 2;    // 无效订单


    public function __construct($dsn)
    {
        $this->dsn = $dsn;
        $this->db = Gek_Db::getInstance($this->dsn);
    }

    public function getService($serviceId)  {
        $service = $this->db->get($this->serviceTable, 'id', $serviceId, '*', 'status = 0');
        if(empty($service)){
           return array();
        }

        $sql = "select * from {$this->skuTable} where service_id = $serviceId and status = 0 order by type asc";
        $sku = $this->db->query($sql, array(), 'all');
        if(empty($sku)) {
            return array();
        }

        $service['sku'] = $sku;

        return $service;
    }

    public function getServiceInfo($serviceId)  {
        $service = $this->db->get($this->serviceTable, 'id', $serviceId, '*', 'status = 0');
        if(empty($service)) {
           return array();
        }

        return $service;
    }

    /**
     * 购买服务 0-未购买， 1-已购买且未过期，2-购买已经过期
     * @param $userId
     * @param $serviceId
     * @return array
     */
    public function serviceIsBuy($userId,$serviceId) {
        $service = array();
        $service['isbuy'] = 0;
        $service['now'] = time();

        $sql = "select * from `{$this->userServicesTable}` where user_id = $userId and service_id = $serviceId and status = 0";
        $order = $this->db->query($sql, array(), 'row');
        if(empty($order)) {
            return $service;
        }

        $startDate = date('Y-m-d', strtotime($order['start_time']));
        $endts = strtotime($order['end_time']);

        // 是否过期
        $now = time();
        if($now >= $endts+86400) { // 已过期
            $service['isbuy'] = 0;
        } else {
            $service['isbuy'] = 1;
            $service['start_date'] = strtotime($order['start_time']);
            $service['end_date'] = $endts + 86400;
        }

        return $service;
    }

    public function getUserServices($userId)  {
        $sql = "select * from {$this->serviceTable} where status = 0";
        $services = $this->db->query($sql, array(), 'all');
        if(empty($services)) {
            return array();
        }

        $userServices = array();
        foreach ($services as $k => &$service) {
            $sql = "select * from `{$this->userServicesTable}` where user_id = $userId and service_id = {$service['id']} and status = 0 order by start_time desc limit 1 ";
            $order = $this->db->query($sql, array(), 'row');
            if(empty($order)) {
                continue;
            }

            //服务开始日期就是购买支付时间
            $startDate = date('Y-m-d', strtotime($order['start_time']));
            $endts = strtotime($order['end_time']) + 86400;

            // 是否过期
            $now = time();
            if($now >= $endts+86400) { // 已过期
                $service['status'] = 1;
            }

            $service['start_date'] = strtotime($order['start_time']);
            $service['end_date'] = $endts;
            $service['serviceId'] = $service['id'];
            $userServices[] = $service;
        }

        return $userServices;
    }

    public function get($orderId) {
        $sql = "select * from `{$this->orderTable}` where id = $orderId";
        $order = $this->db->query($sql, array(), 'row');
        if(empty($order)) {
            $order = array();
        }
        return $order;
    }

    public function getBySN($orderSn) {
        $sql = "select * from `{$this->orderTable}` where sn = '$orderSn'";
        $order = $this->db->query($sql, array(), 'row');
        if(empty($order)) {
            $order = array();
        }
        return $order;
    }

    public function getAndLocked($orderId) {
        $sql = "select * from `{$this->orderTable}` where id = $orderId FOR UPDATE";
        $order = $this->db->query($sql, array(), 'row');
        if(empty($order)) {
            $order = array();
        }
        return $order;
    }

    // 生成订单号 建议根据当前系统时间加随机序列来生成订单号
    private function getOrderSn() {
        $now = date("YmdHis");
        $rand = mt_rand(10000000, 99999999);
        $orderSn = $now.$rand;
        return $orderSn;
    }

    // 创建一个订单
    public function create($userId, $serviceId, $price, $skuId, $clientType=0, $a_type_num=0, $type_num=0) {
        $order = array();
        $order['sn'] = $this->getOrderSn();
        $order['user_id'] = $userId;
        $order['service_id'] = $serviceId;
        $order['sku_id'] = $skuId;
        $order['price'] = $price;
        $order['client_type'] = $clientType;
        $order['activity_type'] = $a_type_num;
        $order['point_type'] = $type_num;

        $orderId = $this->db->insert($this->orderTable, $order, NULL, 'lastid');
        return array('id' => $orderId, 'sn' => $order['sn']);
    }

    // 更新订单状态
    public function updateStatus($orderId, $status) {
        $ftime = date("Y-m-d H:i:s");
        $sql = "update `{$this->orderTable}` set status = $status, ftime= '$ftime' where id = $orderId";
        $this->db->query($sql);
    }

    // 通过订单编号更新订单状态
    public function updateStatusBySN($orderSn, $status) {
        $ftime = date("Y-m-d H:i:s");
        $sql = "update `{$this->orderTable}` set status = $status, ftime= '$ftime' where sn = '$orderSn'";
        $this->db->query($sql);
    }

    public function getSku($skuId) {
        $sql = "select * from {$this->skuTable} where id = $skuId";
        $sku = $this->db->query($sql, array(), 'row');
        if(empty($sku)) {
            $sku = array();
        }
        return $sku;
    }

    //修改用户服务过期时间，必须用在事务里
    public function addUserService($orderSn) {
        $sql = "select * from `{$this->orderTable}` where sn = '$orderSn' and status = 1";
        $order = $this->db->query($sql, array(), 'row');
        if(empty($order)) {
            return false;
        }

        //获取服务sku
        $sku = $this->getSku($order['sku_id']);
        if(empty($sku)) {
            return false;
        }

        $sql = "select * from `{$this->userServicesTable}` where user_id = {$order['user_id']} and service_id = {$order['service_id']} and status = 0";
        $userservice = $this->db->query($sql, array(), 'row');
        if(empty($userservice)) {
            // 添加新购买的服务
            $service = array();
            $service['user_id'] = $order['user_id'];
            $service['service_id'] = $order['service_id'];
            $service['start_time'] = date('Y-m-d', strtotime($order['ftime']));
            $service['end_time'] = date('Y-m-d',strtotime("{$service['start_time']} +{$sku['type']} month"));
            $this->db->insert($this->userServicesTable,$service);
            return true;
        }

        //检查用户服务是否过期
        $now = strtotime(date('Y-m-d'));
        $endtime = strtotime($userservice['end_time']);
        if($now >= $endtime+86400) {
            $this->db->update($this->userServicesTable, array('status' => 1), "id = {$userservice['id']}");
            // 添加新购买的服务
            $service = array();
            $service['user_id'] = $order['user_id'];
            $service['service_id'] = $order['service_id'];
            $service['start_time'] = date('Y-m-d', strtotime($order['ftime']));
            $service['end_time'] = date('Y-m-d',strtotime("{$service['start_time']} +{$sku['type']} month"));
            $this->db->insert($this->userServicesTable,$service);
        } else { //未过期，修改过期时间
            $endtime = date('Y-m-d',strtotime("{$userservice['end_time']} +{$sku['type']} month"));
            $this->db->update($this->userServicesTable, array('end_time' => $endtime), "id = {$userservice['id']}");
        }
        return true;
    }
}

