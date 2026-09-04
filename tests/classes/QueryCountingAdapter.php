<?php

declare(strict_types=1);

namespace flight\tests\classes;

/**
 * Test helper class to track SQL queries
 */
class QueryCountingAdapter implements \flight\database\DatabaseInterface
{
    private \flight\database\DatabaseInterface $wrappedAdapter;
    public array $executedQueries = [];

    public function __construct(\flight\database\DatabaseInterface $adapter)
    {
        $this->wrappedAdapter = $adapter;
    }

    public function prepare(string $sql): \flight\database\DatabaseStatementInterface
    {
        $this->executedQueries[] = $sql;
        return $this->wrappedAdapter->prepare($sql);
    }

    public function lastInsertId()
    {
        return $this->wrappedAdapter->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->wrappedAdapter->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->wrappedAdapter->commit();
    }

    public function rollback(): bool
    {
        return $this->wrappedAdapter->rollback();
    }

    public function getQueryCount(): int
    {
        return count($this->executedQueries);
    }

    public function reset(): void
    {
        $this->executedQueries = [];
    }
}
