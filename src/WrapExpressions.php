<?php
declare(strict_types=1);

namespace flight;

/**
 * Class WrapExpressions
 * @property string|Expressions|array $target of this expression (required)
 */
class WrapExpressions extends Expressions
{

    public string $start = '(';
    public string $end = ')';
    public string $delimiter = ',';

    public function __toString()
    {
        return $this->start . implode(($this->delimiter ? $this->delimiter: ','), $this->target) . $this->end;
    }
}
