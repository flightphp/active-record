<?php

namespace flight\tests\classes;

use flight\ActiveRecord;

/**
 * Test subclass with typed public properties.
 * Used to verify ActiveRecord works correctly when subclasses
 * declare typed properties instead of using dynamic properties.
 */
class TypedUser extends ActiveRecord
{
    public int $id;
    public string $name;
    public string $password;
    public ?string $created_dt = null;

    public function __construct($databaseConnection = null, array $config = [])
    {
        parent::__construct($databaseConnection, 'user', $config);
    }
}
