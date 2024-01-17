<?php

namespace flight\tests;

use Exception;
use flight\ActiveRecord;
use flight\tests\classes\Contact;
use flight\tests\classes\User;
use PDO;

class ActiveRecordTest extends \PHPUnit\Framework\TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/classes/User.php';
        require_once __DIR__ . '/classes/Contact.php';

        @unlink('test.db');
        ActiveRecord::setDb(new PDO('sqlite:test.db'));
    }

    public static function tearDownAfterClass(): void
    {
        @unlink('test.db');
    }

    public function setUp(): void
    {
        ActiveRecord::execute("CREATE TABLE IF NOT EXISTS user (
            id INTEGER PRIMARY KEY, 
            name TEXT, 
            password TEXT 
        );");
        ActiveRecord::execute("CREATE TABLE IF NOT EXISTS contact (
            id INTEGER PRIMARY KEY, 
            user_id INTEGER, 
            email TEXT,
            address TEXT
        );");
    }

    public function tearDown(): void
    {
        ActiveRecord::execute("DROP TABLE IF EXISTS contact;");
        ActiveRecord::execute("DROP TABLE IF EXISTS user;");
    }

    public function testError()
    {
        try {
            ActiveRecord::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            ActiveRecord::execute('CREATE TABLE IF NOT EXISTS');
        } catch (Exception $e) {
            $this->assertInstanceOf('PDOException', $e);
            $this->assertEquals('HY000', $e->getCode());
            $this->assertEquals('SQLSTATE[HY000]: General error: 1 incomplete input', $e->getMessage());
        }
    }

    public function testInsert()
    {
        $user = new User();
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();
        $this->assertGreaterThan(0, $user->id);
    }

	public function testInsertNoChanges() {
		$user = new User();
		$result = $user->insert();
		$this->assertTrue($result);
	}

    public function testEdit()
    {
        $original_password = md5('demo');
        $user = new User();
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

	public function testUpdateNoChanges() {
		$user = new User();
		$user->name = 'demo';
        $user->password = 'pass';
        $user->insert();
		$result = $user->update();
		$this->assertTrue($result);
	}

    public function testRelations()
    {
        $user = new User();
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();

        $contact = new Contact();
        $contact->user_id = $user->id;
        $contact->email = 'test@amail.com';
        $contact->address = 'test address';
        $contact->insert();
        
        $this->assertEquals($contact->user->id, $contact->user_id);
        $this->assertEquals($contact->user->contact->id, $contact->id);
        $this->assertEquals($contact->user->contacts[0]->id, $contact->id);
        $this->assertGreaterThan(0, count($contact->user->contacts));
        return $contact;
    }
    
    public function testRelationsBackRef()
    {
        $user = new User();
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();

        $contact = new Contact();
        $contact->user_id = $user->id;
        $contact->email = 'test@amail.com';
        $contact->address = 'test address';
        $contact->insert();

        $this->assertEquals($contact->user->contact === $contact, false);
        $this->assertEquals($contact->user_with_backref->contact === $contact, true);
        $user = $contact->user;
        $this->assertEquals($user->contacts[0]->user === $user, false);
        $this->assertEquals($user->contacts_with_backref[0]->user === $user, true);

        return $contact;
    }
    
    public function testJoin()
    {
        $user = new User();
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();

        $contact = new Contact();
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
    
    public function testQuery()
    {
        $user = new User();
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();

        $contact = new Contact();
        $contact->user_id = $user->id;
        $contact->email = 'test@amail.com';
        $contact->address = 'test address';
        $contact->insert();

        $user->isNotNull('id')->eq('id', 1)->lt('id', 2)->gt('id', 0)->find();
        $this->assertGreaterThan(0, $user->id);
        $this->assertSame([], $user->dirty);
        $user->name = 'testname';
        $this->assertSame(array('name'=>'testname'), $user->dirty);
        $name = $user->name;
        $this->assertEquals('testname', $name);
        unset($user->name);
        $this->assertSame(array(), $user->dirty);
        $user->reset()->isNotNull('id')->eq('id', 'aaa"')->wrap()->lt('id', 2)->gt('id', 0)->wrap('OR')->find();
        $this->assertGreaterThan(0, $user->id);
        $user->reset()->isNotNull('id')->between('id', array(0, 2))->find();
        $this->assertGreaterThan(0, $user->id);
    }
    
    public function testDelete()
    {
        $user = new User();
        $user->name = 'demo';
        $user->password = md5('demo');
        $user->insert();

        $contact = new Contact();
        $contact->user_id = $user->id;
        $contact->email = 'test@amail.com';
        $contact->address = 'test address';
        $contact->insert();
        $cid = $contact->id;
        $uid = $contact->user_id;
        $new_contact = new Contact();
        $new_user = new User();
        $this->assertEquals($cid, $new_contact->find($cid)->id);
        $this->assertEquals($uid, $new_user->eq('id', $uid)->find()->id);
        $this->assertTrue($contact->user->delete());
        $this->assertTrue($contact->delete());
        $new_contact = new Contact();
        $new_user = new User();
        $this->assertFalse($new_contact->eq('id', $cid)->find());
        $this->assertFalse($new_user->find($uid));
    }
}
