## 服务说明

从非SAE PHP运行环境调用taskqueue服务。

## 服务文档

<https://apidocpublic.applinzi.com/class-SaeTaskQueuePublic.html>

## 代码实例

```php
require(dirname(__FILE__) . '/saetaskqueuepublic.class.php');

// test是队列名称
$instance = new SaeTaskQueuePublic('test');
// 应用名、accesskey、secretkey、应用的版本请从sae.sinacloud.com获取
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
    // 获取当前队列中的长度
    $current_length = $instance->curLength();
    // 获取当前应用剩余的长度
    $left_length = $instance->leftLength();
    var_dump('length info: ', $current_length, $left_length);
} 
```