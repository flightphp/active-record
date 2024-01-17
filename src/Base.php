<?php
declare(strict_types=1);

namespace flight;

/**
 * base class to stored attributes in one array.
 */
abstract class Base
{
    /**
     * @var array Stored the attributes of the current object
     */
    public $data = array();
    public function __construct($config = array())
    {
        foreach ($config as $key => $val) {
            $this->$key = $val;
        }
    }
    public function __set($var, $val)
    {
        $this->data[$var] = $val;
    }
    public function & __get($var)
    {
        $result = isset($this->data[$var]) ? $this->data[$var] : null;
        return $result;
    }
}
