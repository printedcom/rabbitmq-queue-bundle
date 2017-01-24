<?php

namespace Printed\Bundle\Queue\Command;

use Printed\Bundle\Queue\EntityInterface\QueueTaskInterface;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * {@inheritdoc}
 */
class MaintenanceWaitForRunningCommand extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('queue:maintenance:wait');
        $this->setDescription('Wait for running tasks to complete and exit');
        $this->setHelp('Check for running queue tasks and output their details, if none then the command exits');

        $this->addOption('refresh', 'r', InputArgument::OPTIONAL, 'Refresh every x second(s)', 3);

    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $repository = $this->container->get('printed.bundle.queue.repository.queue_task');
        $output->writeln('<info>Monitoring running tasks</info>');

        //  Simply notify that maintenance mode is enabled if it is.
        $maintenance = $this->container->get('printed.bundle.queue.service.queue_maintenance');
        if ($maintenance->isEnabled()) {
            $output->writeln('<comment>Maintenance mode is enabled</comment>');
        }

        //  Get the refresh time, this is 3 by default.
        $refresh = (integer) $input->getOption('refresh');

        $table = new Table($output);
        $table->setHeaders(['Exchange', 'Tasks']);

        while (true) {

            //  Find all tasks with running status.
            $tasks = $repository->findBy(['status' => QueueTaskInterface::STATUS_RUNNING]);
            $count = count($tasks);

            //  If there are none we can exit the command.
            if ($count === 0) {
                $output->writeln(sprintf('<info>There are no tasks in running state!</info>'));
                return;
            }

            $table->setRows([]);
            foreach ($this->groupTasksByExchange($tasks) as $exchange => $ids) {
                sort($ids);
                $table->addRow([$exchange, implode(', ', $ids)]);
            }

            $output->writeln(sprintf('<comment>  %s task(s) running</comment>', $count));
            $table->render();

            $output->writeln(sprintf('<comment>Next update in %s second(s) ..</comment>', $refresh));
            $output->writeln('');

            sleep($refresh);

        }

    }

    /**
     * @param QueueTaskInterface[] $tasks
     *
     * @return array
     */
    protected function groupTasksByExchange(array $tasks): array
    {
        $exchanges = [];

        foreach ($tasks as $task) {

            if (!isset($exchanges[$task->getQueueName()])) {
                $exchanges[$task->getQueueName()] = [];
            }

            $exchanges[$task->getQueueName()][] = $task->getId();

        }

        return $exchanges;

    }

}
