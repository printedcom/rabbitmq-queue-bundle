<?php

namespace Printed\Bundle\Queue\Command;

use Printed\Bundle\Queue\EntityInterface\QueueTaskInterface;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Printed\Bundle\Queue\Enum\QueueTaskStatus;
use Printed\Bundle\Queue\Service\QueueMaintenance;
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
class MaintenanceWaitForRunningCommand extends Command
{
    /** @var QueueMaintenance */
    private $queueMaintenance;

    /** @var Connection */
    private $dbalConnection;

    public function __construct(
        QueueMaintenance $queueMaintenance,
        Connection $dbalConnection
    ) {
        parent::__construct();

        $this->queueMaintenance = $queueMaintenance;
        $this->dbalConnection = $dbalConnection;
    }

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
        if ($this->queueMaintenance->isEnabled()) {
            $output->writeln('<comment>Maintenance mode is enabled</comment>');
        }

        /*
         * EntityManager can't be used here, because the database is before db migrations at this moment.
         * DBAL is used instead.
         */
        $dbal = $this->dbalConnection;

        if (!$this->doesDatabaseExist($dbal)) {
            $output->writeln("<error>The database doesn't exist. This is expected, if the bundle is used for the first time. Otherwise it's a critical error you should investigate.</error>");
            return;
        }

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
                [ 'status_running' => QueueTaskStatus::RUNNING ]
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

    /**
     * Does the database exist.
     *
     * This is a copy&paste from the `doctrine:database:create` command. Hopefully the following
     * ticket would be done at some point, allowing me to avoid the duplicated code:
     *
     * https://github.com/doctrine/DoctrineBundle/issues/652
     *
     * @param Connection $connection
     * @return bool
     */
    private function doesDatabaseExist(Connection $connection): bool
    {
        $params = $connection->getParams();
        if (isset($params['master'])) {
            $params = $params['master'];
        }

        $hasPath = isset($params['path']);
        $name = $hasPath ? $params['path'] : (isset($params['dbname']) ? $params['dbname'] : false);
        if (!$name) {
            throw new \InvalidArgumentException("Connection does not contain a 'path' or 'dbname' parameter and cannot be dropped.");
        }
        // Need to get rid of _every_ occurrence of dbname from connection configuration and we have already extracted all relevant info from url
        unset($params['dbname'], $params['path'], $params['url']);

        $tmpConnection = DriverManager::getConnection($params);

        $doesDatabaseExist = in_array($name, $tmpConnection->getSchemaManager()->listDatabases());

        $tmpConnection->close();

        return $doesDatabaseExist;
    }
}
