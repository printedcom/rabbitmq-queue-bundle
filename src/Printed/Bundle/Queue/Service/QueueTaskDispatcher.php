<?php

namespace Printed\Bundle\Queue\Service;

use Printed\Bundle\Queue\Entity\QueueTask;
use Printed\Bundle\Queue\EntityInterface\QueueTaskInterface;
use Printed\Bundle\Queue\Exception\QueuePayloadValidationException;
use Printed\Bundle\Queue\Queue\AbstractQueuePayload;

use Printed\Bundle\Queue\ValueObject\ScheduledQueueTask;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;
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

    /** @var ContainerInterface */
    protected $container;

    /** @var Uuid */
    protected $uuidGenerator;

    /** @var ProducerInterface This must be a producer that uses the default RabbitMQ's "(AMQP default)" exchange. */
    protected $defaultRabbitMqProducer;

    /**
     * In short: in develop environment use empty string and for environments, that use the same rabbitmq server,
     * use a queue names prefix to fight name conflicts. Inform this bundle about the prefix using this
     * configuration variable.
     *
     * @deprecated Use rabbitmq vhost functionality to fight queue names' conflicts.
     *
     * @var string
     */
    protected $queueNamesPrefix;

    /**
     * @var array Of structure { [queuePayloadSplObjectHash: string]: ScheduledQueueTask; }
     */
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
        ContainerInterface $container
    ) {
        $this->em = $em;
        $this->logger = $logger;
        $this->validator = $validator;
        $this->container = $container;
        $this->payloadsDelayedUntilNextDoctrineFlush = [];
        $this->dispatchingOnDoctrineFlushPayloads = false;

        $this->uuidGenerator = $this->container->get('printed.bundle.queue.service.uuid');
        $this->defaultRabbitMqProducer = $this->container->get(
            $container->getParameter('rabbitmq-queue-bundle.default_rabbitmq_producer_name')
        );
        $this->queueNamesPrefix = $container->getParameter('rabbitmq-queue-bundle.queue_names_prefix');
    }

    /**
     * @param AbstractQueuePayload $payload
     *
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
        ], $options);

        if ($options['validatePayload']) {
            $this->throwIfPayloadInvalid($payload);
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

        $this->defaultRabbitMqProducer->publish(
            $task->getId(),
            $this->queueNamesPrefix . $payload::getQueueName()
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
     * @param AbstractQueuePayload $payload
     *
     * @return ScheduledQueueTask
     */
    public function dispatchAfterNextEntityManagerFlush(AbstractQueuePayload $payload): ScheduledQueueTask
    {
        $existingScheduledQueueTask = $this->payloadsDelayedUntilNextDoctrineFlush[spl_object_hash($payload)]
            ?? null;

        /*
         * Do not schedule duplicate payloads.
         */
        if ($existingScheduledQueueTask) {
            return $existingScheduledQueueTask;
        }

        $this->throwIfPayloadInvalid($payload);

        $scheduledQueueTask = new ScheduledQueueTask($payload);
        $this->payloadsDelayedUntilNextDoctrineFlush[spl_object_hash($payload)] = $scheduledQueueTask;

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

            $queueTask = $this->dispatch(
                $scheduledQueueTask->getPayload(),
                ['validatePayload' => false]
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
