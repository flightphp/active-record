<?php

declare(strict_types=1);

namespace flight;

use Exception;
use flight\database\DatabaseInterface;
use flight\database\DatabaseStatementInterface;
use JsonSerializable;
use mysqli;
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
 *
 * @method self select(string $field, [...$field2]) Select
 * @method self from(string $table) From
 * @method self join(string $table_to_join, string $join_condition) Join another table in the database
 * @method self set(string $field, mixed $value, [...$field2]) Set
 * @method self where(string $sql_conditions) Where
 * @method self group(string $field, [...$field2]) Group By
 * @method self groupBy(string $field, [...$field2]) Group By
 * @method self having(string $sql_conditions) Having
 * @method self order(string $field, [...$field2]) Order By
 * @method self orderBy(string $field, [...$field2]) Order By
 * @method self limit(int $limit) Limit
 * @method self offset(int $offset) Offset
 * @method self top(int $top) Top
 */
abstract class ActiveRecord extends Base implements JsonSerializable
{
    public const BELONGS_TO = 'belongs_to';
    public const HAS_MANY = 'has_many';
    public const HAS_ONE = 'has_one';

    /**
     * @var array Stored the SQL Expressions of the SQL.
     */
    protected array $expressions = [];

    /**
     * @var array Stored the Expressions of the SQL.
     */
    protected array $sqlExpressions = [];

    /**
     * Database connection
     *
     * @var DatabaseInterface
     */
    protected DatabaseInterface $databaseConnection;

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
     * @var array Stored the params will bind to SQL when call DatabaseStatement::execute(),
     */
    protected array $params = [];

    /**
     * @var array Stored the configure of the relation, or target of the relation.
     */
    protected array $relations = [];

    /**
     * These are variables that are custom to the model, but not part of the database
     *
     * @var array
     */
    protected array $customData = [];

    /**
     * @var int The count of bind params, using this count and const "PREFIX" (:ph) to generate place holder in SQL.
     */
    protected int $count = 0;

    /**
     * @var boolean This means that if find() or findAll() actually finds a record in the database
     */
    protected bool $isHydrated = false;

    /**
     * The construct
     *
     * @param mixed   $databaseConnection  Database object (PDO, mysqli, etc)
     * @param ?string $table               The table name in database
     * @param array   $config              Manipulate any property in the object
     */
    public function __construct($databaseConnection = null, ?string $table = '', array $config = [])
    {
        $this->processEvent('onConstruct', [ $this, &$config ]);
        $rawConnection = null;
        if ($databaseConnection !== null && ($databaseConnection instanceof DatabaseInterface) === false) {
            $rawConnection = $databaseConnection;
        } elseif (isset($config['connection']) === true) {
            $rawConnection = $config['connection'];
            // we don't want this actually directly set in the model....it'd be useless
            unset($config['connection']);
        }

        if ($rawConnection !== null) {
            $this->transformAndPersistConnection($rawConnection);
        } elseif ($databaseConnection instanceof DatabaseInterface) {
            $this->databaseConnection = $databaseConnection;
        }

        if ($table) {
            $this->table = $table;
        }
        parent::__construct($config);
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
        $name = str_ireplace('by', '', $name);
        if (isset(ActiveRecordData::OPERATORS[$name]) === true) {
            $field = $args[0];
            $operator = ActiveRecordData::OPERATORS[$name];
            $value = isset($args[1]) ? $args[1] : null;
            $last_arg = end($args);
            $and_or_or = is_string($last_arg) && strtolower($last_arg) === 'or' ? 'OR' : 'AND';

            $this->addCondition($field, $operator, $value, $and_or_or);
        } elseif (in_array($name, array_keys(ActiveRecordData::SQL_PARTS))) {
            $this->{$name} = new Expressions([
                'operator' => ActiveRecordData::SQL_PARTS[$name],
                'target' => implode(', ', $args)
            ]);
        }
        return $this;
    }

    /**
     * magic function to SET values of the current object.
     */
    public function __set($var, $val)
    {
        if (array_key_exists($var, $this->sqlExpressions) || array_key_exists($var, ActiveRecordData::DEFAULT_SQL_EXPRESSIONS)) {
            $this->sqlExpressions[$var] = $val;
        } elseif (isset($this->relations[$var]) === true && $val instanceof self) {
            $this->relations[$var] = $val;
        } else {
            $this->data[$var] = $val;
            $this->dirty[$var] = $val;
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
        if (isset($this->customData[$var])) {
            unset($this->customData[$var]);
        }
    }

    /**
     * magic function to GET the values of current object.
     */
    public function &__get($var)
    {
        if (isset($this->sqlExpressions[$var]) === true) {
            return $this->sqlExpressions[$var];
        } elseif (isset($this->relations[$var]) === true) {
            return $this->getRelation($var);
        } elseif (isset($this->customData[$var]) === true) {
            return $this->customData[$var];
        } else {
            return parent::__get($var);
        }
    }

    /**
     * Checks isset for magic properties
     *
     * @param mixed $name the key name to check
     * @return boolean
     */
    public function __isset($name)
    {
        return isset($this->data[$name]) === true || isset($this->relations[$name]) === true || isset($this->customData[$name]) === true;
    }

    /**
     * Transforms the raw connection into a database connection usable by this class
     *
     * @param mixed $rawConnection Raw connection
     * @return void
     * @throws Exception
     */
    protected function transformAndPersistConnection($rawConnection)
    {
        if ($rawConnection instanceof PDO) {
            $this->databaseConnection = new \flight\database\pdo\PdoAdapter($rawConnection);
        } elseif ($rawConnection instanceof mysqli) {
            $this->databaseConnection = new \flight\database\mysqli\MysqliAdapter($rawConnection);
        } else {
            throw new Exception('Database connection type not supported');
        }
    }

    /**
     * This is for setting a custom property on this model, that is not part of the database
     *
     * @param string $key   The key
     * @param mixed $value any value
     * @return void
     */
    public function setCustomData(string $key, $value): void
    {
        $this->customData[$key] = $value;
    }

    /**
     * Clears the data stored in the object, and resets all params and SQL expressions.
     *
     * @param bool $include_query_data If set to true, will also reset the query data.
     *
     * @return ActiveRecord return $this, can using chain method calls.
     */
    public function reset(bool $include_query_data = true): self
    {
        $this->data = [];
        $this->customData = [];
        if ($include_query_data === true) {
            $this->resetQueryData();
        }
        return $this;
    }

    /**
     * Function to reset the $params and $sqlExpressions.
     *
     * @return ActiveRecord return $this, can using chain method calls.
     */
    protected function resetQueryData(): self
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
    public function dirty(array $dirty = []): self
    {
        $this->dirty = $dirty;
        $this->data = array_merge($this->data, $dirty);
        return $this;
    }

    /**
     * Let's you know if this model has been modified
     *
     * @return boolean
     */
    public function isDirty(): bool
    {
        return count($this->dirty) > 0;
    }

    /**
     * Alias for dirty
     *
     * @param array $data data to copy into the model
     * @return self
     */
    public function copyFrom(array $data = []): self
    {
        return $this->dirty($data);
    }

    /**
     * If this model has been hydrated with data because of a find() or findAll() call
     *
     * @return boolean
     */
    public function isHydrated(): bool
    {
        return $this->isHydrated;
    }

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * get the database connection.
     * @return DatabaseInterface
     */
    public function getDatabaseConnection()
    {
        return $this->databaseConnection;
    }

    /**
     * function to find one record and assign in to current object.
     * @param int|string $id If call this function using this param, will find record by using this id. If not set, just find the first record in database.
     * @return bool|ActiveRecord if find record, assign in to current object and return it, other wise return "false".
     */
    public function find($id = null)
    {
        if ($id !== null) {
            $this->resetQueryData()->eq($this->primaryKey, $id);
        }

        $this->processEvent('beforeFind', [ $this ]);

        $result = $this->query($this->limit(1)->buildSql(['select', 'from', 'join', 'where', 'group', 'having', 'order', 'limit', 'offset']), $this->params, $this->resetQueryData(), true);

        $this->processEvent('afterFind', [ $result ]);

        return $result;
    }
    /**
     * function to find all records in database.
     * @return array<int,ActiveRecord> return array of ActiveRecord
     */
    public function findAll(): array
    {
        $this->processEvent('beforeFindAll', [ $this ]);
        $results = $this->query($this->buildSql(['select', 'from', 'join', 'where', 'group', 'having', 'order', 'limit', 'offset']), $this->params, $this->resetQueryData());
        $this->processEvent('afterFindAll', [ $results ]);
        return $results;
    }
    /**
     * Function to delete current record in database.
     * @return bool
     */
    public function delete()
    {
        $this->processEvent('beforeDelete', [ $this ]);
        if (empty($this->sqlExpressions['where'])) {
            $this->eq($this->primaryKey, $this->{$this->primaryKey});
        }
        $result = $this->execute($this->buildSql(['delete', 'from', 'where']), $this->params);
        $this->processEvent('afterDelete', [ $this ]);
        return $result instanceof DatabaseStatementInterface;
    }
    /**
     * function to build insert SQL, and insert current record into database.
     * @return bool|ActiveRecord if insert success return current object
     */
    public function insert(): ActiveRecord
    {
        // execute this before anything else, this could change $this->dirty
        $this->processEvent([ 'beforeInsert', 'beforeSave' ], [ $this ]);

        if (count($this->dirty) === 0) {
            return $this->resetQueryData();
        }

        $value = $this->filterParam($this->dirty);
        $this->insert = new Expressions([
            'operator' => 'INSERT INTO ' . $this->table,
            'target' => new WrapExpressions(['target' => array_keys($this->dirty)])
        ]);
        $this->values = new Expressions(['operator' => 'VALUES', 'target' => new WrapExpressions(['target' => $value])]);

        $this->execute($this->buildSql(['insert', 'values']), $this->params);
        $this->{$this->primaryKey} = $this->databaseConnection->lastInsertId();

        $this->processEvent([ 'afterInsert', 'afterSave' ], [ $this ]);

        return $this->dirty()->resetQueryData();
    }
    /**
     * function to build update SQL, and update current record in database, just write the dirty data into database.
     * @return ActiveRecord if update success return current object
     */
    public function update(): ActiveRecord
    {
        if (count($this->dirty) === 0) {
            return $this->resetQueryData();
        }
        foreach ($this->dirty as $field => $value) {
            $this->addCondition($field, '=', $value, ',', 'set');
        }

        $this->processEvent([ 'beforeUpdate', 'beforeSave' ], [ $this ]);
        $this->execute($this->eq($this->primaryKey, $this->{$this->primaryKey})->buildSql(['update', 'set', 'where']), $this->params);

        $this->processEvent([ 'afterUpdate', 'afterSave' ], [ $this ]);

        return $this->dirty()->resetQueryData();
    }

    /**
     * Updates or inserts a record
     *
     * @return ActiveRecord
     */
    public function save(): ActiveRecord
    {
        if ($this->{$this->primaryKey}) {
            $record = $this->update();
        } else {
            $record = $this->insert();
        }

        if (count($this->relations) > 0) {
            foreach ($this->relations as $relation) {
                if ($relation instanceof ActiveRecord && $relation->isDirty() === true) {
                    $relation->save();
                }
            }
        }

		return $record;
    }

    /**
     * helper function to exec sql.
     * @param string $sql The SQL need to be execute.
     * @param array $params The param will be bind to PDOStatement.
     * @return DatabaseStatementInterface
     */
    public function execute(string $sql, array $params = []): DatabaseStatementInterface
    {
        $statement = $this->databaseConnection->prepare($sql);

        $statement->execute($params);

        // Now that the query has run, reset the data in the object
        $this->resetQueryData();

        return $statement;
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
        $called_class = get_called_class();
        $obj = $obj ?: new $called_class($this->databaseConnection);

        // Since we are finding a new record, this makes sure that nothing is persisted on the object since we're really looking for a new object.
        $obj->reset(false);
        $sth = $this->execute($sql, $param);
        if ($single === true) {
            // fetch results into the object
            $sth->fetch($obj);
            // clear any dirty data
            $obj->dirty();
            $obj->isHydrated = count($obj->getData()) > 0;
            return $obj;
        }
        $result = [];
        while ($obj = $sth->fetch($obj)) {
            $new_obj = clone $obj->dirty();
            $new_obj->isHydrated = count($new_obj->getData()) > 0;
            $result[] = $new_obj;
        }
        return $result;
    }
    /**
     * helper function to get relation of this object.
     * There was three types of relations: {BELONGS_TO, HAS_ONE, HAS_MANY}
     * @param string $name The name of the relation, the array key when defining the relation.
     * @return mixed
     */
    protected function &getRelation(string $name)
    {
        $relation = $this->relations[$name];
        if (is_array($relation) === true) {
            // ActiveRecordData::BELONGS_TO etc
            $relation_type_or_object_name = $relation[0];
            $relation_class = $relation[1] ?? '';
            $relation_local_key = $relation[2] ?? '';
            $relation_array_callbacks = $relation[3] ?? [];
            $relation_back_reference = $relation[4] ?? '';
        }

        if ($relation instanceof self || $relation_type_or_object_name instanceof self) {
            return $relation;
        }

        $obj = new $relation_class($this->databaseConnection);
        $this->relations[$name] = $obj;
        if ($relation_array_callbacks) {
            foreach ($relation_array_callbacks as $method => $args) {
                call_user_func_array([ $obj, $method ], (array) $args);
            }
        }

        if ((!$relation instanceof self) && self::HAS_ONE === $relation_type_or_object_name) {
            $obj->eq($relation_local_key, $this->{$this->primaryKey})->find() && $relation_back_reference && $obj->__set($relation_back_reference, $this);
        } elseif (self::HAS_MANY === $relation_type_or_object_name) {
            $this->relations[$name] = $obj->eq($relation_local_key, $this->{$this->primaryKey})->findAll();
            if ($relation_back_reference) {
                foreach ($this->relations[$name] as $o) {
                    $o->__set($relation_back_reference, $this);
                }
            }
        } elseif (!($relation instanceof self) && self::BELONGS_TO === $relation_type_or_object_name) {
            $obj->eq($obj->primaryKey, $this->{$relation_local_key})->find() && $relation_back_reference && $obj->__set($relation_back_reference, $this);
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
            $sql_statement = strtoupper($sql_statement) . ' ' . $object->table . '.*';
        } elseif (('update' === $sql_statement || 'from' === $sql_statement) && null == $object->$sql_statement) {
            $sql_statement = strtoupper($sql_statement) . ' ' . $object->table;
        } elseif ('delete' === $sql_statement) {
            $sql_statement = strtoupper($sql_statement) . ' ';
        } else {
            $sql_statement = (null !== $object->$sql_statement) ? $object->$sql_statement . ' ' : '';
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
        foreach ($sql_statements as &$sql) {
            $sql = $this->buildSqlCallback($sql, $this);
        }
        //this code to debug info.
        //echo 'SQL: ', implode(' ', $sql_statements), "\n", "PARAMS: ", implode(', ', $this->params), "\n";
        return implode(' ', $sql_statements);
    }
    /**
     * make wrap when build the SQL expressions of WHERE.
     * @param string $op If give this param will build one WrapExpressions include the stored expressions add into WHERE. Otherwise will stored the expressions into array.
     * @return ActiveRecord
     */
    public function wrap(?string $op = null)
    {
        if ($op !== null) {
            $this->wrap = false;
            if (is_array($this->expressions) && count($this->expressions) > 0) {
                $this->addConditionGroup(new WrapExpressions([
                    'delimiter' => ' ',
                    'target' => $this->expressions
                ]), 'or' === strtolower($op) ? 'OR' : 'AND');
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
                $this->params[$value[$key] = ActiveRecordData::PREFIX . ++$this->count] = $val;
            }
        } elseif (is_string($value)) {
            $ph = ActiveRecordData::PREFIX . ++$this->count;
            $this->params[$ph] = $value;
            $value = $ph;
        } elseif ($value === null) {
            $value = 'NULL';
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
        // This will catch unique conditions such as IS NULL, IS NOT NULL, etc
        // You only need to filter by a param if there's a param to really filter by
        if (stripos($operator, 'NULL') === false) {
            $value = $this->filterParam($value);
        }
        $name = strtolower($name);
        $expressions = new Expressions([
            'source' => ('where' === $name ? $this->table . '.' : '') . $field,
            'operator' => $operator,
            'target' => (
                is_array($value)
                ? new WrapExpressions(
                    'between' === strtolower($operator)
                    ? [ 'target' => $value, 'start' => ' ', 'end' => ' ', 'delimiter' => ' AND ' ]
                    : [ 'target' => $value ]
                )
                : $value
            )
        ]);
        if ($expressions) {
            if (!$this->wrap) {
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
            'operator' => $type . ' JOIN',
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
     * Process an event that's been set.
     *
     * @param string|array $event   The name (or array of names) of the event from ActiveRecordData::EVENTS
     * @param array  $data_to_pass Usually ends up being $this
     * @return void
     */
    protected function processEvent($event, array $data_to_pass = [])
    {
        if (is_array($event) === false) {
            $event = [ $event ];
        }

        foreach ($event as $event_name) {
            if (method_exists($this, $event_name) && in_array($event_name, ActiveRecordData::EVENTS, true) === true) {
                $this->{$event_name}(...$data_to_pass);
            }
        }
    }

    /**
     * @inheritDoc
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * This will return the data in the object as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->data + $this->customData;
    }
}
