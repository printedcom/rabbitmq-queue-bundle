<?php

namespace Printed\Bundle\Queue\Helper;

use Printed\Bundle\Queue\EntityInterface\QueueTaskInterface;
use Printed\Bundle\Queue\Queue\AbstractQueuePayload;

class QueueTaskHelper
{
    /**
     * @param QueueTaskInterface $task
     * @return AbstractQueuePayload
     */
    public function getPayload(QueueTaskInterface $task): AbstractQueuePayload
    {
        $class = $task->getPayloadClass();
        return new $class($task->getPayload());
    }
}
