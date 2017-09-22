<?php

namespace ZanPHP\Trace;

use ZanPHP\Contracts\Foundation\Application;

class TraceBuilder
{
    private $data = "";
    private static $hexIp = null;
    private static $index=1;
    private static $hourStamp=0;
    private static $pid=false;

    protected function lintSeparator(array &$data)
    {
        array_walk($data, function(&$str) {
            $str = str_replace("\t", str_repeat(chr(32), 4), $str);
        });
    }

    public function buildHeader(array $header)
    {
        $this->lintSeparator($header);
        $this->data .= sprintf("%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t\n", ...$header);
    }

    public function buildTransaction(array $transaction)
    {
        $this->lintSeparator($transaction);
        $this->data .= sprintf("%s\t%s\t%s\t\n", ...$transaction);
    }

    public function commitTransaction(array $transaction)
    {
        $this->lintSeparator($transaction);
        $this->data .= sprintf("%s\t%s\t%s\t%s\t%s\t%s\t\n", ...$transaction);
    }

    public function buildEvent(array $event)
    {
        $this->lintSeparator($event);
        $this->data .= sprintf("%s\t%s\t%s\t%s\t%s\t\n", ...$event);
    }

    public function isNotEmpty() {
        return !empty($this->data);
    }

    public function getData()
    {
        $len = strlen($this->data);
        $head = pack("N", $len);
        $body = pack("a{$len}",$this->data);
        return $head . $body;
    }

    public static function generateId()
    {
        if (null === self::$hexIp) {
            self::$hexIp = dechex(ip2long(getenv('ip')));
            $zeroLen = strlen(self::$hexIp);
            if ($zeroLen < 8) {
                self::$hexIp = '0' . self::$hexIp;
            }
        }

        if(!self::$pid){
            self::$pid = is_numeric($_SERVER['WORKER_ID'])?$_SERVER['WORKER_ID']:getenv('pid');
        }

        $hourStamp = intval(time()/3600);
        if($hourStamp != self::$hourStamp){
            self::$index = 1;
            self::$hourStamp = $hourStamp;
        }
        $index = intval(self::$index.self::$pid);
        self::$index++;

        /** @var Application $application */
        $application = make(Application::class);
        $data = [
            $application->getName(),
            self::$hexIp,
            $hourStamp,
            $index
        ];
        $data = implode('-', $data);
        return $data;
    }
}