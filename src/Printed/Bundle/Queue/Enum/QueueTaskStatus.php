<?php

namespace Printed\Bundle\Queue\Enum;

class QueueTaskStatus
{
    /**
     * The task is pending execution and should be sitting in the queue already.
     */
    const PENDING = 1;

    /**
     * The task being run on a worker.
     */
    const RUNNING = 2;

    /**
     * The task returned a successful response.
     */
    const COMPLETE = 3;

    /**
     * The task returned a failed response or an exception was caught.
     * There may be data within the response error field. The task will be retried.
     */
    const FAILED = 4;

    /**
     * The task failed more than the allowed amount of times.
     * There may be data within the response error field but it will be historical.
     */
    const FAILED_LIMIT_EXCEEDED = 5;

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
