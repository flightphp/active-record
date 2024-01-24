<?php
declare(strict_types=1);

namespace flight\database\pdo;

use Exception;
use flight\database\DatabaseInterface;
use flight\database\DatabaseStatementInterface;
use PDO;

class PdoAdapter implements DatabaseInterface
{

    private PDO $pdo;

    /**
     * Construct
     *
     * @param PDO $pdo pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @inheritDoc
     */
    public function prepare(string $sql): DatabaseStatementInterface
    {
        $prepared_statement = $this->pdo->prepare($sql);
        if ($prepared_statement === false) {
            throw new Exception($this->pdo->errorInfo()[2]);
        }
        return new PdoStatementAdapter($prepared_statement);
    }

    /**
     * @inheritDoc
     */
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }
}
