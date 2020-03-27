# printed/rabbitmq-queue-bundle

A RabbitMQ wrapper bundle for Symfony that aims to improve your experience with consumers and producers.
The bundle piggybacks off of the `php-amqplib/rabbitmq-bundle` bundle.

## Notable features

* Cancellable queue tasks
* Queue tasks completeness progress tracking
* An opinionated, zero-downtime deployment procedure, focused on supervisord
* (via `php-amqplib/rabbitmq-bundle`) Queue consumer's graceful max execution time
* Queue tasks reattempts

## Setup & Dependencies

* PHP `>=7.0`
* https://packagist.org/packages/symfony/symfony `^3.4|^4.0`
* https://packagist.org/packages/doctrine/orm `~2.5`
* https://packagist.org/packages/monolog/monolog `~1.11`
* https://packagist.org/packages/ramsey/uuid `~3.4`
* https://packagist.org/packages/php-amqplib/rabbitmq-bundle `~1.6`

We assume that you are familiar with the `php-amqplib/rabbitmq-bundle` configuration and setup.

### Special note for memcached users
Please open `vendor/doctrine/cache/lib/Doctrine/Common/Cache/MemcachedCache.php` file
in your project and see whether you can find the following piece of code:
```php
protected function doContains($id)
{
    return false !== $this->memcached->get($id)
        || $this->memcached->getResultCode() !== Memcached::RES_NOTFOUND;
}
```
If you do, then consider upgrading `doctrine/cache` version to at least `1.7.0`, otherwise
`CacheQueueMaintenanceStrategy` might be saying that the maintenance mode is up when
there was any connection issues to your memcached server. 

### Bundle configuration

```yaml
printedcom_rabbitmq_queue_bundle:
  options:
    # Name of the service that acts as a default producer in RabbitMQ. See below this code snippet for details.
    default_rabbitmq_producer_name: 'default_rabbitmq_producer'
  
    # Doctrine's EntityManager needs to be cleared between consumers' runs. `AbstractQueueConsumer`
    # clears its own EntityManager, but if you have a separate EntityManager for your application
    # as well then put the name of the service that points to that EntityManager here.
    application_doctrine_entity_manager__service_name: ~

    # Name of the service that implements the queue maintenance mode. Use one of the following:
    #
    # 1. 'printed.bundle.queue.service.queue_maintenance.cache_queue_maintenance_strategy' (recommended)
    # 2. 'printed.bundle.queue.service.queue_maintenance.filesystem_queue_maintenance_strategy'
    #
    # To understand the difference, see the comments in the source code.
    queue_maintenance_strategy__service_name: 'printed.bundle.queue.service.queue_maintenance.cache_queue_maintenance_strategy'
  
    # Name of a cache service that implements the requirements outlined in the CacheQueueMaintenanceStrategy
    cache_queue_maintenance_strategy__cache_service_name: 'rabbitmq_queue_bundle_cache'

    # Name of the service that implements the New Deployments Detection feature. Choose one of the following:
    #
    # 1. 'printed.bundle.queue.service.new_deployments_detector.noop_strategy' - disables this functionality
    # 2. 'printed.bundle.queue.service.new_deployments_detector.cache_strategy'
    new_deployments_detector_strategy__service_name: 'printed.bundle.queue.service.new_deployments_detector.cache_strategy'

    # Name of a cache service that implements the requirements outlined in the CacheQueueMaintenanceStrategy
    new_deployments_detector_strategy__cache_service_name: 'rabbitmq_queue_bundle_cache'

    # Exit code used to exit a worker when it's detected that it's running old code
    consumer_exit_code__running_using_old_code: 15

    # With tools like supervisord, it's important to have consumers running without exiting for a specified amount of time
    # in order to prove that the script started successfully. This is a problem if a consumer manages to start, connect to rabbitmq 
    # and fail due to exception being thrown during execution of the task faster than the specified amount of time. The following
    # option makes the consumer not fail too fast. You essentially want to put your supervisord's "startsecs" value here (in seconds).
    # Bear in mind that the underlying code will always add 1 second to whatever value you put here in order to compensate for
    # fraction of seconds that aren't taken into account during evaluating of how long the script has been running for. This means that
    # you just need to put the value from supervisord's config "as is" without worrying about race conditions.
    #
    # You can disable this feature by either setting this option to null or not mentioning this option at all. I.e. by default this option
    # is disabled.
    minimal_runtime_in_seconds_on_consumer_exception: 1

    rabbitmq_user: '%rabbitmq_user%'
    rabbitmq_password: '%rabbitmq_pass%'

    # Pass '/' or don't set this option if you don't know what rabbtimq vhost is.
    rabbitmq_vhost: '%build.env.host%'

    # This is used only by commands that call the rabbit management api. You don't need to do 
    # anything with this key if you don't use those commands.
    rabbitmq_api_base_url: 'http://%rabbitmq_host%:%rabbitmq_management_port%'
```
 
* `rabbitmq-queue-bundle.default_rabbitmq_producer_name` You are expected to have at least one RabbitMQ producer with the following config:
```yaml
producers:
    default:
        connection:       default
        service_alias:    default_rabbitmq_producer
```
The value of the `service_alias` should be provided in the `rabbitmq-queue-bundle.default_rabbitmq_producer_name` parameter.
That config creates a RabbitMQ producer which dispatches tasks to appropriate queues by queue names. This is also known
as the "default" producer in RabbitMQ.  
 
### Important notice: Use dedicated EntityManager for your consumers.

Please inject subclasses of AbstractQueueConsumer with dedicated EntityManager that is not used by the
rest of your application. This is needed because AbstractQueueConsumer makes use of that entity manager
to report errors in the queue tasks entries. It's not possible if the entity manager "Is already closed". 

## Usage

Assuming you have the configuration done for the previous bundles and running a basic Symfony demo application the steps below should be fairly easy to follow. 
Small changes can be made where needed but the examples are to help get the point across.

### Example rabbitmq.yml config

```yml
old_sound_rabbit_mq:
    connections:
        default:
            host: '%rabbitmq_host%'
            port: '%rabbitmq_port%'
            user: '%rabbitmq_user%'
            password: '%rabbitmq_pass%'
            vhost: '/'
            lazy: true
            connection_timeout: 3
            
            # this needs to be 2x of the heartbeat option
            read_write_timeout: 7200
            
            # don't forget that you should enable tcp keepalive in rabbitmq as well: https://www.rabbitmq.com/networking.html#socket-gen-tcp-options
            keepalive: true
            
            # keep this value high because https://github.com/php-amqplib/RabbitMqBundle/issues/301
            heartbeat: 3600
    
    producers:
        default:
            connection:       default
            service_alias:    default_rabbitmq_producer
    
    consumers:
        upload_picture: &consumer_template
            connection:       default
            queue_options:    { name: 'upload-picture' }
            callback:         upload_picture_service
            qos_options:      { prefetch_size: 0, prefetch_count: 1, global: false }
            graceful_max_execution:
                timeout: 1800
                exit_code: 10
        
        render_treasure_map:
            <<: *consumer_template
            queue_options:    { name: 'render_treasure_map' }
            callback:         render_treasure_map_service
```

### Monolog

The consumer exposes a property `$this->logger` that will be an instance of `monolog/monolog` registered against Symfony with the `queue` channel. 
Feel free to handle the channel as you wish, but at the least we require `channels: [queue]` be added to your monolog config.

### Producing (Payloads)

We use classes known as `payloads` to contain data for the consumer (also known as worker).
This class when constructed and given to the dispatcher will be first validated using `symfony/validator` and then serialise and stored in the database.
The newly created record's ID is given to the RabbitMQ queue with the exchange defined in the abstract method `getExchangeName`.
This allows for an easy interface for dispatching payloads without having to worry about the destination and keeping code up-to-date when exchange names change.

```PHP
namespace AppBundle\Queue\Payload;

use Printed\Bundle\Queue\Queue\AbstractQueuePayload;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * {@inheritdoc}
 */
class ExampleQueuePayload extends AbstractQueuePayload
{

    /**
     * @Assert\NotNull()
     * @Assert\Type(type="integer")
     */
    protected $data;

    /**
     * {@inheritdoc}
     */
    public static function getExchangeName(): string
    {
        return 'example_exchange';
    }
    
    // public function getData()
    // public function setData()
    // ..

}

```

To dispatch this payload you can make use of the dispatcher class made available through this bundle. You can access it using `printed.bundle.queue.service.queue_task_dispatcher` against the container. For example ..

```PHP
class MyServiceClass
{
    private $queueTaskDispatcher;

    public function __construct(QueueTaskDispatcher $queueTaskDispatcher)
    {
        $this->queueTaskDispatcher = $queueTaskDispatcher;
    }

    public function doSomething()
    {
        // do something..

        $payload = new ExampleQueuePayload;
        $payload->setData($myData);
        
        $this->queueTaskDispatcher->dispatch($payload);
    }
}
```
```yaml
services:
  MyServiceClass:
    class: MyServiceClass
    arguments:
      - '@printed.bundle.queue.service.queue_task_dispatcher'
```

## Consuming (Workers)

As mentioned in `php-amqplib/rabbitmq-bundle` your consumers need to be registered as services and given to the consumer definitions in the configuration. Its exactly the same here but we need a few arguments that are common across all consumers.

```YAML
services:
  app_bundle.queue.consumer.example_consumer:
    class: 'AppBundle\Queue\Consumer\ExampleQueueConsumer'
    arguments:
      - '@doctrine.orm.entity_manager'
      - '@validator'
      - '@monolog.logger.queue'
      - '@Psr\Container\ContainerInterface'
      - '@printed.bundle.queue.service.service_container_parameters'
    public: false
    tags:
      - container.service_subscriber
```

Note that the `container.service_subscriber` tag is required for the ServiceSubscriber Symfony feature [link](https://symfony.com/doc/current/service_container/service_subscribers_locators.html).

Then your work would look very familiar, just a few changes and enhancements:

```PHP
namespace AppBundle\Queue\Consumer;

use AppBundle\Queue\Payload\ExampleQueuePayload;

use Printed\Bundle\Queue\Exception\Consumer\QueueFatalErrorException;
use Printed\Bundle\Queue\Queue\AbstractQueueConsumer;
use Printed\Bundle\Queue\Queue\AbstractQueuePayload;

use Symfony\Component\HttpFoundation\Request;

/**
 * {@inheritdoc}
 */
class ExampleQueueConsumer extends AbstractQueueConsumer
{
    /**
     * {@inheritDoc}
     */
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'some_service' => MySomeService::class,
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @param ExampleQueuePayload $payload
     */
    public function run(AbstractQueuePayload $payload): bool
    {

        //  You hava access to all the standard things ..
        $someService = $this->locator->get('some_service');
        $usefulParameter = $this->containerParameters->get('my_app.useful_parameter');

        $this->em->persist($entity);
        $this->logger->info('Oh noes');

        //  Setting a task response data.
        $this->task->setResponseData(['something' => true]);

        //  Do lots of things ..
        //  And a few more things ..
        
        return self::TASK_COMPLETE;

    }

}
```

A task must always exit with a boolean value, `true` meaning passed and `false` meaning it failed. To make this more verbose you have access to constants `TASK_COMPLETE` and `TASK_FAILED` which are simply those boolean values under the bonnet. When marking the task as failed the attempts count against the task is incremented before it is given back to the queue for another attempt. Each task will be given an attempt limit specified by the `getAttemptLimit()` method in the consumer. Feel free to override this but by default the limit is set to `10`. In those rare cases where you know the task will forever fail and you do not want to let it use its remaining attempts you can throw the `QueueFatalErrorException` exception. The message will be logged to the usual logs and the attempt limit will be maxed, this will prevent the job from spawning anymore attempts.

Running these consumers is the same as the bundle mentioned: `./bin/console rabbitmq:consumer example_consumer -vv`

### Maintenance and deployment

In order to gracefully bring down your queue or halt its progress we have the following console 
commands defined. This will allow for easy maintenance of the queue consumers/workers.

```
queue:maintenance:up
queue:maintenance:down
queue:maintenance:wait
```

In essence, `queue:maintenance:up` will prevent any new jobs from being processed by the workers. When
a new job is being delivered to a worker, while the maintenance mode is up, then the worker will
immediately exit with code `0`. This is helpful if you are running this with something like `supervisord`
because `supervisord` by default doesn't restart programs that exit with that status. 

It's important to understand that the primary purpose of `queue:maintenance:up` is to prevent new jobs 
from being processed. That command is not for stopping/restarting workers (although it effectively happens most of the
time). Please make use of the New Deployments Detection feature described below to restart the workers.

The `queue:maintenance:wait` will poll the database for running tasks, this command will only exit when none of the tasks are marked as running.
The `-r` parameter will let you configure the number in seconds it waits to poll the database.
Also as its a symfony command feel free to quelch the output `-q` in your deployment tools.

Then of course `queue:maintenance:down` will allow the queues to run again.
Although you will need to manually restart them as they would have exited.

Before you disable the maintenance mode, you need to make sure all old idle workers are restarted.
There are many ways of doing it. This bundle provides you with a way for all workers to quit when
they detect that a new deployment has happened. This feature is called "New Deployments Detection"
and requires you to call the `queue:store-new-deployment-stamp-command` with a string that can be used
to compare deployments (timestamp is generally enough). Make sure you configure this bundle to actually
use this feature.

Essentially you might have a build script looking like this:
```
./bin/console queue:maintenance:up
./bin/console queue:maintenance:wait -q -r 10

# deploy code
# database migrations
# cache cleaning/warming

./bin/console queue:store-new-deployment-stamp-command `date +%s` 

./bin/console queue:maintenance:down
# "supervisorctl reread"
# "supervisorctl update"
```

As the tasks are stored in the database with all their payload data it is possible to spawn all the tasks again. This is handy if you need to upgrade or migrate your RabbitMQ instance. At this time we created the command for this (sorry) but it is easy enough to do by replicating the process you do initially to spawn a task but just loop over all the entries in the database marked as `pending` or `status = 1`. See the `QueueTaskInterface` for more information here.

## Tips

### RabbitMQ vhosts

[Official docs](https://www.rabbitmq.com/vhosts.html)

If you use one RabbitMQ server to host queues for multiple of deployments of your app (e.g. hosting test, staging, regression
environments all on one RabbitMQ server), this feature lets you do that without running into queue names' conflicts.

To make use of it, set the following options:

1. `old_sound_rabbit_mq.connections.default.vhost`
2. `printedcom_rabbitmq_queue_bundle.rabbitmq_vhost`

### Supervisord groups of processes.

[Official docs](http://supervisord.org/configuration.html#group-x-section-settings)

This allows you to collect all queue consumers in one supervisord process group, which in turn allows you to start and
stop them by using that group name and it allows you to run queue consumers from multiple of your app environments on
one machine without running into name conflicts. 

It's also the more proper way to stop your consumers, deploy your code, reread supervisord config and start your consumers.
That replaces the "new deployment detection" feature of this bundle.

## Tests & Contribution

Sadly no tests right now, there isn't much too actually test. But if you have anything to contribute then please open a pull-request now. Just keep the code clean!
