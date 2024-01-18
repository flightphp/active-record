<?php

namespace flight\tests\classes;

use flight\ActiveRecord;

class Contact extends ActiveRecord
{
    public string $table = 'contact';
    public string $primaryKey = 'id';
    public array $relations = array(
        'user_with_backref' => array(self::BELONGS_TO, User::class, 'user_id', null, 'contact'),
        'user' => array(self::BELONGS_TO, User::class, 'user_id'),
    );
}
