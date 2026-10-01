<?php

namespace Wexample\SymfonyApi\Service;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;
use Wexample\SymfonyApi\Api\Class\ApiValidationErrorData;
use Wexample\SymfonyApi\Api\Class\BatchReport;
use Wexample\SymfonyApi\Api\Dto\AbstractDto;
use Wexample\SymfonyApi\Entity\AbstractIdempotencyRecord;
use Wexample\SymfonyApi\Enum\BatchItemOutcome;
use Wexample\SymfonyApi\Event\ApiBatchEvent;
use Wexample\SymfonyApi\Exception\BatchItemRejectedException;
use Wexample\SymfonyApi\Exception\ConstraintViolationException;
use Wexample\SymfonyApi\Exception\DeserializationException;
use Wexample\SymfonyApi\Helper\ApiVersionHelper;
use Wexample\SymfonyApi\Helper\IdempotencyHelper;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * Receives a batch of items, `{"items": [{"key": "…", "data": {…}}, …]}`:
 * validates each item against its DTO, hands the valid ones to a processor,
 * and reports each one. An invalid or failing item never rejects the others.
 *
 * Each item runs in its own transaction, which first inserts its key for the
 * sender: the unique constraint, not a prior read, makes a replay — even a
 * concurrent one — store nothing twice.
 */
class BatchReceiverService
{
    final public const string KEY_ITEMS = 'items';

    final public const string KEY_ITEM_KEY = 'key';

    final public const string KEY_ITEM_DATA = 'data';

    private bool $recordTableChecked = false;

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly DtoValidationService $dtoValidationService,
        private readonly Security $security,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'api_batch_record_class')]
        private readonly ?string $recordClass,
        #[Autowire(param: 'api_batch_max_items')]
        private readonly int $maxItems,
        #[Autowire(param: 'api_batch_retention')]
        private readonly string $retention,
    ) {
    }

    /**
     * @param class-string<AbstractDto> $itemDtoClass
     * @param callable(AbstractDto, UserInterface): mixed $processor Persists
     *     one item; what it returns — JSON-encodable — goes in the report and is
     *     given back on a replay. It refuses an item on a rule of its own by
     *     throwing BatchItemRejectedException. After a failed item the entity manager is
     *     cleared: the processor reads what it needs again, from the user it
     *     is given rather than one captured beforehand.
     */
    public function receive(
        Request $request,
        string $itemDtoClass,
        callable $processor,
        ?int $maxItems = null
    ): BatchReport {
        $user = $this->security->getUser()
            ?? throw new AccessDeniedException('A batch is received from an authenticated sender only: its keys are scoped to it.');

        $items = $this->readItems($request, $maxItems ?? $this->maxItems);
        $scope = $this->buildScope($user);
        $report = new BatchReport();

        foreach ($items as $index => $item) {
            $user = $this->receiveItem($report, $index, $item, $itemDtoClass, $processor, $scope, $user);
        }

        $this->eventDispatcher->dispatch(new ApiBatchEvent(
            occurredAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            scope: $scope,
            itemClass: $itemDtoClass,
            summary: $report->getSummary(),
            rejectionCodes: $report->getRejectionCodes(),
            route: $request->attributes->get('_route'),
            apiVersion: ApiVersionHelper::fromPath($request->getPathInfo()),
            requestId: $request->headers->get('X-Request-Id'),
        ));

        return $report;
    }

    /**
     * Deletes the keys older than the retention. A replay arriving later is
     * stored again: the retention has to outlast the longest offline period.
     */
    public function purgeExpiredRecords(): int
    {
        $limit = (new DateTime())->sub(new DateInterval($this->retention));

        return $this->getEntityManager()->createQueryBuilder()
            ->delete($this->getRecordClass(), 'record')
            ->where('record.dateCreated < :limit')
            ->setParameter('limit', $limit)
            ->getQuery()
            ->execute();
    }

    /**
     * @return array<int, mixed>
     */
    private function readItems(Request $request, int $maxItems): array
    {
        $content = json_decode($request->getContent(), true);
        $items = is_array($content) ? ($content[self::KEY_ITEMS] ?? null) : null;

        if (! is_array($items) || ! array_is_list($items)) {
            throw new BadRequestHttpException('A batch is a JSON object whose "' . self::KEY_ITEMS . '" is a list.');
        }

        if (count($items) > $maxItems) {
            throw new UnprocessableEntityHttpException('Batch too large: ' . count($items) . ' items, at most ' . $maxItems . '.');
        }

        return $items;
    }

    /**
     * @return UserInterface The user to go on with, read again after a failure.
     */
    private function receiveItem(
        BatchReport $report,
        int $index,
        mixed $item,
        string $itemDtoClass,
        callable $processor,
        string $scope,
        UserInterface $user
    ): UserInterface {
        $key = is_array($item) ? ($item[self::KEY_ITEM_KEY] ?? null) : null;
        $key = is_string($key) ? $key : null;

        $shapeErrors = $this->validateShape($item, $key);
        if ($shapeErrors) {
            $report->add($index, $key, BatchItemOutcome::REJECTED, errors: $shapeErrors->toArray());

            return $user;
        }

        $data = $item[self::KEY_ITEM_DATA];

        try {
            $dto = $this->dtoValidationService->createDto($data, $itemDtoClass);
        } catch (ConstraintViolationException $exception) {
            $errors = ApiValidationErrorData::create();
            foreach ($exception->getViolations() as $violation) {
                $errors->addIssue(
                    (string) ($violation->getCode() ?? 'INVALID'),
                    $violation->getPropertyPath(),
                    (string) $violation->getMessage()
                );
            }
            $report->add($index, $key, BatchItemOutcome::REJECTED, errors: $errors->toArray());

            return $user;
        } catch (DeserializationException) {
            $report->add($index, $key, BatchItemOutcome::REJECTED, errors: ApiValidationErrorData::create()
                ->addGlobalIssue(DeserializationException::CODE_TYPE_MISMATCH, 'The item data does not match its type.')
                ->toArray());

            return $user;
        }

        $payloadHash = IdempotencyHelper::hashPayload($data);
        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();
        $metadata = $this->getRecordMetadata($entityManager);
        $recordId = Uuid::v7();

        $connection->beginTransaction();

        try {
            $this->insertRecord($metadata, $recordId, $scope, $key, $payloadHash);
        } catch (UniqueConstraintViolationException) {
            $connection->rollBack();
            $this->reportReplay($report, $index, $key, $scope, $payloadHash, $metadata);

            return $user;
        }

        try {
            $result = $processor($dto, $user);
            $entityManager->flush();

            if (null !== $result) {
                $connection->update(
                    $metadata->getTableName(),
                    [$metadata->getColumnName('result') => $result],
                    [$metadata->getColumnName('id') => $recordId],
                    [$metadata->getColumnName('result') => $metadata->getTypeOfField('result'), $metadata->getColumnName('id') => $metadata->getTypeOfField('id')]
                );
            }

            $connection->commit();
            $report->add($index, $key, BatchItemOutcome::ACCEPTED, $result);

            return $user;
        } catch (BatchItemRejectedException $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            $report->add($index, $key, BatchItemOutcome::REJECTED, errors: ApiValidationErrorData::create()
                ->addGlobalIssue($exception->rejectionCode, $exception->getMessage())
                ->toArray());

            return $this->resetEntityManager($entityManager, $user);
        } catch (Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            $this->logger->error('Batch item failed: rolled back, reported as "error".', [
                'item_class' => $itemDtoClass,
                'key' => $key,
                'exception' => $exception,
            ]);
            $report->add($index, $key, BatchItemOutcome::ERROR);

            return $this->resetEntityManager($entityManager, $user);
        }
    }

    private function validateShape(mixed $item, ?string $key): ?ApiValidationErrorData
    {
        $errors = ApiValidationErrorData::create();

        if (! is_array($item) || array_is_list($item)) {
            return $errors->addGlobalIssue('INVALID_ITEM', 'An item is an object with a "key" and a "data".');
        }

        if (null === $key || '' === $key || mb_strlen($key) > IdempotencyHelper::KEY_MAX_LENGTH) {
            $errors->addFieldIssue(self::KEY_ITEM_KEY, 'INVALID_KEY', 'A non-empty string of at most ' . IdempotencyHelper::KEY_MAX_LENGTH . ' characters, unique for its sender.');
        }

        $data = $item[self::KEY_ITEM_DATA] ?? null;
        if (! is_array($data) || ([] !== $data && array_is_list($data))) {
            $errors->addFieldIssue(self::KEY_ITEM_DATA, 'INVALID_DATA', 'An object.');
        }

        foreach (array_diff(array_keys($item), [self::KEY_ITEM_KEY, self::KEY_ITEM_DATA]) as $extra) {
            $errors->addFieldIssue((string) $extra, 'EXTRA_PROPERTY');
        }

        return $errors->toArray()['issues'] ? $errors : null;
    }

    private function insertRecord(
        ClassMetadata $metadata,
        Uuid $recordId,
        string $scope,
        string $key,
        string $payloadHash
    ): void {
        $values = [
            'id' => $recordId,
            'scope' => $scope,
            'idempotencyKey' => $key,
            'payloadHash' => $payloadHash,
            'dateCreated' => new DateTime(),
        ];

        $row = [];
        $types = [];
        foreach ($values as $field => $value) {
            $row[$metadata->getColumnName($field)] = $value;
            $types[$metadata->getColumnName($field)] = $metadata->getTypeOfField($field);
        }

        $this->getEntityManager()->getConnection()->insert($metadata->getTableName(), $row, $types);
    }

    private function reportReplay(
        BatchReport $report,
        int $index,
        string $key,
        string $scope,
        string $payloadHash,
        ClassMetadata $metadata
    ): void {
        $connection = $this->getEntityManager()->getConnection();
        $row = $connection->fetchAssociative(
            sprintf(
                'SELECT %s AS payload_hash, %s AS result FROM %s WHERE %s = ? AND %s = ?',
                $metadata->getColumnName('payloadHash'),
                $metadata->getColumnName('result'),
                $metadata->getTableName(),
                $metadata->getColumnName('scope'),
                $metadata->getColumnName('idempotencyKey'),
            ),
            [$scope, $key]
        );

        if ($row && hash_equals($row['payload_hash'], $payloadHash)) {
            $result = null === $row['result'] ? null : json_decode($row['result'], true);
            $report->add($index, $key, BatchItemOutcome::DUPLICATE, $result);

            return;
        }

        $report->add($index, $key, BatchItemOutcome::CONFLICT, errors: ApiValidationErrorData::create()
            ->addFieldIssue(self::KEY_ITEM_KEY, 'KEY_REUSED', 'This key was already received with another payload.')
            ->toArray());
    }

    /**
     * Nothing the failed item persisted may reach the next flush: the manager
     * is cleared — or replaced when the failure closed it —, and the user read
     * again from it.
     */
    private function resetEntityManager(
        EntityManagerInterface $entityManager,
        UserInterface $user
    ): UserInterface {
        if ($entityManager->isOpen()) {
            $entityManager->clear();
        } else {
            $this->registry->resetManager();
        }

        if ($user instanceof AbstractEntity) {
            return $this->getEntityManager()->find($user::class, $user->getId()) ?? $user;
        }

        return $user;
    }

    private function buildScope(UserInterface $user): string
    {
        return mb_substr((new ReflectionClass($user))->getShortName() . ':' . $user->getUserIdentifier(), 0, 255);
    }

    private function getRecordMetadata(EntityManagerInterface $entityManager): ClassMetadata
    {
        $metadata = $entityManager->getClassMetadata($this->getRecordClass());

        if (! $this->recordTableChecked) {
            $expected = [$metadata->getColumnName('scope'), $metadata->getColumnName('idempotencyKey')];
            $found = false;

            foreach ($metadata->table['uniqueConstraints'] ?? [] as $constraint) {
                $columns = $constraint['columns'] ?? array_map($metadata->getColumnName(...), $constraint['fields'] ?? []);
                sort($columns);
                $sorted = $expected;
                sort($sorted);
                $found = $found || $columns === $sorted;
            }

            if (! $found) {
                throw new LogicException($metadata->getName() . ' needs #[ORM\UniqueConstraint(columns: [\'' . implode('\', \'', $expected) . '\'])]: without it, a replay is stored twice.');
            }

            $this->recordTableChecked = true;
        }

        return $metadata;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return $this->registry->getManagerForClass($this->getRecordClass());
    }

    /**
     * @return class-string<AbstractIdempotencyRecord>
     */
    private function getRecordClass(): string
    {
        return $this->recordClass
            ?? throw new LogicException('Set wexample_symfony_api.batch.record_class to receive batches.');
    }
}
