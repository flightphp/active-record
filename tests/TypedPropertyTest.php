<?php

namespace flight\tests;

use flight\tests\classes\TypedUser;
use PDO;

/**
 * Tests that ActiveRecord works correctly with subclasses that declare
 * typed public properties (e.g. public int $id, public string $name).
 */
class TypedPropertyTest extends \PHPUnit\Framework\TestCase
{
    protected PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/classes/TypedUser.php';
        @unlink('test_typed.db');
    }

    public static function tearDownAfterClass(): void
    {
        @unlink('test_typed.db');
    }

    public function setUp(): void
    {
        $this->pdo = new PDO('sqlite:test_typed.db');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS user (
            id INTEGER PRIMARY KEY,
            name TEXT,
            password TEXT,
            created_dt TEXT
        )");
    }

    public function tearDown(): void
    {
        $this->pdo->exec("DROP TABLE IF EXISTS user");
    }

    public function testInsertWithTypedProperties(): void
    {
        $user = new TypedUser($this->pdo);
        $user->name = 'charlie';
        $user->password = 'hash3';
        $user->insert();

        // Verify persisted via raw query
        $row = $this->pdo->query("SELECT * FROM user WHERE name = 'charlie'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($row, 'insert should persist when properties are set directly');
        $this->assertSame('charlie', $row['name']);
        $this->assertSame('hash3', $row['password']);
    }

    public function testInsertSetsTypedIntId(): void
    {
        $user = new TypedUser($this->pdo);
        $user->name = 'charlie';
        $user->password = 'hash3';
        $user->insert();

        $this->assertIsInt($user->id, 'id should be int after insert, not string');
        $this->assertGreaterThan(0, $user->id);
    }

    public function testUpdateWithTypedProperties(): void
    {
        $this->pdo->exec("INSERT INTO user (name, password) VALUES ('eve', 'hash5')");

        $user = new TypedUser($this->pdo);
        $user->eq('name', 'eve')->find();

        $user->name = 'eve_updated';
        $user->save();

        $row = $this->pdo->query("SELECT * FROM user WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('eve_updated', $row['name'], 'update should persist changed typed property');
    }

    public function testUpdateDoesNotTouchUnchangedFields(): void
    {
        $this->pdo->exec("INSERT INTO user (name, password) VALUES ('frank', 'hash6')");

        $user = new TypedUser($this->pdo);
        $user->eq('id', 1)->find();
        $user->name = 'frank_updated';
        $user->save();

        $row = $this->pdo->query("SELECT * FROM user WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('frank_updated', $row['name']);
        $this->assertSame('hash6', $row['password'], 'unchanged field should not be modified');
    }

    public function testFindIsHydrated(): void
    {
        $this->pdo->exec("INSERT INTO user (name, password) VALUES ('alice', 'hash1')");

        $user = new TypedUser($this->pdo);
        $user->eq('id', 1)->find();

        $this->assertTrue($user->isHydrated(), 'isHydrated() should return true after find()');
        $this->assertSame(1, $user->id);
        $this->assertSame('alice', $user->name);
    }

    public function testFindNoResultIsNotHydrated(): void
    {
        $user = new TypedUser($this->pdo);
        $user->eq('id', 999)->find();

        $this->assertFalse($user->isHydrated(), 'isHydrated() should return false when no row is found');
    }

    public function testFindAllIsHydrated(): void
    {
        $this->pdo->exec("INSERT INTO user (name, password) VALUES ('alice', 'hash1')");
        $this->pdo->exec("INSERT INTO user (name, password) VALUES ('bob', 'hash2')");

        $users = (new TypedUser($this->pdo))->findAll();

        $this->assertCount(2, $users);
        $this->assertTrue($users[0]->isHydrated(), 'findAll() results should be hydrated');
        $this->assertTrue($users[1]->isHydrated());
    }

    public function testSyncDirtySkipsPropertiesAlreadyInDirty(): void
    {
        $user = new TypedUser($this->pdo);
        $user->name = 'prefilled';
        $user->password = 'hash';

        $sync = new \ReflectionMethod(\flight\ActiveRecord::class, 'syncDirtyFromProperties');
        $sync->setAccessible(true);
        $sync->invoke($user, false);

        $dirtyProp = new \ReflectionProperty(\flight\ActiveRecord::class, 'dirty');
        $dirtyProp->setAccessible(true);
        $dirty = $dirtyProp->getValue($user);
        $this->assertArrayHasKey('name', $dirty);

        // Second sync should hit the "already in dirty" continue branch
        $sync->invoke($user, false);
        $this->assertSame('prefilled', $dirtyProp->getValue($user)['name']);
    }
}
