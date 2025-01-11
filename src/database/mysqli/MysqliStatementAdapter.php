<?php

declare(strict_types=1);

namespace flight\database\mysqli;

use Exception;
use flight\database\DatabaseStatementInterface;
use mysqli_stmt;

class MysqliStatementAdapter implements DatabaseStatementInterface
{
    private mysqli_stmt $statement;

    /** @var array */
    private $allResults = [];

    /** @var int */
    private $allResultsCount = 0;

    /** @var int */
    private $resultIndex = 0;

    /**
     * Construct
     *
     * @param mysqli_stmt $statement statement
     */
    public function __construct(mysqli_stmt $statement)
    {
        $this->statement = $statement;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $params = []): bool
    {

        $params = array_values($params);
        $param_count = count($params);
        if ($param_count > 0) {
            $this->statement->bind_param(str_repeat('s', $param_count), ...$params);
        }
        $execute_result = $this->statement->execute();
        if ($execute_result === false) {
            throw new Exception($this->getErrorList()[0]['error']);
        }
        return $execute_result;
    }

    /**
     * @inheritDoc
     */
    public function fetch(&$object)
    {

        // If there are no more results to fetch, return false.
        if ($this->allResultsCount > 0 && $this->resultIndex >= $this->allResultsCount) {
            return false;
        }

        // If it hasn't run the query just yet, run it and store all results in the object.
        if ($this->resultIndex === 0) {
            $raw_result = $this->statement->get_result();
            if ($raw_result === false) {
                throw new Exception($this->getErrorList()[0]['error']);
            }

            while ($row = $raw_result->fetch_assoc()) {
                $this->allResults[] = $row;
                ++$this->allResultsCount;
            }
        }

        // No results to fetch
        if ($this->allResultsCount === 0) {
            return false;
        }

        $result = $this->allResults[$this->resultIndex++];
        if ($result) {
            foreach ($result as $key => $value) {
                $object->{$key} = $value;
            }
        }
        return $object;
    }

    /**
     * Gets the error list (easier to mock with unit testing)
     *
     * @return array
     * @codeCoverageIgnore Can't mock this if your life depends on it.
     */
    protected function getErrorList(): array
    {
        return $this->statement->error_list;
    }
}
