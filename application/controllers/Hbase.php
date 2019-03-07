<?php
/*
 * hbase测试时使用，并不直接用于项目
 */

use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\TSocket;
use Thrift\Transport\THttpClient;
use Thrift\Transport\TBufferedTransport;
use Thrift\Exception\TException;

class HbaseController extends Yaf_Controller_Abstract
{
    private $config;
    private $view;
    private $request;
    private $controller;
    private $action;
     
    public function init()
    {
        $this->config = Yaf_Registry::get("config");
        $this->view = $this->getView();
        $this->request = $this->getRequest();
        $this->controller = strtolower($this->request->getControllerName());
        $this->action = strtolower($this->request->getActionName());
        Yaf_Dispatcher::getInstance()->autoRender(FALSE);
        
        $this->dsn = $this->config->get("database.main");

    }
    
    public function indexAction() 
    { 
        echo "hello, world.";
        return;
    }

    public function testAction() 
    { 
        error_reporting(E_ALL);
        
        $socket = new TSocket('192.168.1.244', '9090');  
        $socket->setSendTimeout(5000); // Ten seconds (too long for production, but this is just a demo ;)  
        $socket->setRecvTimeout(10000); // Twenty seconds  
        $transport = new TBufferedTransport($socket);  
        $protocol = new TBinaryProtocol($transport);
        
        Yaf_loader::import('hbase/THBaseService.php');
        Yaf_loader::import('hbase/Types.php');
        $client = new THBaseServiceClient($protocol);
        $column_1 = new TColumn();
        $column_1->family = 'cf';
        $transport->open();
        //$t = new TGet(array('row' => '20170607000987145414880','columns' => array( $column_1 ) ) );
        //$stock_id = '399001';
        $start_row = 'r2';//date("Ymd").$stock_id;
        //$end_row = '20170609399001100530327'; //date("Ymd",strtotime("+1 day")).$stock_id;
        $t = new TScan(array('startRow' => $start_row, 'columns' => array($column_1)));
        $ret = $client->getScannerResults('test', $t, 10);
        $transport->close();
    }
}