<?php

namespace Printed\Bundle\Queue\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use RabbitMq;

/**
 * {@inheritdoc}
 */
class StoreNewDeploymentStampCommand extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;

        parent::__construct();
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

        $newDeploymentsDetector = $this->container->get('printed.bundle.queue.service.new_deployments_detector');
        $newDeploymentStamp = $input->getArgument('new-deployment-stamp');

        $this->logger->info("Trying to set new deployment stamp: `{$newDeploymentStamp}`");

        $newDeploymentsDetector->setCurrentDeploymentStamp($newDeploymentStamp);

        $this->logger->info("Successfully set new deployment stamp.");
    }

}
