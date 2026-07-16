<?php

declare(strict_types=1);

namespace flight\tests;

use Exception;
use flight\tests\classes\Contact;
use flight\tests\classes\QueryCountingAdapter;
use flight\tests\classes\User;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Test class for eager loading functionality
 */
class EagerLoadingTest extends TestCase
{
    protected PDO $pdo;
    protected QueryCountingAdapter $countingAdapter;

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/classes/User.php';
        require_once __DIR__ . '/classes/Contact.php';
        require_once __DIR__ . '/classes/QueryCountingAdapter.php';
    }

    public function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create tables
        $this->pdo->exec('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, password TEXT, created_dt DATETIME)');
        $this->pdo->exec('CREATE TABLE contact (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, email TEXT, address TEXT)');

        // Insert test data
        $this->pdo->exec("INSERT INTO user (name, password, created_dt) VALUES ('User 1', 'pass1', '2024-01-01 00:00:00')");
        $this->pdo->exec("INSERT INTO user (name, password, created_dt) VALUES ('User 2', 'pass2', '2024-01-02 00:00:00')");
        $this->pdo->exec("INSERT INTO user (name, password, created_dt) VALUES ('User 3', 'pass3', '2024-01-03 00:00:00')");

        $this->pdo->exec("INSERT INTO contact (user_id, email, address) VALUES (1, 'user1-contact1@example.com', 'Address 1-1')");
        $this->pdo->exec("INSERT INTO contact (user_id, email, address) VALUES (1, 'user1-contact2@example.com', 'Address 1-2')");
        $this->pdo->exec("INSERT INTO contact (user_id, email, address) VALUES (2, 'user2-contact1@example.com', 'Address 2-1')");
        $this->pdo->exec("INSERT INTO contact (user_id, email, address) VALUES (3, 'user3-contact1@example.com', 'Address 3-1')");

        // Create counting adapter
        $pdoAdapter = new \flight\database\pdo\PdoAdapter($this->pdo);
        $this->countingAdapter = new QueryCountingAdapter($pdoAdapter);
    }

    public function testHasManyEagerLoading(): void
    {
        $user = new User($this->countingAdapter);

        // Eager load contacts
        $users = $user->with('contacts')->findAll();

        $this->assertCount(3, $users);

        // Verify contacts are loaded
        $this->assertCount(2, $users[0]->contacts);
        $this->assertCount(1, $users[1]->contacts);
        $this->assertCount(1, $users[2]->contacts);

        // Verify data
        $this->assertEquals('user1-contact1@example.com', $users[0]->contacts[0]->email);
        $this->assertEquals('user1-contact2@example.com', $users[0]->contacts[1]->email);
    }

    public function testHasOneEagerLoading(): void
    {
        $user = new User($this->countingAdapter);

        // Eager load single contact
        $users = $user->with('contact')->findAll();

        $this->assertCount(3, $users);

        // Verify single contact is loaded for each user
        $this->assertInstanceOf(Contact::class, $users[0]->contact);
        $this->assertInstanceOf(Contact::class, $users[1]->contact);
        $this->assertInstanceOf(Contact::class, $users[2]->contact);

        // Verify it's the first contact (based on order in relation definition)
        $this->assertNotEmpty($users[0]->contact->email);
    }

    public function testBelongsToEagerLoading(): void
    {
        $contact = new Contact($this->countingAdapter);

        // Eager load users
        $contacts = $contact->with('user')->findAll();

        $this->assertCount(4, $contacts);

        // Verify users are loaded
        $this->assertInstanceOf(User::class, $contacts[0]->user);
        $this->assertInstanceOf(User::class, $contacts[1]->user);
        $this->assertInstanceOf(User::class, $contacts[2]->user);
        $this->assertInstanceOf(User::class, $contacts[3]->user);

        // Verify correct users
        $this->assertEquals('User 1', $contacts[0]->user->name);
        $this->assertEquals('User 1', $contacts[1]->user->name);
        $this->assertEquals('User 2', $contacts[2]->user->name);
        $this->assertEquals('User 3', $contacts[3]->user->name);
    }

    public function testMultipleRelationsEagerLoading(): void
    {
        $user = new User($this->countingAdapter);

        // Eager load multiple relations
        $users = $user->with(['contacts', 'contact'])->findAll();

        $this->assertCount(3, $users);

        // Verify both relations are loaded
        $this->assertIsArray($users[0]->contacts);
        $this->assertInstanceOf(Contact::class, $users[0]->contact);
    }

    public function testQueryCountReduction(): void
    {
        // Test lazy loading (N+1 problem)
        $this->countingAdapter->reset();
        $user = new User($this->countingAdapter);
        $users = $user->findAll(); // 1 query

        foreach ($users as $u) {
            $contacts = $u->contacts; // N queries (lazy loaded)
        }
        $lazyQueryCount = $this->countingAdapter->getQueryCount();

        // Test eager loading
        $this->countingAdapter->reset();
        $user = new User($this->countingAdapter);
        $users = $user->with('contacts')->findAll(); // 2 queries total

        foreach ($users as $u) {
            $contacts = $u->contacts; // 0 additional queries
        }
        $eagerQueryCount = $this->countingAdapter->getQueryCount();

        // Eager loading should use significantly fewer queries
        $this->assertEquals(2, $eagerQueryCount); // 1 for users + 1 for contacts
        $this->assertGreaterThan($eagerQueryCount, $lazyQueryCount);
    }

    public function testEagerLoadingWithFind(): void
    {
        $user = new User($this->countingAdapter);

        // Eager load with find()
        $foundUser = $user->with('contacts')->find(1);

        $this->assertTrue($foundUser->isHydrated());
        $this->assertEquals('User 1', $foundUser->name);
        $this->assertCount(2, $foundUser->contacts);
    }

    public function testEagerLoadingWithBackReference(): void
    {
        $user = new User($this->countingAdapter);

        // Eager load with back reference
        $users = $user->with('contacts_with_backref')->findAll();

        $this->assertCount(3, $users);

        // Verify back reference is set
        $firstContact = $users[0]->contacts_with_backref[0];
        $this->assertInstanceOf(User::class, $firstContact->user);
        $this->assertEquals($users[0]->id, $firstContact->user->id);
    }

    public function testEagerLoadingWithCallbacks(): void
    {
        $user = new User($this->countingAdapter);

        // The 'contact' relation has callbacks defined: ['where' => '1', 'order' => 'id desc']
        $users = $user->with('contact')->findAll();

        $this->assertCount(3, $users);

        // Verify the contact relation respects the callbacks
        $this->assertInstanceOf(Contact::class, $users[0]->contact);
    }

    public function testEagerLoadingWithEmptyResults(): void
    {
        // Delete all users
        $this->pdo->exec('DELETE FROM user');

        $user = new User($this->countingAdapter);
        $users = $user->with('contacts')->findAll();

        $this->assertCount(0, $users);
        // Should not throw any errors
    }

    public function testEagerLoadingWithNoMatches(): void
    {
        // Create a user with no contacts
        $this->pdo->exec("INSERT INTO user (name, password, created_dt) VALUES ('User 4', 'pass4', '2024-01-04 00:00:00')");

        $user = new User($this->countingAdapter);
        $users = $user->with('contacts')->eq('name', 'User 4')->findAll();

        $this->assertCount(1, $users);
        $this->assertIsArray($users[0]->contacts);
        $this->assertCount(0, $users[0]->contacts); // Empty array for HAS_MANY
    }

    public function testBackwardCompatibilityLazyLoading(): void
    {
        $user = new User($this->countingAdapter);

        // Without with(), should still use lazy loading
        $users = $user->findAll();

        $this->assertCount(3, $users);

        // Accessing contacts should trigger lazy loading
        $contacts = $users[0]->contacts;
        $this->assertCount(2, $contacts);
    }

    public function testInvalidRelationThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Relation 'invalid_relation' is not defined");

        $user = new User($this->countingAdapter);
        $user->with('invalid_relation')->findAll();
    }

    public function testWithAcceptsStringAndArray(): void
    {
        $user = new User($this->countingAdapter);

        // Test with string
        $users1 = $user->with('contacts')->findAll();
        $this->assertCount(3, $users1);

        // Test with array
        $user2 = new User($this->countingAdapter);
        $users2 = $user2->with(['contacts'])->findAll();
        $this->assertCount(3, $users2);
    }

    public function testEagerLoadingWithAllNullForeignKeys(): void
    {
        // Create contacts with null user_id to test the empty keyValues scenario
        $this->pdo->exec('DELETE FROM contact');
        $this->pdo->exec("INSERT INTO contact (user_id, email, address) VALUES (NULL, 'orphan1@example.com', 'Address 1')");
        $this->pdo->exec("INSERT INTO contact (user_id, email, address) VALUES (NULL, 'orphan2@example.com', 'Address 2')");

        $contact = new Contact($this->countingAdapter);

        // Try to eager load users when all foreign keys are null
        $contacts = $contact->with('user')->findAll();

        $this->assertCount(2, $contacts);

        // Should have empty/new user objects since no valid foreign keys
        $this->assertInstanceOf(User::class, $contacts[0]->user);
        $this->assertFalse($contacts[0]->user->isHydrated());
    }

    public function testBelongsToEagerLoadingWithBackReference(): void
    {
        $contact = new Contact($this->countingAdapter);

        // Eager load users with back reference (user_with_backref relation)
        $contacts = $contact->with('user_with_backref')->findAll();

        $this->assertCount(4, $contacts);

        // Verify users are loaded
        $this->assertInstanceOf(User::class, $contacts[0]->user_with_backref);

        // Verify back reference is set for BELONGS_TO (non-array relation)
        // The back reference should be set on the User object pointing back to a Contact
        $user = $contacts[0]->user_with_backref;
        $this->assertInstanceOf(Contact::class, $user->contact);

        // The back reference will be one of the contacts that belongs to this user
        // (when multiple contacts point to same user, the last one processed wins)
        $this->assertContains($user->contact->id, [1, 2]); // Contact IDs 1 and 2 both belong to User 1
    }

    public function testHasOneEagerLoadingWithNoMatches(): void
    {
        // Create a user with no contacts to test HAS_ONE with no match
        $this->pdo->exec("INSERT INTO user (name, password, created_dt) VALUES ('User 4', 'pass4', '2024-01-04 00:00:00')");

        $user = new User($this->countingAdapter);

        // Eager load 'contact' (HAS_ONE) for user with no contacts
        $users = $user->with('contact')->eq('name', 'User 4')->findAll();

        $this->assertCount(1, $users);

        // Should have an empty Contact object (not hydrated) for HAS_ONE with no match
        $this->assertInstanceOf(Contact::class, $users[0]->contact);
        $this->assertFalse($users[0]->contact->isHydrated());
    }

    public function testEagerLoadingSkipsAlreadyLoadedRelations(): void
    {
        $user = new User($this->countingAdapter);

        // Find a user first
        $foundUser = $user->find(1);

        // Lazy load a HAS_ONE relation (this will set it as an ActiveRecord instance)
        $lazyContact = $foundUser->contact;
        $this->assertInstanceOf(Contact::class, $lazyContact);

        // Now try to eager load the same relation - it should skip since it's already loaded
        $this->countingAdapter->reset();
        $users = $user->with('contact')->eq('id', 1)->findAll();

        // Should still have the contact
        $this->assertCount(1, $users);
        $this->assertInstanceOf(Contact::class, $users[0]->contact);

        // The eager loading query should have been skipped (only 1 query for users, not 2)
        $this->assertEquals(1, $this->countingAdapter->getQueryCount());
    }
}
