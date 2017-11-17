<?php
/**
 * Created by PhpStorm.
 * User: laogui
 * Date: 2017/11/17
 * Time: 下午12:01
 */
use ZanPHP\Contracts\Trace\Trace;

/**
 * 点评cat追踪记录开始，必要和结束成对使用
 * @param $type
 * @param $name
 * @return int|bool
 */
function logTransactionBegin($type, $name){
    $trace = (yield getContext("trace"));
    if ($trace instanceof Trace) {
        return $trace->transactionBegin($type, $name);
    }else{
        return false;
    }
}

/**
 * 点评cat追踪记录结束，必要和开始成对使用
 * @param $handle
 * @param $status
 * @param string $sendData
 * @return bool
 */
function logTransactionEnd($handle, $status, $sendData = ''){
    if(!empty($handle)){
        $trace = (yield getContext("trace"));
        if ($trace instanceof Trace) {
            return $trace->commit($handle,$status,$sendData);
        }else{
            return false;
        }
    }else{
        return false;
    }
}

/**
 * 点评cat追踪记录事件
 * @param $type
 * @param $status
 * @param string $name
 * @param string $context
 * @return bool
 */
function logEvent($type, $status, $name = "", $context = ""){
    $trace = (yield getContext("trace"));
    if ($trace instanceof Trace) {
        return $trace->logEvent($type,$status,$name,$context);
    }else{
        return false;
    }
}

/**
 * 点评cat追踪记录错误
 * @param $type
 * @param $name
 * @param Exception $error
 * @return bool
 */
function logError($type, $name, \Exception $error){
    $trace = (yield getContext("trace"));
    if ($trace instanceof Trace) {
        return $trace->logError($type,$name,$error);
    }else{
        return false;
    }
}

/**
 * 点评cat追踪 业务统计
 * @param $name
 * @param int $quantity
 * @return bool
 */
function logMetricForCount($name, $quantity = 1){
    $trace = (yield getContext("trace"));
    if ($trace instanceof Trace) {
        return $trace->logMetricForCount($name,$quantity);
    }else{
        return false;
    }
}

/**
 * 点评cat追踪业务额度统计
 * @param $name
 * @param float $value
 * @return bool
 */
function logMetricForSum($name, $value = 1.0){
    $trace = (yield getContext("trace"));
    if ($trace instanceof Trace) {
        return $trace->logMetricForSum($name,$value);
    }else{
        return false;
    }
}

