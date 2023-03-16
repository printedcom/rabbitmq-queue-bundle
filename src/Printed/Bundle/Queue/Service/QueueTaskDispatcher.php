<?php

declare(strict_types=1);

namespace Printed\Bundle\Queue\Service;

use Closure;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Printed\Bundle\Queue\Entity\QueueTask;
use Printed\Bundle\Queue\EntityInterface\QueueTaskInterface;
use Printed\Bundle\Queue\Enum\QueueTaskStatus;
use Printed\Bundle\Queue\Exception\QueuePayloadValidationException;
use Printed\Bundle\Queue\Queue\AbstractQueuePayload;

use Printed\Bundle\Queue\ValueObject\ScheduledQueueTask;
use Ramsey\Uuid\UuidFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use Psr\Log\LoggerInterface;

/**
 * A wrapper class that handles the dispatching of queue payloads.
 */
class QueueTaskDispatcher
{
    /** @var ScheduledQueueTask[]|array Of structure { [queuePayloadSplObjectHash: string]: ScheduledQueueTask; } */
    protected array $payloadsDelayedUntilNextDoctrineFlush = [];

    /** @var bool Used to prevent dispatching the on-doctrine-flush payloads recursively */
    private bool $dispatchingOnDoctrineFlushPayloads = false;

    /**
     * @param ProducerInterface $defaultRabbitMqProducer This must be a producer that uses the default RabbitMQ's "(AMQP default)" exchange.
     */
    public function __construct(
        protected EntityManagerInterface $em,
        protected LoggerInterface $logger,
        protected ValidatorInterface $validator,
        protected ProducerInterface $defaultRabbitMqProducer,
        protected UuidFactory $uuidGenerator
    ) {
    }

    /**
     * @param AbstractQueuePayload $payload
     * @param array $options
     * @return QueueTaskInterface
     */
    public function dispatch(AbstractQueuePayload $payload, array $options = []): QueueTaskInterface
    {
        $options = array_merge([
            /*
             * Set this to false, if the payload is already validated.
             */
            'validatePayload' => true,

            /*
             * The preQueueTaskDispatchFn is called after the newly created QueueTask entity is created (and flushed) but
             * immediately before it's dispatched to the queue server. This is an excellent moment for doing last-minute things
             * (e.g. saving the created QueueTask's id somewhere and flushing the db again) just before the task is handed to
             * to queue server, at which point race conditions start if you're not careful.
             *
             * The preQueueTaskDispatchFn signature should be: (queueTask: QueueTask) => void
             */
            'preQueueTaskDispatchFn' => null,
        ], $options);

        if ($options['validatePayload']) {
            $this->throwIfPayloadInvalid($payload);
        }

        if (
            $options['preQueueTaskDispatchFn']
            && !is_callable($options['preQueueTaskDispatchFn'])
        ) {
            throw new InvalidArgumentException("`preQueueTaskDispatchFn` must either be a callable or a null");
        }

        $task = new QueueTask;
        $task->setPublicId($this->uuidGenerator->uuid4()->toString());

        $task->setStatus(QueueTaskStatus::PENDING);
        $task->setQueueName($payload::getQueueName());
        $task->setAttempts(0);

        $task->setPayloadClass(get_class($payload));
        $task->setPayload($payload->getProperties());

        $task->setCreatedDate(new DateTime);

        $this->em->persist($task);
        $this->em->flush($task);

        if ($options['preQueueTaskDispatchFn']) {
            call_user_func($options['preQueueTaskDispatchFn'], $task);
        }

        $this->defaultRabbitMqProducer->publish(
            $task->getId(),
            $payload::getQueueName(),
            $payload->getQueueMessageProperties()
        );

        $this->logger->info(
            sprintf(
                'Dispatched "%s" with task "%s"',
                $task->getQueueName(),
                $task->getId()
            )
        );

        return $task;

    }

    /**
     * Dispatch the payload, but only after the soonest Doctrine flush.
     *
     * This is helpful, when you need to make sure the consumer doesn't start before your app
     * flushes some relevant changes to the database.
     *
     * It's also the only safe way to dispatch queue tasks from some of the doctrine listeners
     * (e.g. postUpdate) in your app.
     *
     * If you can't create an instance of queue payload before the Doctrine flush (e.g. because you need some ids to pass
     * in the payload), then pass a "payload creator function", which is invoked after final Doctrine flush but before
     * the queue task dispatch.
     *
     * @param Closure|AbstractQueuePayload $payloadOrPayloadCreatorFn This must not be a callable in the array form.
     *      The callable must return an instance of AbstractQueuePayload and require no arguments
     *
     * @return ScheduledQueueTask
     */
    public function dispatchAfterNextEntityManagerFlush(Closure|AbstractQueuePayload $payloadOrPayloadCreatorFn): ScheduledQueueTask
    {
        $existingScheduledQueueTask = $this->payloadsDelayedUntilNextDoctrineFlush[spl_object_hash($payloadOrPayloadCreatorFn)]
            ?? null;

        /*
         * Do not schedule duplicate payloads.
         */
        if ($existingScheduledQueueTask) {
            return $existingScheduledQueueTask;
        }

        /*
         * Validate instantiated payloads now. The "delayed" payloads are validated just before they are dispatched.
         */
        if ($payloadOrPayloadCreatorFn instanceof AbstractQueuePayload) {
            $this->throwIfPayloadInvalid($payloadOrPayloadCreatorFn);
        }

        $scheduledQueueTask = new ScheduledQueueTask(
            $payloadOrPayloadCreatorFn instanceof AbstractQueuePayload ? $payloadOrPayloadCreatorFn : null,
            is_callable($payloadOrPayloadCreatorFn) ? $payloadOrPayloadCreatorFn : null
        );

        $this->payloadsDelayedUntilNextDoctrineFlush[spl_object_hash($payloadOrPayloadCreatorFn)] = $scheduledQueueTask;

        return $scheduledQueueTask;
    }

    /**
     * @internal Don't use this method.
     */
    public function dispatchOnDoctrineFlushPayloads(): void
    {
        if (
            !$this->payloadsDelayedUntilNextDoctrineFlush
            || $this->dispatchingOnDoctrineFlushPayloads
        ) {
            return;
        }

        $this->dispatchingOnDoctrineFlushPayloads = true;

        foreach ($this->payloadsDelayedUntilNextDoctrineFlush as $scheduledQueueTask) {
            $payload = $scheduledQueueTask->getPayload();
            $isPayloadConstructedLate = false;

            if (!$payload) {
                $payload = $scheduledQueueTask->constructAndGetPayload();

                /*
                 * I don't like how the payload validation may fail after doctrine flush, but well..
                 */
                $isPayloadConstructedLate = true;
            }

            $queueTask = $this->dispatch(
                $payload,
                [
                    'validatePayload' => $isPayloadConstructedLate,
                    'preQueueTaskDispatchFn' => $scheduledQueueTask->getPreQueueTaskDispatchFn(),
                ]
            );

            $scheduledQueueTask->setQueueTask($queueTask);
        }

        $this->payloadsDelayedUntilNextDoctrineFlush = [];

        $this->dispatchingOnDoctrineFlushPayloads = false;
    }

    private function throwIfPayloadInvalid(AbstractQueuePayload $payload): void
    {
        $errors = $this->validator->validate($payload);

        if ($errors->count()) {
            throw new QueuePayloadValidationException((string) $errors);
        }
    }
}
