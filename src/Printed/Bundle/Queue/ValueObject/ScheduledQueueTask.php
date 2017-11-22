<?php

namespace Printed\Bundle\Queue\ValueObject;

use Printed\Bundle\Queue\EntityInterface\QueueTaskInterface;
use Printed\Bundle\Queue\Queue\AbstractQueuePayload;

/**
 * A class, that holds a queue payload, that is dispatched at later point of time. Think of it
 * as of a Promise<QueueTaskInterface>.
 *
 * When exactly the actual task is dispatched, is dependant on the feature, that created the
 * instance of this class. See usages to get an idea.
 */
class ScheduledQueueTask
{
    /** @var AbstractQueuePayload */
    private $payload;

    /** @var QueueTaskInterface|null Defined, when the task is dispatched */
    private $queueTask;

    public function __construct(AbstractQueuePayload $payload, QueueTaskInterface $queueTask = null)
    {
        $this->payload = $payload;
        $this->queueTask = $queueTask;
    }

    public function getPayload(): AbstractQueuePayload
    {
        return $this->payload;
    }

    /**
     * @return QueueTaskInterface|null
     */
    public function getQueueTask()
    {
        return $this->queueTask;
    }

    /**
     * @return QueueTaskInterface
     */
    public function getQueueTaskOrThrow(): QueueTaskInterface
    {
        if (!$this->queueTask) {
            throw new \RuntimeException("Can't retrieve scheduled queue task, because it's not been dispatched yet.");
        }

        return $this->queueTask;
    }

    /**
     * @param QueueTaskInterface|null $queueTask
     */
    public function setQueueTask(QueueTaskInterface $queueTask = null)
    {
        $this->queueTask = $queueTask;
    }
}
