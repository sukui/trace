<?php
namespace ZanPHP\Trace;

use ZanPHP\Config\Repository;
use ZanPHP\Coroutine\Task;
use ZanPHP\Timer\Timer;
use ZanPHP\Utilities\File\OnceFile;

class SystemMonitor
{
    public $server;
    protected $timer_id;

    public function bootstrap($server,$workerId)
    {
        if($workerId == 0){
            $this->timer_id = Timer::tick(60000,function ()use($server){
                $stats = $server->swooleServer->stats();
                $data['app.connection_num'] = $stats['connection_num'];
                $data['app.total_worker'] = $stats['total_worker'];
                $data['app.active_worker'] = $stats['active_worker'];
                $data['app.idle_worker'] = $stats['idle_worker'];
                $data['app.worker_normal_exit'] = $stats['worker_normal_exit'];
                $data['app.worker_abnormal_exit'] = $stats['worker_abnormal_exit'];
                $co = $this->upload($data);
                Task::execute($co);
            });
        }
    }

    public function upload($data){
        $repository = make(Repository::class);
        $info = $this->getSystemInfo();
        $info = array_merge($data,$info);
        $str = null;
        foreach ($info as $key=>$value){
            if ($key != null) {
                $pair = $key;
                if ($value!= null){
                    $pair.= "=" . $value;
                }
                if ($str == null) {
                    $str = $pair;
                } else {
                    $str .= '&' . $pair;
                }
            }
        }
        $ip = getenv('ip');
        $config = $repository->get('monitor.trace');
        $trace = new Trace($config);
        $trace->initHeader();
        $traceHandle = $trace->transactionBegin("System", "Status");
        $trace->logHeartbeat("Heartbeat",$ip,$str);
        $trace->commit($traceHandle,'0','End');
        //send数据
        yield $trace->send();
    }

    //获取系统信息
    public function getSystemInfo(){
        $mem  =  $this->getSystemInfo();
        $load =  $this->getLoadAvg();
        $net  =  $this->getNetSpeed();
        return array_merge($mem,$load,$net);
    }

    //内存信息
    protected function getMemoryInfo(){
        $strs = @file("/proc/meminfo");
        $str = implode("", $strs);
        preg_match_all("/MemTotal\s{0,}\:+\s{0,}([\d\.]+).+?MemFree\s{0,}\:+\s{0,}([\d\.]+).+?Cached\s{0,}\:+\s{0,}([\d\.]+).+?SwapTotal\s{0,}\:+\s{0,}([\d\.]+).+?SwapFree\s{0,}\:+\s{0,}([\d\.]+)/s", $str, $buf);
        preg_match_all("/Buffers\s{0,}\:+\s{0,}([\d\.]+)/s", $str, $buffers);
        $res['memTotal'] = round($buf[1][0]/1024, 2);
        $res['memFree'] = round($buf[2][0]/1024, 2);
        $res['memBuffers'] = round($buffers[1][0]/1024, 2);
        $res['memCached'] = round($buf[3][0]/1024, 2);
        $res['memUsed'] = $res['memTotal']-$res['memFree'];
        $res['memPercent'] = (floatval($res['memTotal'])!=0)?round($res['memUsed']/$res['memTotal']*100,2):0;
        $res['memRealUsed'] = $res['memTotal'] - $res['memFree'] - $res['memCached'] - $res['memBuffers']; //真实内存使用
        $res['memRealFree'] = $res['memTotal'] - $res['memRealUsed']; //真实空闲
        $res['memRealPercent'] = (floatval($res['memTotal'])!=0)?round($res['memRealUsed']/$res['memTotal']*100,2):0; //真实内存使用率
        $res['memCachedPercent'] = (floatval($res['memCached'])!=0)?round($res['memCached']/$res['memTotal']*100,2):0; //Cached内存使用率
        $res['swapTotal'] = round($buf[4][0]/1024, 2);
        $res['swapFree'] = round($buf[5][0]/1024, 2);
        $res['swapUsed'] = round($res['swapTotal']-$res['swapFree'], 2);
        $res['swapPercent'] = (floatval($res['swapTotal'])!=0)?round($res['swapUsed']/$res['swapTotal']*100,2):0;
        $result = [
            'memory.total' => $res['memTotal'],
            'memory.used' => $res['memRealUsed'],
            'memory.free' => $res['memRealFree'],
            'memory.percent' => $res['memRealPercent'],
            'swap.total' => $res['swapTotal'],
            'swap.used' => $res['swapUsed'],
            'swap.free' => $res['swapFree'],
            'swap.percent' => $res['swapPercent'],
        ];
        yield $result;
    }

    //系统负载
    protected function getLoadAvg(){
        $strs = @file("/proc/loadavg");
        $str = explode(" ", implode("", $strs));
        $str = array_chunk($str, 4);
        $res['system.loadAvg'] = implode(" ", $str[0]);
        yield $res;
    }

    //网速
    protected function getNetSpeed(){
        $data1 = yield $this->getNetInfo();
        yield taskSleep(1000);
        $data2 = yield $this->getNetInfo();
        $result = [];
        foreach ($data1 as $key => $value){
            $result[$key] = $data2[$key]-$data1[$key];
        }
        yield $result;
    }

    //网卡信息
    protected function getNetInfo()
    {
        $strs = @file("/proc/net/dev");
        $result = [];
        for ($i = 2; $i < count($strs); $i++) {
            preg_match_all("/([^\s]+):[\s]{0,}(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/", $strs[$i], $info);
            $eth = $info[1][0];
            $result["{$eth}.input"] = round($info[2][0] / 1024 , 2);
            $result["{$eth}.output"] = round($info[10][0] / 1024 , 2);
        }
        yield $result;
    }


}