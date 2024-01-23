<?php
declare(strict_types=1);

namespace flight\database\mysqli;

use Exception;
use flight\database\DatabaseInterface;
use flight\database\DatabaseStatementInterface;
use mysqli;

class MysqliAdapter implements DatabaseInterface {

	private mysqli $mysqli;

	/**
	 * Construct
	 *
	 * @param mysqli $mysqli mysqli
	 */
	public function __construct(mysqli $mysqli) {
		$this->mysqli = $mysqli;
	}

	/**
	 * @inheritDoc
	 */
	public function prepare(string $sql): DatabaseStatementInterface {
		$sql = $this->convertNamedPlaceholdersToQuestionMarks($sql);
		$prepared_statement = $this->mysqli->prepare($sql);
		if ($prepared_statement === false) {
			throw new Exception($this->getErrorList()[0]['error']);
		}
		return new MysqliStatementAdapter($prepared_statement);
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore Can't mock this if your life depends on it.
	 */
	public function lastInsertId() {
		return $this->mysqli->insert_id;
	}

	/**
	 * Because mysqli can't handle named placeholders, we need to convert them to question marks.
	 *
	 * @param string $sql sql
	 * @return string
	 */
	protected function convertNamedPlaceholdersToQuestionMarks(string $sql): string {
		return preg_replace('/:(\w+)/', '?', $sql);
	}

	/**
	 * Gets the error list (easier to mock with unit testing)
	 *
	 * @return array
	 * @codeCoverageIgnore Can't mock this if your life depends on it.
	 */
	protected function getErrorList(): array {
		return $this->mysqli->error_list;
	}
}