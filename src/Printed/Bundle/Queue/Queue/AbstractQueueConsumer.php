<?php

namespace Printed\Bundle\Queue\Queue;

use Printed\Bundle\Queue\EntityInterface\QueueTaskInterface;
use Printed\Bundle\Queue\Exception\Consumer\QueueFatalErrorException;
use Printed\Bundle\Queue\Repository\QueueTaskRepository;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Doctrine\ORM\EntityManager;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * An abstract representation of a queue worker (or consumer in RabbitMQ's case).
 *
 * This method extends the functionality of the {@link ConsumerInterface} to allow for tasks to be picked up
 * from the message body and tracked in the database.
 */
abstract class AbstractQueueConsumer implements ConsumerInterface
{

    /**
     * Return constant for a complete task, this removes it from the queue and marks the task as complete
     * in the database.
     */
    const TASK_COMPLETE = true;

    /**
     * Return constant for a failed task, this pops the task back in to the queue.
     */
    const TASK_FAILED = false;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @var QueueTaskRepository
     */
    protected $repository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var AMQPMessage
     */
    protected $message;

    /**
     * @var QueueTaskInterface
     */
    protected $task;

    /**
     * {@inheritdoc}
     *
     * @param EntityManager $em
     * @param QueueTaskRepository $repository
     * @param LoggerInterface $logger
     * @param ContainerInterface $container
     */
    public function __construct(
        EntityManager $em,
        ValidatorInterface $validator,
        QueueTaskRepository $repository,
        LoggerInterface $logger,
        ContainerInterface $container
    ) {
        $this->em = $em;
        $this->validator = $validator;
        $this->repository = $repository;
        $this->logger = $logger;
        $this->container = $container;
    }

    /**
     * Handle the payload.
     *
     * Returning here is important, it must be a boolean and the values represent the queue tasks completion.
     * If true then the queue task is considered complete, if false it will be added to the queue again.
     *
     * To make this process more verbose the following constants have been made:
     * * {@link TASK_FAILED}
     * * {@link TASK_COMPLETE}
     *
     * @param AbstractQueuePayload $payload
     *
     * @return bool
     *
     * @throws QueueFatalErrorException
     */
    abstract public function run(AbstractQueuePayload $payload): bool;

    /**
     * Return the number of attempts the task will be given before it is marked as failed and dropped from
     * the queue. When hit the queue will be marked in the database with failed because of limit.
     *
     * @return int
     */
    public function getAttemptLimit(): int
    {
        return 10;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function getLoggerContext(array $data = []): array
    {
        return array_merge(
            [
                'time' => time(),
                'consumer' => get_called_class()
            ],
            $data
        );
    }

    /**
     * A helper method to quickly dispatch queue payloads.
     *
     * @param AbstractQueuePayload $payload
     */
    public function dispatchQueuePayload(AbstractQueuePayload $payload)
    {
        $queue = $this->container->get('printed.bundle.queue.service.queue_task_dispatcher');
        $queue->dispatch($payload);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(AMQPMessage $msg)
    {
        $this->message = $msg;

        //  Hold off execution if in maintenance
        $maintenance = $this->container->get('printed.bundle.queue.service.queue_maintenance');
        if ($maintenance->isEnabled()) {
            $this->logger->debug('Accepting no more work, maintenance mode has been enabled');

            //  Exit code 0 will tell supervisor that this script has exited intentionally.
            //  Too restart this process you need to restart supervisor.
            exit(0);

        }

        // @codingStandardsIgnoreStart
        $id = $msg->delivery_info['delivery_tag'];
        $exchange = $msg->delivery_info['exchange'];
        $redelivered = $msg->delivery_info['redelivered'];
        // @codingStandardsIgnoreEnd

        //  Attempt to retrieve the task from the database.
        //  The task ID was given as the message body.
        $this->task = $this->repository->find($this->message->body);

        //  If the task doesn't exist then we have a problem.
        //  We can throw a fit here but no-one will hear it, for now critical log and get out.
        //  Ideally we would email at this point, but there is nothing to email about.
        if (is_null($this->task)) {
            $this->logger->emergency(sprintf('Invalid task "%s" given to "%s"', $this->message->body, $exchange));
            //  Instead of using failed we use complete, this prevents the job being re-queued.
            return self::TASK_COMPLETE;
        }

        //  If the task has exceeded limit we just pop out of the queue.
        if ($this->validateTaskAttempts()) {
            //  Instead of using failed we use complete, this prevents the job being re-queued.
            return self::TASK_COMPLETE;
        }

        $this->updateTaskRunning();
        $payload = $this->container->get('printed.bundle.queue.helper.queue_task_helper')->getPayload($this->task);

        //  Attempting to log enough information to make debugging easy.
        $this->logger->info(
            sprintf(
                'Consumer "%s" %s task "%s" (payload "%s")',
                $this->task->getQueueName(),
                $redelivered ? 're-attempting' : 'attempting',
                $this->task->getId(),
                json_encode($this->task->getPayload())
            ),
            [
                'rabbitmq_id' => $id,
                'rabbitmq_redelivered' => $redelivered,
                'timestamp' => $this->task->getStartedDate()->getTimestamp(),
                'attempts' => $this->task->getAttempts()
            ]
        );

        try {

            $errors = $this->validator->validate($payload);
            if ($errors->count()) {
                throw new QueueFatalErrorException((string) $errors);
            }

            //  Handle the job.
            $status = $this->run($payload);

        } catch (QueueFatalErrorException $exception) {
            $status = false;

            //  Log the child exception in the database, if dev provided it.
            if ($exception->getPrevious()) {
                $exception = $exception->getPrevious();
            }

            //  Enforce that the limit on the task is maxed, this will prevent the task from running again on the next
            //  iteration, and because we are good we can log this exception against the task record.
            $this->task->setAttempts($this->getAttemptLimit());
            $this->task->setResponseError(get_class($exception), $exception->getMessage(), $exception->getTrace());
            $this->logger->error($exception->getMessage(), $this->getLoggerContext());

        } catch (\Throwable $exception) {
            $status = false;

            //  Its good to know why a task failed, in this case we can log the exception.
            $this->task->setResponseError(get_class($exception), $exception->getMessage(), $exception->getTrace());
            $this->logger->emergency(
                sprintf(
                    "%s\n%s\n\n%s",
                    get_class($exception),
                    $exception->getMessage(),
                    $exception->getTraceAsString()
                )
            );

        }

        if ($status) {
            $this->updateTaskComplete();
        } else {
            $this->updateTaskFailed();
        }

        $this->em->persist($this->task);
        $this->em->flush($this->task);

        //  If we have an exception then we should throw it again, we have done all the logging we need.
        //  This will fall back to Symfony to handle and the process with die.
        if (isset($exception)) {
            throw $exception;
        }

        return $status;

    }

    /**
     * Check the task has attempts left, return true to remove the job.
     *
     * @return bool
     */
    private function validateTaskAttempts(): bool
    {

        if ($this->task->getAttempts() < $this->getAttemptLimit()) {
            return false;
        }

        //  First a spot of logging, make sure there is an error.
        $this->logger->error(
            sprintf(
                'The task "%s" exceeded the max attempt limit ("%s") for the consumer "%s"',
                $this->task->getId(),
                $this->getAttemptLimit(),
                $this->task->getQueueName()
            )
        );

        $this->task->setStatus(QueueTaskInterface::STATUS_FAILED_LIMIT_EXCEEDED);
        $this->task->setCompletedDate(new \DateTime);

        $this->em->persist($this->task);
        $this->em->flush($this->task);

        return true;

    }

    private function updateTaskRunning()
    {

        //  Increment the task attempts count.
        $this->task->setAttempts($this->task->getAttempts() + 1);

        //  Store the process ID so we can target failing workers in debug.
        $this->task->setProcessId(getmypid());

        //  Mark the task as running but also set the running date.
        $this->task->setStatus(QueueTaskInterface::STATUS_RUNNING);
        $this->task->setStartedDate(new \DateTime);

        $this->em->persist($this->task);
        $this->em->flush($this->task);

    }

    private function updateTaskComplete()
    {
        $this->task->setStatus(QueueTaskInterface::STATUS_COMPLETE);
        $this->task->setCompletedDate(new \DateTime);

        $this->logger->info(
            sprintf(
                'Consumer "%s" for task "%s" completed',
                $this->task->getQueueName(),
                $this->task->getId()
            )
        );

    }

    private function updateTaskFailed()
    {
        $this->task->setStatus(QueueTaskInterface::STATUS_FAILED);
        $this->task->setCompletedDate(new \DateTime);

        $this->logger->error(
            sprintf(
                'Consumer "%s" for task "%s" failed',
                $this->task->getQueueName(),
                $this->task->getId()
            )
        );

    }

}
