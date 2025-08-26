<?php
require __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;

$connection = new AMQPStreamConnection(getenv('RABBITMQ_HOST'), getenv('RABBITMQ_PORT'), getenv('RABBITMQ_USER'), getenv('RABBITMQ_PASS'));
$channel    = $connection->channel();

echo " [*] Waiting for messages. To exit press CTRL+C\n";

$callback = function ($msg) {
    $data = json_decode($msg->body, true);
    echo " [x] Received User Data: ", print_r($data, true), "\n";

    file_put_contents(__DIR__ . '/user_logs.txt', $msg->body . PHP_EOL, FILE_APPEND);
};

$channel->basic_consume('user_created', '', false, true, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}
