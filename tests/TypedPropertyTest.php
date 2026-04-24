<?php

namespace flight\tests;

use flight\tests\classes\TypedUser;
use PDO;

/**
 * Tests that insert() correctly assigns a typed int primary key
 * after insert when the subclass declares public int $id.
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

    public function testInsertSetsTypedIntId(): void
    {
        $user = new TypedUser($this->pdo);
        // Use dirty() to set values since the dirty-sync fix is separate
        $user->dirty(['name' => 'charlie', 'password' => 'hash3']);
        $user->insert();

        $this->assertIsInt($user->id, 'id should be int after insert, not string');
        $this->assertGreaterThan(0, $user->id);
    }

    public function testInsertPersistsWithTypedId(): void
    {
        $user = new TypedUser($this->pdo);
        $user->dirty(['name' => 'dave', 'password' => 'hash4']);
        $user->insert();

        // Verify persisted via raw query (avoids isHydrated dependency)
        $row = $this->pdo->query("SELECT * FROM user WHERE id = {$user->id}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('dave', $row['name']);
    }
}
