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
    /**
     * When payload is not defined then the $payloadCreatorFn is used to construct it just before the queue task is
     * dispatched (i.e. after the entity manager flush).
     *
     * That's impossible for both the payload and the payload creator function not to be set.
     *
     * @var AbstractQueuePayload|null
     */
    private $payload;

    /** @var callable|null */
    private $payloadCreatorFn;

    /** @var callable|null See QueueTaskDispatcher::dispatch() */
    private $preQueueTaskDispatchFn;

    /** @var QueueTaskInterface|null Defined, when the task is dispatched */
    private $queueTask;

    public function __construct(
        AbstractQueuePayload $payload = null,
        callable $payloadCreatorFn = null,
        QueueTaskInterface $queueTask = null
    ) {
        if (!$payload && !$payloadCreatorFn) {
            throw new \InvalidArgumentException(sprintf(
                "Can't construct `%s` without providing either the queue payload or the queue payload creator function",
                get_class()
            ));
        }

        $this->payload = $payload;
        $this->payloadCreatorFn = $payloadCreatorFn;
        $this->queueTask = $queueTask;
    }

    /**
     * @return AbstractQueuePayload|null
     */
    public function getPayload()
    {
        return $this->payload;
    }

    public function getPayloadOrThrow(): AbstractQueuePayload
    {
        if (!$this->payload) {
            throw new \RuntimeException("The queue payload isn't constructed yet. It will be after the final EntityManager flush");
        }

        return $this->payload;
    }

    /**
     * @return callable|null
     */
    public function getPreQueueTaskDispatchFn()
    {
        return $this->preQueueTaskDispatchFn;
    }

    public function setPreQueueTaskDispatchFn(callable $preQueueTaskDispatchFn = null)
    {
        $this->preQueueTaskDispatchFn = $preQueueTaskDispatchFn;
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

    /**
     * @internal Do not call this function.
     */
    public function constructAndGetPayload(): AbstractQueuePayload
    {
        if ($this->payload) {
            throw new \RuntimeException('Queue payload is already constructed');
        }

        if (!$this->payloadCreatorFn) {
            throw new \RuntimeException("Can't construct the queue payload because the payload creator function wasn't provided");
        }

        $this->payload = call_user_func($this->payloadCreatorFn);

        if (!$this->payload instanceof AbstractQueuePayload) {
            throw new \RuntimeException("Queue payload creator function didn't construct an instance of a queue payload");
        }

        return $this->payload;
    }
}
