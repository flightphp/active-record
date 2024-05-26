<?php

declare(strict_types=1);

namespace flight\commands;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;

class RecordCommand extends AbstractBaseCommand
{
    /**
     * Construct
     *
     * @param array<string,mixed> $config JSON config from .runway-config.json
     */
    public function __construct(array $config)
    {
        parent::__construct('make:record', 'Creates a new Active Record based on the columns in your database table.', $config);
        $this->argument('<table_name>', 'The name of the table to read from and create the active record');
        $this->argument('[class_name]', 'The name of the active record class to create');
        $this->usage(<<<TEXT
            <bold>  make:record users</end> ## Creates a file named UserRecord.php based on the users table<eol/>
            <bold>  make:record users Author</end> ## Creates a file named AuthorRecord.php based on the users table<eol/>
            TEXT);
    }

    /**
     * Executes the record command.
     *
     * @param string $tableName The name of the table to perform the command on.
     * @param string $className The name of the class to use for the record. (optional)
     * @return void
     * @
     */
    public function execute(string $tableName, ?string $className = null)
    {
        $io = $this->app()->io();
        if (isset($this->config['app_root']) === false) {
            $io->error('app_root not set in .runway-config.json', true);
            return;
        }

        if (isset($this->config['database']) === false) {
            $this->registerDatabaseConfig();
        }

        if ($className === '' || $className === null) {
            $className = $this->singularizeTable($tableName);
        }

        if (!preg_match('/Record$/', $className)) {
            $className .= 'Record';
        }

        $recordPath = getcwd() . DIRECTORY_SEPARATOR . $this->config['app_root'] . 'records' . DIRECTORY_SEPARATOR . $className . '.php';
        if (file_exists($recordPath) === true) {
            $io->error($className . ' already exists.', true);
            return;
        }

        if (is_dir(dirname($recordPath)) === false) {
            $io->info('Creating directory ' . dirname($recordPath), true);
            mkdir(dirname($recordPath), 0755, true);
        }

        $file = new PhpFile();
        $file->setStrictTypes();

        $namespace = new PhpNamespace('app\\records');

        $class = new ClassType($className);
        $class->setExtends('flight\\ActiveRecord');

        $pdo = $this->getPdoConnection();

        // need to pull out all the fields from the table
        // for the various database drivers
        // this also will normalize the types to php types
        $fields = [];
        if ($this->config['database']['driver'] === 'mysql') {
            $statement = $pdo->query('DESCRIBE ' . $tableName);
            $rawFields = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $fields = array_map(function ($field) {
                $type = $field['Type'];
                $phpType = $this->getPhpTypeFromDatabaseType($type);
                return [
                    'name' => $field['Field'],
                    'type' => $phpType
                ];
            }, $rawFields);
        } elseif ($this->config['database']['driver'] === 'pgsql') {
            $statement = $pdo->query('SELECT column_name, data_type FROM information_schema.columns WHERE table_name = \'' . $tableName . '\'');
            $rawFields = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $fields = array_map(function ($field) {
                $type = $field['data_type'];
                $phpType = $this->getPhpTypeFromDatabaseType($type);
                return [
                    'name' => $field['column_name'],
                    'type' => $phpType
                ];
            }, $rawFields);
        } elseif ($this->config['database']['driver'] === 'sqlite') {
            $statement = $pdo->query('PRAGMA table_info(' . $tableName . ')');
            $rawFields = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $fields = array_map(function ($field) {
                $type = $field['type'];
                $phpType = $this->getPhpTypeFromDatabaseType($type);
                return [
                    'name' => $field['name'],
                    'type' => $phpType
                ];
            }, $rawFields);
        }

        $class->addComment('ActiveRecord class for the ' . $tableName . ' table.');

        $class->addComment('@link https://docs.flightphp.com/awesome-plugins/active-record');
        $class->addComment('');

        foreach ($fields as $field) {
            $class->addComment('@property ' . $field['type'] . ' $' . $field['name']);
        }
        $class->addProperty('relations')
            ->setVisibility('protected')
            ->setType('array')
            ->setValue([])
            ->addComment('@var array $relations Set the relationships for the model' . "\n" . '  https://docs.flightphp.com/awesome-plugins/active-record#relationships');
        $method = $class->addMethod('__construct')
            ->addComment('Constructor')
            ->addComment('@param mixed $databaseConnection The connection to the database')
            ->setVisibility('public')
            ->setBody('parent::__construct($databaseConnection, \'' . $tableName . '\');');
        $method->addParameter('databaseConnection');

        $namespace->add($class);
        $file->addNamespace($namespace);

        $this->persistClass($className, $file);

        $io->ok('Active Record successfully created at ' . $recordPath, true);
    }

    /**
     * Saves the class name to a file
     *
     * @param string    $recordName  Name of the Controller
     * @param PhpFile   $file        Class Object from Nette\PhpGenerator
     *
     * @return void
     */
    protected function persistClass(string $recordName, PhpFile $file)
    {
        $printer = new \Nette\PhpGenerator\PsrPrinter();
        file_put_contents(getcwd() . DIRECTORY_SEPARATOR . $this->config['app_root'] . 'records' . DIRECTORY_SEPARATOR . $recordName . '.php', $printer->printFile($file));
    }

    /**
     * Does the setup for the database configuration
     *
     * @return void
     */
    protected function registerDatabaseConfig()
    {
        $interactor = $this->app()->io();

        $interactor->boldBlue('Database configuration not found. Please provide the following details:', true);

        $driver = $interactor->choice('Driver', ['mysql', 'pgsql', 'sqlite'], 'mysql');

        $file_path = '';
        $host = '';
        $port = '';
        $database = '';
        $charset = '';
        if ($driver === 'sqlite') {
            $file_path = $interactor->prompt('Database file path', 'database.sqlite');
        } else {
            $host = $interactor->prompt('Host', 'localhost');
            $port = $interactor->prompt('Port', '3306');
            $database = $interactor->prompt('Database');
            if ($driver === 'mysql') {
                $charset = $interactor->prompt('Charset', 'utf8mb4');
            }
        }

        $username = $interactor->prompt('Username (for no username, press enter)', '', null, 0);
        $password = $interactor->prompt('Password (for no password, press enter)', '', null, 0);

        $this->config['database'] = [
            'driver' => $driver,
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => $charset,
            'file_path' => $file_path
        ];

        $interactor->info('Writing database configuration to .runway-config.json', true);
        file_put_contents(getcwd() . DIRECTORY_SEPARATOR . '.runway-config.json', json_encode($this->config, JSON_PRETTY_PRINT));
    }

    /**
     * Gets the PDO connection
     *
     * @return \PDO
     */
    protected function getPdoConnection(): \PDO
    {
        $database = $this->config['database'];
        if ($database['driver'] === 'sqlite') {
            $dsn = $database['driver'] . ':' . $database['file_path'];
        } else {
            // @codeCoverageIgnoreStart
            // This is due to only being able to test sqlite in unit test mode.
            $dsn = $database['driver'] . ':host=' . $database['host'] . ';port=' . $database['port'] . ';dbname=' . $database['database'];
            if ($database['driver'] === 'mysql') {
                $dsn .= ';charset=' . $database['charset'];
            }
            // @codeCoverageIgnoreEnd
        }
        return new \PDO($dsn, $database['username'], $database['password']);
    }

    /**
     * Gets the PHP type from the database type
     *
     * @param string $type Database type
     *
     * @return string
     */
    protected function getPhpTypeFromDatabaseType(string $type): string
    {
        $phpType = '';
        if (stripos($type, 'int') !== false) {
            $phpType = 'int';
        } elseif (stripos($type, 'float') !== false || stripos($type, 'double') !== false || stripos($type, 'decimal') !== false || stripos($type, 'numeric') !== false) {
            $phpType = 'float';
        } elseif (stripos($type, 'binary') !== false || stripos($type, 'blob') !== false || stripos($type, 'byte') !== false) {
            $phpType = 'mixed';
        } else {
            $phpType = 'string';
        }
        return $phpType;
    }

    /**
     * Takes a table name, makes it singular (including tables that end in ses)
     * and then converts it from snake_case to CamelCase
     *
     * @param string $table [description]
     * @return string
     */
    protected function singularizeTable(string $table): string
    {
        $className = $table;
        if (substr($table, -3) === 'ses') {
            $className = substr($table, 0, -2);
        } elseif (substr($table, -1) === 's') {
            $className = substr($table, 0, -1);
        }
        $className = str_replace('_', ' ', $className);
        $className = ucwords($className);
        $className = str_replace(' ', '', $className);
        return $className;
    }
}
