<?php
/**
 * Created by PhpStorm.
 * User: 37708
 * Date: 2019/6/18
 * Time: 16:13
 */
require(dirname(__FILE__) . '/saetaskqueuepublic.class.php');

$instance = new SaeTaskQueuePublic('test');
$instance->setAuth('应用名', '应用的accessKey',
    '应用的secretKey', '应用的版本');

$array = array();

for ($i = 0; $i < 99; $i++) {
    $array[] = array('url' => "/tq.php", "postdata" => "message=" . $i);
}

$instance->addTask($array);

$ret = $instance->push();

//任务添加失败时输出错误码和错误信息
if ($ret === false) {
    var_dump($instance->errno(), $instance->errmsg());
} else {
    var_dump('add task success');
    $current_length = $instance->curLength();
    $left_length = $instance->leftLength();
    var_dump('length info: ', $current_length, $left_length);
}