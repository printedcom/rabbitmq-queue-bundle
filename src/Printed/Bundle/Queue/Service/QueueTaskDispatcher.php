<?php

namespace Printed\Bundle\Queue\Service;

use Printed\Bundle\Queue\Entity\QueueTask;
use Printed\Bundle\Queue\EntityInterface\QueueTaskInterface;
use Printed\Bundle\Queue\Exception\QueuePayloadValidationException;
use Printed\Bundle\Queue\Queue\AbstractQueuePayload;
use Printed\Bundle\Queue\ValueObject\QueueBundleOptions;

use Printed\Bundle\Queue\ValueObject\ScheduledQueueTask;
use Ramsey\Uuid\UuidFactory;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use Doctrine\ORM\EntityManager;

use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use Psr\Log\LoggerInterface;

/**
 * A wrapper class that handles the dispatching of queue payloads.
 */
class QueueTaskDispatcher
{
    /** @var EntityManager */
    protected $em;

    /** @var LoggerInterface */
    protected $logger;

    /** @var ValidatorInterface */
    protected $validator;

    /** @var UuidFactory */
    protected $uuidGenerator;

    /** @var ProducerInterface This must be a producer that uses the default RabbitMQ's "(AMQP default)" exchange. */
    protected $defaultRabbitMqProducer;

    /** @var array Of structure { [queuePayloadSplObjectHash: string]: ScheduledQueueTask; } */
    protected $payloadsDelayedUntilNextDoctrineFlush;

    /** @var bool Used to prevent dispatching the on-doctrine-flush payloads recursively */
    private $dispatchingOnDoctrineFlushPayloads;

    /**
     * {@inheritdoc}
     */
    public function __construct(
        EntityManager $em,
        LoggerInterface $logger,
        ValidatorInterface $validator,
        ProducerInterface $defaultRabbitMqProducer,
        UuidFactory $uuidGenerator
    ) {
        $this->em = $em;
        $this->logger = $logger;
        $this->validator = $validator;
        $this->payloadsDelayedUntilNextDoctrineFlush = [];
        $this->dispatchingOnDoctrineFlushPayloads = false;

        $this->defaultRabbitMqProducer = $defaultRabbitMqProducer;
        $this->uuidGenerator = $uuidGenerator;
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
            throw new \InvalidArgumentException("`preQueueTaskDispatchFn` must either be a callable or a null");
        }

        $task = new QueueTask;
        $task->setPublicId($this->uuidGenerator->uuid4());

        $task->setStatus(QueueTaskInterface::STATUS_PENDING);
        $task->setQueueName($payload::getQueueName());
        $task->setAttempts(0);

        $task->setPayloadClass(get_class($payload));
        $task->setPayload($payload->getProperties());

        $task->setCreatedDate(new \DateTime);

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
     * @param AbstractQueuePayload|callable $payloadOrPayloadCreatorFn This must not be a callable in the array form.
     *      The callable must return an instance of AbstractQueuePayload and require no arguments
     * @return ScheduledQueueTask
     */
    public function dispatchAfterNextEntityManagerFlush($payloadOrPayloadCreatorFn): ScheduledQueueTask
    {
        if (
            !$payloadOrPayloadCreatorFn instanceof AbstractQueuePayload
            && !is_callable($payloadOrPayloadCreatorFn)
        ) {
            throw new \InvalidArgumentException(sprintf(
                'Argument to `%s` function must either be an instance of `%s` or a callable',
                __METHOD__,
                AbstractQueuePayload::class
            ));
        }

        /*
         * Disallow "array" callables because I can't get object hashes of arrays.
         */
        if (is_array($payloadOrPayloadCreatorFn)) {
            throw new \InvalidArgumentException(sprintf(
                'Callables in the array form are not supported in `%s`.',
                __METHOD__
            ));
        }

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
    public function dispatchOnDoctrineFlushPayloads()
    {
        if (
            !$this->payloadsDelayedUntilNextDoctrineFlush
            || $this->dispatchingOnDoctrineFlushPayloads
        ) {
            return;
        }

        $this->dispatchingOnDoctrineFlushPayloads = true;

        foreach ($this->payloadsDelayedUntilNextDoctrineFlush as $scheduledQueueTask) {
            /** @var ScheduledQueueTask $scheduledQueueTask */

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

    private function throwIfPayloadInvalid(AbstractQueuePayload $payload)
    {
        $errors = $this->validator->validate($payload);

        if ($errors->count()) {
            throw new QueuePayloadValidationException((string) $errors);
        }
    }
}
