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
 */
abstract class ActiveRecord extends Base
{
	const BELONGS_TO = 'belongs_to';
    const HAS_MANY = 'has_many';
    const HAS_ONE = 'has_one';

    /**
     * @var PDO static property to connect database.
     */
    public static $db;
    /**
     * @var array mapping the function name and the operator, to build Expressions in WHERE condition.
     * <pre>user can call it like this:
     *      $user->isNotNull()->eq('id', 1);
     * will create Expressions can explain to SQL:
     *      WHERE user.id IS NOT NULL AND user.id = :ph1</pre>
     */
    public static $operators = [
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
    public static $sqlParts = array(
        'select' => 'SELECT',
        'from' => 'FROM',
        'set' => 'SET',
        'where' => 'WHERE',
        'group' => 'GROUP BY','groupby' => 'GROUP BY',
        'having' => 'HAVING',
        'order' => 'ORDER BY','orderby' => 'ORDER BY',
        'limit' => 'limit',
        'top' => 'TOP',
    );
    /**
     * @var array Static property to stored the default Sql Expressions values.
     */
    public static $defaultSqlExpressions = [ 
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
    protected $sqlExpressions = [];
    /**
     * @var string  The table name in database.
     */
    public $table;
    /**
     * @var string  The primary key of this ActiveRecord, just suport single primary key.
     */
    public $primaryKey = 'id';
    /**
     * @var array Stored the drity data of this object, when call "insert" or "update" function, will write this data into database.
     */
    public $dirty = [];
    /**
     * @var array Stored the params will bind to SQL when call PDOStatement::execute(),
     */
    public $params = [];
    
    /**
     * @var array Stored the configure of the relation, or target of the relation.
     */
    public $relations = [];
    /**
     * @var int The count of bind params, using this count and const "PREFIX" (:ph) to generate place holder in SQL.
     */
    public static $count = 0;
    const PREFIX = ':ph';

    /**
     * function to reset the $params and $sqlExpressions.
     * @return ActiveRecord return $this, can using chain method calls.
     */
    public function reset()
    {
        $this->params = array();
        $this->sqlExpressions = array();
        return $this;
    }
    /**
     * function to SET or RESET the dirty data.
     * @param array $dirty The dirty data will be set, or empty array to reset the dirty data.
     * @return ActiveRecord return $this, can using chain method calls.
     */
    public function dirty($dirty = array())
    {
        $this->data = array_merge($this->data, $this->dirty = $dirty);
        return $this;
    }
    /**
     * set the DB connection.
     * @param PDO $db
     */
    public static function setDb($db)
    {
        self::$db = $db;
    }
    /**
     * function to find one record and assign in to current object.
     * @param int $id If call this function using this param, will find record by using this id. If not set, just find the first record in database.
     * @return bool|ActiveRecord if find record, assign in to current object and return it, other wise return "false".
     */
    public function find($id = null)
    {
        if ($id) {
            $this->reset()->eq($this->primaryKey, $id);
        }
        return self::_query($this->limit(1)->_buildSql(array('select', 'from', 'join', 'where', 'group', 'having', 'order', 'limit')), $this->params, $this->reset(), true);
    }
    /**
     * function to find all records in database.
     * @return array return array of ActiveRecord
     */
    public function findAll()
    {
        return self::_query($this->_buildSql(array('select', 'from', 'join', 'where', 'group', 'having', 'order', 'limit')), $this->params, $this->reset());
    }
    /**
     * function to delete current record in database.
     * @return bool
     */
    public function delete()
    {
        return self::execute($this->eq($this->primaryKey, $this->{$this->primaryKey})->_buildSql(array('delete', 'from', 'where')), $this->params);
    }
    /**
     * function to build update SQL, and update current record in database, just write the dirty data into database.
     * @return bool|ActiveRecord if update success return current object, other wise return false.
     */
    public function update()
    {
        if (count($this->dirty) == 0) {
            return true;
        }
        foreach ($this->dirty as $field => $value) {
            $this->addCondition($field, '=', $value, ',', 'set');
        }
        if (self::execute($this->eq($this->primaryKey, $this->{$this->primaryKey})->_buildSql(array('update', 'set', 'where')), $this->params)) {
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
        $value = $this->_filterParam($this->dirty);
        $this->insert = new Expressions(array('operator'=> 'INSERT INTO '. $this->table,
            'target' => new WrapExpressions(array('target' => array_keys($this->dirty)))));
        $this->values = new Expressions(array('operator'=> 'VALUES', 'target' => new WrapExpressions(array('target' => $value))));
        if (self::execute($this->_buildSql(array('insert', 'values')), $this->params)) {
            $this->{$this->primaryKey} = self::$db->lastInsertId();
            return $this->dirty()->reset();
        }
        return false;
    }
    /**
     * helper function to exec sql.
     * @param string $sql The SQL need to be execute.
     * @param array $param The param will be bind to PDOStatement.
     * @return bool
     */
    public static function execute($sql, $param = array())
    {
        $statement = self::$db->prepare($sql);

        if ($statement === false) {
            throw new Exception(self::$db->errorInfo()[2]);
        }

        $result = $statement->execute($param);
        if (!$result) {
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
    public static function _query($sql, $param = array(), $obj = null, $single = false)
    {
        if ($sth = self::$db->prepare($sql)) {
            $called_class = get_called_class();
            $sth->setFetchMode(PDO::FETCH_INTO, ($obj ? $obj : new $called_class ));
            $sth->execute($param);
            if ($single) {
                return $sth->fetch(PDO::FETCH_INTO) ? $obj->dirty() : false;
            }
            $result = array();
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
    protected function &getRelation($name)
    {
        $relation = $this->relations[$name];
        if ($relation instanceof self || (is_array($relation) && $relation[0] instanceof self)) {
            return $relation;
        }
        $this->relations[$name] = $obj = new $relation[1];
        if (isset($relation[3]) && is_array($relation[3])) {
            foreach ((array)$relation[3] as $func => $args) {
                call_user_func_array(array($obj, $func), (array)$args);
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
     * @param string $n The SQL part will be build.
     * @param int $i The index of $n in $sqls array.
     * @param ActiveRecord $o The refrence to $this
     * @return string
     */
    private function _buildSqlCallback(&$n, $i, $o)
    {
        if ('select' === $n && null == $o->$n) {
            $n = strtoupper($n). ' '.$o->table.'.*';
        } elseif (('update' === $n||'from' === $n) && null == $o->$n) {
            $n = strtoupper($n).' '. $o->table;
        } elseif ('delete' === $n) {
            $n = strtoupper($n). ' ';
        } else {
            $n = (null !== $o->$n) ? $o->$n. ' ' : '';
        }
    }
    /**
     * helper function to build SQL with sql parts.
     * @param array $sqls The SQL part will be build.
     * @return string
     */
    protected function _buildSql($sqls = array())
    {
        array_walk($sqls, array($this, '_buildSqlCallback'), $this);
        //this code to debug info.
        //echo 'SQL: ', implode(' ', $sqls), "\n", "PARAMS: ", implode(', ', $this->params), "\n";
        return implode(' ', $sqls);
    }
    /**
     * make wrap when build the SQL expressions of WHWRE.
     * @param string $op If give this param will build one WrapExpressions include the stored expressions add into WHWRE. otherwise wil stored the expressions into array.
     * @return ActiveRecord return $this, can using chain method calls.
     */
    public function wrap($op = null)
    {
        if (1 === func_num_args()) {
            $this->wrap = false;
            if (is_array($this->expressions) && count($this->expressions) > 0) {
                $this->_addCondition(new WrapExpressions(array('delimiter' => ' ','target'=>$this->expressions)), 'or' === strtolower($op) ? 'OR' : 'AND');
            }
            $this->expressions = array();
        } else {
            $this->wrap = true;
        }
        return $this;
    }
    /**
     * helper function to build place holder when make SQL expressions.
     * @param mixed $value the value will bind to SQL, just store it in $this->params.
     * @return mixed $value
     */
    protected function _filterParam($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $this->params[$value[$key] = self::PREFIX. ++self::$count] = $val;
            }
        } elseif (is_string($value)) {
            $this->params[$ph = self::PREFIX. ++self::$count] = $value;
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
     * @param string $op the operator to concat this Expressions into WHERE or SET statement.
     * @param string $name The Expression will contact to.
     */
    public function addCondition($field, $operator, $value, $op = 'AND', $name = 'where')
    {
        $value = $this->_filterParam($value);
		$exp = new Expressions([
			'source' => ('where' == $name ? $this->table.'.' : '') . $field, 
			'operator'=>$operator, 
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
        if($exp) {
            if (!$this->wrap) {
                $this->_addCondition($exp, $op, $name);
            } else {
                $this->_addExpression($exp, $op);
            }
        }
    }
    /**
     * helper function to add condition into JOIN.
     * create the SQL Expressions.
     * @param string $table The join table name
     * @param string $on The condition of ON
     * @param string $type The join type, like "LEFT", "INNER", "OUTER"
     */
    public function join($table, $on, $type = 'LEFT')
    {
        $this->join = new Expressions(array('source' => $this->join ?: '', 'operator' => $type. ' JOIN', 'target' => new Expressions(
            array('source' => $table, 'operator' => 'ON', 'target' => $on)
        )));
        return $this;
    }
    /**
     * helper function to make wrapper. Stored the expression in to array.
     * @param Expressions $exp The expression will be stored.
     * @param string $operator The operator to concat this Expressions into WHERE statment.
     */
    protected function _addExpression($exp, $operator)
    {
        if (!is_array($this->expressions) || count($this->expressions) == 0) {
            $this->expressions = array($exp);
        } else {
            $this->expressions[] = new Expressions(array('operator'=>$operator, 'target'=>$exp));
        }
    }
    /**
     * helper function to add condition into WHERE.
     * @param Expressions $exp The expression will be concat into WHERE or SET statment.
     * @param string $operator the operator to concat this Expressions into WHERE or SET statment.
     * @param string $name The Expression will contact to.
     */
    protected function _addCondition($exp, $operator, $name = 'where')
    {
        if (!$this->$name) {
            $this->$name = new Expressions(array('operator'=>strtoupper($name) , 'target'=>$exp));
        } else {
            $this->$name->target = new Expressions(array('source'=>$this->$name->target, 'operator'=>$operator, 'target'=>$exp));
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
        if (is_callable($callback = array(self::$db,$name))) {
            return call_user_func_array($callback, $args);
        }
		$name= str_replace('by', '', $name);
        if (in_array($name, array_keys(self::$operators))) {
			$field = $args[0];
			$operator = self::$operators[$name];
			$value = isset($args[1]) ? $args[1] : null;
			$last_arg = end($args);
			$and_or_or = is_string($last_arg) && strtolower($last_arg) === 'or' ? 'OR' : 'AND';

            $this->addCondition($field, $operator, $value, $and_or_or);
        } elseif (in_array($name, array_keys(self::$sqlParts))) {
            $this->{$name} = new Expressions([
				'operator'=>self::$sqlParts[$name], 
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
        if (array_key_exists($var, $this->sqlExpressions) || array_key_exists($var, self::$defaultSqlExpressions)) {
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
    public function & __get($var)
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
