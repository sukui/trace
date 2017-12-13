<?php
/**
 * Created by PhpStorm.
 * User: laogui
 * Date: 2017/11/17
 * Time: 下午12:01
 */
use ZanPHP\Contracts\Trace\Trace;
use ZanPHP\Coroutine\Signal;
use ZanPHP\Coroutine\SysCall;
use ZanPHP\Coroutine\Task;

/**
 * 点评cat追踪记录开始，必要和结束成对使用
 * @param $type
 * @param $name
 * @return SysCall
 */
function logTransactionBegin($type, $name){
    return new SysCall(function (Task $task) use ($type, $name) {
        $context = $task->getContext();
        $trace = $context->get("trace");
        if ($trace instanceof Trace) {
            $task->send($trace->transactionBegin($type, $name));
        }else{
            $task->send(false);
        }
        return Signal::TASK_CONTINUE;
    });
}

/**
 * 点评cat追踪记录结束，必要和开始成对使用
 * @param $handle
 * @param $status
 * @param string $sendData
 * @return SysCall
 */
function logTransactionEnd($handle, $status, $sendData = ''){
    return new SysCall(function (Task $task)use($handle,$status,$sendData){
        if(!empty($handle)){
            $context = $task->getContext();
            $trace = $context->get("trace");
            if ($trace instanceof Trace) {
                $task->send($trace->commit($handle,$status,$sendData));
            }else{
                $task->send(false);
            }
        }else{
            $task->send(false);
        }
        return Signal::TASK_CONTINUE;
    });
}

/**
 * 点评cat追踪记录事件
 * @param $type
 * @param $status
 * @param string $name
 * @param string $data
 * @return SysCall
 */
function logEvent($type, $status, $name = "", $data = ""){
    return new SysCall(function (Task $task)use($type,$status,$name,$data){
        $context = $task->getContext();
        $trace = $context->get("trace");
        if ($trace instanceof Trace) {
            $task->send($trace->logEvent($type, $status, $name, $data));
        } else {
            $task->send(false);
        }
        return Signal::TASK_CONTINUE;
    });
}

/**
 * 点评cat追踪记录错误
 * @param $type
 * @param $name
 * @param Exception $error
 * @return SysCall
 */
function logError($type, $name, \Exception $error){
    return new SysCall(function (Task $task)use($type,$name,$error){
        $context = $task->getContext();
        $trace = $context->get("trace");
        if ($trace instanceof Trace) {
            $task->send($trace->logError($type,$name,$error));
        }else{
            $task->send(false);
        }
        return Signal::TASK_CONTINUE;
    });
}

/**
 * 点评cat追踪 业务统计
 * @param $name
 * @param int $quantity
 * @return SysCall
 */
function logMetricForCount($name, $quantity = 1){
    return new SysCall(function (Task $task)use($name,$quantity){
        $context = $task->getContext();
        $trace = $context->get("trace");
        if ($trace instanceof Trace) {
            $task->send($trace->logMetricForCount($name,$quantity));
        }else{
            $task->send(false);
        }
        return Signal::TASK_CONTINUE;
    });
}

/**
 * 点评cat追踪业务额度统计
 * @param $name
 * @param float $value
 * @return SysCall
 */
function logMetricForSum($name, $value = 1.0){
    return new SysCall(function (Task $task)use($name,$value){
        $context = $task->getContext();
        $trace = $context->get("trace");
        if ($trace instanceof Trace) {
            $task->send($trace->logMetricForSum($name,$value));
        }else{
            $task->send(false);
        }
        return Signal::TASK_CONTINUE;
    });
}
