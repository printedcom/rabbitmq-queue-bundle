<?php

namespace Printed\Bundle\Queue\Repository;

use Doctrine\DBAL\Connection;
use Printed\Bundle\Queue\EntityInterface\QueueTaskInterface;

use Doctrine\ORM\EntityRepository;
use Printed\Bundle\Queue\Enum\QueueTaskStatus;
use Printed\Bundle\Queue\Exception\CouldNotFindAllRequestedQueueTasksException;
use Printed\Bundle\Queue\Queue\AbstractQueuePayload;

/**
 * @method QueueTaskInterface find($id, $lockMode = null, $lockVersion = null)
 * @method QueueTaskInterface findOneBy(array $criteria, array $orderBy = null)
 *
 * @method QueueTaskInterface[] findAll()
 */
class QueueTaskRepository extends EntityRepository
{
    /**
     * Find all queue tasks, that are in one of the statuses considered as unsettled.
     *
     * Searching by queue task payload criteria requires postgres db (version >= 9.3).
     *
     * Example queue task payload criteria:
     * [
     *   'field1' => 'value',
     *   'nested.field.field2' => 10,
     * ]
     *
     * In case you're using queue task payload criteria, it's your job to ensure the payload has
     * the expected keys. If the keys are missing, then the queue task will gracefully not be
     * considered as matching.
     *
     * @param string|null $queueName
     * @param array $queueTaskPayloadCriteria
     * @return QueueTaskInterface[]
     */
    public function findUnsettled(
        string $queueName = null,
        array $queueTaskPayloadCriteria = []
    ): array {
        $dbalConnection = $this->getEntityManager()->getConnection();
        $tableAlias = 'qt';

        $queueNameWhereSql = '';
        if ($queueName) {
            $queueNameWhereSql = "AND {$tableAlias}.queue_name = {$dbalConnection->quote($queueName)}";
        }

        $queueTaskPayloadWhereSql = '';
        if ($queueTaskPayloadCriteria) {
            $this->assertDatabaseIsPostgres();

            $queueTaskPayloadWhereSql = $this->translateQueueTaskPayloadCriteriaToSql(
                $tableAlias,
                $queueTaskPayloadCriteria
            );
        }

        $resultSetMappingBuilder  = $this->createResultSetMappingBuilder($tableAlias);

        $nativeQuery = $this->getEntityManager()->createNativeQuery("
            SELECT {$resultSetMappingBuilder->generateSelectClause()}
            FROM {$this->getClassMetadata()->getTableName()} AS {$tableAlias}
            WHERE
                {$tableAlias}.status IN (?)
                {$queueNameWhereSql}
                {$queueTaskPayloadWhereSql}
        ", $resultSetMappingBuilder);

        $nativeQuery->setParameter(
            1,
            QueueTaskStatus::getUnsettledStatuses(),
            Connection::PARAM_INT_ARRAY
        );

        $result = $nativeQuery->getResult();

        return $result;
    }

    /**
     * Find queue tasks by public ids.
     *
     * Additionally make sure they all are for the specific queue name and with specific payload
     * criteria.
     *
     * If the number of tasks retrieved isn't the same as the number of task ids, then an exception
     * is thrown.
     *
     * Learn more about the payload criteria by reading the ::findUnsettled() docblock.
     *
     * @param string[] $taskPublicIds
     * @param string|null $queueName
     * @param array $queueTaskPayloadCriteria
     * @return QueueTaskInterface[]
     */
    public function findByPublicIdsAndQueueNameOrThrow(
        array $taskPublicIds,
        string $queueName = null,
        array $queueTaskPayloadCriteria = []
    ): array {
        $dbalConnection = $this->getEntityManager()->getConnection();
        $tableAlias = 'qt';

        $queueNameWhereSql = '';
        if ($queueName) {
            $queueNameWhereSql = "AND {$tableAlias}.queue_name = {$dbalConnection->quote($queueName)}";
        }

        $queueTaskPayloadWhereSql = '';
        if ($queueTaskPayloadCriteria) {
            $this->assertDatabaseIsPostgres();

            $queueTaskPayloadWhereSql = $this->translateQueueTaskPayloadCriteriaToSql(
                $tableAlias,
                $queueTaskPayloadCriteria
            );
        }

        $resultSetMappingBuilder  = $this->createResultSetMappingBuilder($tableAlias);

        $nativeQuery = $this->getEntityManager()->createNativeQuery("
            SELECT {$resultSetMappingBuilder->generateSelectClause()}
            FROM {$this->getClassMetadata()->getTableName()} AS {$tableAlias}
            WHERE
                {$tableAlias}.id_public IN (?)
                {$queueNameWhereSql}
                {$queueTaskPayloadWhereSql}
        ", $resultSetMappingBuilder);

        $nativeQuery->setParameter(1, $taskPublicIds, Connection::PARAM_STR_ARRAY);

        $result = $nativeQuery->getResult();

        if (count($result) !== count($taskPublicIds)) {
            $retrievedTasksPublicIds = array_map(function (QueueTaskInterface $queueTask): string {
                return $queueTask->getPublicId();
            }, $result);

            $missingTaskIds = array_diff($taskPublicIds, $retrievedTasksPublicIds);

            throw new CouldNotFindAllRequestedQueueTasksException(
                sprintf(
                    'Could not find all requested queue tasks. Missing tasks: `"%s"`',
                    join('", "', $missingTaskIds)
                ),
                $missingTaskIds
            );
        }

        return $result;
    }

    /**
     * Best effort way to find out, whether there are a queue tasks, created by a given payload, that
     * are already in the database (i.e. that are already dispatched).
     *
     * @param AbstractQueuePayload $payload
     * @param string|null $queueTaskStatus
     * @return QueueTaskInterface[]
     */
    public function findByQueuePayload(AbstractQueuePayload $payload, string $queueTaskStatus = null)
    {
        $searchCriteria = [
            'queueName' => $payload->getQueueName(),
            'payloadClass' => get_class($payload),
            /*
             * This is, where the "best effort" stems from.
             */
            'payload' => $payload->getProperties(),
        ];

        if ($queueTaskStatus) {
            $searchCriteria['status'] = $queueTaskStatus;
        }

        $results = $this->findBy($searchCriteria);

        return $results;
    }

    private function assertDatabaseIsPostgres()
    {
        if ('postgresql' === $this->getEntityManager()->getConnection()->getDatabasePlatform()->getName()) {
            return;
        }

        throw new \RuntimeException('Failed to assert, that database is PostgreSQL.');
    }

    /**
     * Turn:
     * [
     *   'field1' => 'value',
     *   'nested.field.field2' => 10,
     * ]
     *
     * into:
     *
     *   AND tableAlias.payload->>'field1' = 'value'
     *   AND tableAlias.payload->'nested'->'field'->>'field2' = '10'
     *
     * @param string $tableAlias
     * @param array $queueTaskPayloadCriteria
     * @return string
     */
    private function translateQueueTaskPayloadCriteriaToSql(
        string $tableAlias,
        array $queueTaskPayloadCriteria
    ): string {
        /** @var string[] $sqlLines */
        $sqlLines = [];

        foreach ($queueTaskPayloadCriteria as $criterionKey => $criterionValue) {
            $jsonLevels = explode('.', $criterionKey);

            $lastJsonLevelIndex = count($jsonLevels) - 1;

            /*
             * Prefix each json key with correct json dereference operator (->> or ->).
             */
            foreach ($jsonLevels as $jsonLevelKey => $jsonLevel) {
                $jsonDereferenceOperator = $jsonLevelKey === $lastJsonLevelIndex ? '->>' : '->';

                $jsonLevels[$jsonLevelKey] = "{$jsonDereferenceOperator}'{$jsonLevel}'";
            }

            $sqlLines[] = sprintf(
                "AND %s.payload%s = '%s'",
                $tableAlias,
                join('', $jsonLevels),
                $criterionValue
            );
        }

        return join("\n", $sqlLines);
    }
}
