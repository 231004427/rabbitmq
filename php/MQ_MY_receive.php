<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$routingKey="testRoutingKey";
$exchangeName="master";
$exchangeNameRetry="master.retry";
$exchangeNameFailed="master.failed";

$queueName="queueTest";
$failedQueueName="QueueTestFailed";
$retryQueueName="QueueTestRetry";


/**
* @param string $host IP地址
* @param string $port 端口
* @param string $user 用户名
* @param string $password 用户密码
* @param string $vhost 虚拟域
 */
$connection = new AMQPStreamConnection('localhost', 5672, 'admin', 'admin','/');
$channel = $connection->channel();


//消息消费实现
function getRetryCount(AMQPMessage $msg): int
{
    $retry = 0;
    if ($msg->has('application_headers')) {
        $headers = $msg->get('application_headers')->getNativeData();
        if (isset($headers['x-death'][0]['count'])) {
            $retry = $headers['x-death'][0]['count'];
        }
    }

    return (int)$retry;
}

$channel->basic_consume(
    $queueName,
    '',    // customer_tag
    false, // no_local
    false, // no_ack
    false, // exclusive
    false, // nowait
    function (AMQPMessage $msg) use ($channel) {
        echo $msg->delivery_info['routing_key'], ':', $msg->body, "\n";
        $routingKey="testRoutingKey";
        $exchangeName="master";
        $exchangeNameRetry="master.retry";
        $exchangeNameFailed="master.failed";

        //如果处理出错，进入重试队列
        $retry=getRetryCount($msg);
        if($retry>3){
            // 重试次数大于3次，则自动加入到失败队列
            echo "重试次数大于3次，则自动加入到失败队列";
            $channel->basic_publish($msg, $exchangeNameFailed, $routingKey);
        }else{
            // 重试次数小于3，则加入到重试队列，30s后再重试
            echo "重试次数".$retry."，则加入到重试队列，30s后再重试";
            $channel->basic_publish($msg, $exchangeNameRetry, $routingKey);

        }
        //确认消息被正常消费
        echo "消息确认消费";
        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
    }
);
while (count($channel->callbacks)) {
    $channel->wait();
    
}

$channel->close();
$connection->close();



