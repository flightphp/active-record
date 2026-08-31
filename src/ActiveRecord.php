<?php

declare(strict_types=1);

namespace flight;

use Exception;
use flight\database\DatabaseInterface;
use flight\database\DatabaseStatementInterface;
use flight\database\mysqli\MysqliAdapter;
use flight\database\pdo\PdoAdapter;
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
 * Security notes:
 * - Condition values via eq/like/in/etc. are bound as parameters (safe for untrusted values).
 * - Table/column identifiers passed through escapeIdentifier() are quoted and delimiter-escaped.
 * - where(), having(), select(), order()/orderBy(), group()/groupBy(), and join() ON clauses
 *   accept raw SQL fragments. Do not pass untrusted input into those methods.
 *   Prefer eq()/in()/… for filters, and orderByColumn() when the sort column may be user-supplied.
 * - copyFrom()/dirty() treat array keys as column names; only pass trusted keys (or a fixed allowlist).
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
 * @method self select(string $field, [...$field2]) Select columns/expressions (raw SQL fragments — do not pass untrusted input)
 * @method self from(string $table) From
 * @method self join(string $table_to_join, string $join_condition) Join another table in the database
 * @method self set(string $field, mixed $value, [...$field2]) Set
 * @method self where(string $sql_conditions) Raw WHERE SQL — do not interpolate untrusted input; use eq()/in()/etc. instead
 * @method self group(string $field, [...$field2]) Group By (raw SQL fragments — do not pass untrusted input)
 * @method self groupBy(string $field, [...$field2]) Group By (raw SQL fragments — do not pass untrusted input)
 * @method self having(string $sql_conditions) Raw HAVING SQL — do not interpolate untrusted input
 * @method self order(string $field, [...$field2]) Order By (raw SQL fragments — prefer orderByColumn() for untrusted sort fields)
 * @method self orderBy(string $field, [...$field2]) Order By (raw SQL fragments — prefer orderByColumn() for untrusted sort fields)
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
     * @var array Store the SQL expressions inside wrapped (parentheses).
     */
    protected array $wrapExpressions = [];

    /**
     * @var boolean if a statement is being wrapped in parentheses
     */
    protected bool $wrap = false;

    /**
     * @var array Stored the Expressions of the SQL.
     */
    protected array $sqlExpressions = [];

    /**
     * @var string SQL that is built to be used by execute()
     */
    protected string $builtSql = '';

    /**
     * Captures all the joins that are made
     *
     * @var Expressions|null
     */
    protected ?Expressions $join = null;

    /**
     * Database connection
     *
     * @var DatabaseInterface|PDO|mysqli|null
     */
    protected ?DatabaseInterface $databaseConnection;

    /**
     * @var string  The table name in database.
     */
    protected string $table;

    /**
     * @var string The type of database engine
     */
    protected string $databaseEngineType;

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
     * Relationships to eager load
     *
     * @var array
     */
    protected array $eagerLoad = [];

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
        $this->processEvent('onConstruct', [$this, &$config]);
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
        } else {
            $this->databaseConnection = null;
        }

        $this->databaseEngineType = $this->getDatabaseEngine();

        if ($table) {
            $this->table = $table;
        }
        parent::__construct($config);
    }

    /**
     * Magic function to make calls to ActiveRecordData::OPERATORS or ActiveRecordData::SQL_PARTS.
     * also can call function of databaseConnection object.
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

            // If the last arg is "OR" make this an OR condition
            // e.g. $this->eq('name', 'John', 'or')->eq('age', 25);
            $and_or_or = is_string($last_arg) && strtolower($last_arg) === 'or' ? 'OR' : 'AND';

            $this->addCondition($field, $operator, $value, $and_or_or);
        } elseif (in_array($name, array_keys(ActiveRecordData::SQL_PARTS))) {
            $args = $this->normalizeSqlPartArgs($name, $args);
            $this->{$name} = new Expressions([
                'operator' => ActiveRecordData::SQL_PARTS[$name],
                'target' => implode(', ', $args)
            ]);
        } elseif (method_exists($this->databaseConnection, $name) === true) {
            return call_user_func_array([$this->databaseConnection, $name], $args);
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
        $this->isHydrated = false;
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
        $this->join = null;
        $this->eagerLoad = [];
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
     * Syncs declared public properties into $dirty for insert/update.
     *
     * Direct assignment to typed public properties bypasses __set(), so
     * $dirty stays empty. This method detects initialized declared properties
     * and copies them into $dirty so insert() and update() pick them up.
     *
     * @param bool $onlyChanged When true, only sync properties whose value
     *                          differs from $data (for update). When false,
     *                          sync all initialized properties (for insert).
     */
    protected function syncDirtyFromProperties(bool $onlyChanged = false): void
    {
        $ref = new \ReflectionClass($this);
        $parentRef = new \ReflectionClass(self::class);

        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->isStatic()) {
                continue;
            }

            // Skip properties that exist on ActiveRecord (even if redeclared by subclass)
            if ($parentRef->hasProperty($prop->getName())) {
                continue;
            }

            $name = $prop->getName();

            // Skip the primary key
            if ($name === $this->primaryKey) {
                continue;
            }

            if (!$prop->isInitialized($this) || array_key_exists($name, $this->dirty)) {
                continue;
            }

            $currentValue = $this->{$name};

            if ($onlyChanged) {
                $storedValue = $this->data[$name] ?? null;
                if ($currentValue != $storedValue) {
                    $this->dirty[$name] = $currentValue;
                }
            } else {
                $this->dirty[$name] = $currentValue;
            }
        }
    }

    /**
     * Syncs declared public properties into the internal $data array.
     *
     * PDO::FETCH_INTO sets typed public properties directly, bypassing __set(),
     * so $data stays empty. This method detects initialized declared properties
     * and copies them into $data so that isHydrated() and getData() work
     * correctly with subclasses that use typed properties.
     */
    protected function syncDeclaredProperties(): void
    {
        $ref = new \ReflectionClass($this);
        $parentRef = new \ReflectionClass(self::class);

        // If the primary key isn't initialized, no row was fetched — nothing to sync
        $pkProp = $ref->hasProperty($this->primaryKey) ? $ref->getProperty($this->primaryKey) : null;
        if ($pkProp !== null && !$pkProp->isInitialized($this)) {
            return;
        }

        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->isStatic()) {
                continue;
            }

            $name = $prop->getName();

            // Skip properties that exist on ActiveRecord (even if redeclared by subclass)
            if ($parentRef->hasProperty($name)) {
                continue;
            }

            if ($prop->isInitialized($this) && !array_key_exists($name, $this->data)) {
                $this->data[$name] = $this->{$name};
            }
        }
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
     * set the database connection.
     * @param DatabaseInterface|mysqli|PDO $databaseConnection
     * @return void
     */
    public function setDatabaseConnection($databaseConnection): void
    {
        if (($databaseConnection instanceof DatabaseInterface) === true) {
            $this->databaseConnection = $databaseConnection;
        } else {
            $this->transformAndPersistConnection($databaseConnection);
        }
    }

    /**
     * Returns the type of database engine. Can be one of: mysql, pgsql, sqlite, oci, sqlsrv, odbc, ibm, informix, firebird, 4D, generic.
     *
     * @return string
     */
    public function getDatabaseEngine(): string
    {
        if ($this->databaseConnection instanceof PdoAdapter || is_subclass_of($this->databaseConnection, PDO::class) === true) {
            // returns value of mysql, pgsql, sqlite, oci, sqlsrv, odbc, ibm, informix, firebird, 4D, generic.
            return $this->databaseConnection->getConnection()->getAttribute(PDO::ATTR_DRIVER_NAME);
        } elseif ($this->databaseConnection instanceof MysqliAdapter) {
            return 'mysql';
        } else {
            return 'generic';
        }
    }

    /**
     * function to find one record and assign in to current object.
     * @param int|string $id If call this function using this param, will find record by using this id. If not set, just find the first record in database.
     * @return ActiveRecord Returns a hydrated ActiveRecord object if found, otherwise returns an empty ActiveRecord object.
     */
    public function find($id = null)
    {
        if ($id !== null) {
            $this->eq($this->primaryKey, $id);
        }

        $this->processEvent('beforeFind', [$this]);

        // Save eager load array before resetQueryData() clears it
        $eagerLoad = $this->eagerLoad;

        $result = $this->query($this->limit(1)->buildSql(['select', 'from', 'join', 'where', 'group', 'having', 'order', 'limit', 'offset']), $this->params, $this->resetQueryData(), true);

        // Eager load relationships if specified (wrap in array for consistency)
        if (count($eagerLoad) > 0 && $result->isHydrated()) {
            $this->eagerLoad = $eagerLoad;
            $this->loadEagerRelations([$result]);
        }

        $this->processEvent('afterFind', [$result]);

        return $result;
    }

    /**
     * function to find all records in database.
     * @return array<int,ActiveRecord> return array of ActiveRecord
     */
    public function findAll(): array
    {
        $this->processEvent('beforeFindAll', [$this]);

        // Save eager load array before resetQueryData() clears it
        $eagerLoad = $this->eagerLoad;

        $results = $this->query($this->buildSql(['select', 'from', 'join', 'where', 'group', 'having', 'order', 'limit', 'offset']), $this->params, $this->resetQueryData());

        // Eager load relationships if specified
        if (count($eagerLoad) > 0 && count($results) > 0) {
            $this->eagerLoad = $eagerLoad;
            $this->loadEagerRelations($results);
        }

        $this->processEvent('afterFindAll', [$results]);
        return $results;
    }

    /**
     * Count the number of records matching the current query conditions.
     *
     * Any active group/groupBy is intentionally ignored: `SELECT COUNT(*) ... GROUP BY`
     * returns one row per group, which a single scalar count cannot represent.
     *
     * @return int
     */
    public function count(): int
    {
        $this->select = new Expressions([
            'operator' => 'SELECT COUNT(*) AS _ar_count'
        ]);

        $result = $this->queryScalar($this->buildSql(['select', 'from', 'join', 'where', 'having']), $this->params);

        return (int) $result;
    }

    /**
     * Whether any records match the current query conditions.
     *
     * @return bool
     */
    public function exists(): bool
    {
        $this->select = new Expressions([
            'operator' => 'SELECT 1'
        ]);

        if ($this->limit === null) {
            $this->limit(1);
        }

        $result = $this->queryScalar($this->buildSql(['select', 'from', 'join', 'where', 'group', 'having', 'limit']), $this->params);

        return $result !== false;
    }
    /**
     * Function to delete current record in database.
     * @return bool
     */
    public function delete()
    {
        $this->processEvent('beforeDelete', [$this]);
        if (empty($this->sqlExpressions['where'])) {
            $this->eq($this->primaryKey, $this->{$this->primaryKey});
        }
        $result = $this->execute($this->buildSql(['delete', 'from', 'where']), $this->params);
        $this->processEvent('afterDelete', [$this]);
        return $result instanceof DatabaseStatementInterface;
    }
    /**
     * function to build insert SQL, and insert current record into database.
     * @return bool|ActiveRecord if insert success return current object
     */
    public function insert(): ActiveRecord
    {
        // execute this before anything else, this could change $this->dirty
        $this->processEvent(['beforeInsert', 'beforeSave'], [$this]);

        // Sync typed public properties into dirty — direct assignment to
        // declared properties bypasses __set() and leaves dirty empty.
        $this->syncDirtyFromProperties(false);

        if (count($this->dirty) === 0) {
            return $this->resetQueryData();
        }

        $value = $this->filterParam($this->dirty);

        // escape column names from dirty data
        $columnNames = array_keys($this->dirty);
        $escapedColumnNames = array_map([$this, 'escapeIdentifier'], $columnNames);

        $this->insert = new Expressions([
            'operator' => 'INSERT INTO ' . $this->escapeIdentifier($this->table),
            'target' => new WrapExpressions(['target' => $escapedColumnNames])
        ]);
        $this->values = new Expressions(['operator' => 'VALUES', 'target' => new WrapExpressions(['target' => $value])]);

        $intentionallyAssignedPrimaryKey = $this->dirty[$this->primaryKey] ?? null;

        $this->execute($this->buildSql(['insert', 'values']), $this->params);

        $lastInsertId = $intentionallyAssignedPrimaryKey ?: $this->databaseConnection->lastInsertId();

        // Cast to int if the primary key property is typed as int,
        // since lastInsertId() returns a string which fails under strict_types.
        $classRef = new \ReflectionClass($this);
        if ($classRef->hasProperty($this->primaryKey)) {
            $type = $classRef->getProperty($this->primaryKey)->getType();
            if ($type instanceof \ReflectionNamedType && $type->getName() === 'int') {
                $lastInsertId = (int) $lastInsertId;
            }
        }

        $this->{$this->primaryKey} = $lastInsertId;

        $this->processEvent(['afterInsert', 'afterSave'], [$this]);

        $this->isHydrated = true;

        return $this->dirty();
    }
    /**
     * function to build update SQL, and update current record in database, just write the dirty data into database.
     * @return ActiveRecord if update success return current object
     */
    public function update(): ActiveRecord
    {
        $this->processEvent(['beforeUpdate', 'beforeSave'], [$this]);

        // Sync typed public properties that changed since the last find/sync.
        $this->syncDirtyFromProperties(true);

        foreach ($this->dirty as $field => $value) {
            $this->addCondition($field, '=', $value, ',', 'set');
        }

        // Only update something if there is something to update
        if (count($this->dirty) > 0) {
            $this->execute($this->eq($this->primaryKey, $this->{$this->primaryKey})->buildSql(['update', 'set', 'where']), $this->params);
        }

        $this->processEvent(['afterUpdate', 'afterSave'], [$this]);

        return $this->dirty()->resetQueryData();
    }

    /**
     * Updates or inserts a record
     *
     * @return ActiveRecord
     */
    public function save(): ActiveRecord
    {
        if ($this->{$this->primaryKey} !== null && $this->isHydrated() === true) {
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
     * @param ActiveRecord|null $obj The object, if find record in database, will assign the attributes in to this object.
     * @param bool $single if set to true, will find record and fetch in current object, otherwise will find all records.
     * @return ActiveRecord|array
     */
    public function query(string $sql, array $param = [], ?ActiveRecord $obj = null, bool $single = false)
    {
        $called_class = get_called_class();
        $obj = $obj ?: new $called_class($this->databaseConnection);

        // Since we are finding a new record, this makes sure that nothing is persisted on the object since we're really looking for a new object.
        $obj->reset(false);
        $sth = $this->execute($sql, $param);
        if ($single === true) {
            // fetch results into the object
            $sth->fetch($obj);
            // Sync typed public properties that PDO::FETCH_INTO set directly
            $obj->syncDeclaredProperties();
            // clear any dirty data
            $obj->dirty();
            $obj->isHydrated = count($obj->getData()) > 0;
            return $obj;
        }
        $result = [];
        while ($obj = $sth->fetch($obj)) {
            $obj->syncDeclaredProperties();
            $new_obj = clone $obj->dirty();
            $new_obj->isHydrated = count($new_obj->getData()) > 0;
            $result[] = $new_obj;
        }
        return $result;
    }

    /**
     * Execute a SQL query and return a single scalar value.
     *
     * @param string $sql    SQL with named placeholders
     * @param array  $params Bound parameters
     * @return mixed The first column of the first row, or false if no row is available
     */
    private function queryScalar(string $sql, array $params = [])
    {
        return $this->execute($sql, $params)->fetchColumn();
    }
    /**
     * helper function to get relation of this object.
     * There was three types of relations: {BELONGS_TO, HAS_ONE, HAS_MANY}
     * @param string $name The name of the relation, the array key when defining the relation.
     * @return mixed
     */
    protected function &getRelation(string $name)
    {

        // can't set the name of a relation to a protected keyword
        if (in_array($name, ['select', 'from', 'join', 'where', 'group', 'having', 'order', 'limit', 'offset'], true) === true) {
            throw new Exception($name . ' is a protected keyword and cannot be used as a relation name');
        }

        $relation = $this->relations[$name];
        if (is_array($relation) === true && isset($relation[0])) {
            // ActiveRecordData::BELONGS_TO etc
            $relation_type_or_object_name = $relation[0];
            $relation_class = $relation[1] ?? '';
            $relation_local_key = $relation[2] ?? '';
            $relation_array_callbacks = $relation[3] ?? [];
            $relation_back_reference = $relation[4] ?? '';
        } else {
            return $relation;
        }

        if ($relation instanceof self || $relation_type_or_object_name instanceof self) {
            return $relation;
        }

        $obj = new $relation_class($this->databaseConnection);
        $this->relations[$name] = $obj;
        if ($relation_array_callbacks) {
            foreach ($relation_array_callbacks as $method => $args) {
                call_user_func_array([$obj, $method], (array) $args);
            }
        }

        if ((!$relation instanceof self) && self::HAS_ONE === $relation_type_or_object_name) {
            $obj->eq($relation_local_key, $this->{$this->primaryKey})->find() && $relation_back_reference && $obj->__set($relation_back_reference, $this);
        } elseif (self::HAS_MANY === $relation_type_or_object_name) {
            $results = $obj->eq($relation_local_key, $this->{$this->primaryKey})->findAll();
            $this->relations[$name] = $results ?: [];
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
     * Specify relationships to eager load
     *
     * @param string|array $relations Relation name(s) to eager load
     * @return self
     */
    public function with($relations): self
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }

        $this->eagerLoad = array_merge($this->eagerLoad, $relations);
        return $this;
    }

    /**
     * Load eager relationships for a collection of records
     *
     * @param array $records Array of ActiveRecord objects
     * @return void
     */
    protected function loadEagerRelations(array $records): void
    {
        foreach ($this->eagerLoad as $relationName) {
            // Validate relation exists
            if (!isset($this->relations[$relationName])) {
                throw new Exception("Relation '{$relationName}' is not defined");
            }

            $relationConfig = $this->relations[$relationName];

            // Skip if already loaded as an object
            if ($relationConfig instanceof self) {
                continue;
            }

            // Extract relation configuration
            $relationType = $relationConfig[0];
            $relationClass = $relationConfig[1];
            $keyField = $relationConfig[2];
            $callbacks = $relationConfig[3] ?? [];
            $backReference = $relationConfig[4] ?? '';

            // Determine which keys to collect and which field to query
            if ($relationType === self::BELONGS_TO) {
                // For BELONGS_TO: collect foreign keys from current records, query by primary key
                $keyValues = $this->collectUniqueValues($records, $keyField);
                $relatedModel = new $relationClass($this->databaseConnection);
                $queryField = $relatedModel->primaryKey;
            } else {
                // For HAS_ONE/HAS_MANY: collect primary keys from current records, query by foreign key
                $keyValues = $this->collectUniqueValues($records, $this->primaryKey);
                $relatedModel = new $relationClass($this->databaseConnection);
                $queryField = $keyField;
            }

            if (empty($keyValues)) {
                continue;
            }

            // Apply any additional conditions from relation config
            if ($callbacks) {
                foreach ($callbacks as $method => $args) {
                    call_user_func_array([$relatedModel, $method], (array) $args);
                }
            }

            // Fetch all related records in one query
            $relatedRecords = $relatedModel->in($queryField, $keyValues)->findAll();

            // Assign related records to parent records
            $this->assignEagerLoadedRelations(
                $records,
                $relatedRecords,
                $relationName,
                $relationType,
                $keyField,
                $queryField,
                $backReference,
                $relationClass
            );
        }

        // Clear eager load array for next query
        $this->eagerLoad = [];
    }

    /**
     * Collect unique values from a field across multiple records
     *
     * @param array $records Array of ActiveRecord objects
     * @param string $field Field name to collect values from
     * @return array Unique non-null values
     */
    protected function collectUniqueValues(array $records, string $field): array
    {
        $values = [];
        foreach ($records as $record) {
            if ($record->{$field} !== null) {
                $values[] = $record->{$field};
            }
        }
        return array_unique($values);
    }

    /**
     * Assign eager loaded relations to parent records
     *
     * @param array $parentRecords Parent records
     * @param array $relatedRecords Related records fetched from database
     * @param string $relationName Name of the relation
     * @param string $relationType Type of relation (HAS_MANY, HAS_ONE, BELONGS_TO)
     * @param string $keyField The key field used for matching
     * @param string $queryField The field that was queried
     * @param string $backReference Optional back reference name
     * @param string $relationClass Class name of related model
     * @return void
     */
    protected function assignEagerLoadedRelations(
        array $parentRecords,
        array $relatedRecords,
        string $relationName,
        string $relationType,
        string $keyField,
        string $queryField,
        string $backReference,
        string $relationClass
    ): void {
        // Group related records by the query field for efficient lookup
        $grouped = [];
        foreach ($relatedRecords as $relatedRecord) {
            $key = $relatedRecord->{$queryField};

            if ($relationType === self::HAS_MANY) {
                // HAS_MANY: group multiple records
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [];
                }
                $grouped[$key][] = $relatedRecord;
            } else {
                // HAS_ONE/BELONGS_TO: take first match only
                if (!isset($grouped[$key])) {
                    $grouped[$key] = $relatedRecord;
                }
            }
        }

        // Assign to parent records
        foreach ($parentRecords as $parentRecord) {
            // Determine the lookup key based on relation type
            if ($relationType === self::BELONGS_TO) {
                $lookupKey = $parentRecord->{$keyField};
            } else {
                $lookupKey = $parentRecord->{$this->primaryKey};
            }

            // Assign the relation
            if (isset($grouped[$lookupKey])) {
                $relatedData = $grouped[$lookupKey];

                // Set back reference if specified
                if ($backReference) {
                    if (is_array($relatedData)) {
                        foreach ($relatedData as $relatedRecord) {
                            $relatedRecord->__set($backReference, $parentRecord);
                        }
                    } else {
                        $relatedData->__set($backReference, $parentRecord);
                    }
                }

                $parentRecord->relations[$relationName] = $relatedData;
            } else {
                // No match found - set empty array for HAS_MANY, empty object for others
                if ($relationType === self::HAS_MANY) {
                    $parentRecord->relations[$relationName] = [];
                } else {
                    $parentRecord->relations[$relationName] = new $relationClass($this->databaseConnection);
                }
            }
        }
    }
    /**
     * helper function to build SQL with sql parts.
     * @param string $sqlStatement The SQL part will be build.
     * @param ActiveRecord $o The reference to $this
     * @return string
     */
    protected function buildSqlCallback(string $sqlStatement, ActiveRecord $object): string
    {
        // First add the SELECT table.*
        if ('select' === $sqlStatement && null == $object->$sqlStatement) {
            $sqlStatement = strtoupper($sqlStatement) . ' ' . $this->escapeIdentifier($object->table) . '.*';
        } elseif (('update' === $sqlStatement || 'from' === $sqlStatement) && null == $object->$sqlStatement) {
            $sqlStatement = strtoupper($sqlStatement) . ' ' . $this->escapeIdentifier($object->table);
        } elseif ('delete' === $sqlStatement) {
            $sqlStatement = strtoupper($sqlStatement);
        } else {
            $sqlStatement = (null !== $object->$sqlStatement) ? (string) $object->$sqlStatement : '';
        }

        return $sqlStatement;
    }

    /**
     * helper function to build SQL with sql parts.
     * @param array $sqlStatements The SQL part will be build.
     * @return string
     */
    protected function buildSql(array $sqlStatements = []): string
    {
        $finalSql = [];
        foreach ($sqlStatements as $sql) {
            $statement = $this->buildSqlCallback($sql, $this);
            if ($statement !== '') {
                $finalSql[] = $statement;
            }
        }
        //this code to debug info.
        //echo 'SQL: ', implode(' ', $sqlStatements), "\n", "PARAMS: ", implode(', ', $this->params), "\n";
        $this->builtSql = implode(' ', $finalSql);

        // get rid of multiple spaces in the query for prettiness
        $this->builtSql = preg_replace('/\s+/', ' ', $this->builtSql);
        return $this->builtSql;
    }

    /**
     * Gets the built SQL after buildSql has been called
     *
     * @return string
     */
    public function getBuiltSql(): string
    {
        return $this->builtSql;
    }

    public function startWrap(): self
    {
        $this->wrap = true;
        return $this;
    }

    /**
     * Alias to encWrap
     *
     * @deprecated 0.6.0
     * @param string|null $op If give this param will build one WrapExpressions include the stored expressions add into WHERE. Otherwise will stored the expressions into array.
     * @return self
     */
    public function wrap(?string $op = null): self
    {
        return $op === null ? $this->startWrap() : $this->endWrap($op);
    }

    /**
     * make wrap when build the SQL expressions of WHERE.
     * @param string $op If give this param will build one WrapExpressions include the stored expressions add into WHERE. Otherwise will stored the expressions into array.
     * @return ActiveRecord
     */
    public function endWrap(string $op): self
    {
        $this->wrap = false;
        if (is_array($this->wrapExpressions) === true && count($this->wrapExpressions) > 0) {
            $this->addCondition(new WrapExpressions([
                'delimiter' => ('or' === strtolower($op) ? ' OR ' : ' AND '),
                'target' => $this->wrapExpressions
            ]), null, null);
        }
        $this->wrapExpressions = [];
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
                $value[$key] = $this->filterParam($val);
            }
        } elseif ($value === null) {
            $value = 'NULL';
        } elseif (is_bool($value) === true && is_int($value) === false && is_float($value) === false) {
            $value = $value ? 'TRUE' : 'FALSE';
        } else {
            $ph = ActiveRecordData::PREFIX . ++$this->count;
            $this->params[$ph] = $value;
            $value = $ph;
        }
        return $value;
    }

    /**
     * helper function to add condition into WHERE.
     * create the SQL Expressions.
     * @param string|Expressions $field The field name, the source of Expressions
     * @param ?string $operator
     * @param mixed $value the target of the Expressions
     * @param string $delimiter the operator to concat this Expressions into WHERE or SET statement.
     * @param string $name The Expression will contact to.
     */
    public function addCondition($field, ?string $operator, $value, string $delimiter = 'AND', string $name = 'where')
    {
        // This will catch unique conditions such as IS NULL, IS NOT NULL, etc
        // You only need to filter by a param if there's a param to really filter by
        // A true null value is passed in from a endWrap() method to skip the param.
        if ($operator !== null && stripos((string) $operator, 'NULL') === false) {
            $value = $this->filterParam($value);
        }

        // This is used for wrapped expressions so extra statements aren't printed out.
        if ($operator === null) {
            $operator = '';
        }
        $name = strtolower($name);

        // skip adding the `table.` prefix if it's already there or a function is being supplied.
        $skipTablePrefix = $field instanceof WrapExpressions || is_string($field) === true && (strpos($field, '.') !== false || strpos($field, '(') !== false);
        $expressions = new Expressions([
            'source' => ('where' === $name && $skipTablePrefix === false ? $this->escapeIdentifier($this->table) . '.' : '') . (is_string($field) === true ? $this->escapeIdentifier($field) : $field),
            'operator' => $operator,
            'target' => (
                is_array($value)
                ? new WrapExpressions(
                    'between' === strtolower($operator)
                        ? ['target' => $value, 'start' => ' ', 'end' => ' ', 'delimiter' => ' AND ']
                        : ['target' => $value]
                )
                : $value
            )
        ]);
        if ($expressions) {
            if ($this->wrap === false) {
                $this->addConditionGroup($expressions, $delimiter, $name);
            } else {
                // This method is only for wrapping conditions in parentheses
                $this->addExpression($expressions);
            }
        }
    }

    /**
     * helper function to add condition into JOIN.
     * create the SQL Expressions.
     *
     * Simple table names and `table alias` / `table AS alias` forms are identifier-escaped.
     * Complex expressions (subqueries, already-quoted SQL) are left unchanged.
     *
     * The ON clause is raw SQL — do not interpolate untrusted input into $on.
     *
     * @param string $table The join table name (or "table alias" / "table AS alias")
     * @param string $on The condition of ON (raw SQL fragment)
     * @param string $type The join type, like "LEFT", "INNER", "OUTER", "RIGHT"
     */
    public function join(string $table, string $on, string $type = 'LEFT')
    {
        $this->join = new Expressions([
            'source' => $this->join ?? '',
            'operator' => $type . ' JOIN',
            'target' => new Expressions(
                [
                    'source' => $this->escapeJoinTable($table),
                    'operator' => 'ON',
                    'target' => $on
                ]
            )
        ]);
        return $this;
    }

    /**
     * ORDER BY a single column with ASC/DESC. Safe when the column name may be untrusted
     * (e.g. from a request), unlike order()/orderBy() which accept raw SQL fragments.
     *
     * Only simple identifiers or table.column paths are allowed.
     *
     * @param string $column Column name or table.column
     * @param string $direction ASC or DESC (case-insensitive)
     * @return self
     * @throws Exception If the column or direction is invalid
     */
    public function orderByColumn(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper(trim($direction));
        if ($direction !== 'ASC' && $direction !== 'DESC') {
            throw new Exception('Invalid ORDER BY direction; use ASC or DESC');
        }
        if ($this->isSafeIdentifierPath($column) === false) {
            throw new Exception('Invalid ORDER BY column identifier');
        }

        return $this->order($this->escapeIdentifierPath($column) . ' ' . $direction);
    }

    /**
     * helper function to make wrapper. Stored the expression in to array.
     * @param Expressions $exp The expression will be stored.
     * @param string $delimiter The operator to concat this Expressions into WHERE statement.
     */
    protected function addExpression(Expressions $expressions)
    {
        $wrapExpressions = &$this->wrapExpressions;
        if (is_array($wrapExpressions) === false || count($wrapExpressions) === 0) {
            $wrapExpressions = [$expressions];
        } else {
            $wrapExpressions[] = new Expressions(['operator' => '', 'target' => $expressions]);
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
            $this->{$name} = new Expressions([
                'operator' => strtoupper($name),
                'target' => $expressions
            ]);
        } else {
            $this->{$name}->target = new Expressions([
                'source' => $this->$name->target,
                'operator' => $operator,
                'target' => $expressions
            ]);
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
            $event = [$event];
        }

        foreach ($event as $event_name) {
            if (method_exists($this, $event_name) && in_array($event_name, ActiveRecordData::EVENTS, true) === true) {
                $this->{$event_name}(...$data_to_pass);
            }
        }
    }


    /**
     * Escapes a database identifier (e.g., table or column name) to prevent SQL injection.
     *
     * Delimiter characters inside the name are escaped per engine rules so a value such as
     * id" OR 1=1 -- cannot break out of the quoted identifier.
     *
     * @param string $name The database identifier to be escaped.
     * @return string The escaped database identifier.
     */
    public function escapeIdentifier(string $name)
    {
        // Identifiers must not contain NUL bytes
        $name = str_replace("\0", '', $name);

        switch ($this->databaseEngineType) {
            case 'sqlite':
            case 'pgsql':
                return '"' . str_replace('"', '""', $name) . '"';
            case 'mysql':
                return '`' . str_replace('`', '``', $name) . '`';
            case 'sqlsrv':
                return '[' . str_replace(']', ']]', $name) . ']';
            default:
                return $name;
        }
    }

    /**
     * Escapes a dotted identifier path (e.g. table.column) by quoting each segment.
     *
     * @param string $path Identifier or table.column path (must already be a safe path)
     * @return string
     */
    protected function escapeIdentifierPath(string $path): string
    {
        $parts = explode('.', $path);
        $escaped = [];
        foreach ($parts as $part) {
            $escaped[] = $this->escapeIdentifier($part);
        }
        return implode('.', $escaped);
    }

    /**
     * Whether a string is a safe identifier or table.column path (no SQL metacharacters).
     *
     * @param string $path
     * @return bool
     */
    protected function isSafeIdentifierPath(string $path): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $path);
    }

    /**
     * Escape join table when it is a simple table / alias form; leave complex SQL unchanged.
     *
     * @param string $table
     * @return string
     */
    protected function escapeJoinTable(string $table): string
    {
        $table = trim($table);

        // table AS alias
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s+[Aa][Ss]\s+([A-Za-z_][A-Za-z0-9_]*)$/', $table, $matches) === 1) {
            return $this->escapeIdentifier($matches[1]) . ' AS ' . $this->escapeIdentifier($matches[2]);
        }

        // table alias (implicit AS)
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s+([A-Za-z_][A-Za-z0-9_]*)$/', $table, $matches) === 1) {
            return $this->escapeIdentifier($matches[1]) . ' ' . $this->escapeIdentifier($matches[2]);
        }

        // schema.table
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\.([A-Za-z_][A-Za-z0-9_]*)$/', $table, $matches) === 1) {
            return $this->escapeIdentifier($matches[1]) . '.' . $this->escapeIdentifier($matches[2]);
        }

        // simple table name
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) === 1) {
            return $this->escapeIdentifier($table);
        }

        // Subquery, already-quoted SQL, or other complex expression — leave as-is for BC
        return $table;
    }

    /**
     * Normalize raw SQL part arguments without breaking complex developer SQL.
     *
     * - limit/offset/top: coerce pure integers
     * - select/group/order: quote args that are clearly simple identifiers / order expressions
     * - Complex expressions (functions, commas inside one arg, etc.) are left unchanged
     *
     * @param string $part SQL part name after "By" stripping (select, order, group, limit, …)
     * @param array $args
     * @return array
     */
    protected function normalizeSqlPartArgs(string $part, array $args): array
    {
        if (in_array($part, ['limit', 'offset', 'top'], true) === true) {
            return array_map(function ($arg) {
                if (is_int($arg) === true) {
                    return $arg;
                }
                if (is_string($arg) === true && preg_match('/^-?\d+$/', $arg) === 1) {
                    return (int) $arg;
                }
                return $arg;
            }, $args);
        }

        if (in_array($part, ['select', 'group', 'order', 'from'], true) === true) {
            return array_map(function ($arg) use ($part) {
                if (is_string($arg) === false) {
                    return $arg;
                }

                $trimmed = trim($arg);

                // order/orderBy: "column", "column ASC|DESC", "table.column", "table.column ASC|DESC"
                if ($part === 'order') {
                    if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)\s+(ASC|DESC)$/i', $trimmed, $matches) === 1) {
                        return $this->escapeIdentifierPath($matches[1]) . ' ' . strtoupper($matches[2]);
                    }
                    if ($this->isSafeIdentifierPath($trimmed) === true) {
                        return $this->escapeIdentifierPath($trimmed);
                    }
                    return $arg;
                }

                // select / group / from: only pure identifier paths (not *, functions, aliases)
                if ($this->isSafeIdentifierPath($trimmed) === true) {
                    return $this->escapeIdentifierPath($trimmed);
                }

                return $arg;
            }, $args);
        }

        return $args;
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
