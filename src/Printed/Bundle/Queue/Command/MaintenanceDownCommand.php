<?php

namespace Printed\Bundle\Queue\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * {@inheritdoc}
 */
class MaintenanceDownCommand extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait;

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

        $maintenance = $this->container->get('printed.bundle.queue.service.queue_maintenance');
        $maintenance->disable();

    }

}
