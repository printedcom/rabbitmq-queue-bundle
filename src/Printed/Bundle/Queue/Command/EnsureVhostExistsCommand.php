<?php

declare(strict_types=1);

namespace Printed\Bundle\Queue\Command;

use Printed\Bundle\Queue\Service\RabbitMqVhostExistenceEnsurer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use RabbitMq;

/**
 * {@inheritdoc}
 */
class EnsureVhostExistsCommand extends Command
{
    public function __construct(
        private readonly RabbitMqVhostExistenceEnsurer $rabbitMqVhostExistenceEnsurer
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('queue:ensure-vhost-exists');
        $this->setDescription("Ensures, that a rabbitmq's vhost exists, and that rabbitmq's user can manage it");
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL) {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        }

        $this->rabbitMqVhostExistenceEnsurer->ensure();

        return static::SUCCESS;
    }

}
