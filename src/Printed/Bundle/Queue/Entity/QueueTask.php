<?php

declare(strict_types=1);

namespace Printed\Bundle\Queue\Entity;

use Doctrine\ORM\Mapping as ORM;
use Printed\Bundle\Queue\Common\Traits\GetDataItemFromDataOrThrowTrait;
use Printed\Bundle\Queue\EntityInterface\QueueTaskInterface;
use Printed\Bundle\Queue\Enum\QueueTaskStatus;
use Printed\Bundle\Queue\Repository\QueueTaskRepository;

#[ORM\Entity(repositoryClass: QueueTaskRepository::class)]
#[ORM\Table(
    name: 'queue_task',
    indexes: [
        new ORM\Index(columns: ['status']),
        new ORM\Index(columns: ['queue_name']),
        new ORM\Index(columns: ['created_date']),
    ]
)]
class QueueTask implements QueueTaskInterface
{
    use GetDataItemFromDataOrThrowTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: 'integer')]
    protected int $id;

    #[ORM\Column(name: 'id_public', type: 'string', length: 50, unique: true)]
    protected ?string $publicId = null;

    #[ORM\Column(name: 'queue_name', type: 'string')]
    protected string $queueName;

    #[ORM\Column(name: 'status', type: 'integer')]
    protected int $status;

    #[ORM\Column(name: 'attempts', type: 'integer')]
    protected int $attempts;

    /**
     * @var int 0 ~ 100
     */
    #[ORM\Column(name: 'completion_percentage', type: 'integer')]
    protected int $completionPercentage = 0;

    /**
     * @var bool Task cancellation is graceful for the consumers, that's why you should
     *  expect to see tasks, that have been run to their completion, even though
     *  a cancellation has been requested.
     */
    #[ORM\Column(name: 'cancellation_requested', type: 'boolean')]
    protected bool $cancellationRequested = false;

    #[ORM\Column(name: 'process_id', type: 'integer', nullable: true)]
    protected ?int $processId;

    #[ORM\Column(name: 'payload_class', type: 'string')]
    protected string $payloadClass;

    #[ORM\Column(name: 'payload', type: 'jsonb')]
    protected array $payload;

    #[ORM\Column(name: 'response', type: 'jsonb')]
    protected array $response = [];

    /**
     * This is a non-searchable json field.
     * There is also an issue with the stack trace being persisted with the "jsonb" column type.
     */
    #[ORM\Column(name: 'response_error', type: 'json_array')]
    protected array $responseError = [];

    #[ORM\Column(name: 'created_date', type: 'datetime')]
    protected \DateTimeInterface $createdDate;

    #[ORM\Column(name: 'started_date', type: 'datetime', nullable: true)]
    protected ?\DateTimeInterface $startedDate;

    #[ORM\Column(name: 'completed_date', type: 'datetime', nullable: true)]
    protected ?\DateTimeInterface $completedDate;

    public function getId(): int
    {
        return $this->id;
    }

    public function getPublicId(): string
    {
        return $this->publicId;
    }

    public function setPublicId(string $id)
    {
        $this->publicId = $id;

        return $this;
    }

    public function getQueueName(): string
    {
        return $this->queueName;
    }

    public function assertQueueName(string $queueName)
    {
        if ($this->queueName === $queueName) {
            return;
        }

        throw new \RuntimeException("Failed to assert, that queue task `{$this->id}` is for queue `{$queueName}`.");
    }

    public function setQueueName(string $queueName)
    {
        $this->queueName = $queueName;

        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function isStatus(int $status): bool
    {
        return $this->status === $status;
    }

    public function isAnyFailedStatus(): bool
    {
        return in_array($this->status, [QueueTaskStatus::FAILED, QueueTaskStatus::FAILED_LIMIT_EXCEEDED]);
    }

    public function setStatus(int $status)
    {
        $this->status = $status;

        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function setAttempts(int $attempts)
    {
        $this->attempts = $attempts;

        return $this;
    }

    public function getCompletionPercentage(): int
    {
        return $this->completionPercentage;
    }

    public function setCompletionPercentage(int $completionPercentage)
    {
        if ($completionPercentage < 0 || $completionPercentage > 100) {
            throw new \LogicException("Queue task's completion percentage must be between 0 and 100. Given: `{$completionPercentage}`");
        }

        $this->completionPercentage = $completionPercentage;

        return $this;
    }

    public function isCancellationRequested(): bool
    {
        return $this->cancellationRequested;
    }

    public function setCancellationRequested(bool $cancellationRequested)
    {
        $this->cancellationRequested = $cancellationRequested;

        return $this;
    }

    public function getProcessId()
    {
        return $this->processId;
    }

    public function setProcessId(int $pid = null)
    {
        $this->processId = $pid;

        return $this;
    }

    public function getPayloadClass(): string
    {
        return $this->payloadClass;
    }

    public function setPayloadClass(string $class)
    {
        $this->payloadClass = $class;

        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getPayloadDataItem(string $key)
    {
        return $this->getDataItemFromData($this->payload, $key);
    }

    public function getPayloadDataItemOrThrow(string $key)
    {
        return $this->getDataItemFromDataOrThrow($this->payload, $key);
    }

    public function setPayload(array $payload)
    {
        $this->payload = $payload;

        return $this;
    }

    public function getResponse(): array
    {
        return $this->response;
    }

    public function setResponse(array $response)
    {
        $this->response = $response;

        return $this;
    }

    public function getResponseError(): array
    {
        return $this->responseError;
    }

    public function getResponseDataItem(string $key)
    {
        return $this->getDataItemFromData($this->response, $key);
    }

    public function getResponseDataItemOrThrow(string $key)
    {
        return $this->getDataItemFromDataOrThrow($this->response, $key);
    }

    public function setResponseError(string $class, string $message, array $stack)
    {
        /*
         * Gracefully handle case when $stack can't be json encoded
         */
        $jsonCheck = json_encode($stack);
        if (!$jsonCheck) {
            $stack = json_last_error_msg();
        }

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
