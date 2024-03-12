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
        $record = new class ($pdo_mock) extends ActiveRecord {
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
        $record = new class ($pdo_mock) extends ActiveRecord {
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
        $record = new class ($pdo_mock) extends ActiveRecord {
        };
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('test_statement');
        $record->execute('SELECT * FROM user');
    }

    public function testUnsetSqlExpressions()
    {
        $pdo_mock = $this->createStub(PDO::class);
        $record = new class ($pdo_mock) extends ActiveRecord {
        };
        $record->where = '1';
        unset($record->where);
        $this->assertEquals($record->where, null);
    }

    public function testCustomData()
    {
        $pdo_mock = $this->createStub(PDO::class);
        $record = new class ($pdo_mock) extends ActiveRecord {
        };
        $record->setCustomData('test', 'something');
        $this->assertEquals('something', $record->test);
    }

    public function testCustomDataUnset()
    {
        $pdo_mock = $this->createStub(PDO::class);
        $record = new class ($pdo_mock) extends ActiveRecord {
        };
        $record->setCustomData('test', 'something');
        unset($record->test);
        $this->assertEquals(null, $record->test);
    }

    public function testConstructBadDatabaseInput()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Database connection type not supported');
        $record = new class (new stdClass()) extends ActiveRecord {
        };
    }

    public function testSetTableOnConstruct()
    {
        $record = new class (null, 'test_table') extends ActiveRecord {
            public function getTable()
            {
                return $this->table;
            }
        };
        $this->assertEquals('test_table', $record->getTable());
    }

    public function testIsDirty()
    {
        $record = new class (null, 'test_table') extends ActiveRecord {
        };
        $record->name = 'John';
        $this->assertTrue($record->isDirty());
        unset($record->name);
        $this->assertFalse($record->isDirty());
    }

    public function testCopyFrom()
    {
        $record = new class (null, 'test_table') extends ActiveRecord {
        };
        $record->copyFrom(['name' => 'John']);
        $this->assertEquals('John', $record->name);
        $this->assertEquals(['name' => 'John'], $record->getData());
    }

    public function testIsset()
    {
        $record = new class (null, 'test_table') extends ActiveRecord {
        };
        $record->name = 'John';
        $this->assertTrue(isset($record->name));
        $this->assertFalse(isset($record->email));
    }

    public function testMultipleJoins()
    {
        $record = new class (null, 'test_table') extends ActiveRecord {
            public function query(string $sql, array $param = [], ?ActiveRecord $obj = null, bool $single = false)
            {
                return $this;
            }
        };
        $record->join('table1', 'table1.some_id = test_table.id');
        $record->join('table2', 'table2.some_id = table1.id');
        $result = $record->find()->getBuiltSql();
        $this->assertEquals('SELECT test_table.* FROM test_table  LEFT JOIN table1 ON table1.some_id = test_table.id LEFT JOIN table2 ON table2.some_id = table1.id       LIMIT 1  ', $result);
    }
}
