<?php

namespace flight\tests\classes;

use flight\ActiveRecord;

class User extends ActiveRecord
{
    public $table = 'user';
    public $primaryKey = 'id';
    public $relations = array(
        'contacts' => array(self::HAS_MANY, Contact::class, 'user_id'),
        'contacts_with_backref' => array(self::HAS_MANY, Contact::class, 'user_id', null, 'user'),
        'contact' => array(self::HAS_ONE, Contact::class, 'user_id', array('where' => '1', 'order' => 'id desc')),
    );
}
