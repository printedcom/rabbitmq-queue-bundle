<?php

namespace Printed\Bundle\Queue\Command;

use Printed\Bundle\Queue\Service\NewDeploymentsDetector;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use RabbitMq;

/**
 * {@inheritdoc}
 */
class StoreNewDeploymentStampCommand extends Command
{
    /** @var LoggerInterface */
    private $logger;

    /** @var NewDeploymentsDetector */
    private $newDeploymentsDetector;

    public function __construct(
        LoggerInterface $logger,
        NewDeploymentsDetector $newDeploymentsDetector
    ) {
        parent::__construct();

        $this->logger = $logger;
        $this->newDeploymentsDetector = $newDeploymentsDetector;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('queue:store-new-deployment-stamp');
        $this->setDescription("Store a new deployment stamp, so old workers can be told to shut down.");

        $this->addArgument(
            'new-deployment-stamp',
            InputArgument::OPTIONAL,
            'New deployment stamp (default: current time)',
            (new \DateTime())->format('YmdHis')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if ($output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL) {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        }
        
        $newDeploymentStamp = $input->getArgument('new-deployment-stamp');

        $this->logger->info("Trying to set new deployment stamp: `{$newDeploymentStamp}`");

        $this->newDeploymentsDetector->setCurrentDeploymentStamp($newDeploymentStamp);

        $this->logger->info("Successfully set new deployment stamp.");
    }
}
