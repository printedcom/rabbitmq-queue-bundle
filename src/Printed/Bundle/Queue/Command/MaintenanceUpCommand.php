<?php

namespace Printed\Bundle\Queue\Command;

use Printed\Bundle\Queue\Service\QueueMaintenance;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * {@inheritdoc}
 */
class MaintenanceUpCommand extends Command
{
    private QueueMaintenance $queueMaintenance;

    public function __construct(QueueMaintenance $queueMaintenance)
    {
        parent::__construct();

        $this->queueMaintenance = $queueMaintenance;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('queue:maintenance:up');
        $this->setDescription('Puts the queue in to maintenance mode');
        $this->setHelp('Maintenance mode tells all queue works to complete the first job and stop');
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Enabling maintenance mode</info>');

        $this->queueMaintenance->enable();
    }
}
