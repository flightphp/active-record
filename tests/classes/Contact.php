<?php

namespace flight\tests\classes;

use flight\ActiveRecord;

/**
 * @property int $id
 * @property int $user_id
 * @property string $email
 * @property string $address
 */
class Contact extends ActiveRecord
{
    public string $table = 'contact';
    public string $primaryKey = 'id';
    public array $relations = [
        'user_with_backref' => [self::BELONGS_TO, User::class, 'user_id', null, 'contact'],
        'user' => [self::BELONGS_TO, User::class, 'user_id'],
    ];
}
