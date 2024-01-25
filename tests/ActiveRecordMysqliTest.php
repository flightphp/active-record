<?php

namespace flight\tests;

use Exception;
use flight\ActiveRecord;
use flight\database\mysqli\MysqliAdapter;
use flight\database\mysqli\MysqliStatementAdapter;
use flight\tests\classes\Contact;
use flight\tests\classes\User;
use mysqli;
use mysqli_result;
use mysqli_stmt;
use PDO;
use ReflectionClass;

class ActiveRecordMysqliTest extends \PHPUnit\Framework\TestCase
{
    protected $ActiveRecord;

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/classes/User.php';
        require_once __DIR__ . '/classes/Contact.php';
    }

    public function testInsert()
    {
        $mysqli = $this->createMock(mysqli::class);
        $mysqli_stmt = $this->createMock(mysqli_stmt::class);
        $mysqli_stmt->method('execute')->willReturn(true);
        $mysqli->method('prepare')->willReturn($mysqli_stmt);
        $connection = new class ($mysqli) extends MysqliAdapter {
            public function lastInsertId()
            {
                return 1;
            }
        };
        $user = new User($connection);
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();
        $this->assertEquals(1, $user->id);
    }

    public function testEdit()
    {
        $mysqli = $this->createMock(mysqli::class);
        $mysqli_stmt = $this->createMock(mysqli_stmt::class);
        $mysqli_stmt->method('execute')->will($this->onConsecutiveCalls(true, true));
        $mysqli->method('prepare')->willReturn($mysqli_stmt);
        $connection = new class ($mysqli) extends MysqliAdapter {
            public function lastInsertId()
            {
                return 1;
            }
        };
        $original_password = md5('demo');
        $user = new User($connection);
        $user->name = 'demo';
        $user->password = $original_password;
        $user->insert();
        $original_id = $user->id;
        $user->name = 'demo1';
        $user->password = md5('demo1');
        $user->update();
        $this->assertGreaterThan(0, $user->id);
        $this->assertEquals('demo1', $user->name);
        $this->assertNotEquals($original_password, $user->password);
        $this->assertEquals($original_id, $user->id);
    }

    public function testQuery()
    {
        $mysqli = $this->createMock(mysqli::class);
        $mysqli_stmt = $this->createMock(mysqli_stmt::class);
        $mysqli_result = $this->createMock(mysqli_result::class);
        $mysqli_result->method('fetch_assoc')->willReturn(['id' => 1, 'name' => 'demo', 'password' => md5('demo')]);
        $mysqli_stmt->method('execute')->willReturn(true);
        $mysqli_stmt->method('bind_param')->willReturn(true);
        $mysqli_stmt->method('get_result')->willReturn($mysqli_result);
        $mysqli->method('prepare')->willReturn($mysqli_stmt);
        $connection = new class ($mysqli) extends MysqliAdapter {
            public function lastInsertId()
            {
                return 1;
            }
        };
        $user = new User($connection);
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();
        $user->clearData();
        $user->isNotNull('id')->eq('id', 1)->lt('id', 2)->gt('id', 0)->find();
        $this->assertSame(1, $user->id);
        $this->assertSame('demo', $user->name);
    }

    public function testDelete()
    {
        $mysqli = $this->createMock(mysqli::class);
        $mysqli_stmt = $this->createMock(mysqli_stmt::class);
        $mysqli_stmt->method('execute')->willReturn(true);
        $mysqli_stmt->method('bind_param')->willReturn(true);
        $mysqli->method('prepare')->willReturn($mysqli_stmt);
        $connection = new class ($mysqli) extends MysqliAdapter {
            public function lastInsertId()
            {
                return 1;
            }
        };
        $user = new User($connection);
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();

        $this->assertTrue($user->delete());

        // This object will still hold onto the data from the database if you still need it.
        $this->assertEquals(1, $user->id);
    }

    public function testExecuteStatementError()
    {
        $mysqli_stmt = $this->createMock(mysqli_stmt::class);
        $mysqli_stmt->method('execute')->willReturn(false);
        $MysqliStatementAdapter = new class ($mysqli_stmt) extends MysqliStatementAdapter {
            protected function getErrorList(): array
            {
                return [ [ 'sqlstate' => 'HY000', 'errno' => 1, 'error' => 'test_statement'] ];
            }
        };
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('test_statement');
        $MysqliStatementAdapter->execute();
    }

    public function testFetchWithParams()
    {
        $mysqli_stmt = $this->createMock(mysqli_stmt::class);
        $mysqli_result = $this->createMock(mysqli_result::class);
        $mysqli_result->method('fetch_assoc')->willReturn(['id' => 1, 'name' => 'demo', 'password' => md5('demo')]);
        $mysqli_stmt->method('get_result')->willReturn($mysqli_result);

        $MysqliStatementAdapter = new class ($mysqli_stmt) extends MysqliStatementAdapter {
        };
        $user = new class extends User {
            public function __construct()
            {
            }
        };
        $MysqliStatementAdapter->fetch($user);
        $this->assertSame(1, $user->id);
        $this->assertSame('demo', $user->name);
    }

    public function testPrepareError()
    {
        $mysqli = $this->createMock(mysqli::class);
        $mysqli->method('prepare')->willReturn(false);
        $MysqliAdapter = new class ($mysqli) extends MysqliAdapter {
            protected function getErrorList(): array
            {
                return [ [ 'sqlstate' => 'HY000', 'errno' => 1, 'error' => 'test_statement'] ];
            }
        };
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('test_statement');
        $MysqliAdapter->prepare("SELECT * FROM users");
    }

    public function testConstructTransformAndPersist()
    {
        $mysqli = $this->createMock(mysqli::class);
        $contact = new Contact($mysqli);
        $db_connection = $contact->getDatabaseConnection();
        $this->assertNotEquals($mysqli, $db_connection);
        $this->assertInstanceOf(MysqliAdapter::class, $db_connection);
    }
}
