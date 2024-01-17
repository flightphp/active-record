<?php
declare(strict_types=1);

namespace flight;

/**
 * Class WrapExpressions
 */
class WrapExpressions extends Expressions
{
    public function __toString()
    {
        $start = $this->start ? $this->start : '(';
        $end = $this->end ? $this->end : ')';
        return $start . implode(($this->delimiter ? $this->delimiter: ','), $this->target) . $end;
    }
}
