<?php

declare(strict_types=1);

namespace Printed\Bundle\Queue\Queue;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * A queue payload wrapper.
 *
 * Much like an event this class represents a payload of data that needs to be given to an exchange.
 */
abstract class AbstractQueuePayload
{
    const MESSAGE_PROPERTY_PRIORITY = 'priority';

    /**
     * Bump this version in child classes to allow for an easy way to validate whether
     * a payload is from a legacy worker. Imagine the deployment process.
     *
     * @Assert\NotBlank()
     * @Assert\Type(type="integer")
     */
    protected int $version = 1;

    /**
     * Queue message properties passed-through to OldSound\RabbitMqBundle\RabbitMq\ProducerInterface::publish()::$additionalProperties
     *
     * See available properties here: PhpAmqpLib\Message\AMQPMessage::$propertyDefinitions or use one of the ::MESSAGE_PROPERTY_
     * constants.
     *
     * This variable makes sense only during dispatching/publishing a queue task payload. Otherwise, it's always empty.
     */
    private array $__queueMessageProperties;

    /**
     * Return the destination queue name.
     *
     * @return string
     */
    abstract public static function getQueueName(): string;

    /**
     * {@inheritdoc}
     *
     * @param array $data
     * @param array $queueMessageProperties
     */
    public function __construct(array $data = [], array $queueMessageProperties = [])
    {
        foreach (get_object_vars($this) as $key => $value) {
            if (isset($data[$key])) {
                $this->{$key} = $data[$key];
            }
        }

        $this->__queueMessageProperties = $queueMessageProperties;
    }

    public function getQueueMessageProperties(): array
    {
        return $this->__queueMessageProperties;
    }

    /**
     * Return the payload version.
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Return all the properties for serialisation.
     */
    public function getProperties(): array
    {
        return array_filter(get_object_vars($this), function ($value, string $key) {
            return !str_starts_with($key, '__');
        }, ARRAY_FILTER_USE_BOTH);
    }
}
