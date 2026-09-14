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

    /**
     * Nullable float column so tests can prove strict comparison detection
     * when a falsy value (0.0) is written over a NULL stored value.
     */
    public ?float $credits = null;

    /**
     * Intentionally left unset in many tests so sync helpers skip uninitialized props.
     * @var string
     */
    public string $unused_optional;

    /** Skipped by sync helpers; present so the static-property branch is covered */
    public static string $marker = 'typed-user';

    public function __construct($databaseConnection = null, array $config = [])
    {
        parent::__construct($databaseConnection, 'user', $config);
    }
}
