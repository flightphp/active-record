<?php

namespace flight\tests\classes;

use flight\ActiveRecord;

class Contact extends ActiveRecord
{
    public string $table = 'contact';
    public string $primaryKey = 'id';
    public array $relations = [
        'user_with_backref' => [self::BELONGS_TO, User::class, 'user_id', null, 'contact'],
        'user' => [self::BELONGS_TO, User::class, 'user_id'],
    ];
}
