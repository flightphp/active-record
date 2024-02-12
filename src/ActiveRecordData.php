<?php

declare(strict_types=1);

namespace flight;

class ActiveRecordData
{
    public const PREFIX = ':ph';

    /**
     * @var array mapping the function name and the operator, to build Expressions in WHERE condition.
     * <pre>user can call it like this:
     *      $user->isNotNull()->eq('id', 1);
     * will create Expressions can explain to SQL:
     *      WHERE user.id IS NOT NULL AND user.id = :ph1</pre>
     */
    public const OPERATORS = [
        'equal' => '=', 'eq' => '=',
        'notEqual' => '<>', 'ne' => '<>',
        'greaterThan' => '>', 'gt' => '>',
        'lessThan' => '<', 'lt' => '<',
        'greaterThanOrEqual' => '>=', 'ge' => '>=','gte' => '>=',
        'lessThanOrEqual' => '<=', 'le' => '<=','lte' => '<=',
        'between' => 'BETWEEN',
        'like' => 'LIKE',
        'notLike' => 'NOT LIKE',
        'in' => 'IN',
        'notIn' => 'NOT IN',
        'isNull' => 'IS NULL',
        'isNotNull' => 'IS NOT NULL',
        'notNull' => 'IS NOT NULL',
    ];
    /**
     * @var array Part of SQL, mapping the function name and the operator to build SQL Part.
     * <pre>call function like this:
     *      $user->order('id desc', 'name asc')->limit(2,1);
     *  can explain to SQL:
     *      ORDER BY id desc, name asc limit 2,1</pre>
     */
    public const SQL_PARTS = [
        'select' => 'SELECT',
        'from' => 'FROM',
        'set' => 'SET',
        'where' => 'WHERE',
        'group' => 'GROUP BY','groupBy' => 'GROUP BY',
        'having' => 'HAVING',
        'order' => 'ORDER BY','orderBy' => 'ORDER BY',
        'limit' => 'LIMIT',
        'offset' => 'OFFSET',
        'top' => 'TOP',
    ];
    /**
     * @var array property to stored the default Sql Expressions values.
     */
    public const DEFAULT_SQL_EXPRESSIONS = [
        'expressions'   => [],
        'wrap'          => false,
        'select'        => null,
        'insert'        => null,
        'update'        => null,
        'set'           => null,
        'delete'        => 'DELETE ',
        'join'          => null,
        'from'          => null,
        'values'        => null,
        'where'         => null,
        'having'        => null,
        'limit'         => null,
        'order'         => null,
        'group'         => null
    ];

    /**
     * Possible Events that can be run on the Active Record
     *
     * @var array
     */
    public const EVENTS = [
        'beforeInsert',
        'afterInsert',
        'beforeUpdate',
        'afterUpdate',
        'beforeSave',
        'afterSave',
        'beforeDelete',
        'afterDelete',
        'beforeFind',
        'afterFind',
        'beforeFindAll',
        'afterFindAll',
        'onConstruct'
    ];
}
