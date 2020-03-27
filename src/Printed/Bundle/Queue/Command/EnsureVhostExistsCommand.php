<?php

namespace Printed\Bundle\Queue\Command;

use Printed\Bundle\Queue\Service\RabbitMqVhostExistenceEnsurer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use RabbitMq;

/**
 * {@inheritdoc}
 */
class EnsureVhostExistsCommand extends Command
{
    /** @var RabbitMqVhostExistenceEnsurer */
    private $rabbitMqVhostExistenceEnsurer;

    public function __construct(
        RabbitMqVhostExistenceEnsurer $rabbitMqVhostExistenceEnsurer
    ) {
        parent::__construct();

        $this->rabbitMqVhostExistenceEnsurer = $rabbitMqVhostExistenceEnsurer;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('queue:ensure-vhost-exists');
        $this->setDescription("Ensures, that a rabbitmq's vhost exists, and that rabbitmq's user can manage it");
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if ($output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL) {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        }

        $this->rabbitMqVhostExistenceEnsurer->ensure();
    }

}
