<?php

namespace flight\test;

use flight\ActiveRecord;

class Contact extends ActiveRecord
{
    public $table = 'contact';
    public $primaryKey = 'id';
    public $relations = array(
        'user_with_backref' => array(self::BELONGS_TO, 'User', 'user_id', null, 'contact'),
        'user' => array(self::BELONGS_TO, 'User', 'user_id'),
    );
}
