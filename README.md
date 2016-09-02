# printed/rabbitmq-queue-bundle

A RabbitMQ wrapper bundle for Symfony that aims to improve your experience with consumers and producers.
The bundle piggybacks off of the `php-amqplib/rabbitmq-bundle` bundle.

## Setup & Dependencies

* PHP `>=7.0`
* https://packagist.org/packages/symfony/symfony `~3.0`
* https://packagist.org/packages/doctrine/orm `~2.5`
* https://packagist.org/packages/monolog/monolog `~1.11`
* https://packagist.org/packages/ramsey/uuid `~3.4`
* https://packagist.org/packages/php-amqplib/rabbitmq-bundle `~1.6`

We assume that you are familiar with the `php-amqplib/rabbitmq-bundle` configuration and setup.

## Usage

Assuming you have the configuration done for the previous bundles and running a basic Symfony demo application the steps below should be fairly easy to follow. 
Small changes can be made where needed but the examples are to help get the point across.

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
$payload = new ExampleQueuePayload;
$payload->setData($myData);

$dispatcher = $this->get('printed.bundle.queue.service.queue_task_dispatcher');
$dispatcher->dispatch($payload);
```

## Consuming (Workers)

As mentioned in `php-amqplib/rabbitmq-bundle` your consumers need to be registered as services and given to the consumer definitions in the configuration. Its exactly the same here but we need a few arguments that are common across all consumers.

```YAML
services:

  app_bundle.queue.consumer.example_consumer:
    class: 'AppBundle\Queue\Consumer\ExampleQueueConsumer'
    arguments:
      - '@doctrine.orm.entity_manager'
      - '@printed.bundle.queue.repository.queue_task'
      - '@monolog.logger.queue'
      - '@service_container'
    public: false
      
```

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
     * {@inheritdoc}
     *
     * @param ExampleQueuePayload $payload
     */
    public function run(AbstractQueuePayload $payload): bool
    {

        //  You hava access to all the standard things ..
        $this->container->get('some_service');
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

Running these consumers is the same as the bundle mentioned: `./bin/console rabbitmq:consumer example_consumer`

### Maintenance

In order to bring down your queue or halt its progress we have the following console commands defined. This will allow for easy maintenace of the queue consumers/workers.

```
queue:maintenance:up
queue:maintenance:down
queue:maintenance:wait
```

In essence, bring `queue:maintenance:up` will tell the queue to finish its job, then it will exit with code `0`.
This is helpful if you are running this with something like `supervisord`!

The `queue:maintenance:wait` will poll the database for running tasks, this command will only exit when none of the tasks are marked as running.
The `-r` parameter will let you configure the number in seconds it waits to poll the database.
Also as its a symfony command feel free to quelch the output `-q` in your deployment tools.

Then of course `queue:maintenance:down` will allow the queues to run again.
Although you will need to manually restart them as they would have exited.

Essentially you might have a build script looking like this:
```
./bin/console queue:maintenance:up
./bin/console queue:maintenance:wait -q -r 10

# deploy code
# database migrations
# cache cleaning/warming

./bin/console queue:maintenance:down
```

As the tasks are stored in the database with all their payload data it is possible to spawn all the tasks again. This is handy if you need to upgrade or migrate your RabbitMQ instance. At this time we created the command for this (sorry) but it is easy enough to do by replicating the process you do initially to spawn a task but just loop over all the entries in the database marked as `pending` or `status = 1`. See the `QueueTaskInterface` for more information here.

## Tests & Contribution

Sadly no tests right now, there isn't much too actually test. But if you have anything to contribute then please open a pull-request now. Just keep the code clean!
