<?php
declare(strict_types=1);

namespace flight\database;

interface DatabaseInterface
{

    /**
     * Prepares a query for execution and returns a statement
     *
     * @param string $sql sql
     * @return DatabaseStatementInterface
     */
    public function prepare(string $sql): DatabaseStatementInterface;

    /**
     * Last insert id
     *
     * @return int|string
     */
    public function lastInsertId();
}
