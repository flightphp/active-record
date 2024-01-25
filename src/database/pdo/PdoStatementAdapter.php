<?php

declare(strict_types=1);

namespace flight\database\pdo;

use Exception;
use flight\database\DatabaseStatementInterface;
use PDO;
use PDOStatement;

class PdoStatementAdapter implements DatabaseStatementInterface
{
    private PDOStatement $statement;

    /**
     * Construct
     *
     * @param PDOStatement $statement statement
     */
    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $params = []): bool
    {
        $execute_result = $this->statement->execute($params);
        if ($execute_result === false) {
            $errorInfo = $this->statement->errorInfo();
            throw new Exception($errorInfo[2]);
        }
        return $execute_result;
    }

    /**
     * @inheritDoc
     */
    public function fetch(&$object)
    {
        $this->statement->setFetchMode(PDO::FETCH_INTO, $object);
        return $this->statement->fetch();
    }
}
