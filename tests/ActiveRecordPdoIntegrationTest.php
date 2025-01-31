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
        $this->assertStringContainsString('SELECT "user".* FROM "user" WHERE "user"."id" IS NOT NULL AND "user"."id" = 1 AND "user"."id" < 2 AND "user"."id" > 0', $sql);
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
        $this->assertEquals('SELECT "user".* FROM "user" WHERE "user"."name" IS NOT NULL AND ("user"."name" = :ph1 OR "user"."id" = 1 OR "user"."password" >= :ph2) AND "user"."id" = 2 LIMIT 1', $sql);
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
        $this->assertEquals('SELECT "user".* FROM "user" LEFT JOIN contact ON "contact"."user_id" = "user"."id" WHERE ("user"."name" = :ph1 OR "user"."id" IN (:ph2,:ph3,:ph4) OR "user"."id" = 1) AND "user"."name" IS NOT NULL AND "user"."id" BETWEEN :ph5 AND :ph6 LIMIT 1', $sql);
    }
}
