<?php

namespace Printed\Bundle\Queue\Enum;

use Printed\Bundle\Queue\EntityInterface\QueueTaskInterface;

class QueueTaskStatus
{
    /**
     * The task is pending execution and should be sitting in the queue already.
     */
    const PENDING = QueueTaskInterface::STATUS_PENDING;

    /**
     * The task being run on a worker.
     */
    const RUNNING = QueueTaskInterface::STATUS_RUNNING;

    /**
     * The task returned a successful response.
     */
    const COMPLETE = QueueTaskInterface::STATUS_COMPLETE;

    /**
     * The task returned a failed response or an exception was caught.
     * There may be data within the response error field. The task will be retried.
     */
    const FAILED = QueueTaskInterface::STATUS_FAILED;

    /**
     * The task failed more than the allowed amount of times.
     * There may be data within the response error field but it will be historical.
     */
    const FAILED_LIMIT_EXCEEDED = QueueTaskInterface::STATUS_FAILED_LIMIT_EXCEEDED;

    /**
     * The task has been cancelled either before it started of during its runtime. Bear in
     * mind, that consumers are in full control, whether they want to cancel themselves or not.
     */
    const CANCELLED = 6;

    public static function getUnsettledStatuses(): array
    {
        return [
            self::PENDING,
            self::RUNNING,
            self::FAILED,
        ];
    }

    public static function getSettledStatuses(): array
    {
        return [
            self::COMPLETE,
            self::FAILED_LIMIT_EXCEEDED,
            self::CANCELLED,
        ];
    }
}
