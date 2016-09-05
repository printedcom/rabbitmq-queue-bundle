<?php

namespace Printed\Bundle\Queue\Service;

use Printed\Bundle\Queue\Entity\QueueTask;
use Printed\Bundle\Queue\EntityInterface\QueueTaskInterface;
use Printed\Bundle\Queue\Exception\MissingQueueException;
use Printed\Bundle\Queue\Exception\QueuePayloadValidationException;
use Printed\Bundle\Queue\Queue\AbstractQueuePayload;

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

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @var ContainerInterface
     */
    protected $container;

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

        $service = sprintf('old_sound_rabbit_mq.%s_producer', $payload::getExchangeName());
        if (!$this->container->has($service)) {
            throw new MissingQueueException(
                sprintf(
                    'The producer "%s" is invalid, tried for service "%s"',
                    $payload::getExchangeName(),
                    $service
                )
            );
        }

        $task = new QueueTask;
        $task->setPublicId($this->container->get('printed.bundle.queue.service.uuid')->uuid4());

        $task->setStatus(QueueTaskInterface::STATUS_PENDING);
        $task->setExchange($payload::getExchangeName());
        $task->setAttempts(0);

        $task->setPayloadClass(get_class($payload));
        $task->setPayload($payload->getProperties());

        $task->setCreatedDate(new \DateTime);

        $this->em->persist($task);
        $this->em->flush($task);

        /** @var ProducerInterface $service */
        $service = $this->container->get($service);
        $service->publish($task->getId());

        $this->logger->info(
            sprintf(
                'Dispatched "%s" with task "%s"',
                $task->getExchange(),
                $task->getId()
            )
        );

        return $task;

    }

}
