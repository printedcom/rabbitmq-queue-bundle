<?php

namespace Printed\Bundle\Queue\Entity;

use Printed\Bundle\Queue\Common\Traits\GetDataItemFromDataOrThrowTrait;
use Printed\Bundle\Queue\EntityInterface\QueueTaskInterface;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Printed\Bundle\Queue\Repository\QueueTaskRepository")
 * @ORM\Table(name="queue_task")
 */
class QueueTask implements QueueTaskInterface
{

    use GetDataItemFromDataOrThrowTrait;

    /**
     * @var int
     *
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @ORM\Column(name="id", type="integer")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="id_public", type="string", length=50)
     */
    protected $publicId = null;

    /**
     * @var string
     *
     * @ORM\Column(name="exchange", type="string")
     */
    protected $exchange;

    /**
     * @var int
     *
     * @ORM\Column(name="status", type="integer")
     */
    protected $status;

    /**
     * @var int
     *
     * @ORM\Column(name="attempts", type="integer")
     */
    protected $attempts;

    /**
     * @var int|null
     *
     * @ORM\Column(name="process_id", type="integer", nullable=true)
     */
    protected $processId;

    /**
     * @var string
     *
     * @ORM\Column(name="payload_class", type="string")
     */
    protected $payloadClass;

    /**
     * @var string
     *
     * @ORM\Column(name="payload", type="jsonb")
     */
    protected $payload;

    /**
     * @var array
     *
     * @ORM\Column(name="response", type="jsonb")
     */
    protected $response = [];

    /**
     * This is a non-searchable json field.
     * There is also an issue with the stack trace being persisted with the "jsonb" column type.
     *
     * @var array
     *
     * @ORM\Column(name="response_error", type="json_array")
     */
    protected $responseError = [];

    /**
     * @var \DateTimeInterface
     *
     * @ORM\Column(name="created_date", type="datetime")
     */
    protected $createdDate;

    /**
     * @var \DateTimeInterface
     *
     * @ORM\Column(name="started_date", type="datetime", nullable=true)
     */
    protected $startedDate;

    /**
     * @var \DateTimeInterface
     *
     * @ORM\Column(name="completed_date", type="datetime", nullable=true)
     */
    protected $completedDate;

    /**
     * {@inheritdoc}
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getPublicId(): string
    {
        return $this->publicId;
    }

    /**
     * {@inheritdoc}
     */
    public function setPublicId(string $id)
    {
        $this->publicId = $id;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getExchange(): string
    {
        return $this->exchange;
    }

    /**
     * {@inheritdoc}
     */
    public function setExchange(string $exchange)
    {
        $this->exchange = $exchange;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * {@inheritdoc}
     */
    public function isStatus(int $status): bool
    {
        return $this->status === $status;
    }

    /**
     * {@inheritdoc}
     */
    public function isAnyFailedStatus(): bool
    {
        return in_array($this->status, [ static::STATUS_FAILED, static::STATUS_FAILED_LIMIT_EXCEEDED ]);
    }

    /**
     * {@inheritdoc}
     */
    public function setStatus(int $status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttempts(int $attempts)
    {
        $this->attempts = $attempts;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getProcessId()
    {
        return $this->processId;
    }

    /**
     * {@inheritdoc}
     */
    public function setProcessId(int $pid = null)
    {
        $this->processId = $pid;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPayloadClass(): string
    {
        return $this->payloadClass;
    }

    /**
     * {@inheritdoc}
     */
    public function setPayloadClass(string $class)
    {
        $this->payloadClass = $class;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * {@inheritdoc}
     */
    public function getPayloadDataItem(string $key)
    {
        return $this->getDataItemFromData($this->payload, $key);
    }

    /**
     * {@inheritdoc}
     */
    public function getPayloadDataItemOrThrow(string $key)
    {
        return $this->getDataItemFromDataOrThrow($this->payload, $key);
    }

    /**
     * {@inheritdoc}
     */
    public function setPayload(array $payload)
    {
        $this->payload = $payload;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponse(): array
    {
        return $this->response;
    }

    /**
     * {@inheritdoc}
     */
    public function setResponse(array $response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseError(): array
    {
        return $this->responseError;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseDataItem(string $key)
    {
        return $this->getDataItemFromData($this->response, $key);
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseDataItemOrThrow(string $key)
    {
        return $this->getDataItemFromDataOrThrow($this->response, $key);
    }

    /**
     * {@inheritdoc}
     */
    public function setResponseError(string $class, string $message, array $stack)
    {
        $this->responseError['class'] = $class;
        $this->responseError['message'] = $message;
        $this->responseError['stack'] = $stack;
        return $this;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getCreatedDate(): \DateTimeInterface
    {
        return $this->createdDate;
    }

    /**
     * @param \DateTimeInterface $date
     *
     * @return mixed
     */
    public function setCreatedDate(\DateTimeInterface $date)
    {
        $this->createdDate = $date;
        return $this;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getStartedDate()
    {
        return $this->startedDate;
    }

    /**
     * @param \DateTimeInterface $date
     *
     * @return mixed
     */
    public function setStartedDate(\DateTimeInterface $date)
    {
        $this->startedDate = $date;
        return $this;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getCompletedDate()
    {
        return $this->completedDate;
    }

    /**
     * @param \DateTimeInterface $date
     *
     * @return mixed
     */
    public function setCompletedDate(\DateTimeInterface $date)
    {
        $this->completedDate = $date;
        return $this;
    }

}
