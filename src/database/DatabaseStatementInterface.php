<?php
declare(strict_types=1);

namespace flight\database;

interface DatabaseStatementInterface {

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
}