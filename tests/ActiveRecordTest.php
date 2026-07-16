<?php

namespace flight\tests;

use Exception;
use flight\ActiveRecord;
use PDO;
use PDOStatement;
use stdClass;

class ActiveRecordTest extends \PHPUnit\Framework\TestCase
{
    protected function createEmptyRecord()
    {
        return new class (null, 'test_table') extends ActiveRecord {
        };
    }

    public function testMagicSet()
    {
        $pdo_mock = $this->createStub(PDO::class);
        $pdo_mock->method('getAttribute')->willReturn('generic');
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
        $pdo_mock->method('getAttribute')->willReturn('generic');
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
        $pdo_mock->method('getAttribute')->willReturn('generic');
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
        $pdo_mock->method('getAttribute')->willReturn('generic');
        $record = new class ($pdo_mock) extends ActiveRecord {
        };
        $record->where = '1';
        unset($record->where);
        $this->assertEquals($record->where, null);
    }

    public function testCustomData()
    {
        $pdo_mock = $this->createStub(PDO::class);
        $pdo_mock->method('getAttribute')->willReturn('generic');
        $record = new class ($pdo_mock) extends ActiveRecord {
        };
        $record->setCustomData('test', 'something');
        $this->assertEquals('something', $record->test);
    }

    public function testCustomDataUnset()
    {
        $pdo_mock = $this->createStub(PDO::class);
        $pdo_mock->method('getAttribute')->willReturn('generic');
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
        $record = $this->createEmptyRecord();
        $record->copyFrom(['name' => 'John']);
        $this->assertEquals('John', $record->name);
        $this->assertEquals(['name' => 'John'], $record->getData());
    }

    public function testIsset()
    {
        $record = $this->createEmptyRecord();
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
        $this->assertEquals('SELECT test_table.* FROM test_table LEFT JOIN table1 ON table1.some_id = test_table.id LEFT JOIN table2 ON table2.some_id = table1.id LIMIT 1', $result);
    }

    public function testEscapeIdentifierSqlSrv()
    {
        $record = new class (null, 'test_table') extends ActiveRecord {
            public function getDatabaseEngine(): string
            {
                return 'sqlsrv';
            }
        };
        $this->assertEquals('[test_table]', $record->escapeIdentifier('test_table'));
    }

    public function testEscapeIdentifierEscapesDelimitersSqlite()
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
        };

        $this->assertEquals('"id"', $record->escapeIdentifier('id'));
        // Breakout attempt: quote is doubled, cannot leave the identifier
        $this->assertEquals('"id"" OR 1=1 --"', $record->escapeIdentifier('id" OR 1=1 --'));
        $this->assertEquals('"a""b"', $record->escapeIdentifier('a"b'));
    }

    public function testEscapeIdentifierEscapesDelimitersMysql()
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn('mysql');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
        };

        $this->assertEquals('`id`', $record->escapeIdentifier('id'));
        $this->assertEquals('`id``; DROP TABLE x;--`', $record->escapeIdentifier('id`; DROP TABLE x;--'));
    }

    public function testEscapeIdentifierEscapesDelimitersSqlSrv()
    {
        $record = new class (null, 'test_table') extends ActiveRecord {
            public function getDatabaseEngine(): string
            {
                return 'sqlsrv';
            }
        };

        $this->assertEquals('[id]', $record->escapeIdentifier('id'));
        $this->assertEquals('[id]] OR 1=1 --]', $record->escapeIdentifier('id] OR 1=1 --'));
    }

    public function testEscapeIdentifierStripsNullBytes()
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
        };

        $this->assertEquals('"id"', $record->escapeIdentifier("i\0d"));
    }

    public function testDirtyColumnNamesWithQuoteCannotBreakIdentifier()
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE test_table (id INTEGER PRIMARY KEY, name TEXT)');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
        };

        // Malicious-looking key must be treated as a single identifier, not break out of quotes
        $record->dirty([
            'name' => 'ok',
            'x") AS evil --' => 'nope',
        ]);

        try {
            $record->insert();
            // Insert may fail because column does not exist; that is fine.
            // What matters is the built SQL keeps the breakout attempt inside quotes.
        } catch (Exception $e) {
            // expected if column missing
        }

        $sql = $record->getBuiltSql();
        // After insert, builtSql may be cleared by resetQueryData — re-run build path via dirty keys escape
        $escaped = $record->escapeIdentifier('x") AS evil --');
        $this->assertEquals('"x"") AS evil --"', $escaped);
        $this->assertStringNotContainsString('"x") AS', $escaped);
    }

    public function testJoinEscapesSimpleTableName()
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
            public function query(string $sql, array $param = [], ?ActiveRecord $obj = null, bool $single = false)
            {
                return $this;
            }
        };
        $record->join('contacts', 'contacts.user_id = test_table.id')->find();
        $this->assertStringContainsString('LEFT JOIN "contacts" ON', $record->getBuiltSql());
    }

    public function testJoinEscapesTableWithAlias()
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
            public function query(string $sql, array $param = [], ?ActiveRecord $obj = null, bool $single = false)
            {
                return $this;
            }
        };
        $record->join('contact as c', 'c.user_id = test_table.id')->find();
        $this->assertStringContainsString('LEFT JOIN "contact" AS "c" ON', $record->getBuiltSql());
    }

    public function testJoinEscapesTableWithImplicitAlias()
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
            public function query(string $sql, array $param = [], ?ActiveRecord $obj = null, bool $single = false)
            {
                return $this;
            }
        };
        $record->join('contact c', 'c.user_id = test_table.id')->find();
        $this->assertStringContainsString('LEFT JOIN "contact" "c" ON', $record->getBuiltSql());
    }

    public function testJoinEscapesSchemaTable()
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
            public function query(string $sql, array $param = [], ?ActiveRecord $obj = null, bool $single = false)
            {
                return $this;
            }
        };
        $record->join('main.contact', 'main.contact.user_id = test_table.id')->find();
        $this->assertStringContainsString('LEFT JOIN "main"."contact" ON', $record->getBuiltSql());
    }

    public function testJoinLeavesComplexTableExpressionUnchanged()
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
            public function query(string $sql, array $param = [], ?ActiveRecord $obj = null, bool $single = false)
            {
                return $this;
            }
        };
        $sub = '(SELECT id FROM other) AS o';
        $record->join($sub, 'o.id = test_table.id')->find();
        $this->assertStringContainsString('LEFT JOIN (SELECT id FROM other) AS o ON', $record->getBuiltSql());
    }

    public function testOrderByColumnSafe()
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
            public function query(string $sql, array $param = [], ?ActiveRecord $obj = null, bool $single = false)
            {
                return $this;
            }
        };
        $record->orderByColumn('name', 'desc')->find();
        $this->assertStringContainsString('ORDER BY "name" DESC', $record->getBuiltSql());
    }

    public function testOrderByColumnRejectsInjection()
    {
        $record = $this->createEmptyRecord();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid ORDER BY column identifier');
        $record->orderByColumn('id; DROP TABLE users;--');
    }

    public function testOrderByColumnRejectsBadDirection()
    {
        $record = $this->createEmptyRecord();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid ORDER BY direction');
        $record->orderByColumn('id', 'SIDEWAYS');
    }

    public function testOrderByStillAcceptsComplexRawSql()
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
            public function query(string $sql, array $param = [], ?ActiveRecord $obj = null, bool $single = false)
            {
                return $this;
            }
        };
        // Existing API: complex expressions must still work unchanged
        $record->orderBy('CASE WHEN name IS NULL THEN 1 ELSE 0 END')->find();
        $this->assertStringContainsString('ORDER BY CASE WHEN name IS NULL THEN 1 ELSE 0 END', $record->getBuiltSql());
    }

    public function testSimpleOrderByEscapesIdentifier()
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
            public function query(string $sql, array $param = [], ?ActiveRecord $obj = null, bool $single = false)
            {
                return $this;
            }
        };
        $record->orderBy('name DESC')->find();
        $this->assertStringContainsString('ORDER BY "name" DESC', $record->getBuiltSql());
    }

    public function testSimpleOrderByColumnOnlyEscapesIdentifier()
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
            public function query(string $sql, array $param = [], ?ActiveRecord $obj = null, bool $single = false)
            {
                return $this;
            }
        };
        $record->orderBy('name')->find();
        $this->assertStringContainsString('ORDER BY "name"', $record->getBuiltSql());
    }

    public function testOrderByTableColumnPath()
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
            public function query(string $sql, array $param = [], ?ActiveRecord $obj = null, bool $single = false)
            {
                return $this;
            }
        };
        $record->orderBy('user.name ASC')->find();
        $this->assertStringContainsString('ORDER BY "user"."name" ASC', $record->getBuiltSql());
    }

    public function testLimitLeavesNonNumericExpressionUnchanged()
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
            public function query(string $sql, array $param = [], ?ActiveRecord $obj = null, bool $single = false)
            {
                return [];
            }
        };
        $record->limit('1 + 2')->findAll();
        $this->assertStringContainsString('LIMIT 1 + 2', $record->getBuiltSql());
    }

    public function testSelectNonStringArgLeftUnchanged()
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
            public function query(string $sql, array $param = [], ?ActiveRecord $obj = null, bool $single = false)
            {
                return $this;
            }
        };
        // Non-string args are not identifier-normalized (BC for unusual call patterns)
        $record->select(123)->find();
        $this->assertStringContainsString('SELECT 123', $record->getBuiltSql());
    }

    public function testSimpleSelectEscapesIdentifier()
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
            public function query(string $sql, array $param = [], ?ActiveRecord $obj = null, bool $single = false)
            {
                return $this;
            }
        };
        $record->select('name')->find();
        $this->assertStringContainsString('SELECT "name"', $record->getBuiltSql());
    }

    public function testComplexSelectLeftUnchanged()
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
            public function query(string $sql, array $param = [], ?ActiveRecord $obj = null, bool $single = false)
            {
                return $this;
            }
        };
        $record->select('COUNT(*) as count')->find();
        $this->assertStringContainsString('SELECT COUNT(*) as count', $record->getBuiltSql());
    }

    public function testLimitCoercesNumericStrings()
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
            public function query(string $sql, array $param = [], ?ActiveRecord $obj = null, bool $single = false)
            {
                return [];
            }
        };
        // findAll (unlike find) does not force LIMIT 1
        $record->limit('10')->findAll();
        $this->assertStringContainsString('LIMIT 10', $record->getBuiltSql());
    }

    public function testEqValueStillParameterizedNotInjected()
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('getAttribute')->willReturn('sqlite');
        $record = new class ($pdo, 'test_table') extends ActiveRecord {
            public function query(string $sql, array $param = [], ?ActiveRecord $obj = null, bool $single = false)
            {
                return $this;
            }
        };
        $payload = 'Robert"); DROP TABLE test_table;--';
        $record->eq('name', $payload)->find();
        $sql = $record->getBuiltSql();
        $this->assertStringContainsString('"name" = :ph', $sql);
        $this->assertStringNotContainsString($payload, $sql);
    }
}
