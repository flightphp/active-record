<?php

namespace flight\tests;

use Exception;
use flight\ActiveRecord;
use PDO;
use PDOStatement;
use stdClass;

class ActiveRecordTest extends \PHPUnit\Framework\TestCase
{
    public function testMagicSet()
    {
        $pdo_mock = $this->createStub(PDO::class);
        $record = new class($pdo_mock) extends ActiveRecord {
            public function getData()
            {
                return $this->data;
            }
            public function getDirty()
            {
                return $this->dirty;
            }
        };
        $record->name = 'John';
        $record->email = 'john@example.com';
        $this->assertEquals(['name' => 'John', 'email' => 'john@example.com' ], $record->getData());
        $this->assertEquals(['name' => 'John', 'email' => 'john@example.com' ], $record->getDirty());
    }

    public function testExecutePdoError()
    {
        $pdo_mock = $this->createStub(PDO::class);
        $pdo_mock->method('prepare')->willReturn(false);
        $pdo_mock->method('errorInfo')->willReturn(['HY000', 1, 'test']);
        $record = new class($pdo_mock) extends ActiveRecord {
        };
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('test');
        $record->execute('SELECT * FROM user');
    }

	public function testExecuteStatementError()
    {
        $statement_mock = $this->createStub(PDOStatement::class);
        $pdo_mock = $this->createStub(PDO::class);
        $pdo_mock->method('prepare')->willReturn($statement_mock);
        $statement_mock->method('execute')->willReturn(false);
        $statement_mock->method('errorInfo')->willReturn(['HY000', 1, 'test_statement']);
        $record = new class($pdo_mock) extends ActiveRecord {
        };
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('test_statement');
        $record->execute('SELECT * FROM user');
    }

    public function testUnsetSqlExpressions()
    {
        $pdo_mock = $this->createStub(PDO::class);
        $record = new class($pdo_mock) extends ActiveRecord {
        };
        $record->where = '1';
        unset($record->where);
        $this->assertEquals($record->where, null);
    }

    public function testCustomData()
    {
        $pdo_mock = $this->createStub(PDO::class);
        $record = new class($pdo_mock) extends ActiveRecord {
        };
        $record->setCustomData('test', 'something');
        $this->assertEquals('something', $record->test);
    }

	public function testCustomDataUnset()
	{
		$pdo_mock = $this->createStub(PDO::class);
		$record = new class($pdo_mock) extends ActiveRecord {
		};
		$record->setCustomData('test', 'something');
		unset($record->test);
		$this->assertEquals(null, $record->test);
	}

	public function testConstructBadDatabaseInput() {
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Database connection type not supported');
		$record = new class(new stdClass) extends ActiveRecord {
		};
	}

	public function testSetTableOnConstruct() {
		$record = new class(null, 'test_table') extends ActiveRecord {
			public function getTable() {
				return $this->table;
			}
		};
		$this->assertEquals('test_table', $record->getTable());
	}

}
