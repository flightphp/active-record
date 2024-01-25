<?php

declare(strict_types=1);

namespace flight;

/**
 * Class Expressions, part of SQL.
 * Every SQL can be split into multiple expressions.
 * Each expression contains three parts:
 * @property string|Expressions $source of this expression, (option)
 * @property string $operator (required)
 * @property string|Expressions $target of this expression (required)
 * Just implement one function __toString.
 */
class Expressions extends Base
{
    public $source;
    public string $operator;
    public $target;
    public function __toString()
    {
        return $this->source . ' ' . $this->operator . ' ' . $this->target;
    }
}
