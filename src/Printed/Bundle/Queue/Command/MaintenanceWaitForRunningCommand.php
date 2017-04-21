<?php

namespace Printed\Bundle\Queue\Command;

use Printed\Bundle\Queue\EntityInterface\QueueTaskInterface;

use Doctrine;
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
        $output->writeln('<info>Monitoring running tasks</info>');

        //  Simply notify that maintenance mode is enabled if it is.
        $maintenance = $this->container->get('printed.bundle.queue.service.queue_maintenance');
        if ($maintenance->isEnabled()) {
            $output->writeln('<comment>Maintenance mode is enabled</comment>');
        }

        /*
         * EntityManager can't be used here, because the database is before db migrations at this moment.
         * DBAL is used instead.
         */
        $dbal = $this->container->get('doctrine.dbal.default_connection');

        /*
         * Exit immediately if the queue tasks db table is not in the database. Assume no workers
         * are running.
         */
        if (!in_array('queue_task', $dbal->getSchemaManager()->listTableNames())) {
            $output->writeln("<error>Couldn't find the queue tasks table in the database. This is expected, if the bundle is used for the first time. Otherwise it's a critical error you should investigate.</error>");
            return;
        }

        //  Get the refresh time, this is 3 by default.
        $refresh = (integer) $input->getOption('refresh');

        $table = new Table($output);
        $table->setHeaders(['Queue', 'Tasks']);

        while (true) {

            //  Find all tasks with running status.
            $tasks = $dbal->fetchAll(
                'SELECT id, queue_name FROM queue_task WHERE status = :status_running',
                [ 'status_running' => QueueTaskInterface::STATUS_RUNNING ]
            );
            $count = count($tasks);

            //  If there are none we can exit the command.
            if ($count === 0) {
                $output->writeln(sprintf('<info>There are no tasks in running state!</info>'));
                return;
            }

            $table->setRows([]);
            foreach ($this->groupTasksByQueue($tasks) as $exchange => $ids) {
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
     * @param array[] $tasks { id: number; queue_name: string; }[]
     *
     * @return array { [queueName: string]: number[] }
     */
    protected function groupTasksByQueue(array $tasks): array
    {
        $exchanges = [];

        foreach ($tasks as $task) {

            if (!isset($exchanges[$task['queue_name']])) {
                $exchanges[$task['queue_name']] = [];
            }

            $exchanges[$task['queue_name']][] = $task['id'];

        }

        return $exchanges;

    }

}
