<?php

namespace Printed\Bundle\Queue\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use RabbitMq;

/**
 * {@inheritdoc}
 */
class EnsureVhostExistsCommand extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /** @var OutputInterface */
    private $output;

    /** @var RabbitMq\ManagementApi\Client */
    private $rabbitmqManagementClient;

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
        $this->output = $output;

        $rabbitmqUser = $this->container->getParameter('rabbitmq-queue-bundle.rabbitmq-user');
        $rabbitmqPassword = $this->container->getParameter('rabbitmq-queue-bundle.rabbitmq-password');
        $rabbitmqVhost = $this->container->getParameter('rabbitmq-queue-bundle.rabbitmq-vhost');
        $rabbitmqApiBaseUrl = $this->container->getParameter('rabbitmq-queue-bundle.rabbitmq-api-base-url');

        $output->writeln("<info>Ensuring {$rabbitmqUser} can access and manage {$rabbitmqVhost} rabbitmq's vhost.</info>");

        $this->rabbitmqManagementClient = new RabbitMq\ManagementApi\Client(
            null,
            $rabbitmqApiBaseUrl,
            $rabbitmqUser,
            $rabbitmqPassword
        );

        $this->ensureVhostExists($rabbitmqVhost);
        $this->ensureVhostUserPermissionsSet($rabbitmqVhost, $rabbitmqUser);

        $output->writeln('<info>Done</info>');
    }

    private function ensureVhostExists(string $rabbitmqVhost)
    {
        $vhostOrError = $this->rabbitmqManagementClient->vhosts()->get($rabbitmqVhost);

        /*
         * Is already there?
         */
        if ($rabbitmqVhost === ($vhostOrError['name'] ?? '')) {
            return;
        }

        /*
         * Expect "not found" error.
         */
        $responseErrorType = $vhostOrError['error'] ?? '(undefined)';
        if ($responseErrorType !== 'Object Not Found') {
            throw new \RuntimeException(sprintf(
                "Expected rabbitmq's api not found error, but got: `%s`. Full response: `%s`",
                $responseErrorType,
                json_encode($vhostOrError)
            ));
        }

        /*
         * Create the vhost.
         */
        $response = $this->rabbitmqManagementClient->vhosts()->create($rabbitmqVhost);

        if ($response['error'] ?? false) {
            throw new \RuntimeException(
                sprintf("Couldn't create rabbitmq vhost. Error: `%s`", json_encode($response))
            );
        }

        $this->output->writeln("<info>Created rabbitmq vhost: `{$rabbitmqVhost}`</info>");
    }

    private function ensureVhostUserPermissionsSet(string $rabbitmqVhost, string $rabbitmqUser)
    {
        $expectedPermissions = [
            'user' => $rabbitmqUser,
            'vhost' => $rabbitmqVhost,
            'configure' => '.*',
            'write' => '.*',
            'read' => '.*'
        ];

        $permissionsOrError = $this->rabbitmqManagementClient->permissions()->get($rabbitmqVhost, $rabbitmqUser);

        /*
         * Is already there?
         */
        if ($permissionsOrError == $expectedPermissions) {
            return;
        }

        /*
         * Create the permissions.
         */
        $response = $this->rabbitmqManagementClient->permissions()->create(
            $rabbitmqVhost,
            $rabbitmqUser,
            [
                'configure' => $expectedPermissions['configure'],
                'write' => $expectedPermissions['write'],
                'read' => $expectedPermissions['read'],
            ]
        );

        if ($response['error'] ?? false) {
            throw new \RuntimeException(
                sprintf("Couldn't create rabbitmq vhost permissions. Error: `%s`", json_encode($response))
            );
        }

        $this->output->writeln("<info>Created permissions for rabbitmq vhost and user: `{$rabbitmqVhost}`, `{$rabbitmqUser}`</info>");
    }
}
