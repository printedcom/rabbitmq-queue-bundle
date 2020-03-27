<?php

namespace Printed\Bundle\Queue\Command;

use Printed\Bundle\Queue\Service\QueueMaintenance;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * {@inheritdoc}
 */
class MaintenanceDownCommand extends Command
{
    /** @var QueueMaintenance */
    private $queueMaintenance;

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
        $this->setName('queue:maintenance:down');
        $this->setDescription('Disable the maintenance mode for the queue');
        $this->setHelp('Maintenance mode tells all queue works to complete the first job and stop');
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Disabling maintenance mode</info>');

        $this->queueMaintenance->disable();
    }
}
