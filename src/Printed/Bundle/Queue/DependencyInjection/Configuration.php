<?php

namespace Printed\Bundle\Queue\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /** @var string */
    private $bundleAlias;

    public function __construct(string $bundleAlias)
    {
        $this->bundleAlias = $bundleAlias;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder($this->bundleAlias);
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('options')
                ->isRequired()
                    ->children()
                        ->scalarNode('default_rabbitmq_producer_name')
                            ->info('Name of the service, that acts as a default producer in RabbitMQ')
                            ->defaultValue('default_rabbitmq_producer')
                        ->end()
                        ->scalarNode('application_doctrine_entity_manager__service_name')
                            ->defaultNull()
                        ->end()
                        ->enumNode('queue_maintenance_strategy__service_name')
                            ->values([
                                'printed.bundle.queue.service.queue_maintenance.cache_queue_maintenance_strategy',
                                'printed.bundle.queue.service.queue_maintenance.filesystem_queue_maintenance_strategy',
                            ])
                            ->defaultValue('printed.bundle.queue.service.queue_maintenance.cache_queue_maintenance_strategy')
                        ->end()
                            ->scalarNode('cache_queue_maintenance_strategy__cache_service_name')
                        ->end()
                        ->enumNode('new_deployments_detector_strategy__service_name')
                            ->values([
                                'printed.bundle.queue.service.new_deployments_detector.noop_strategy',
                                'printed.bundle.queue.service.new_deployments_detector.cache_strategy',
                            ])
                            ->defaultValue('printed.bundle.queue.service.new_deployments_detector.noop_strategy')
                        ->end()
                            ->scalarNode('new_deployments_detector_strategy__cache_service_name')
                        ->end()
                        ->integerNode('consumer_exit_code__running_using_old_code')
                            ->defaultValue(20)
                        ->end()
                        ->scalarNode('minimal_runtime_in_seconds_on_consumer_exception')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('rabbitmq_user')
                            ->isRequired()
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('rabbitmq_password')
                            ->isRequired()
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('rabbitmq_vhost')
                            ->defaultValue('/')
                        ->end()
                        ->scalarNode('rabbitmq_api_base_url')
                            ->defaultValue('[configure "rabbitmq_api_base_url" for this to work]')
                        ->end()
                ->end();

        return $treeBuilder;
    }
}
