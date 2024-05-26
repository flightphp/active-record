<?php

declare(strict_types=1);

namespace tests\commands;

use Ahc\Cli\Application;
use Ahc\Cli\IO\Interactor;
use flight\commands\RecordCommand;
use PHPUnit\Framework\TestCase;

class RecordCommandTest extends TestCase
{
    protected static $in = __DIR__ . '/input.test';
    protected static $ou = __DIR__ . '/output.test';

    public function setUp(): void
    {
        file_put_contents(static::$in, '', LOCK_EX);
        file_put_contents(static::$ou, '', LOCK_EX);

        $pdo = new \PDO('sqlite::memory:');
        // create a users table with an id, name, some decimal type, some binary or blob type and an int type.
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, price DECIMAL(10,2), data BLOB, created_dt TEXT)');
    }

    public function tearDown(): void
    {
        // Make sure we clean up after ourselves:
        if (file_exists(static::$in)) {
            unlink(static::$in);
        }
        if (file_exists(static::$ou)) {
            unlink(static::$ou);
        }

        if (file_exists(__DIR__ . '/records/UserRecord.php')) {
            unlink(__DIR__ . '/records/UserRecord.php');
        }

        if (file_exists(__DIR__ . '/records/StatusRecord.php')) {
            unlink(__DIR__ . '/records/StatusRecord.php');
        }

        if (file_exists(__DIR__ . '/records/')) {
            rmdir(__DIR__ . '/records/');
        }
    }

    protected function newApp(string $in = '')
    {
        $app = new Application('Test App', '0.0.1', fn () => false);

        return $app->io($this->newInteractor($in));
    }

    protected function newInteractor(string $in = '')
    {
        file_put_contents(static::$in, $in, LOCK_EX);

        return new Interactor(static::$in, static::$ou);
    }

    public function testConfigAppRootNotSet()
    {
        $app = $this->newApp('test', '0.0.1');
        $app->add(new RecordCommand([]));
        $app->handle(['runway', 'make:record', 'test']);

        $this->assertStringContainsString('app_root not set in .runway-config.json', file_get_contents(static::$ou));
    }

    public function testConfigDatabaseConfigNotSet()
    {
        $commands = <<<TEXT
            sqlite
            :memory:
            TEXT;
        $app = $this->newApp($commands);
        $app->add(new RecordCommand(['app_root' => 'tests/commands/']));
        $app->handle(['runway', 'make:record', 'users']);

        $this->assertStringContainsString('Database configuration not found. Please provide the following details:', file_get_contents(static::$ou));
    }

    public function testRecordAlreadyExists()
    {
        mkdir(__DIR__ . '/records/');
        file_put_contents(__DIR__ . '/records/UserRecord.php', '<?php class UserRecord {}');
        $app = $this->newApp();
        $app->add(new RecordCommand([
            'app_root' => 'tests/commands/',
            'database' => [
                'driver' => 'sqlite',
                'file_path' => ':memory:'
            ]
        ]));
        $app->handle(['runway', 'make:record', 'users']);

        $this->assertStringContainsString('UserRecord already exists.', file_get_contents(static::$ou));
    }

    public function testRecordAlreadyExistsMysql()
    {
        mkdir(__DIR__ . '/records/');
        file_put_contents(__DIR__ . '/records/UserRecord.php', '<?php class UserRecord {}');
        $commands = <<<TEXT
            mysql
            localhost
            3306
            test_db
            utf8mb4
                
                
            TEXT;
        $app = $this->newApp($commands);
        $app->add(new RecordCommand([
            'app_root' => 'tests/commands/'
        ]));
        $app->handle(['runway', 'make:record', 'users']);

        $this->assertStringContainsString('UserRecord already exists.', file_get_contents(static::$ou));
    }

    public function testRecordAlreadyExistsPgsql()
    {
        mkdir(__DIR__ . '/records/');
        file_put_contents(__DIR__ . '/records/UserRecord.php', '<?php class UserRecord {}');
        $commands = <<<TEXT
            mysql
            localhost
            3306
            test_db
            utf8mb4


            TEXT;
        $app = $this->newApp($commands);
        $app->add(new RecordCommand([
            'app_root' => 'tests/commands/'
        ]));
        $app->handle(['runway', 'make:record', 'users']);

        $this->assertStringContainsString('UserRecord already exists.', file_get_contents(static::$ou));
    }

    public function testSqliteCreation()
    {
        $app = $this->newApp();
        $this->createMock(\PDO::class);
        $statementMock = $this->createMock(\PDOStatement::class);
        $statementMock->method('fetchAll')->willReturn([
            ['name' => 'id', 'type' => 'INTEGER', 'pk' => 1],
            ['name' => 'name', 'type' => 'TEXT', 'pk' => 0],
            ['name' => 'price', 'type' => 'DECIMAL(10,2)', 'pk' => 0],
            ['name' => 'data', 'type' => 'BLOB', 'pk' => 0],
            ['name' => 'created_dt', 'type' => 'TEXT', 'pk' => 0]
        ]);
        $pdoMock = $this->createMock(\PDO::class);
        $pdoMock->method('query')->willReturn($statementMock);
        $RecordCommand = new class ([
            'app_root' => 'tests/commands/',
            'database' => [
                'driver' => 'sqlite',
                'file_path' => ':memory:'
            ]
            ], $pdoMock) extends RecordCommand {
            protected $pdoMock = null;
            public function __construct(array $config, \PDO $pdoMock)
            {
                parent::__construct($config);
                $this->pdoMock = $pdoMock;
            }
            public function getPdoConnection(): \PDO
            {
                return $this->pdoMock;
            }
        };
        $app->add($RecordCommand);
        $app->handle(['runway', 'make:record', 'users']);

        $this->assertFileExists(__DIR__ . '/records/UserRecord.php');
        $file_contents = file_get_contents(__DIR__ . '/records/UserRecord.php');
        $this->assertStringContainsString('@property int $id', $file_contents);
        $this->assertStringContainsString('@property string $name', $file_contents);
        $this->assertStringContainsString('@property float $price', $file_contents);
        $this->assertStringContainsString('@property mixed $data', $file_contents);
        $this->assertStringContainsString('@property string $created_dt', $file_contents);
    }

    public function testMysqlCreation()
    {
        $app = $this->newApp();
        $this->createMock(\PDO::class);
        $statementMock = $this->createMock(\PDOStatement::class);
        $statementMock->method('fetchAll')->willReturn([
            ['Field' => 'id', 'Type' => 'int(11)', 'Key' => 'PRI'],
            ['Field' => 'name', 'Type' => 'varchar(255)', 'Key' => ''],
            ['Field' => 'price', 'Type' => 'decimal(10,2)', 'Key' => ''],
            ['Field' => 'data', 'Type' => 'blob', 'Key' => ''],
            ['Field' => 'created_dt', 'Type' => 'timestamp', 'Key' => '']
        ]);
        $pdoMock = $this->createMock(\PDO::class);
        $pdoMock->method('query')->willReturn($statementMock);
        $RecordCommand = new class ([
            'app_root' => 'tests/commands/',
            'database' => [
                'driver' => 'mysql'
            ]
            ], $pdoMock) extends RecordCommand {
            protected $pdoMock = null;
            public function __construct(array $config, \PDO $pdoMock)
            {
                parent::__construct($config);
                $this->pdoMock = $pdoMock;
            }
            public function getPdoConnection(): \PDO
            {
                return $this->pdoMock;
            }
        };
        $app->add($RecordCommand);
        $app->handle(['runway', 'make:record', 'users']);

        $this->assertFileExists(__DIR__ . '/records/UserRecord.php');
        $file_contents = file_get_contents(__DIR__ . '/records/UserRecord.php');
        $this->assertStringContainsString('@property int $id', $file_contents);
        $this->assertStringContainsString('@property string $name', $file_contents);
        $this->assertStringContainsString('@property float $price', $file_contents);
        $this->assertStringContainsString('@property mixed $data', $file_contents);
        $this->assertStringContainsString('@property string $created_dt', $file_contents);
    }

    public function testPgsqlCreation()
    {
        $app = $this->newApp();
        $this->createMock(\PDO::class);
        $statementMock = $this->createMock(\PDOStatement::class);
        $statementMock->method('fetchAll')->willReturn([
            ['column_name' => 'id', 'data_type' => 'integer', 'is_nullable' => 'NO', 'column_default' => null],
            ['column_name' => 'name', 'data_type' => 'character varying', 'is_nullable' => 'YES', 'column_default' => null],
            ['column_name' => 'price', 'data_type' => 'numeric', 'is_nullable' => 'YES', 'column_default' => null],
            ['column_name' => 'data', 'data_type' => 'bytea', 'is_nullable' => 'YES', 'column_default' => null],
            ['column_name' => 'created_dt', 'data_type' => 'timestamp without time zone', 'is_nullable' => 'YES', 'column_default' => null]
        ]);
        $pdoMock = $this->createMock(\PDO::class);
        $pdoMock->method('query')->willReturn($statementMock);
        $RecordCommand = new class ([
            'app_root' => 'tests/commands/',
            'database' => [
                'driver' => 'pgsql'
            ]
            ], $pdoMock) extends RecordCommand {
            protected $pdoMock = null;
            public function __construct(array $config, \PDO $pdoMock)
            {
                parent::__construct($config);
                $this->pdoMock = $pdoMock;
            }
            public function getPdoConnection(): \PDO
            {
                return $this->pdoMock;
            }
        };
        $app->add($RecordCommand);
        $app->handle(['runway', 'make:record', 'users']);

        $this->assertFileExists(__DIR__ . '/records/UserRecord.php');
        $file_contents = file_get_contents(__DIR__ . '/records/UserRecord.php');
        $this->assertStringContainsString('@property int $id', $file_contents);
        $this->assertStringContainsString('@property string $name', $file_contents);
        $this->assertStringContainsString('@property float $price', $file_contents);
        $this->assertStringContainsString('@property mixed $data', $file_contents);
        $this->assertStringContainsString('@property string $created_dt', $file_contents);
    }

    public function testSqliteCreationWithMultipleSs()
    {
        $app = $this->newApp();
        $this->createMock(\PDO::class);
        $statementMock = $this->createMock(\PDOStatement::class);
        $statementMock->method('fetchAll')->willReturn([
            ['name' => 'id', 'type' => 'INTEGER', 'pk' => 1],
            ['name' => 'name', 'type' => 'TEXT', 'pk' => 0],
            ['name' => 'price', 'type' => 'DECIMAL(10,2)', 'pk' => 0],
            ['name' => 'data', 'type' => 'BLOB', 'pk' => 0],
            ['name' => 'created_dt', 'type' => 'TEXT', 'pk' => 0]
        ]);
        $pdoMock = $this->createMock(\PDO::class);
        $pdoMock->method('query')->willReturn($statementMock);
        $RecordCommand = new class ([
            'app_root' => 'tests/commands/',
            'database' => [
                'driver' => 'sqlite',
                'file_path' => ':memory:'
            ]
            ], $pdoMock) extends RecordCommand {
            protected $pdoMock = null;
            public function __construct(array $config, \PDO $pdoMock)
            {
                parent::__construct($config);
                $this->pdoMock = $pdoMock;
            }
            public function getPdoConnection(): \PDO
            {
                return $this->pdoMock;
            }
        };
        $app->add($RecordCommand);
        $app->handle(['runway', 'make:record', 'statuses']);

        $this->assertFileExists(__DIR__ . '/records/StatusRecord.php');
        $file_contents = file_get_contents(__DIR__ . '/records/StatusRecord.php');
        $this->assertStringContainsString('@property int $id', $file_contents);
        $this->assertStringContainsString('@property string $name', $file_contents);
        $this->assertStringContainsString('@property float $price', $file_contents);
        $this->assertStringContainsString('@property mixed $data', $file_contents);
        $this->assertStringContainsString('@property string $created_dt', $file_contents);
    }
}
