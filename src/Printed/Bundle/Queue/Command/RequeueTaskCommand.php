<?php

namespace Printed\Bundle\Queue\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use RabbitMq;

/**
 * {@inheritdoc}
 */
class RequeueTaskCommand extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('queue:requeue-task');
        $this->setDescription("Requeues a task. Should be used with great care (preferably never)");
        $this->setHelp('This command allows to perform dangerous stuff and is mostly useful only for debugging.');

        $this->addArgument('queue-task-id', InputArgument::REQUIRED, 'Queue task to requeue');
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if ($output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL) {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        }

        $logger = $this->container->get('logger');
        $queueTaskDispatcher = $this->container->get('printed.bundle.queue.service.queue_task_dispatcher');
        $queueTaskHelper = $this->container->get('printed.bundle.queue.helper.queue_task_helper');
        $queueTaskIdToRequeue = $input->getArgument('queue-task-id');

        $logger->info("Trying to requeue a queue task with id `{$queueTaskIdToRequeue}`");

        $task = $this->container->get('printed.bundle.queue.repository.queue_task')->find($queueTaskIdToRequeue);

        if (!$task) {
            throw new \RuntimeException("Couldn't find queue task with id: `{$queueTaskIdToRequeue}`");
        }

        $newTask = $queueTaskDispatcher->dispatch($queueTaskHelper->getPayload($task));

        $logger->info("Successfully requeued task. New task id: `{$newTask->getId()}`");
    }

}
