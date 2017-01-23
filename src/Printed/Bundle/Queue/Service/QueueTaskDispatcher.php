<?php

namespace Printed\Bundle\Queue\Service;

use Printed\Bundle\Queue\Entity\QueueTask;
use Printed\Bundle\Queue\EntityInterface\QueueTaskInterface;
use Printed\Bundle\Queue\Exception\QueuePayloadValidationException;
use Printed\Bundle\Queue\Queue\AbstractQueuePayload;

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

        $this->uuidGenerator = $this->container->get('printed.bundle.queue.service.uuid');
        $this->defaultRabbitMqProducer = $this->container->get(
            $container->getParameter('rabbitmq-queue-bundle.default_rabbitmq_producer_name')
        );
    }

    /**
     * @param AbstractQueuePayload $payload
     *
     * @return QueueTaskInterface
     */
    public function dispatch(AbstractQueuePayload $payload): QueueTaskInterface
    {

        //  Validate the payload is good to serialise.
        $errors = $this->validator->validate($payload);
        if ($errors->count()) {
            throw new QueuePayloadValidationException((string) $errors);
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

        $this->defaultRabbitMqProducer->publish($task->getId(), $payload::getQueueName());

        $this->logger->info(
            sprintf(
                'Dispatched "%s" with task "%s"',
                $task->getQueueName(),
                $task->getId()
            )
        );

        return $task;

    }

}
