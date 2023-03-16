<?php

declare(strict_types=1);

namespace Printed\Bundle\Queue\Command;

use Printed\Bundle\Queue\Helper\QueueTaskHelper;
use Printed\Bundle\Queue\Repository\QueueTaskRepository;
use Printed\Bundle\Queue\Service\QueueTaskDispatcher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use RabbitMq;

/**
 * {@inheritdoc}
 */
class RequeueTaskCommand extends Command
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly QueueTaskDispatcher $queueTaskDispatcher,
        private readonly QueueTaskHelper $queueTaskHelper,
        private readonly QueueTaskRepository $queueTaskRepository
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('queue:requeue-task');
        $this->setDescription("Re-queues a task. Should be used with great care (preferably never)");
        $this->setHelp('This command allows to perform dangerous stuff and is mostly useful only for debugging.');

        $this->addArgument('queue-task-id', InputArgument::REQUIRED, 'Queue task to requeue');
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL) {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        }

        $queueTaskIdToRequeue = $input->getArgument('queue-task-id');

        $this->logger->info("Trying to requeue a queue task with id `{$queueTaskIdToRequeue}`");

        $task = $this->queueTaskRepository->find($queueTaskIdToRequeue);

        if (!$task) {
            throw new \RuntimeException("Couldn't find queue task with id: `{$queueTaskIdToRequeue}`");
        }

        $newTask = $this->queueTaskDispatcher->dispatch($this->queueTaskHelper->getPayload($task));

        $this->logger->info("Successfully requeued task. New task id: `{$newTask->getId()}`");

        return static::SUCCESS;
    }
}
