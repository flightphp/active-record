<?php

declare(strict_types=1);

namespace flight\database;

interface DatabaseStatementInterface
{
    /**
     * Executes a prepared statement
     *
     * @param array $params params
     * @return boolean
     */
    public function execute(array $params = []): bool;

    /**
     * Fetches the next row from a result set. It does fetch into an object
     *
     * @param object $object object
     * @return array|object|null
     */
    public function fetch(&$object);

    /**
     * Fetch the first column of the next row, or false when there are no more rows.
     *
     * NULL column values are returned as-is (null) and are distinct from the
     * false end-of-results sentinel.
     *
     * @return mixed The column value, null for SQL NULL, or false if no row is available
     */
    public function fetchColumn();

    /**
     * Number of rows affected by the last statement.
     *
     * @return int
     */
    public function rowCount(): int;
}
