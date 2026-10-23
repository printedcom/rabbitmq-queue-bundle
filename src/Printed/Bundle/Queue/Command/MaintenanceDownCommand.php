<?php

declare(strict_types=1);

namespace Printed\Bundle\Queue\Command;

use Printed\Bundle\Queue\Service\QueueMaintenance;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * {@inheritdoc}
 */
class MaintenanceDownCommand extends Command
{
    public function __construct(private readonly QueueMaintenance $queueMaintenance)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('queue:maintenance:down');
        $this->setDescription('Disable the maintenance mode for the queue');
        $this->setHelp('Maintenance mode tells all queue works to complete the first job and stop');
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Disabling maintenance mode</info>');

        $this->queueMaintenance->disable();

        return static::SUCCESS;
    }
}
