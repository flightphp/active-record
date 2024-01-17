# FlightPHP Active Record 
[![License](https://poser.pugx.org/flightphp/active-record/license)](https://packagist.org/packages/flightphp/active-record)

Micro Active Record library in PHP, support chain calls and relations(HAS_ONE, HAS_MANY, BELONGS_TO).

## API Reference
### CRUD functions
#### setDb(\PDO  $db) 
set the DB connection.

```php
ActiveRecord::setDb(new PDO('sqlite:test.db'));
```

#### insert() : boolean|\ActiveRecord
function to build insert SQL, and insert current record into database.
if insert success return current object, other wise return false.

```php
$user = new User();
$user->name = 'demo';
$user->password = md5('demo');
$user->insert();
```

#### find(integer  $id = null) : boolean|\ActiveRecord
function to find one record and assign in to current object. If call this function using $id param, will find record by using this id. If not set, just find the first record in database. if find record, assign in to current object and return it, other wise return "false".

```php
    $user->notnull('id')->orderby('id desc')->find();
```

#### findAll() : array
function to find all records in database. return array of ActiveRecord

```php
$user->findAll();
```

#### update() : boolean|\ActiveRecord
function to build update SQL, and update current record in database, just write the dirty data into database.
if update success return current object, other wise return false.

```php
$user->notnull('id')->orderby('id desc')->find();
$user->email = 'test@example.com';
$user->update();
```

#### delete() : boolean
function to delete current record in database. 

#### reset() : \ActiveRecord
function to reset the $params and $sqlExpressions. return $this, can using chain method calls.

#### dirty(array  $dirty = array()) : \ActiveRecord
function to SET or RESET the dirty data. The dirty data will be set, or empty array to reset the dirty data.

### SQL part functions
#### select()
function to set the select columns.

```php
$user->select('id', 'name')->find();
```

#### from()
function to set the table to find record

```php
$user->select('id', 'name')->from('user')->find();
```

#### join()
function to set the table to find record

```php
$user->join('contact', 'contact.user_id = user.id')->find();
```

#### where()
function to set where conditions

```php
$user->where('id=1 AND name="demo"')->find();
```

#### group()/groupby()

```php
$user->select('count(1) as count')->groupby('name')->findAll();
```

#### order()/orderby()

```php
$user->orderby('name DESC')->find();
```

#### limit()

```php
$user->orderby('name DESC')->limit(0, 1)->find();
```

### WHERE conditions
#### equal()/eq()

```php
$user->eq('id', 1)->find();
```

#### notequal()/ne()

```php
$user->ne('id', 1)->find();
```

#### greaterthan()/gt()

```php
$user->gt('id', 1)->find();
```

#### lessthan()/lt()
```php
$user->lt('id', 1)->find();
```
#### greaterthanorequal()/ge()/gte()
```php
$user->ge('id', 1)->find();
```
#### lessthanorequal()/le()/lte()
```php
$user->le('id', 1)->find();
```
#### like()
```php
$user->like('name', 'de')->find();
```
#### in()
```php
$user->in('id', [1, 2])->find();
```
#### notin()
```php
$user->notin('id', [1,3])->find();
```
#### isnull()
```php
$user->isnull('id')->find();
```
#### isnotnull()/notnull()
```php
$user->isnotnull('id')->find();
```
## Install
```php
composer require bephp/activerecord 
```

## Demo
### Include base class ActiveRecord
```php
include "ActiveRecord.php";
```
### Define Class
```php
class User extends ActiveRecord{
	public $table = 'user';
	public $primaryKey = 'id';
	public $relations = array(
		'contacts' => array(self::HAS_MANY, 'Contact', 'user_id'),
		'contact' => array(self::HAS_ONE, 'Contact', 'user_id'),
	);
}
class Contact extends ActiveRecord{
	public $table = 'contact';
	public $primaryKey = 'id';
	public $relations = array(
		'user' => array(self::BELONGS_TO, 'User', 'user_id'),
		'user_with_backref' => array(self::BELONGS_TO, 'User', 'user_id', array(), 'contact'),
        // using 5th param to define backref
	);
}
```
### Init data
```php
ActiveRecord::setDb(new PDO('sqlite:test.db'));
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
```
### Insert one User into database.
```php
$user = new User();
$user->name = 'demo';
$user->password = md5('demo');
var_dump($user->insert());
```
### Insert one Contact belongs the current user.
```php
$contact = new Contact();
$contact->address = 'test';
$contact->email = 'test1234456@domain.com';
$contact->user_id = $user->id;
var_dump($contact->insert());
```
### Example to using relations 
```php
$user = new User();
// find one user
var_dump($user->notnull('id')->orderby('id desc')->find());
echo "\nContact of User # {$user->id}\n";
// get contacts by using relation:
//   'contacts' => array(self::HAS_MANY, 'Contact', 'user_id'),
var_dump($user->contacts);

$contact = new Contact();
// find one contact
var_dump($contact->find());
// get user by using relation:
//    'user' => array(self::BELONGS_TO, 'User', 'user_id'),
var_dump($contact->user);
```

