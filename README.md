# FlightPHP Active Record 
[![Latest Stable Version](https://poser.pugx.org/flightphp/active-record/v)](https://packagist.org/packages/flightphp/active-record)
[![License](https://poser.pugx.org/flightphp/active-record/license)](https://packagist.org/packages/flightphp/active-record)
[![PHP Version Require](https://poser.pugx.org/flightphp/active-record/require/php)](https://packagist.org/packages/flightphp/active-record)
[![Dependencies](https://poser.pugx.org/flightphp/active-record/dependents)](https://packagist.org/packages/flightphp/active-record)

An active record is mapping a database entity to a PHP object. Spoken plainly, if you have a users table in your database, you can "translate" a row in that table to a `User` class and a `$user` object in your codebase. See [basic example](#basic-example).

## Basic Example

Let's assume you have the following table:

```sql
CREATE TABLE users (
	id INTEGER PRIMARY KEY, 
	name TEXT, 
	password TEXT 
);
```

Now you can setup a new class to represent this table:

```php
/**
 * An ActiveRecord class is usually singular
 * 
 * It's highly recommended to add the properties of the table as comments here
 *
 * @property int    $id
 * @property string $name
 * @property string $password
 */ 
class User extends flight\ActiveRecord {
	public function __construct($databaseConnection)
	{
		parent::__construct($databaseConnection, 'users', [ /* custom values */ ]);
	}
}
```

Now watch the magic happen!

```php
// for sqlite
$database_connection = new PDO('sqlite:test.db'); // this is just for example, you'd probably use a real database connection

// for mysql
$database_connection = new PDO('mysql:host=localhost;dbname=test_db&charset=utf8bm4', 'username', 'password');

// or mysqli
$database_connection = new mysqli('localhost', 'username', 'password', 'test_db');
// or mysqli with non-object based creation
$database_connection = mysqli_connect('localhost', 'username', 'password', 'test_db');

$user = new User($database_connection);
$user->name = 'Bobby Tables';
$user->password = password_hash('some cool password');
$user->insert();
// or $user->save();

echo $user->id; // 1

$user->name = 'Joseph Mamma';
$user->password = password_hash('some cool password again!!!');
$user->insert();

echo $user->id; // 2
```

And it was just that easy to add a new user! Now that there is a user row in the database, how do you pull it out?

```php
$user->find(1); // find id = 1 in the database and return it.
echo $user->name; // 'Bobby Tables'
```

And what if you want to find all the users?

```php
$users = $user->findAll();
```

What about with a certain condition?

```php
$users = $user->like('name', '%mamma%')->findAll();
```

See how much fun this is? Let's install it and get started!

## Installation

Simply install with Composer

```php
composer require flightphp/active-record 
```

## Documentation

Head over to the [documentation page](https://docs.flightphp.com/awesome-plugins/active-record) to learn more about usage and how cool this thing is! :)

## License

MIT
