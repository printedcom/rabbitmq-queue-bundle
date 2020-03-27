<?php

namespace Printed\Bundle\Queue\DependencyInjection;

use Printed\Bundle\Queue\ValueObject\QueueBundleOptions;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * {@inheritdoc}
 */
class QueueExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(sprintf('%s/../Resources/config', __DIR__)));
        $loader->load('services.yml');
        $loader->load('services/commands.yml');
        $loader->load('services/helpers.yml');
        $loader->load('services/listeners.yml');
        $loader->load('services/repositories.yml');

        $this->defineQueueBundleOptions($container);
        $this->defineClientApplicationEntityManager($container);

        $this->configureQueueServicesWithDynamicDependencies($container);
    }

    private function defineQueueBundleOptions(ContainerBuilder $container)
    {
        $bundleOptionsNames = [
            'rabbitmq-queue-bundle.queue_names_prefix' => 'queue_names_prefix',
            'rabbitmq-queue-bundle.consumer_exit_code.running_using_old_code' => 'consumer_exit_code.running_using_old_code',
            'rabbitmq-queue-bundle.minimal_runtime_in_seconds_on_consumer_exception' => 'minimal_runtime_in_seconds_on_consumer_exception',
        ];

        $bundleOptions = [];
        foreach ($bundleOptionsNames as $parameterName => $bundleOptionName) {
            $bundleOptions[$bundleOptionName] = $container->getParameter($parameterName);
        }

        $queueBundleOptionsDefinition = $container->getDefinition('printed.bundle.queue.service.queue_bundle_options');
        $queueBundleOptionsDefinition->setArgument(0, $bundleOptions);
    }

    private function defineClientApplicationEntityManager(ContainerBuilder $container)
    {
        $clientApplicationEntityManagerServiceName = $container->getParameter('rabbitmq-queue-bundle.application_doctrine_entity_manager.service_name');

        /*
         * In any case, remove this bundle's service definition. It eventually is either an alias or not defined.
         */
        $container->removeDefinition('printed.bundle.queue.service.client.application_entity_manager');

        /*
         * Case when null: nothing to alias.
         */
        if (!$clientApplicationEntityManagerServiceName) {
            return;
        }

        /*
         * Alias this bundle's service with the supplied entity manager.
         */
        $container->setAlias(
            'printed.bundle.queue.service.client.application_entity_manager',
            $clientApplicationEntityManagerServiceName
        );
    }

    private function configureQueueServicesWithDynamicDependencies(ContainerBuilder $container)
    {
        /*
         * QueueMaintenance.php
         */
        $serviceDefinition = $container->getDefinition('printed.bundle.queue.service.queue_maintenance');
        $serviceDefinition->setArgument(0, new Reference($container->getParameter('rabbitmq-queue-bundle.queue_maintenance_strategy.service_name')));

        /*
         * CacheQueueMaintenanceStrategy.php
         */
        $serviceDefinition = $container->getDefinition('printed.bundle.queue.service.queue_maintenance.cache_queue_maintenance_strategy');
        $serviceDefinition->setArgument(0, new Reference($container->getParameter('rabbitmq-queue-bundle.cache_queue_maintenance_strategy.cache_service_name')));

        /*
         * CacheNewDeploymentsDetectorStrategy.php
         */
        $serviceDefinition = $container->getDefinition('printed.bundle.queue.service.new_deployments_detector.cache_strategy');
        $serviceDefinition->setArgument(0, new Reference($container->getParameter('rabbitmq-queue-bundle.new_deployments_detector_strategy.cache_service_name')));

        /*
         * NewDeploymentsDetector.php
         */
        $serviceDefinition = $container->getDefinition('printed.bundle.queue.service.new_deployments_detector');
        $serviceDefinition->setArgument(0, new Reference(
            $container->hasParameter('rabbitmq-queue-bundle.new_deployments_detector_strategy.service_name')
                ? $container->getParameter('rabbitmq-queue-bundle.new_deployments_detector_strategy.service_name')
                : 'printed.bundle.queue.service.new_deployments_detector.noop_strategy'
        ));

        /*
         * QueueTaskDispatcher.php
         */
        $serviceDefinition = $container->getDefinition('printed.bundle.queue.service.queue_task_dispatcher');
        $serviceDefinition->setArgument(3, new Reference($container->getParameter('rabbitmq-queue-bundle.default_rabbitmq_producer_name')));
    }

}
