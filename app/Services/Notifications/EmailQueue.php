<?php

namespace App\Services\Notifications;

use RuntimeException;

class EmailQueue
{
    public static function connection(): string
    {
        $connection = config('mail.queue_connection', 'redis');
        $driver = config("queue.connections.{$connection}.driver");
        if (! in_array($driver, ['redis', 'database', 'sqs', 'beanstalkd'], true)) {
            throw new RuntimeException('Email requires a persistent asynchronous queue connection.');
        }

        return $connection;
    }
}
