<?php

namespace Printed\Bundle\Queue\Queue;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * A queue payload wrapper.
 *
 * Much like an event this class represents a payload of data that needs to be given to an exchange.
 */
abstract class AbstractQueuePayload
{

    /**
     * Bump this version in child classes to allow for an easy way to validate whether
     * a payload is from a legacy worker. Imagine the deployment process.
     *
     * @var int
     *
     * @Assert\NotBlank()
     * @Assert\Type(type="integer")
     */
    protected $version = 1;

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
     */
    public function __construct(array $data = [])
    {
        foreach (get_object_vars($this) as $key => $value) {
            if (isset($data[$key])) {
                $this->{$key} = $data[$key];
            }
        }
    }

    /**
     * Return the payload version.
     *
     * @return int
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Return all the properties for serialisation.
     *
     * @return array
     */
    public function getProperties(): array
    {
        return get_object_vars($this);
    }

}
