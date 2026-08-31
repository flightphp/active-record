<?php

namespace flight\tests;

use flight\ActiveRecord;
use flight\database\pdo\PdoAdapter;
use flight\database\pdo\PdoStatementAdapter;
use flight\tests\classes\Contact;
use flight\tests\classes\User;
use PDO;

class ActiveRecordPdoIntegrationTest extends \PHPUnit\Framework\TestCase
{
    protected $ActiveRecord;

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/classes/User.php';
        require_once __DIR__ . '/classes/Contact.php';
        @unlink('test.db');
    }

    public static function tearDownAfterClass(): void
    {
        @unlink('test.db');
    }

    public function setUp(): void
    {
        $this->ActiveRecord = new class (new PDO('sqlite:test.db')) extends ActiveRecord {
        };
        $this->ActiveRecord->execute("CREATE TABLE IF NOT EXISTS user (
            id INTEGER PRIMARY KEY, 
            name TEXT, 
            password TEXT,
			created_dt TEXT
        );");
        $this->ActiveRecord->execute("CREATE TABLE IF NOT EXISTS contact (
            id INTEGER PRIMARY KEY, 
            user_id INTEGER, 
            email TEXT,
            address TEXT
        );");
    }

    public function tearDown(): void
    {
        $this->ActiveRecord->execute("DROP TABLE IF EXISTS contact;");
        $this->ActiveRecord->execute("DROP TABLE IF EXISTS user;");
        $this->ActiveRecord->execute("DROP TABLE IF EXISTS my_text_table;");
        $this->ActiveRecord->execute("DROP TABLE IF EXISTS distinct_test;");
        $this->ActiveRecord->execute("DROP TABLE IF EXISTS timestamped;");
        $this->ActiveRecord->execute("DROP TABLE IF EXISTS no_ts_columns;");
    }

    public function testInsert()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();
        $this->assertGreaterThan(0, $user->id);
        $sql = $user->getBuiltSql();
        $this->assertStringContainsString('INSERT INTO "user" ("name","password")', $sql);
        $this->assertStringContainsString('VALUES (:ph1,:ph2)', $sql);
    }

    public function testInsertNoChanges()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $insert_result = $user->insert();
        $this->assertIsObject($insert_result);
    }

    public function testEdit()
    {
        $original_password = md5('demo');
        $user = new User(new PDO('sqlite:test.db'));
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

        $sql = $user->getBuiltSql();
        $this->assertStringContainsString('UPDATE "user" SET "name" = :ph3 , "password" = :ph4 WHERE "user"."id" = :ph5', $sql);
    }

    public function testUpdateNoChanges()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->name = 'demo';
        $user->password = 'pass';
        $user->insert();
        $user_result = $user->update();
        $this->assertIsObject($user_result);
    }

    public function testSave()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->name = 'demo';
        $user->password = 'pass';
        $user->save(); // should have inserted
        $insert_id = $user->id;
        $user->name = 'new name';
        $user->save();
        $this->assertEquals($insert_id, $user->id);
        $this->assertEquals('new name', $user->name);
    }

    public function testBeforeInsertUsingSaveWithMixedInput()
    {
        $user = new class (new PDO('sqlite:test.db')) extends User {
            protected function beforeInsert(self $self)
            {
                $self->created_dt = '2024-02-18 12:00:00';
            }
        };
        $user->name = 'bob';
        $user->password = 'test';
        $user->save();

        $this->assertEquals('test', $user->password);
        $this->assertEquals('2024-02-18 12:00:00', $user->created_dt);
    }

    public function testRelations()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();

        $contact = new Contact(new PDO('sqlite:test.db'));
        $contact->user_id = $user->id;
        $contact->email = 'test@amail.com';
        $contact->address = 'test address';
        $contact->insert();

        $this->assertEquals($contact->user->id, $contact->user_id);
        $this->assertEquals($contact->user->contact->id, $contact->id);
        $this->assertEquals($contact->user->contacts[0]->id, $contact->id);
        $this->assertGreaterThan(0, count($contact->user->contacts));
    }

    public function testRelationsBackRef()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();

        $contact = new Contact(new PDO('sqlite:test.db'));
        $contact->user_id = $user->id;
        $contact->email = 'test@amail.com';
        $contact->address = 'test address';
        $contact->insert();

        $this->assertEquals($contact->user->contact === $contact, false);
        $this->assertSame($contact->user_with_backref->contact, $contact);
        $user = $contact->user;
        $this->assertEquals($user->contacts[0]->user === $user, false);
        $this->assertEquals($user->contacts_with_backref[0]->user === $user, true);

        return $contact;
    }

    public function testJoin()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();

        $contact = new Contact(new PDO('sqlite:test.db'));
        $contact->user_id = $user->id;
        $contact->email = 'test@amail.com';
        $contact->address = 'test address';
        $contact->insert();

        $user->select('*, c.email, c.address')->join('contact as c', 'c.user_id = user.id')->find();
        // email and address will stored in user data array.
        $this->assertEquals($user->id, $contact->user_id);
        $this->assertEquals($user->email, $contact->email);
        $this->assertEquals($user->address, $contact->address);
    }

    public function testJoinIsClearedAfterCalledTwice()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();

        $contact = new Contact(new PDO('sqlite:test.db'));
        $contact->user_id = $user->id;
        $contact->email = 'test@amail.com';
        $contact->address = 'test address';
        $contact->insert();

        $user->select('*, c.email, c.address')->join('contact as c', 'c.user_id = user.id')->find();
        // email and address will stored in user data array.
        $this->assertEquals($user->id, $contact->user_id);
        $this->assertEquals($user->email, $contact->email);
        $this->assertEquals($user->address, $contact->address);

        $user->select('*, c.email, c.address')->join('contact as c', 'c.user_id = user.id')->find();
        // email and address will stored in user data array.
        $this->assertEquals($user->id, $contact->user_id);
        $this->assertEquals($user->email, $contact->email);
        $this->assertEquals($user->address, $contact->address);
    }

    public function testQuery()
    {
        $user = new class (new PDO('sqlite:test.db')) extends User {
            public function getDirty()
            {
                return $this->dirty;
            }
        };
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();

        $contact = new Contact(new PDO('sqlite:test.db'));
        $contact->user_id = $user->id;
        $contact->email = 'test@amail.com';
        $contact->address = 'test address';
        $contact->insert();

        $user->isNotNull('id')->eq('id', 1)->lt('id', 2)->gt('id', 0)->find();
        $sql = $user->getBuiltSql();
        $this->assertStringContainsString('SELECT "user".* FROM "user" WHERE "user"."id" IS NOT NULL AND "user"."id" = :ph3 AND "user"."id" < :ph4 AND "user"."id" > :ph5', $sql);
        $this->assertGreaterThan(0, $user->id);
        $this->assertSame([], $user->getDirty());
        $user->name = 'testname';
        $this->assertSame(['name' => 'testname'], $user->getDirty());
        $name = $user->name;
        $this->assertEquals('testname', $name);
        unset($user->name);
        $this->assertSame([], $user->getDirty());
        $user->isNotNull('id')->eq('id', 'aaa"')->wrap()->lt('id', 2)->gt('id', 0)->wrap('OR')->find();
        $this->assertEmpty($user->id);
        $user->isNotNull('id')->between('id', [0, 2])->find();
        $this->assertGreaterThan(0, $user->id);
    }

    public function testWrapWithArrays()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();
        $user->name = 'demo1';
        $user->password = md5('demo1');
        $user->insert();

        $users = $user->isNotNull('id')->wrap()->in('name', [ 'demo', 'demo1' ])->wrap('OR')->lt('id', 3)->gt('id', 0)->findAll();
        $this->assertGreaterThan(0, $users[0]->id);
        $this->assertGreaterThan(0, $users[1]->id);
    }

    public function testDelete()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();

        $contact = new Contact(new PDO('sqlite:test.db'));
        $contact->user_id = $user->id;
        $contact->email = 'test@amail.com';
        $contact->address = 'test address';
        $contact->insert();
        $cid = $contact->id;
        $uid = $contact->user_id;
        $new_contact = new Contact(new PDO('sqlite:test.db'));
        $new_user = new User(new PDO('sqlite:test.db'));
        $this->assertEquals($cid, $new_contact->find($cid)->id);
        $this->assertEquals($uid, $new_user->eq('id', $uid)->find()->id);
        $this->assertTrue($contact->user->delete());
        $this->assertTrue($contact->delete());

        $sql = $contact->getBuiltSql();
        $new_contact = new Contact(new PDO('sqlite:test.db'));
        $new_user = new User(new PDO('sqlite:test.db'));
        $this->assertInstanceOf(Contact::class, $new_contact->eq('id', $cid)->find());
        $this->assertEmpty($new_contact->id);
        $this->assertInstanceOf(User::class, $new_user->find($uid));
        $this->assertEmpty($new_user->id);
        $this->assertStringContainsString('DELETE FROM "contact" WHERE "contact"."id" = :ph4', $sql);
    }

    public function testDeleteWithConditions()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();
        $user->name = 'demo1';
        $user->password = md5('demo1');
        $user->insert();
        $user->name = 'bob';
        $user->password = md5('bob');
        $user->insert();

        $this->assertEquals(3, $user->id);

        $user->like('name', 'demo%')->delete();
        $remaining_users = $user->findAll();

        $this->assertEquals(1, count($remaining_users));
    }

    public function testFindEvents()
    {
        $user = new class (new PDO('sqlite:test.db')) extends User {
            public function beforeFind(self $self)
            {
                // This will force it to pull this kind of query
                // every time.
                $self->eq('name', 'Bob');
            }

            public function afterFind(self $self)
            {
                $self->password = 'joepassword';
                $self->setCustomData('real_name', 'Joe');
            }
        };
        $user->name = 'Bob';
        $user->password = 'bobbytables';
        $user->insert();
        $user_record = $user->find();
        $this->assertEquals('Joe', $user_record->real_name);
        $this->assertEquals('joepassword', $user_record->password);
    }

    public function testInsertEvents()
    {
        $user = new class (new PDO('sqlite:test.db')) extends User {
            protected function beforeInsert(self $self)
            {
                $self->password = 'defaultpassword';
            }

            protected function afterInsert(self $self)
            {
                $self->name .= ' after insert';
            }
        };
        $user->name = 'Bob';
        $user->password = 'bobbytables';
        $user->insert();
        $this->assertEquals('Bob after insert', $user->name);
        $this->assertEquals('defaultpassword', $user->password);
    }

    public function testUpdateEvents()
    {
        $user = new class (new PDO('sqlite:test.db')) extends User {
            protected function beforeUpdate(self $self)
            {
                $self->password = 'defaultpassword';
            }

            protected function afterUpdate(self $self)
            {
                $self->name .= ' after update';
            }
        };
        $user->name = 'Bob';
        $user->password = 'bobbytables';
        $user->insert();
        $user->update();
        $this->assertEquals('Bob after update', $user->name);
        $this->assertEquals('defaultpassword', $user->password);
    }

    public function testLimit()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob2', 'password' => 'pass2' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob3', 'password' => 'pass3' ]);
        $user->insert();

        $users = $user->limit(2)->findAll();
        $this->assertEquals('bob', $users[0]->name);
        $this->assertEquals('bob2', $users[1]->name);
        $this->assertTrue(empty($users[2]->name));

        $users = $user->limit(1, 2)->findAll();
        $this->assertEquals('bob2', $users[0]->name);
        $this->assertEquals('bob3', $users[1]->name);
        $this->assertTrue(empty($users[2]->name));
    }

    public function testCountWithSelect()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob2', 'password' => 'pass2' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob3', 'password' => 'pass3' ]);
        $user->insert();

        $user->select('COUNT(*) as count')->find();
        $this->assertEquals(3, $user->count);
    }

    public function testSelectOneColumn()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->select('name')->find();
        $this->assertEquals('bob', $user->name);
        $this->assertEmpty($user->password);
    }

    public function testBetween()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob2', 'password' => 'pass2' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob3', 'password' => 'pass3' ]);
        $user->insert();

        $users = $user->between('id', [1, 2])->findAll();
        $this->assertEquals('bob', $users[0]->name);
        $this->assertEquals('bob2', $users[1]->name);
        $this->assertTrue(empty($users[2]->name));
    }

    public function testOnConstruct()
    {
        $user = new class () extends User {
            protected function onConstruct(self $self, &$config)
            {
                $config['connection'] = new PDO('sqlite:test.db');
            }
        };

        $user->name = 'bob';
        $user->insert();

        // if it gets to this point it means it's working.
        $this->assertEquals('bob', $user->name);
    }

    public function testJsonSerializeableWithCustomData()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->setCustomData('test', 'test');

        $this->assertEquals('{"name":"bob","password":"pass","id":"1","test":"test"}', json_encode($user));
    }

    public function testReset()
    {
        $user = new class (new PDO('sqlite:test.db')) extends User {
            public function getSqlExpressions()
            {
                return $this->sqlExpressions;
            }
            public function getParams()
            {
                return $this->params;
            }
        };
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->reset();
        $this->assertEmpty($user->name);
        $this->assertEmpty($user->password);
        $this->assertEmpty($user->getSqlExpressions());
        $this->assertEmpty($user->getParams());
    }

    public function testResetKeepQueryData()
    {
        $user = new class (new PDO('sqlite:test.db')) extends User {
            public function getSqlExpressions()
            {
                return $this->sqlExpressions;
            }
            public function getParams()
            {
                return $this->params;
            }
        };
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->eq('id', 1);
        $user->reset(false);
        $this->assertEmpty($user->name);
        $this->assertEmpty($user->password);
        $this->assertGreaterThan(0, count($user->getSqlExpressions()));
    }

    public function testAssignValAndThenAssignNull()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->name = 'bob';
        $user->password = 'pass';
        $user->save();
        $user->name = null;
        $user->save();
        $this->assertEmpty($user->name);
    }

    public function testIsHydratedBadFind()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->name = 'bob';
        $user->password = 'pass';
        $user->save();
        $user->find(0);
        $this->assertFalse($user->isHydrated());
    }

    public function testIsHydratedGoodFind()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->name = 'bob';
        $user->password = 'pass';
        $user->save();
        $user->find(1);
        $this->assertTrue($user->isHydrated());
    }

    public function testIsHydratedGoodFindWithSelect()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->name = 'bob';
        $user->password = 'pass';
        $user->save();
        $user->select('name')->find(1);
        $this->assertTrue($user->isHydrated());
        $this->assertEmpty($user->password);
        $this->assertEquals('bob', $user->name);
    }

    public function testIsHydratedGoodFindAll()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->name = 'bob';
        $user->password = 'pass';
        $user->save();
        $users = $user->findAll();
        $this->assertTrue($users[0]->isHydrated());
    }

    public function testRelationsCascadingSave()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();

        $user->name = 'bobby';
        $user->contact->user_id = $user->id;
        $user->contact->email = 'test@amail.com';
        $user->contact->address = 'test address';
        $user->save();

        $this->assertEquals($user->id, $user->contact->user_id);
        $this->assertFalse($user->contact->isDirty());
        $this->assertGreaterThan(0, $user->contact->id);
        $this->assertFalse($user->isDirty());
    }

    public function testSetDatabaseConnection()
    {
        $user = new User();
        $user->setDatabaseConnection(new PDO('sqlite:test.db'));
        $user->name = 'bob';
        $user->password = 'pass';
        $user->save();

        $this->assertGreaterThan(0, $user->id);
    }

    public function testSetDatabaseConnectionWithAdapter()
    {
        $user = new User();
        $user->setDatabaseConnection(new PdoAdapter(new PDO('sqlite:test.db')));
        $user->name = 'bob';
        $user->password = 'pass';
        $user->save();

        $this->assertGreaterThan(0, $user->id);
    }

    public function testRelationWithProtectedKeyword()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();

        $contact = new class (new PDO('sqlite:test.db')) extends ActiveRecord {
            protected array $relations = [
                'group' => [self::HAS_ONE, User::class, 'user_id']
            ];
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('group is a protected keyword and cannot be used as a relation name');
        $contact->group->id;
    }

    public function testTextBasedPrimaryKey()
    {
        $this->ActiveRecord->execute("CREATE TABLE IF NOT EXISTS my_text_table (
			my_pk TEXT NOT NULL PRIMARY KEY, 
			data INTEGER, name TEXT
		)");

        $myTextTable = new class (new PDO('sqlite:test.db'), 'my_text_table', [ 'primaryKey' => 'my_pk' ]) extends ActiveRecord {
        };

        $my_pk = time();
        $myTextTable->my_pk = $my_pk;
        $myTextTable->data = 12345;

        $this->assertTrue($myTextTable->isDirty());
        $myTextTable->save();

        $this->assertTrue($myTextTable->isHydrated());

        $myTextTable->reset();

        $myTextTable->find($my_pk);

        $this->assertEquals($my_pk, $myTextTable->my_pk);
        $this->assertEquals(12345, $myTextTable->data);
        $this->assertTrue($myTextTable->isHydrated());
    }

    public function testTextBasedPrimaryKeyDuplicateKey()
    {
        $this->ActiveRecord->execute("CREATE TABLE IF NOT EXISTS my_text_table (
			my_pk TEXT NOT NULL PRIMARY KEY, 
			data INTEGER, name TEXT
		)");

        $myTextTable = new class (new PDO('sqlite:test.db'), 'my_text_table', [ 'primaryKey' => 'my_pk' ]) extends ActiveRecord {
        };

        $my_pk = time();
        $myTextTable->my_pk = $my_pk;
        $myTextTable->data = 12345;
        $myTextTable->save();

        $myTextTable2 = new class (new PDO('sqlite:test.db'), 'my_text_table', [ 'primaryKey' => 'my_pk' ]) extends ActiveRecord {
        };

        $myTextTable2->my_pk = $my_pk;
        $myTextTable2->data = 12345;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('UNIQUE constraint failed: my_text_table.my_pk');
        $myTextTable2->save();
    }

    public function testCallMethodPassingToPdoConnection()
    {
        $result = $this->ActiveRecord->prepare('SELECT * FROM user');
        $this->assertInstanceOf(PdoStatementAdapter::class, $result);
        $this->assertNotInstanceOf(ActiveRecord::class, $result);
    }

    public function testWrapMultipleOrStatements()
    {
        $record = new User(new PDO('sqlite:test.db'));
        $record
            ->notNull('name')
            ->startWrap()
            ->eq('name', 'John')
            ->eq('id', 1)
            ->gte('password', '123')
            ->endWrap('OR')
            ->eq('id', 2)
            ->find();
        $sql = $record->getBuiltSql();
        $this->assertEquals('SELECT "user".* FROM "user" WHERE "user"."name" IS NOT NULL AND ("user"."name" = :ph1 OR "user"."id" = :ph2 OR "user"."password" >= :ph3) AND "user"."id" = :ph4 LIMIT 1', $sql);
    }

    public function testWrapWithComplexLogic()
    {
        $record = new User(new PDO('sqlite:test.db'));
        $record
            ->startWrap()
            ->eq('name', 'John')
            ->in('id', [ 1,5,9 ])
            ->eq('id', 1)
            ->endWrap('OR')
            ->notNull('name')
            ->between('id', [ 1, 2 ])
            ->join('contact', '"contact"."user_id" = "user"."id"')
            ->find();
        $sql = $record->getBuiltSql();
        $this->assertEquals('SELECT "user".* FROM "user" LEFT JOIN "contact" ON "contact"."user_id" = "user"."id" WHERE ("user"."name" = :ph1 OR "user"."id" IN (:ph2,:ph3,:ph4) OR "user"."id" = :ph5) AND "user"."name" IS NOT NULL AND "user"."id" BETWEEN :ph6 AND :ph7 LIMIT 1', $sql);
    }

    public function testOrAsFinalParameter()
    {
        $record = new User(new PDO('sqlite:test.db'));
        $record
            ->eq('name', 'John')
            ->in('id', [ 1,5,9 ])
            ->eq('id', 1, 'or')
            ->find();
        $sql = $record->getBuiltSql();
        $this->assertEquals('SELECT "user".* FROM "user" WHERE "user"."name" = :ph1 AND "user"."id" IN (:ph2,:ph3,:ph4) OR "user"."id" = :ph5 LIMIT 1', $sql);
    }

    public function testBooleanParam()
    {
        $record = new User(new PDO('sqlite:test.db'));
        $record->eq('name', 'John');
        $record->eq('id', true);
        $record->find();
        $sql = $record->getBuiltSql();
        $this->assertEquals('SELECT "user".* FROM "user" WHERE "user"."name" = :ph1 AND "user"."id" = TRUE LIMIT 1', $sql);
    }

    public function testBooleanParamWithArray()
    {
        $record = new User(new PDO('sqlite:test.db'));
        $record->eq('name', 'John');
        $record->in('id', [ true, false ]);
        $record->find();
        $sql = $record->getBuiltSql();
        $this->assertEquals('SELECT "user".* FROM "user" WHERE "user"."name" = :ph1 AND "user"."id" IN (TRUE,FALSE) LIMIT 1', $sql);
    }

    public function testHasManyInvalidRelation()
    {
        $user = new class (new PDO('sqlite:test.db')) extends User {
            public array $relations = [
                'invalid_relation' => [] // Invalid - missing required elements
            ];
        };

        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();

        // Should return empty array for invalid relation instead of throwing exception
        $this->assertIsArray($user->invalid_relation);
        $this->assertEmpty($user->invalid_relation);
    }
    public function testHasManyEmptyRelation()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();

        // No contacts exist for this user
        $this->assertEmpty($user->contacts);
        $this->assertIsArray($user->contacts);
        $this->assertEquals(0, count($user->contacts));
    }

    public function testCountAll()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob2', 'password' => 'pass2' ]);
        $user->insert();
        $user->dirty([ 'name' => 'alice', 'password' => 'pass3' ]);
        $user->insert();

        $this->assertSame(3, $user->count());
    }

    public function testCountWithWhere()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob2', 'password' => 'pass2' ]);
        $user->insert();
        $user->dirty([ 'name' => 'alice', 'password' => 'pass3' ]);
        $user->insert();

        $this->assertSame(2, $user->like('name', 'bob%')->count());
    }

    public function testCountEmptyTable()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $this->assertSame(0, $user->count());
    }

    public function testCountIgnoresGroup()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob', 'password' => 'pass2' ]);
        $user->insert();
        $user->dirty([ 'name' => 'alice', 'password' => 'pass3' ]);
        $user->insert();

        // Two distinct names: a grouped count would be 2. The total is 3.
        $this->assertSame(3, $user->groupBy('name')->count());
    }

    public function testExistsTrue()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();

        $this->assertTrue($user->eq('name', 'bob')->exists());
    }

    public function testExistsFalse()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();

        $this->assertFalse($user->eq('name', 'nobody')->exists());
    }

    public function testExistsNoConditions()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();

        $this->assertTrue($user->exists());
    }

    public function testExistsEmptyTable()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $this->assertFalse($user->exists());
    }

    public function testChainingCount()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'active', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'active', 'password' => 'pass2' ]);
        $user->insert();

        $this->assertSame(2, $user->eq('name', 'active')->count());
    }

    public function testChainingExists()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();

        $this->assertFalse($user->eq('name', 'nonexistent')->exists());
    }

    public function testFetchColumnInterface()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob2', 'password' => 'pass2' ]);
        $user->insert();

        $statement = $this->ActiveRecord->execute('SELECT name FROM user ORDER BY id ASC');
        $this->assertSame('bob', $statement->fetchColumn());
        $this->assertSame('bob2', $statement->fetchColumn());
        $this->assertFalse($statement->fetchColumn());
    }

    public function testFetchColumnReturnsFalseWhenNoRows()
    {
        $statement = $this->ActiveRecord->execute('SELECT name FROM user WHERE 1 = 0');
        $this->assertFalse($statement->fetchColumn());
    }

    public function testPluckSingleColumn()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob2', 'password' => 'pass2' ]);
        $user->insert();
        $user->dirty([ 'name' => 'alice', 'password' => 'pass3' ]);
        $user->insert();

        $this->assertSame([ 'bob', 'bob2', 'alice' ], $user->orderByColumn('id')->pluck('name'));
    }

    public function testPluckWithWhere()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob2', 'password' => 'pass2' ]);
        $user->insert();
        $user->dirty([ 'name' => 'alice', 'password' => 'pass3' ]);
        $user->insert();

        $this->assertSame([ 'bob', 'bob2' ], $user->like('name', 'bob%')->orderByColumn('id')->pluck('name'));
    }

    public function testPluckEmptyTable()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $this->assertSame([], $user->pluck('name'));
    }

    public function testPluckPreservesType()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob2', 'password' => 'pass2' ]);
        $user->insert();

        // Strings stay strings, and numeric keys stay numeric for the driver in use
        $this->assertSame([ 'bob', 'bob2' ], $user->orderByColumn('id')->pluck('name'));
        $this->assertEquals([ 1, 2 ], $user->orderByColumn('id')->pluck('id'));
    }

    public function testPluckWithOrder()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'alice', 'password' => 'pass2' ]);
        $user->insert();

        $this->assertSame([ 'bob', 'alice' ], $user->orderByColumn('name', 'DESC')->pluck('name'));
    }

    public function testPluckWithLimit()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob2', 'password' => 'pass2' ]);
        $user->insert();
        $user->dirty([ 'name' => 'alice', 'password' => 'pass3' ]);
        $user->insert();

        $this->assertSame([ 'bob', 'bob2' ], $user->orderByColumn('id')->limit(2)->pluck('name'));
    }

    public function testPluckWithNullValuesDoesNotTruncate()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => null, 'password' => 'pass2' ]);
        $user->insert();
        $user->dirty([ 'name' => 'alice', 'password' => 'pass3' ]);
        $user->insert();

        $this->assertSame([ 'bob', null, 'alice' ], $user->orderByColumn('id')->pluck('name'));
    }

    public function testIdsReturnsPrimaryKeys()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob2', 'password' => 'pass2' ]);
        $user->insert();

        $this->assertEquals([ 1, 2 ], $user->orderByColumn('id')->ids());
    }

    public function testIdsWithConditions()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'alice', 'password' => 'pass2' ]);
        $user->insert();

        $this->assertEquals([ 2 ], $user->eq('name', 'alice')->ids());
    }

    public function testChainingIds()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'alice', 'password' => 'pass2' ]);
        $user->insert();

        $this->assertEquals([ 1, 2 ], $user->eq('password', 'pass')->eq('name', 'alice', 'or')->orderByColumn('id')->ids());
    }

    public function testFirstReturnsFirstByPk()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'alice', 'password' => 'pass2' ]);
        $user->insert();

        $first = $user->first();
        $this->assertInstanceOf(User::class, $first);
        $this->assertSame('bob', $first->name);
    }

    public function testFirstWithWhere()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'alice', 'password' => 'pass2' ]);
        $user->insert();

        $first = $user->eq('name', 'alice')->first();
        $this->assertSame('alice', $first->name);
    }

    public function testFirstEmptyTable()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $first = $user->first();
        $this->assertInstanceOf(User::class, $first);
        $this->assertFalse($first->isHydrated());
    }

    public function testFirstRespectsExplicitOrder()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'alice', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob', 'password' => 'pass2' ]);
        $user->insert();

        $first = $user->orderByColumn('name', 'DESC')->first();
        $this->assertSame('bob', $first->name);
    }

    public function testLastReturnsLastByPk()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'alice', 'password' => 'pass2' ]);
        $user->insert();

        $last = $user->last();
        $this->assertInstanceOf(User::class, $last);
        $this->assertSame('alice', $last->name);
    }

    public function testLastWithWhere()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'alice', 'password' => 'pass2' ]);
        $user->insert();

        $last = $user->eq('password', 'pass')->last();
        $this->assertSame('bob', $last->name);
    }

    public function testLastEmptyTable()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $last = $user->last();
        $this->assertInstanceOf(User::class, $last);
        $this->assertFalse($last->isHydrated());
    }

    public function testLastRespectsExplicitOrder()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'alice', 'password' => 'pass2' ]);
        $user->insert();

        $last = $user->orderByColumn('name', 'DESC')->last();
        $this->assertSame('bob', $last->name);
    }

    public function testUpdateAttributeSingleField()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();

        $loaded = new User(new PDO('sqlite:test.db'));
        $loaded->find(1);
        $loaded->updateAttribute('name', 'robert');

        $check = new User(new PDO('sqlite:test.db'));
        $check->find(1);
        $this->assertSame('robert', $check->name);
        $this->assertSame('pass', $check->password);
    }

    public function testUpdateAttributeDoesNotTouchOtherFields()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();

        $loaded = new User(new PDO('sqlite:test.db'));
        $loaded->find(1);
        $loaded->updateAttribute('password', 'newpass');

        $check = new User(new PDO('sqlite:test.db'));
        $check->find(1);
        $this->assertSame('bob', $check->name);
        $this->assertSame('newpass', $check->password);
    }

    public function testDistinctProducesSelectDistinct()
    {
        $this->ActiveRecord->execute("CREATE TABLE distinct_test (name TEXT)");
        $record = new class (new PDO('sqlite:test.db'), 'distinct_test') extends ActiveRecord {
        };
        $record->dirty([ 'name' => 'bob' ]);
        $record->insert();
        $record->dirty([ 'name' => 'bob' ]);
        $record->insert();

        $rows = $record->distinct()->findAll();
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('SELECT DISTINCT "distinct_test".*', $record->getBuiltSql());
    }

    public function testDistinctWithWhere()
    {
        $this->ActiveRecord->execute("CREATE TABLE distinct_test (name TEXT)");
        $record = new class (new PDO('sqlite:test.db'), 'distinct_test') extends ActiveRecord {
        };
        $record->dirty([ 'name' => 'bob' ]);
        $record->insert();
        $record->dirty([ 'name' => 'bob' ]);
        $record->insert();
        $record->dirty([ 'name' => 'alice' ]);
        $record->insert();

        $rows = $record->eq('name', 'bob')->distinct()->findAll();
        $this->assertCount(1, $rows);
    }

    public function testDistinctWithPluck()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob', 'password' => 'pass2' ]);
        $user->insert();
        $user->dirty([ 'name' => 'alice', 'password' => 'pass3' ]);
        $user->insert();

        $this->assertSame([ 'bob', 'alice' ], $user->orderByColumn('id')->distinct()->pluck('name'));
    }

    public function testUpdateAllMatchingRows()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob2', 'password' => 'pass2' ]);
        $user->insert();

        $count = $user->eq('name', 'bob')->updateAll([ 'password' => 'newpass' ]);
        $this->assertSame(1, $count);

        $check = new User(new PDO('sqlite:test.db'));
        $check->find(1);
        $this->assertSame('newpass', $check->password);

        $check2 = new User(new PDO('sqlite:test.db'));
        $check2->find(2);
        $this->assertSame('pass2', $check2->password);
    }

    public function testUpdateAllNoConditions()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob2', 'password' => 'pass2' ]);
        $user->insert();
        $user->dirty([ 'name' => 'alice', 'password' => 'pass3' ]);
        $user->insert();

        $this->assertSame(3, $user->updateAll([ 'password' => 'reset' ]));
    }

    public function testUpdateAllReturnsCount()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob2', 'password' => 'pass2' ]);
        $user->insert();

        $this->assertSame(2, $user->updateAll([ 'password' => 'x' ]));
        $this->assertSame(0, $user->eq('name', 'nobody')->updateAll([ 'password' => 'y' ]));
    }

    public function testUpdateAllNoCallbacks()
    {
        $user = new class (new PDO('sqlite:test.db')) extends User {
            protected function beforeUpdate(self $self)
            {
                throw new \Exception('beforeUpdate should not fire for batch updates');
            }
            protected function afterUpdate(self $self)
            {
                throw new \Exception('afterUpdate should not fire for batch updates');
            }
        };
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();

        $this->assertSame(1, $user->updateAll([ 'password' => 'x' ]));
    }

    public function testUpdateAllWithRawString()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob2', 'password' => 'pass2' ]);
        $user->insert();

        $this->assertSame(2, $user->updateAll("password = 'reset'"));

        $check = new User(new PDO('sqlite:test.db'));
        $check->find(1);
        $this->assertSame('reset', $check->password);
    }

    public function testDeleteAllMatchingRows()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob2', 'password' => 'pass2' ]);
        $user->insert();

        $this->assertSame(1, $user->eq('name', 'bob')->deleteAll());
        $this->assertSame(1, $user->count());
    }

    public function testDeleteAllNoConditions()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();
        $user->dirty([ 'name' => 'bob2', 'password' => 'pass2' ]);
        $user->insert();
        $user->dirty([ 'name' => 'alice', 'password' => 'pass3' ]);
        $user->insert();

        $this->assertSame(3, $user->deleteAll());
    }

    public function testDeleteAllReturnsCount()
    {
        $user = new User(new PDO('sqlite:test.db'));
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();

        $this->assertSame(0, $user->eq('name', 'nobody')->deleteAll());
        $this->assertSame(1, $user->deleteAll());
    }

    public function testDeleteAllNoCallbacks()
    {
        $user = new class (new PDO('sqlite:test.db')) extends User {
            protected function beforeDelete(self $self)
            {
                throw new \Exception('beforeDelete should not fire for batch deletes');
            }
            protected function afterDelete(self $self)
            {
                throw new \Exception('afterDelete should not fire for batch deletes');
            }
        };
        $user->dirty([ 'name' => 'bob', 'password' => 'pass' ]);
        $user->insert();

        $this->assertSame(1, $user->deleteAll());
    }

    public function testInsertSetsTimestamps()
    {
        $this->ActiveRecord->execute("CREATE TABLE timestamped (
            id INTEGER PRIMARY KEY,
            name TEXT,
            created_at TEXT,
            updated_at TEXT
        )");
        $record = new class (new PDO('sqlite:test.db'), 'timestamped') extends ActiveRecord {
            protected bool $timestamps = true;
        };
        $record->dirty([ 'name' => 'bob' ]);
        $record->insert();

        $this->assertNotNull($record->created_at);
        $this->assertNotNull($record->updated_at);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $record->created_at);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $record->updated_at);
    }

    public function testUpdateSetsUpdatedAt()
    {
        $this->ActiveRecord->execute("CREATE TABLE timestamped (
            id INTEGER PRIMARY KEY,
            name TEXT,
            created_at TEXT,
            updated_at TEXT
        )");
        $record = new class (new PDO('sqlite:test.db'), 'timestamped') extends ActiveRecord {
            protected bool $timestamps = true;
        };
        $record->dirty([ 'name' => 'bob' ]);
        $record->insert();
        $created_at = $record->created_at;

        $loaded = new class (new PDO('sqlite:test.db'), 'timestamped') extends ActiveRecord {
            protected bool $timestamps = true;
        };
        $loaded->find(1);
        $loaded->updateAttribute('name', 'robert');

        $this->assertSame($created_at, $loaded->created_at);
        $this->assertNotNull($loaded->updated_at);
        $this->assertGreaterThanOrEqual($created_at, $loaded->updated_at);
    }

    public function testTimestampsDisabledByDefault()
    {
        $this->ActiveRecord->execute("CREATE TABLE timestamped (
            id INTEGER PRIMARY KEY,
            name TEXT,
            created_at TEXT,
            updated_at TEXT
        )");
        $record = new class (new PDO('sqlite:test.db'), 'timestamped') extends ActiveRecord {
        };
        $record->dirty([ 'name' => 'bob' ]);
        $record->insert();

        $this->assertNull($record->created_at);
        $this->assertNull($record->updated_at);
    }

    public function testTimestampsDontOverwrite()
    {
        $this->ActiveRecord->execute("CREATE TABLE timestamped (
            id INTEGER PRIMARY KEY,
            name TEXT,
            created_at TEXT,
            updated_at TEXT
        )");
        $record = new class (new PDO('sqlite:test.db'), 'timestamped') extends ActiveRecord {
            protected bool $timestamps = true;
        };
        $record->created_at = '2024-01-01 00:00:00';
        $record->updated_at = '2024-01-01 00:00:00';
        $record->name = 'bob';
        $record->insert();

        $this->assertSame('2024-01-01 00:00:00', $record->created_at);
        $this->assertSame('2024-01-01 00:00:00', $record->updated_at);
    }

    public function testTimestampsWithCustomFormat()
    {
        $this->ActiveRecord->execute("CREATE TABLE timestamped (
            id INTEGER PRIMARY KEY,
            name TEXT,
            created_at TEXT,
            updated_at TEXT
        )");
        $record = new class (new PDO('sqlite:test.db'), 'timestamped') extends ActiveRecord {
            protected bool $timestamps = true;
            protected function setTimestamps(bool $isNew = true): void
            {
                if ($isNew === true) {
                    $this->created_at = 'custom-created';
                }
                $this->updated_at = 'custom-updated';
            }
        };
        $record->dirty([ 'name' => 'bob' ]);
        $record->insert();

        $this->assertSame('custom-created', $record->created_at);
        $this->assertSame('custom-updated', $record->updated_at);
    }

    public function testTimestampsMissingColumnsErrors()
    {
        $this->ActiveRecord->execute("CREATE TABLE no_ts_columns (
            id INTEGER PRIMARY KEY,
            name TEXT
        )");
        $record = new class (new PDO('sqlite:test.db'), 'no_ts_columns') extends ActiveRecord {
            protected bool $timestamps = true;
        };
        $record->dirty([ 'name' => 'bob' ]);

        try {
            $record->insert();
            $this->fail('Enabling timestamps on a table without the columns should error');
        } catch (\Exception $e) {
            $this->assertStringContainsString('has no column named created_at', $e->getMessage());
        }
    }
}
