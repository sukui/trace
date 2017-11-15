<?php

namespace ZanPHP\Trace;

use ZanPHP\ConnectionPool\ConnectionEx;
use ZanPHP\ConnectionPool\TCP\TcpClient;
use ZanPHP\ConnectionPool\TCP\TcpClientEx;
use ZanPHP\Contracts\ConnectionPool\ConnectionManager;
use ZanPHP\Contracts\Foundation\Application;
use ZanPHP\Contracts\Trace\Constant;
use ZanPHP\Contracts\Trace\Tracer;

class ZanTracer extends Tracer
{

    private $appName;
    private $hostName;
    private $ip;
    private $pid;
    private $builder;
    private $currentTrace;

    /*
     * 存放traceBegin数据,key为begin的位置,value为trace数据
     */
    private $data = [];

    public function __construct($rootId = null, $parentId = null)
    {
        $this->builder = new TraceBuilder();
        /** @var Application $application */
        $application = make(Application::class);
        $this->appName = $application->getName();
        $this->hostName = getenv('hostname');
        $this->ip = getenv('ip');
        $this->pid = getenv('pid');

        if ($rootId) {
            $this->root_id = $rootId;
        }

        if ($parentId) {
            $this->parent_id = $parentId;
        }

    }

    public function initHeader($msgId = null)
    {
        if (!$msgId) {
            $msgId = $this->builder->generateId();
        }

        if (!$this->root_id) {
            $this->root_id = 'null';
        }

        if (!$this->parent_id) {
            $this->parent_id = 'null';
        }

        $header = [
            Trace::PROTOCOL,
            $this->appName,
            $this->hostName,
            $this->ip,
            Trace::GROUP_NAME,
            $this->pid,
            Trace::NAME,
            $msgId,
            $this->parent_id,
            $this->root_id,
            "null"
        ];
        $this->builder->buildHeader($header);

        if ($this->root_id === 'null') {
            $this->root_id = $msgId;
        }

        $this->parent_id = $msgId;
    }

    public function transactionBegin($type, $name)
    {
        list($usec, $sec) = explode(' ', microtime());
        $time = date("Y-m-d H:i:s", $sec) . substr($usec, 1, 4);

        $trace = [
            $sec + $usec,
            "t$time",
            $type,
            $name,
        ];
        //$this->builder->buildTransaction($trace);
        //$trace[0] = $sec + $usec;

        $this->currentTrace = $trace;

        $this->data[] = $trace;

        return count($this->data) - 1;
    }

    public function transactionEnd($handle, $status, $sendData = '')
    {
        list($usec, $sec) = explode(' ', microtime());
        $time = date("Y-m-d H:i:s", $sec) . substr($usec, 1, 4);

        //$handle为0代表整个请求结束,需要fixTrace
        if ($handle === 0) {
            $this->fixTrace($sec, $usec, $time);
        }
        $data = $this->data[$handle];
        $this->data[$handle] = null;
        $utime = floor(($sec + $usec - $data[0]) * 1000000);

        //嵌入事件判断
        if($handle != 0 && $this->currentTrace != null){
            $mTime = "A{$time}";
        }else{
            $mTime = "T{$time}";
        }

        if (!is_scalar($sendData)) {
            $sendData = json_encode($sendData);
        }

        $trace = [
            $mTime,
            $data[2],
            $data[3],
            addslashes($status),
            $utime . "us",
            addslashes($sendData)
        ];
        $this->builder->commitTransaction($trace);
    }

    /**
     * 标记当前未提交的trace
     */
    private function commitCurrentTrace(){
        if($this->currentTrace != null){
            $currentTrace = $this->currentTrace;
            $this->currentTrace = null;
            $trace = [
                $currentTrace[1],
                $currentTrace[2],
                $currentTrace[3],
            ];
            $this->builder->buildTransaction($trace);
        }
    }

    /*
     * 补全Trace中调用了transactionBegin但还没有调用transactionEnd的信息
     */
    private function fixTrace($sec, $usec, $time)
    {
        $cnt = count($this->data);
        for ($i = 1; $i < $cnt; $i++) {
            if ($this->data[$i] !== null) {
                $data = $this->data[$i];
                $this->data[$i] = null;
                $utime = floor(($sec + $usec - $data[0]) * 1000000);
                $trace = [
                    "T$time",
                    $data[1],
                    $data[2],
                    addslashes('fix timeout trace'),
                    $utime . "us",
                    addslashes('')
                ];
                $this->builder->commitTransaction($trace);
            }
        }
    }

    public function logEvent($type, $status, $name = "", $context = "")
    {
        list($usec, $sec) = explode(' ', microtime());
        $time = date("Y-m-d H:i:s", $sec) . substr($usec, 1, 4);

        if (!is_scalar($name)) {
            $name = json_encode($name);
        }

        if (!is_scalar($context)) {
            $context = json_encode($context);
        }

        $trace = [
            "E$time",
            $type,
            $name,
            $status,
            addslashes($context),
        ];
        $this->commitCurrentTrace();
        $this->builder->buildEvent($trace);
    }

    public function logError($type, $name, \Exception $error)
    {
        $context = "\n".$error->getMessage()."\n".$error->getTraceAsString()."\n";
        $this->commitCurrentTrace();
        $this->logEvent($type,'error',$name,$context);
    }

    public function logMetricForCount($name, $quantity = 1)
    {
        list($usec, $sec) = explode(' ', microtime());
        $time = date("Y-m-d H:i:s", $sec) . substr($usec, 1, 4);

        if (!is_scalar($name)) {
            $name = json_encode($name);
        }
        $quantity = intval($quantity);
        $trace = [
            "M$time",
            '',
            $name,
            'C',
            $quantity,
        ];
        $this->commitCurrentTrace();
        $this->builder->buildEvent($trace);
    }

    public function logMetricForSum($name, $value = 1.0)
    {
        list($usec, $sec) = explode(' ', microtime());
        $time = date("Y-m-d H:i:s", $sec) . substr($usec, 1, 4);

        if (!is_scalar($name)) {
            $name = json_encode($name);
        }
        $value = sprintf("%.2f", $value);
        $trace = [
            "M$time",
            '',
            $name,
            'S',
            $value,
        ];
        $this->commitCurrentTrace();
        $this->builder->buildEvent($trace);
    }

    public function logHeartbeat($type,$name='',$content='')
    {
        list($usec, $sec) = explode(' ', microtime());
        $time = date("Y-m-d H:i:s", $sec) . substr($usec, 1, 4);

        if (!is_scalar($name)) {
            $name = json_encode($name);
        }
        $trace = [
            "H$time",
            $type,
            $name,
            Constant::SUCCESS,
            $content,
        ];
        $this->commitCurrentTrace();
        $this->builder->buildEvent($trace);
    }

    public function uploadTraceData()
    {
        try {
            /** @var ConnectionManager $connectionManager */
            $connectionManager = make(ConnectionManager::class);
            $connection = (yield $connectionManager->get("tcp.trace", false));
            if ($connection instanceof ConnectionEx) {
                $tcpClient = new TcpClientEx($connection);
                yield $tcpClient->send($this->builder->getData());
            } else {
                $tcpClient = new TcpClient($connection);
                yield $tcpClient->send($this->builder->getData());
            }
        } catch (\Throwable $t) {
            echo_exception($t);
        } catch (\Exception $e) {
            echo_exception($e);
        }
    }

}

