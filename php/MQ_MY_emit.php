<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
//删除数组某个值
function delByValue($arr, $value){  
    $keys = array_keys($arr, $value);  
    //var_dump($keys);  
    if(!empty($keys)){  
        foreach ($keys as $key) {
            unset($arr[$key]);  
        }  
    }  
    return $arr;  
}
//删除之前所有数据
function delByValueAll($arr, $value){  
    sort($arr);
    $keys = array_keys($arr, $value);  
    //var_dump($keys);  
    if(!empty($keys)){
        for ($i=0; $i <= $key; $i++) { 
            unset($arr[$i]);
        }
    }
    return $arr;
}

$routingKey = "testRoutingKey";

$exchangeName = "master";
$exchangeNameRetry = "master.retry";
$exchangeNameFailed = "master.failed";

$queueName = "queueTest";
$failedQueueName = "QueueTestFailed";
$retryQueueName = "QueueTestRetry";

$connection = new AMQPStreamConnection('localhost', 5673, 'admin', 'admin','/');
$channel = $connection->channel();

//每次接受消息的条数
$channel->basic_qos(null, 1, null);

/**
 *
 * @param string $exchange
 * @param string $type
 * @param bool $passive=false 如果Exchange已经存在，则返回成功，不存在则创建
 * @param bool $durable
 * @param bool $auto_delete
 * @param bool $internal = false
 * @param bool $nowait = false //是否异步调用
 * @param array $arguments = null
 * @param int $ticket = null
 * @return mixed|null
 */
// 普通交换机
$channel->exchange_declare($exchangeName, 'topic', false, true, false);
// 重试交换机
$channel->exchange_declare($exchangeNameRetry, 'topic', false, true, false);
// 失败交换机
$channel->exchange_declare($exchangeNameFailed, 'topic', false, true, false);
/**
 *
 * @param string $queue
 * @param bool $passive
 * @param bool $durable
 * @param bool $exclusive
 * @param bool $auto_delete
 * @param bool $nowait=false 该方法需要应答确认
 * @param array $arguments
 * @param int $ticket
 * @return mixed|null
 */
//消息订阅
$channel->queue_declare($queueName, false, true, false, false, false);
$channel->queue_declare($failedQueueName, false, true, false, false, false);
$channel->queue_declare(
    $retryQueueName, // 队列名称
    false,           // passive
    true,            // durable
    false,           // exclusive
    false,           // auto_delete
    false,           // nowait
    new AMQPTable([
        'x-dead-letter-exchange' => $exchangeName,//死信重试消息队列
        'x-message-ttl'          => 30 * 1000,//30秒重试
    ])
);
/**
* 将交换器与队列通过路由键绑定
*/
$channel->queue_bind($queueName, $exchangeName, $routingKey);
$channel->queue_bind($retryQueueName, $exchangeNameRetry, $routingKey);
$channel->queue_bind($failedQueueName, $exchangeNameFailed, $routingKey);


//异步Confirm模式
$channel->confirm_select();
//异步回调消息确认
//已确认消息,$multiples是否多消息确认
$channel->set_ack_handler(
    function (AMQPMessage $message) {

        echo "Message acked with content " . $message->body . PHP_EOL;
    }
);
$channel->set_nack_handler(
    function (AMQPMessage $message) {
        echo "Message nacked with content " . $message->body . PHP_EOL;
    }
);
//批量消息发布
for ($i=0; $i < 3; $i++) { 

    $message='hello';
    $msg = new AMQPMessage($message, [
        'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,//持久化消息
        'content_type' => 'text/plain',//消息类型
    ]);
    $channel->basic_publish($msg, $exchangeName, $routingKey);

}
//阻塞等待消息确认，处理完成
$channel->wait_for_pending_acks();


$channel->close();
$connection->close();



