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
    public array $data = [];

    /**
     * Construct
     *
     * @param array $config Object properties to save.
     */
    public function __construct(array $config = [])
    {
        foreach ($config as $key => $val) {
            $this->{$key} = $val;
        }
    }

    /**
     * Magic set method
     *
     * Not sure if this could even be hit with the way it's structured now.
     *
     * @param string $var the variable
     * @param mixed $val the value
     * @codeCoverageIgnore
     */
    public function __set($var, $val)
    {
        $this->data[$var] = $val;
    }

    /**
     * Magic get method.
     * @param string $var the Variable
     */
    public function &__get($var)
    {
        $result = isset($this->data[$var]) ? $this->data[$var] : null;
        return $result;
    }
}
