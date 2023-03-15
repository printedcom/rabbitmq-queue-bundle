<?php

namespace Printed\Bundle\Queue\Queue;

use Printed\Bundle\Queue\EntityInterface\QueueTaskInterface;
use Printed\Bundle\Queue\Enum\QueueTaskStatus;
use Printed\Bundle\Queue\Exception\Consumer\QueueFatalErrorException;
use Printed\Bundle\Queue\Exception\QueueTaskCancellationException;
use Printed\Bundle\Queue\Helper\QueueTaskHelper;
use Printed\Bundle\Queue\Repository\QueueTaskRepository;
use Printed\Bundle\Queue\Service\NewDeploymentsDetector;

use Printed\Bundle\Queue\Service\QueueMaintenance;
use Printed\Bundle\Queue\Service\ServiceContainerParameters;
use Printed\Bundle\Queue\ValueObject\QueueBundleOptions;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * An abstract representation of a queue worker (or consumer in RabbitMQ's case).
 *
 * This method extends the functionality of the {@link ConsumerInterface} to allow for tasks to be picked up
 * from the message body and tracked in the database.
 */
abstract class AbstractQueueConsumer implements ConsumerInterface, ServiceSubscriberInterface
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

    /** @var EntityManager */
    protected $em;

    /** @var ValidatorInterface */
    protected $validator;

    /** @var QueueTaskRepository */
    protected $repository;

    /** @var LoggerInterface */
    protected $logger;

    /**
     * Services requested by ::getSubscribedServices() are available in this container.
     *
     * @var ContainerInterface
     */
    protected $locator;

    /** @var ServiceContainerParameters */
    protected $containerParameters;

    /** @var AMQPMessage */
    protected $message;

    /** @var QueueTaskInterface */
    protected $task;

    /** @var QueueBundleOptions */
    private $queueBundleOptions;

    /** @var NewDeploymentsDetector */
    private $newDeploymentsDetector;

    /**
     * @var string
     * @see NewDeploymentsDetector::getCurrentDeploymentStamp()
     */
    private $startUpDeploymentStamp;

    /** @var \DateTime The time the consumer were constructed. */
    private $startUpDateTime;

    public function __construct(
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        LoggerInterface $logger,
        ContainerInterface $locator,
        ServiceContainerParameters $containerParameters
    ) {
        $this->em = $em;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->locator = $locator;
        $this->containerParameters = $containerParameters;
        $this->repository = $locator->get('printed.bundle.queue.repository.queue_task');
        $this->queueBundleOptions = $locator->get('printed.bundle.queue.service.queue_bundle_options');

        $this->newDeploymentsDetector = $locator->get('printed.bundle.queue.service.new_deployments_detector');
        $this->startUpDeploymentStamp = $this->newDeploymentsDetector->getCurrentDeploymentStamp();

        $this->startUpDateTime = new \DateTime();
    }

    public static function getSubscribedServices(): array
    {
        /*
         * Dependencies for this class are required this way instead of injecting them to the constructor, so that:
         *
         * 1. The subclasses overriding this method don't forget to merge the parent deps.
         * 2. More deps can be added without introducing a breaking change to the the constructor's params.
         */
        return [
            'printed.bundle.queue.service.queue_bundle_options' => QueueBundleOptions::class,
            'printed.bundle.queue.repository.queue_task' => QueueTaskRepository::class,
            'printed.bundle.queue.service.new_deployments_detector' => NewDeploymentsDetector::class,
            'printed.bundle.queue.service.queue_maintenance' => QueueMaintenance::class,
            'printed.bundle.queue.helper.queue_task_helper' => QueueTaskHelper::class,
            'printed.bundle.queue.service.client.application_entity_manager' => '?'.EntityManagerInterface::class,
        ];
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
        return 1;
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
        $queue = $this->locator->get('printed.bundle.queue.service.queue_task_dispatcher');
        $queue->dispatch($payload);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(AMQPMessage $msg)
    {
        $this->message = $msg;

        //  Hold off execution if in maintenance
        $maintenance = $this->locator->get('printed.bundle.queue.service.queue_maintenance');
        if ($maintenance->isEnabled()) {
            $this->logger->notice('Accepting no more work, maintenance mode has been enabled');

            //  Exit code 0 will tell supervisor that this script has exited intentionally.
            //  To restart this process you need to restart supervisor.
            exit(0);

        }

        //  Exit, if there's a newer code deployed
        if (!$this->newDeploymentsDetector->isDeploymentStampTheCurrentOne($this->startUpDeploymentStamp)) {
            $this->logger->notice("The consumer exits, because it's running using an old code.");

            exit($this->queueBundleOptions->get('consumer_exit_code__running_using_old_code'));
        }

        $this->clearKnownEntityManagers();

        // @codingStandardsIgnoreStart
        $id = $msg->delivery_info['delivery_tag'];
        $queueName = $msg->delivery_info['routing_key'];
        $redelivered = $msg->delivery_info['redelivered'];
        // @codingStandardsIgnoreEnd

        //  Attempt to retrieve the task from the database.
        //  The task ID was given as the message body.
        $this->task = $this->repository->find($this->message->body);

        //  If the task doesn't exist then we have a problem.
        //  We can throw a fit here but no-one will hear it, for now critical log and get out.
        //  Ideally we would email at this point, but there is nothing to email about.
        if (is_null($this->task)) {
            $this->logger->emergency(sprintf('Invalid task "%s" given to "%s"', $this->message->body, $queueName));
            //  Instead of using failed we use complete, this prevents the job being re-queued.
            return self::TASK_COMPLETE;
        }

        //  If the task has exceeded limit we just pop out of the queue.
        if ($this->validateTaskAttempts()) {
            //  Instead of using failed we use complete, this prevents the job being re-queued.
            return self::TASK_COMPLETE;
        }

        $this->updateTaskRunning();
        $payload = $this->locator->get('printed.bundle.queue.helper.queue_task_helper')->getPayload($this->task);

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

            $this->throwTaskCancellationExceptionIfCancellationRequested();

            //  Handle the job.
            $queueTaskStatus = $this->run($payload) ? QueueTaskStatus::COMPLETE : QueueTaskStatus::FAILED;

        } catch (QueueFatalErrorException $exception) {
            $queueTaskStatus = QueueTaskStatus::FAILED;

            //  Log the child exception in the database, if dev provided it.
            if ($exception->getPrevious()) {
                $exception = $exception->getPrevious();
            }

            //  Enforce that the limit on the task is maxed, this will prevent the task from running again on the next
            //  iteration, and because we are good we can log this exception against the task record.
            $this->task->setAttempts($this->getAttemptLimit());
            $this->task->setResponseError(get_class($exception), $exception->getMessage(), $exception->getTrace());
            $this->logger->error($exception->getMessage(), $this->getLoggerContext());

        } catch (QueueTaskCancellationException $exception) {
            $queueTaskStatus = QueueTaskStatus::CANCELLED;

            $exception = null;

        } catch (\Throwable $exception) {
            $queueTaskStatus = QueueTaskStatus::FAILED;

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

        switch ($queueTaskStatus) {
            case QueueTaskStatus::COMPLETE:
                $this->updateTaskComplete();
                break;

            case QueueTaskStatus::FAILED:
                $this->updateTaskFailed();
                break;

            case QueueTaskStatus::CANCELLED:
                $this->updateTaskCancelled();
                break;

            default:
                throw new \RuntimeException("Unexpected queue task status: `{$queueTaskStatus}`");
        }

        $this->em->persist($this->task);
        $this->em->flush($this->task);

        //  Trigger relevant lifecycle events if necessary.
        if (QueueTaskStatus::CANCELLED === $queueTaskStatus) {
            $this->onTaskCancelled($payload);
        }

        if (isset($exception)) {
            $isPermanentFailure = $this->task->getAttempts() >= $this->getAttemptLimit();

            $this->onTaskAbortedByException($payload, $exception, $isPermanentFailure);
        }

        //  If we have an exception then we should throw it again, we have done all the logging we need.
        //  This will fall back to Symfony to handle and the process with die.
        if (isset($exception)) {
            $this->sleepUntilMinimalRuntimeIsMet();

            throw $exception;
        }

        //  Translate status failed to "false" and every other to "true", to tell rabbitmq to requeue
        //  only the failed tasks.
        return $queueTaskStatus === QueueTaskStatus::FAILED
            ? self::TASK_FAILED
            : self::TASK_COMPLETE;

    }

    /**
     * Override this method to react on this task's cancellation.
     *
     * Be careful, when flushing the entity manager in this method, because you don't know at which point the consumer
     * has been cancelled. The entity manager might have some not flushed changes, which you probably don't want to
     * be flushed at this point. In other words, either don't use the entity manager at all, or flush only the entities
     * you really intend to flush via `EntityManager::flush($entityIIntendToFlush);`
     *
     * @param AbstractQueuePayload $payload
     * @return void
     */
    protected function onTaskCancelled(AbstractQueuePayload $payload)
    {
    }

    /**
     * Override this method to react on this task's being aborted by an exception.
     *
     * Read about the entity manager's usage caveats in the docblock for ::onTaskCancelled().
     *
     * @param AbstractQueuePayload $payload
     * @param \Throwable $exception
     * @param bool $isPermanentFailure
     * @return void
     */
    protected function onTaskAbortedByException(AbstractQueuePayload $payload, \Throwable $exception, bool $isPermanentFailure)
    {
    }

    /**
     * Run this from your consumer to update the task's completion percentage without flushing
     * anything else into database.
     *
     * @param int $completionPercentage
     */
    protected function setTaskCompletionPercentage(int $completionPercentage)
    {
        $this->task->setCompletionPercentage($completionPercentage);

        $taskClassName = get_class($this->task);

        $query = $this->em->createQuery("
            UPDATE {$taskClassName} t 
            SET t.completionPercentage = :completionPercentage
            WHERE t.id = :taskId
        ");

        $query->setParameters([
            'completionPercentage' => $completionPercentage,
            'taskId' => $this->task->getId(),
        ]);

        $query->getResult();
    }

    /**
     * Run this from your consumer at different points to cancel the tasks' processing due
     * to the fact, that a cancellation has been requested.
     *
     * Naturally, you're not forced to cancel your consumer if you don't want to or when
     * you're past "the point of no return".
     */
    protected function throwTaskCancellationExceptionIfCancellationRequested()
    {
        $this->em->refresh($this->task);

        if ($this->task->isCancellationRequested()) {
            throw new QueueTaskCancellationException("Queue task `{$this->task->getId()}` is being cancelled.");
        }
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

        $this->task->setStatus(QueueTaskStatus::FAILED_LIMIT_EXCEEDED);
        $this->task->setCompletedDate(new \DateTime);

        $this->em->persist($this->task);
        $this->em->flush($this->task);

        return true;

    }

    private function updateTaskRunning()
    {

        //  Increment the task attempts count.
        $this->task->setAttempts($this->task->getAttempts() + 1);

        //  Reset completion percentage in case this task is being retried.
        $this->task->setCompletionPercentage(0);

        //  Store the process ID so we can target failing workers in debug.
        $this->task->setProcessId(getmypid());

        //  Mark the task as running but also set the running date.
        $this->task->setStatus(QueueTaskStatus::RUNNING);
        $this->task->setStartedDate(new \DateTime);

        $this->em->persist($this->task);
        $this->em->flush($this->task);

    }

    private function updateTaskCancelled()
    {
        $this->task->setStatus(QueueTaskStatus::CANCELLED);
        $this->task->setCompletedDate(new \DateTime);

        $this->logger->info(
            sprintf(
                'Consumer "%s" for task "%s" cancelled',
                $this->task->getQueueName(),
                $this->task->getId()
            )
        );

        $this->em->persist($this->task);
        $this->em->flush($this->task);

    }

    private function updateTaskComplete()
    {
        $this->task->setStatus(QueueTaskStatus::COMPLETE);
        $this->task->setCompletionPercentage(100);
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
        $this->task->setStatus(QueueTaskStatus::FAILED);
        $this->task->setCompletedDate(new \DateTime);

        $this->logger->error(
            sprintf(
                'Consumer "%s" for task "%s" failed',
                $this->task->getQueueName(),
                $this->task->getId()
            )
        );

    }

    /**
     * Clear all known entity managers, so entities are not cached between consumers' runs.
     */
    private function clearKnownEntityManagers()
    {
        $this->em->clear();

        /*
         * Clear the application's doctrine entity manager, if defined.
         */
        $applicationEntityManagerServiceName = 'printed.bundle.queue.service.client.application_entity_manager';
        if ($this->locator->has($applicationEntityManagerServiceName)) {
            /** @var EntityManagerInterface $applicationEntityManager */
            $applicationEntityManager = $this->locator->get($applicationEntityManagerServiceName);

            $applicationEntityManager->clear();
        }
    }

    /**
     * Sleep until minimal runtime is met.
     *
     * Ensure the consumer has been running for at least the amount of seconds configured, so tools like supervisord
     * don't assume that the consumer didn't even start, if it manages to start and fail too quickly.
     */
    private function sleepUntilMinimalRuntimeIsMet()
    {
        $minimalRuntimeInSeconds = $this->queueBundleOptions->get('minimal_runtime_in_seconds_on_consumer_exception');

        if (null === $minimalRuntimeInSeconds) {
            return;
        }
        $minimalRuntimeInSeconds = (int) $minimalRuntimeInSeconds;

        //  Forcefully add 1 second, because microseconds are not respected in this routine, so if the time since start
        //  is 0.900s and the time of failure is 1.100, then the time difference in seconds in 1s, but in reality it
        //  only elapsed 0.2s. On the other hand, if the times are 0.100 and 1.900, the adding of 1 second is redundant,
        //  because the time difference is already more than 1s. It's better to be safe than sorry, I guess.
        $minimalRuntimeInSeconds += 1;

        $secondsSinceConsumerStart = (new \DateTime())->getTimeStamp() - $this->startUpDateTime->getTimeStamp();
        $timeToSleepToMeetMinimalRuntime = $minimalRuntimeInSeconds - $secondsSinceConsumerStart;

        if ($timeToSleepToMeetMinimalRuntime <= 0) {
            return;
        }

        sleep($timeToSleepToMeetMinimalRuntime);
    }
}
