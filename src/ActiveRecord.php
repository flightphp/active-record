<?php
declare(strict_types=1);

namespace flight;

use Exception;
use PDO;

/**
 * Created on Nov 26, 2013
 * @author Lloyd Zhou
 * @email lloydzhou@qq.com
 *
 * Updated on Jan 16, 2024
 * @author n0nag0n <n0nag0n@sky-9.com>
 */

/**
 * Simple implement of active record in PHP.<br />
 * Using magic function to implement more smarty functions.<br />
 * Can using chain method calls, to build concise and compactness program.<br />
 * 
 * @method self equal(string $field, mixed $value, string $operator = 'AND') Equal operator
 * @method self eq(string $field, mixed $value, string $operator = 'AND') Equal operator
 * @method self notEqual(string $field, mixed $value, string $operator = 'AND') Not Equal operator
 * @method self ne(string $field, mixed $value, string $operator = 'AND') Not Equal operator
 * @method self greaterThan(string $field, mixed $value, string $operator = 'AND') Greater Than
 * @method self gt(string $field, mixed $value, string $operator = 'AND') Greater Than
 * @method self lessThan(string $field, mixed $value, string $operator = 'AND') Less Than
 * @method self lt(string $field, mixed $value, string $operator = 'AND') Less Than
 * @method self greaterThanOrEqual(string $field, mixed $value, string $operator = 'AND') Greater Than or Equal To
 * @method self ge(string $field, mixed $value, string $operator = 'AND') Greater Than or Equal To
 * @method self gte(string $field, mixed $value, string $operator = 'AND') Greater Than or Equal To
 * @method self less(string $field, mixed $value, string $operator = 'AND') Less Than or Equal To
 * @method self le(string $field, mixed $value, string $operator = 'AND') Less Than or Equal To
 * @method self lte(string $field, mixed $value, string $operator = 'AND') Less Than or Equal To
 * @method self between(string $field, array<int,mixed> $value, string $operator = 'AND') Between
 * @method self like(string $field, mixed $value, string $operator = 'AND') Like
 * @method self notLike(string $field, mixed $value, string $operator = 'AND') Not Like
 * @method self in(string $field, array<int,mixed> $value, string $operator = 'AND') In
 * @method self notIn(string $field, array<int,mixed> $value, string $operator = 'AND') Not In
 * @method self isNull(string $field, string $operator = 'AND') Is Null
 * @method self isNotNull(string $field, string $operator = 'AND') Is Not Null
 * @method self notNull(string $field, string $operator = 'AND') Not Null
 */
abstract class ActiveRecord extends Base
{
	public const BELONGS_TO = 'belongs_to';
    public const HAS_MANY = 'has_many';
    public const HAS_ONE = 'has_one';
	public const PREFIX = ':ph';

    /**
     * @var array mapping the function name and the operator, to build Expressions in WHERE condition.
     * <pre>user can call it like this:
     *      $user->isNotNull()->eq('id', 1);
     * will create Expressions can explain to SQL:
     *      WHERE user.id IS NOT NULL AND user.id = :ph1</pre>
     */
    protected array $operators = [
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
     * @var array Part of SQL, maping the function name and the operator to build SQL Part.
     * <pre>call function like this:
     *      $user->order('id desc', 'name asc')->limit(2,1);
     *  can explain to SQL:
     *      ORDER BY id desc, name asc limit 2,1</pre>
     */
    protected array $sqlParts = [
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
    protected array $defaultSqlExpressions = [ 
		'expressions' => [], 
		'wrap' => false,
        'select'=>null, 
		'insert'=>null, 
		'update'=>null, 
		'set' => null, 
		'delete'=>'DELETE ', 
		'join' => null,
        'from'=>null, 
		'values' => null, 
		'where'=>null, 
		'having'=>null, 
		'limit'=>null, 
		'order'=>null, 
		'group' => null 
	];

    /**
     * @var array Stored the Expressions of the SQL.
     */
    protected array $sqlExpressions = [];

	/**
	 * PDO connection
	 *
	 * @var PDO
	 */
	protected PDO $pdo;

    /**
     * @var string  The table name in database.
     */
    protected string $table;

    /**
     * @var string  The primary key of this ActiveRecord, only supports single primary key.
     */
    protected string $primaryKey = 'id';

    /**
     * @var array Stored the dirty data of this object, when call "insert" or "update" function, will write this data into database.
     */
    protected array $dirty = [];

    /**
     * @var array Stored the params will bind to SQL when call PDOStatement::execute(),
     */
    protected array $params = [];
    
    /**
     * @var array Stored the configure of the relation, or target of the relation.
     */
    protected array $relations = [];

    /**
     * @var int The count of bind params, using this count and const "PREFIX" (:ph) to generate place holder in SQL.
     */
    protected int $count = 0;

	/**
	 * The construct
	 *
	 * @param PDO   $pdo    PDO object
	 * @param array $config Manipulate any property in the object
	 */
	public function __construct(PDO $pdo, array $config = [])
	{
		$this->pdo = $pdo;
		parent::__construct($config);
	}
    
    /**
     * function to reset the $params and $sqlExpressions.
     * @return ActiveRecord return $this, can using chain method calls.
     */
    public function reset()
    {
        $this->params = [];
        $this->sqlExpressions = [];
        return $this;
    }
    /**
     * function to SET or RESET the dirty data.
     * @param array $dirty The dirty data will be set, or empty array to reset the dirty data.
     * @return ActiveRecord return $this, can using chain method calls.
     */
    public function dirty(array $dirty = [])
    {
		$this->dirty = $dirty;
        $this->data = array_merge($this->data, $dirty);
        return $this;
    }

	/**
     * get the pdo connection.
     * @return PDO
     */
    public function getPdo()
    {
        return $this->pdo;
    }

    /**
     * set the PDO connection.
     * @param PDO $pdo
     */
    public function setPdo(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * function to find one record and assign in to current object.
     * @param int|string $id If call this function using this param, will find record by using this id. If not set, just find the first record in database.
     * @return bool|ActiveRecord if find record, assign in to current object and return it, other wise return "false".
     */
    public function find($id = null)
    {
        if ($id !== null) {
            $this->reset()->eq($this->primaryKey, $id);
        }
        return $this->query($this->limit(1)->buildSql(['select', 'from', 'join', 'where', 'group', 'having', 'order', 'limit', 'offset']), $this->params, $this->reset(), true);
    }
    /**
     * function to find all records in database.
     * @return array<int,ActiveRecord> return array of ActiveRecord
     */
    public function findAll(): array
    {
        return $this->query($this->buildSql(['select', 'from', 'join', 'where', 'group', 'having', 'order', 'limit', 'offset']), $this->params, $this->reset());
    }
    /**
     * function to delete current record in database.
     * @return bool
     */
    public function delete()
    {
        return $this->execute($this->eq($this->primaryKey, $this->{$this->primaryKey})->buildSql(['delete', 'from', 'where']), $this->params);
    }
    /**
     * function to build update SQL, and update current record in database, just write the dirty data into database.
     * @return bool|ActiveRecord if update success return current object, other wise return false.
     */
    public function update()
    {
        if (count($this->dirty) === 0) {
            return true;
        }
        foreach ($this->dirty as $field => $value) {
            $this->addCondition($field, '=', $value, ',', 'set');
        }
        if ($this->execute($this->eq($this->primaryKey, $this->{$this->primaryKey})->buildSql(['update', 'set', 'where']), $this->params)) {
            return $this->dirty()->reset();
        }
        return false;
    }
    /**
     * function to build insert SQL, and insert current record into database.
     * @return bool|ActiveRecord if insert success return current object, other wise return false.
     */
    public function insert()
    {
        if (count($this->dirty) == 0) {
            return true;
        }
        $value = $this->filterParam($this->dirty);
        $this->insert = new Expressions([
			'operator'=> 'INSERT INTO '. $this->table,
            'target' => new WrapExpressions(['target' => array_keys($this->dirty)])
		]);
        $this->values = new Expressions(['operator'=> 'VALUES', 'target' => new WrapExpressions(['target' => $value])]);
        if ($this->execute($this->buildSql(['insert', 'values']), $this->params)) {
            $this->{$this->primaryKey} = $this->pdo->lastInsertId();
            return $this->dirty()->reset();
        }
        return false;
    }
    /**
     * helper function to exec sql.
     * @param string $sql The SQL need to be execute.
     * @param array $params The param will be bind to PDOStatement.
     * @return bool
     */
    public function execute(string $sql, array $params = []): bool
    {
        $statement = $this->pdo->prepare($sql);

        if ($statement === false) {
            throw new Exception($this->pdo->errorInfo()[2]);
        }
        $result = $statement->execute($params);
        if ($result === false) {
            throw new Exception($statement->errorInfo()[2]);
        }
        return $result;
    }
    /**
     * helper function to query one record by sql and params.
     * @param string $sql The SQL to find record.
     * @param array $param The param will be bind to PDOStatement.
     * @param ActiveRecord $obj The object, if find record in database, will assign the attributes in to this object.
     * @param bool $single if set to true, will find record and fetch in current object, otherwise will find all records.
     * @return bool|ActiveRecord|array
     */
    public function query(string $sql, array $param = [], ActiveRecord $obj = null, bool $single = false)
    {
        if ($sth = $this->pdo->prepare($sql)) {
            $called_class = get_called_class();
            $sth->setFetchMode(PDO::FETCH_INTO, ($obj ? $obj : new $called_class ));
            $sth->execute($param);
            if ($single) {
                return $sth->fetch(PDO::FETCH_INTO) ? $obj->dirty() : false;
            }
            $result = [];
            while ($obj = $sth->fetch(PDO::FETCH_INTO)) {
                $result[] = clone $obj->dirty();
            }
            return $result;
        }
        return false;
    }
    /**
     * helper function to get relation of this object.
     * There was three types of relations: {BELONGS_TO, HAS_ONE, HAS_MANY}
     * @param string $name The name of the relation, the array key when defind the relation.
     * @return mixed
     */
    protected function &getRelation(string $name)
    {
        $relation = $this->relations[$name];
        if ($relation instanceof self || (is_array($relation) && $relation[0] instanceof self)) {
            return $relation;
        }
        $this->relations[$name] = $obj = new $relation[1]($this->pdo);
        if (isset($relation[3]) && is_array($relation[3])) {
            foreach ((array)$relation[3] as $func => $args) {
                call_user_func_array([$obj, $func], (array)$args);
            }
        }
        $backref = isset($relation[4]) ? $relation[4] : '';
        if ((!$relation instanceof self) && self::HAS_ONE == $relation[0]) {
            $obj->eq($relation[2], $this->{$this->primaryKey})->find() && $backref && $obj->__set($backref, $this);
        } elseif (is_array($relation) && self::HAS_MANY == $relation[0]) {
            $this->relations[$name] = $obj->eq($relation[2], $this->{$this->primaryKey})->findAll();
            if ($backref) {
                foreach ($this->relations[$name] as $o) {
                    $o->__set($backref, $this);
                }
            }
        } elseif ((!$relation instanceof self) && self::BELONGS_TO == $relation[0]) {
            $obj->eq($obj->primaryKey, $this->{$relation[2]})->find() && $backref && $obj->__set($backref, $this);
        } else {
            throw new Exception("Relation $name not found.");
        }
        return $this->relations[$name];
    }
    /**
     * helper function to build SQL with sql parts.
     * @param string $sql_statement The SQL part will be build.
     * @param ActiveRecord $o The reference to $this
     * @return string
     */
    protected function buildSqlCallback(string $sql_statement, ActiveRecord $object): string
    {
        if ('select' === $sql_statement && null == $object->$sql_statement) {
            $sql_statement = strtoupper($sql_statement). ' '.$object->table.'.*';
        } elseif (('update' === $sql_statement || 'from' === $sql_statement) && null == $object->$sql_statement) {
            $sql_statement = strtoupper($sql_statement).' '. $object->table;
        } elseif ('delete' === $sql_statement) {
            $sql_statement = strtoupper($sql_statement). ' ';
        } else {
            $sql_statement = (null !== $object->$sql_statement) ? $object->$sql_statement. ' ' : '';
        }

		return $sql_statement;
    }

    /**
     * helper function to build SQL with sql parts.
     * @param array $sql_statements The SQL part will be build.
     * @return string
     */
    protected function buildSql(array $sql_statements = []): string
    {
		foreach($sql_statements as &$sql) {
			$sql = $this->buildSqlCallback($sql, $this);
		}
        //this code to debug info.
        //echo 'SQL: ', implode(' ', $sql_statements), "\n", "PARAMS: ", implode(', ', $this->params), "\n";
        return implode(' ', $sql_statements);
    }
    /**
     * make wrap when build the SQL expressions of WHERE.
     * @param string $op If give this param will build one WrapExpressions include the stored expressions add into WHERE. otherwise wil stored the expressions into array.
     * @return ActiveRecord return $this, can using chain method calls.
     */
    public function wrap(?string $op = null)
    {
        if (1 === func_num_args()) {
            $this->wrap = false;
            if (is_array($this->expressions) && count($this->expressions) > 0) {
                $this->addConditionGroup(new WrapExpressions(['delimiter' => ' ','target'=>$this->expressions]), 'or' === strtolower($op) ? 'OR' : 'AND');
            }
            $this->expressions = [];
        } else {
            $this->wrap = true;
        }
        return $this;
    }
    /**
     * helper function to build place holder when making SQL expressions.
     * @param mixed $value the value will bind to SQL, just store it in $this->params.
     * @return mixed $value
     */
    protected function filterParam($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $this->params[$value[$key] = self::PREFIX. ++$this->count] = $val;
            }
        } elseif (is_string($value)) {
			$ph = self::PREFIX. ++$this->count;
            $this->params[$ph] = $value;
            $value = $ph;
        }
        return $value;
    }
    /**
     * helper function to add condition into WHERE.
     * create the SQL Expressions.
     * @param string $field The field name, the source of Expressions
     * @param string $operator
     * @param mixed $value the target of the Expressions
     * @param string $delimiter the operator to concat this Expressions into WHERE or SET statement.
     * @param string $name The Expression will contact to.
     */
    public function addCondition(string $field, string $operator, $value, string $delimiter = 'AND', string $name = 'where')
    {
        $value = $this->filterParam($value);
		$name = strtolower($name);
		$expressions = new Expressions([
			'source' => ('where' === $name ? $this->table.'.' : '') . $field, 
			'operator' => $operator, 
			'target'=> (
				is_array($value)
				? new WrapExpressions(
					'between' === strtolower($operator)
					? [ 'target' => $value, 'start' => ' ', 'end' => ' ', 'delimiter' => ' AND ' ]
					: [ 'target' => $value ]
					)
				: $value
			)
		]);
        if($expressions) {
            if (empty($this->wrap)) {
                $this->addConditionGroup($expressions, $delimiter, $name);
            } else {
                $this->addExpression($expressions, $delimiter);
            }
        }
    }
    /**
     * helper function to add condition into JOIN.
     * create the SQL Expressions.
     * @param string $table The join table name
     * @param string $on The condition of ON
     * @param string $type The join type, like "LEFT", "INNER", "OUTER", "RIGHT"
     */
    public function join(string $table, string $on, string $type = 'LEFT')
    {
        $this->join = new Expressions([
			'source' => $this->join ?? '', 
			'operator' => $type. ' JOIN', 
			'target' => new Expressions(
					[
						'source' => $table, 
						'operator' => 'ON', 
						'target' => $on
					]
				)
			]);
        return $this;
    }
    /**
     * helper function to make wrapper. Stored the expression in to array.
     * @param Expressions $exp The expression will be stored.
     * @param string $delimiter The operator to concat this Expressions into WHERE statement.
     */
    protected function addExpression(Expressions $expressions, string $delimiter)
    {
        if (!is_array($this->expressions) || count($this->expressions) == 0) {
            $this->expressions = [ $expressions ];
        } else {
            $this->expressions[] = new Expressions(['operator' => $delimiter, 'target' => $expressions]);
        }
    }
    /**
     * helper function to add condition into WHERE.
     * @param Expressions $exp The expression will be concat into WHERE or SET statement.
     * @param string $operator the operator to concat this Expressions into WHERE or SET statement.
     * @param string $name The Expression will contact to.
     */
    protected function addConditionGroup(Expressions $expressions, string $operator, string $name = 'where')
    {
        if (!$this->{$name}) {
            $this->{$name} = new Expressions(['operator' => strtoupper($name) , 'target' => $expressions]);
        } else {
            $this->{$name}->target = new Expressions(['source' => $this->$name->target, 'operator' => $operator, 'target' => $expressions]);
        }
    }
	/**
     * magic function to make calls witch in function mapping stored in $operators and $sqlPart.
     * also can call function of PDO object.
     * @param string $name function name
     * @param array $args The arguments of the function.
     * @return mixed Return the result of callback or the current object to make chain method calls.
     */
    public function __call($name, $args)
    {
        if (is_callable($callback = [$this->pdo,$name])) {
            return call_user_func_array($callback, $args);
        }
		$name= str_replace('by', '', $name);
        if (in_array($name, array_keys($this->operators))) {
			$field = $args[0];
			$operator = $this->operators[$name];
			$value = isset($args[1]) ? $args[1] : null;
			$last_arg = end($args);
			$and_or_or = is_string($last_arg) && strtolower($last_arg) === 'or' ? 'OR' : 'AND';

            $this->addCondition($field, $operator, $value, $and_or_or);
        } elseif (in_array($name, array_keys($this->sqlParts))) {
            $this->{$name} = new Expressions([
				'operator'=>$this->sqlParts[$name], 
				'target' => implode(', ', $args)
			]);
        } else {
            throw new Exception("Method {$name} not exist.");
        }
        return $this;
    }

    /**
     * magic function to SET values of the current object.
     */
    public function __set($var, $val)
    {
        if (array_key_exists($var, $this->sqlExpressions) || array_key_exists($var, $this->defaultSqlExpressions)) {
            $this->sqlExpressions[$var] = $val;
        } elseif (array_key_exists($var, $this->relations) && $val instanceof self) {
            $this->relations[$var] = $val;
        } else {
            $this->dirty[$var] = $this->data[$var] = $val;
        }
    }

    /**
     * magic function to UNSET values of the current object.
     */
    public function __unset($var)
    {
        if (array_key_exists($var, $this->sqlExpressions)) {
            unset($this->sqlExpressions[$var]);
        }
        if (isset($this->data[$var])) {
            unset($this->data[$var]);
        }
        if (isset($this->dirty[$var])) {
            unset($this->dirty[$var]);
        }
    }

    /**
     * magic function to GET the values of current object.
     */
    public function &__get($var)
    {
        if (array_key_exists($var, $this->sqlExpressions)) {
            return  $this->sqlExpressions[$var];
        } elseif (array_key_exists($var, $this->relations)) {
            return $this->getRelation($var);
        } else {
            return  parent::__get($var);
        }
    }
}
