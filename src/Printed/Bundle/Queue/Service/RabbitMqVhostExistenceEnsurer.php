<?php

namespace Printed\Bundle\Queue\Service;

use Printed\Bundle\Queue\ValueObject\QueueBundleOptions;
use Psr\Log\LoggerInterface;
use RabbitMq;

class RabbitMqVhostExistenceEnsurer
{
    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $rabbitmqUser;

    /** @var string */
    private $rabbitmqPassword;

    /** @var string */
    private $rabbitmqVhost;

    /** @var string */
    private $rabbitmqApiBaseUrl;

    /** @var RabbitMq\ManagementApi\Client */
    private $rabbitmqManagementClient;

    public function __construct(
        LoggerInterface $logger,
        QueueBundleOptions $queueBundleOptions
    ) {
        $this->logger = $logger;
        $this->rabbitmqUser = $queueBundleOptions->get('rabbitmq_user');
        $this->rabbitmqPassword = $queueBundleOptions->get('rabbitmq_password');
        $this->rabbitmqVhost = $queueBundleOptions->get('rabbitmq_vhost');
        $this->rabbitmqApiBaseUrl = $queueBundleOptions->get('rabbitmq_api_base_url');

        $this->rabbitmqManagementClient = new RabbitMq\ManagementApi\Client(
            null,
            $this->rabbitmqApiBaseUrl,
            $this->rabbitmqUser,
            $this->rabbitmqPassword
        );
    }

    public function ensure()
    {
        $rabbitmqUser = $this->rabbitmqUser;
        $rabbitmqVhost = $this->rabbitmqVhost;

        $this->logger->info("Ensuring `{$rabbitmqUser}` can access and manage `{$rabbitmqVhost}` rabbitmq's vhost.");

        $this->ensureVhostExists($rabbitmqVhost);
        $this->ensureVhostUserPermissionsSet($rabbitmqVhost, $rabbitmqUser);

        $this->logger->info('Done.');
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

        $this->logger->info("Created rabbitmq vhost: `{$rabbitmqVhost}`");
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

        $this->logger->info("Created permissions for rabbitmq vhost and user: `{$rabbitmqVhost}`, `{$rabbitmqUser}`");
    }

}
