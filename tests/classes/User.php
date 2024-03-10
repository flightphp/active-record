<?php

namespace flight\tests\classes;

use flight\ActiveRecord;

/**
 * @property int $id
 * @property string $name
 * @property string $password
 * @property string $created_dt
 */
class User extends ActiveRecord
{
    public string $table = 'user';
    public string $primaryKey = 'id';
    public array $relations = [
        'contacts' => [self::HAS_MANY, Contact::class, 'user_id'],
        'contacts_with_backref' => [self::HAS_MANY, Contact::class, 'user_id', null, 'user'],
        'contact' => [self::HAS_ONE, Contact::class, 'user_id', ['where' => '1', 'order' => 'id desc']],
    ];
}
