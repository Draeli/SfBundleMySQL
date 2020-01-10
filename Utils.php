<?php
declare(strict_types=1);

namespace Draeli\Mysql;

use Draeli\Mysql\Components\AbstractConfigurationImportField;
use Draeli\Mysql\Components\ConfigurationTable;
use Draeli\Mysql\Components\ConfigurationTableField;

/**
 * @package Draeli\Mysql
 */
class Utils
{
    /**
     * Option values : string|null
     * Default : null
     */
    public const LOAD_DATA_OPTION_SCHEMA = 'schema';

    /**
     * Option values : string|null
     * Default : null
     */
    public const LOAD_DATA_OPTION_CHARSET = 'charset';

    /**
     * Option values : 'replace'|'ignore'|null
     * Default : null
     * Null will get an error on duplication
     */
    public const LOAD_DATA_OPTION_DUPLICATE_STRATEGY = 'duplicate_strategy';

    /**
     * Option values : string|null
     * Default : null
     * Null will be replaced with ','
     */
    public const LOAD_DATA_OPTION_TERMINATED_BY = 'terminated_by';

    /**
     * Option values : string
     * Default : null
     * Null will be replaced with '"'
     */
    public const LOAD_DATA_OPTION_ENCLOSED_BY = 'enclosed_by';

    /**
     * Option values : string
     * Default : null
     * Null will be replaced with '\\'
     */
    public const LOAD_DATA_OPTION_ESCAPED_BY = 'escaped_by';

    /**
     * Option values : int|null
     * Default : null
     * Value null or 0 is avoided
     */
    public const LOAD_DATA_OPTION_IGNORE_LINES = 'ignore_lines';

    /**
     * Option values : array|null
     * Default : null
     * Empty array or null is avoided
     */
    public const LOAD_DATA_OPTION_COLUMNS = 'columns';

    public const LOAD_DATA_STRATEGY_IGNORE = 'ignore';

    public const LOAD_DATA_STRATEGY_REPLACE = 'replace';

    public const OUT_NAME_INSERTED = 'inserted';

    public const OUT_NAME_RESULT = 'result';

    public const OUT_SCHEMA_DEFAULT_CHARACTER_NAME = 'DEFAULT_CHARACTER_SET_NAME';

    public const OUT_SCHEMA_DEFAULT_COLLATION_NAME = 'DEFAULT_COLLATION_NAME';

    /** @var \DateTimeZone */
    static private $UTC;

    /**
     * @return array
     */
    public static function getCurrentDatabaseTimeoutParameters(): array
    {
        $toReturn = [];
        foreach(self::getDatabaseTimeoutParameters() as $parameter){
            $value = ini_get( $parameter );
            // false is return if configuration parameter is not available
            if( false !== $value ){
                $toReturn[$parameter] = $value;
            }
        }

        return $toReturn;
    }

    /**
     * @return array
     */
    private static function getDatabaseTimeoutParameters(): array
    {
        return ['default_socket_timeout', 'connect_timeout', 'mysql.connect_timeout', 'mysqlnd.net_read_timeout'];
    }

    /**
     * @param string $schemaName
     * @return string
     */
    public static function getSqlSchemaNameQuoted(string $schemaName): string
    {
        return '`' . $schemaName . '`';
    }

    /**
     * @param string $tableName
     * @return string
     */
    public static function getSqlTableNameQuoted(string $tableName): string
    {
        return '`' . $tableName . '`';
    }

    /**
     * @param string $fieldName
     * @return string
     */
    public static function getSqlFieldNameQuoted(string $fieldName): string
    {
        return '`' . $fieldName . '`';
    }

    /**
     * @param ConfigurationTable $configurationTable
     * @return string
     */
    private static function getSchemaAndTable(ConfigurationTable $configurationTable): string
    {
        $schemaName = $configurationTable->getSchema();
        $tableName = $configurationTable->getTableName();
        return self::getJoinSchemaAndTable($tableName, $schemaName);
    }

    /**
     * @param string $tableName
     * @param string|null $schemaName
     * @return string
     */
    public static function getJoinSchemaAndTable(string $tableName, ?string $schemaName = null): string
    {
        return ( null === $schemaName ? '' : self::getSqlSchemaNameQuoted($schemaName) . '.') . self::getSqlTableNameQuoted($tableName);
    }

    /**
     * @param ConfigurationTable $configurationTable
     * @return string SQL to create table
     */
    public static function getSqlStructureTable(ConfigurationTable $configurationTable): string
    {
        $fields = $configurationTable->getFields();

        if( !\count($fields) ){
            throw new \LogicException('You must define at least one field!');
        }

        $toReturn = 'CREATE TABLE ' . self::getSchemaAndTable($configurationTable) . '(';

        $columns = [];
        foreach($fields as $Field){
            $isNullable = $Field->isNullable();
            $name = $Field->getName();
            $signed = $Field->getSigned();

            $parts = [];
            $parts[] = self::getSqlFieldNameQuoted($name);
            $parts[] = self::getColumnType($Field);

            if( null !== $signed ){
                $parts[] = 'UNSIGNED';
            }

            $parts[] = $isNullable ? 'NULL': 'NOT NULL';
            $parts[] = 'DEFAULT ' . self::getDefaultValue($Field);

            $columns[] = implode(' ', $parts);
        }

        $toReturn .= implode(',',$columns );
        $toReturn .= ')';
        $toReturn .= 'DEFAULT COLLATE ' . $configurationTable->getCollation();
        $toReturn .= ' ENGINE ' . $configurationTable->getEngine();

        return $toReturn;
    }

    /**
     * Return collection of queries to create indexes
     * @param ConfigurationTable $configurationTable
     * @return string[] List of SQL
     */
    public static function getSqlAddIndex(ConfigurationTable $configurationTable): array
    {
        $schemaAndTable = self::getSchemaAndTable($configurationTable);
        $begin = 'ALTER TABLE ' . $schemaAndTable . ' ADD ';
        $return = [];
        foreach($configurationTable->getIndexes() as $index){
            $type = $index->getType();
            $name = $index->getName();
            $fields = $index->getFields();
            if( !\count($fields) ){
                throw new \LogicException('Index ' . $name . ' define without fields.');
            }
            $typeWord = self::getWordIndexForAlter($type);
            $fieldsList = [];

            foreach($fields as $fieldNameOrigin => $fieldLength){
                $fieldsList[] = self::getSqlFieldNameQuoted($fieldNameOrigin) . ($fieldLength ? '(' . $fieldLength . ')' : '');
            }
            $return[] = $begin . $typeWord . ' ' . self::getSqlFieldNameQuoted($name) . '(' . implode(',', $fieldsList) . ')';
        }

        return $return;
    }

    /**
     * Return keyword to use in SQL statement of ALTER
     * @param string $type
     * @return string
     */
    private static function getWordIndexForAlter(string $type): string
    {
        switch($type){
            case Constants::INDEX_UNIQUE:
            case Constants::INDEX_PRIMARY:
                return 'UNIQUE INDEX';
            case Constants::INDEX_NORMAL:
                return 'INDEX';
            case Constants::INDEX_FULLTEXT:
                return 'FULLTEXT INDEX';
        }

        throw new \InvalidArgumentException('Unsupported type ' . $type);
    }

    /**
     * @param ConfigurationTable $configurationTable
     * @param bool $errorIfNotExist
     * @return string
     */
    public static function getSqlDropTable(ConfigurationTable $configurationTable, bool $errorIfNotExist = false): string
    {
        return 'DROP TABLE' . ( $errorIfNotExist ? '' : ' IF EXISTS ') . self::getSchemaAndTable($configurationTable);
    }

    /**
     * @param string $filePath
     * @param string $tableName
     * @param array $options
     *      See constants LOAD_DATA_OPTION_*
     * @return string
     */
    public static function getSqlLoadData(string $filePath, string $tableName, array $options): string
    {
        $duplicationStrategies = [
            self::LOAD_DATA_STRATEGY_REPLACE => 'REPLACE'
            , self::LOAD_DATA_STRATEGY_IGNORE => 'IGNORE'
        ];
        $duplicationStrategy = strtolower((string)($options[self::LOAD_DATA_OPTION_DUPLICATE_STRATEGY] ?? null));
        if( !\array_key_exists($duplicationStrategy, $duplicationStrategies) ){
            $duplicationStrategy = null;
        }

        $charset = $options[self::LOAD_DATA_OPTION_CHARSET] ?? null;
        // Some charset are not supported for load data, see https://dev.mysql.com/doc/refman/8.0/en/load-data.html#load-data-index-handling
        if( null !== $charset && \in_array($charset, ['ucs2', 'utf16', 'utf16le', 'utf32'], true) ){
            throw new \UnexpectedValueException('Collation inside charge "' . $charset . '" is not supported for load data.');
        }

        $optionsTerminatedBy = (string)($options[self::LOAD_DATA_OPTION_TERMINATED_BY] ?? ',');
        $optionsEnclosedBy = (string)($options[self::LOAD_DATA_OPTION_ENCLOSED_BY] ?? '"');
        $optionsEscapedBy = (string)($options[self::LOAD_DATA_OPTION_ESCAPED_BY] ?? '\\');

        $schema = $options[self::LOAD_DATA_OPTION_SCHEMA] ?? null;

        $sqlParts = ['LOAD DATA'];

        $sqlParts[] = 'INFILE ' . "'" . $filePath . "'";

        if( null !== $duplicationStrategy ){
            $sqlParts[] = $duplicationStrategies[$duplicationStrategy];
        }

        $sqlParts[] = 'INTO TABLE ' . self::getJoinSchemaAndTable($tableName, $schema);
        $sqlParts[] = 'CHARACTER SET ' . $charset;
        $sqlParts[] = 'FIELDS';
            $sqlParts[] = 'TERMINATED BY ' . "'" . self::quoteLoadDataFormattingOption($optionsTerminatedBy) . "'";
            $sqlParts[] = 'ENCLOSED BY ' . "'" . self::quoteLoadDataFormattingOption($optionsEnclosedBy) . "'";
            $sqlParts[] = 'ESCAPED BY ' . "'" . self::quoteLoadDataFormattingOption($optionsEscapedBy) . "'";

        // TODO : add configuration possibility
        $sqlParts[] = 'LINES';
            $sqlParts[] = 'STARTING BY ' . "''";
            $sqlParts[] = 'TERMINATED BY ' . "'" . '\n' . "'";

        $optionIgnoreLines = (int)($options[self::LOAD_DATA_OPTION_IGNORE_LINES] ?? null);
        if( $optionIgnoreLines ){
            $sqlParts[] = 'IGNORE ' . $optionIgnoreLines . ' LINES';
        }

        $optionColumns = (array)($options[self::LOAD_DATA_OPTION_COLUMNS] ?? null);
        if( $optionColumns ){
            $columns = [];
            foreach($optionColumns as $columnName){
                $columns[] = self::getSqlFieldNameQuoted($columnName);
            }
            $sqlParts[] = '(' . implode(',', $columns) . ')';
        }

        return implode(' ', $sqlParts);
    }

    /**
     * @param string $optionValue
     * @return string
     */
    private static function quoteLoadDataFormattingOption(string $optionValue): string
    {
        // TODO : add unit test for this case and test if each case is accepted by MySQL
        // Character \ must be always treated in first place
        // https://www.php.net/manual/fr/regexp.reference.escape.php
        // https://dev.mysql.com/doc/refman/8.0/en/load-data.html#load-data-index-handling => Escape Sequence
        return addcslashes($optionValue, "\\\r\n\t\f\e\7\v'");
    }

    /**
     * @param string $collation
     * @return string
     */
    public static function getCharsetFromCollation(string $collation): string
    {
        if( preg_match('/^([^_]+)(?:_.+)?$/U', $collation, $matches) ){
            return $matches[1];
        }

        throw new \UnexpectedValueException('Collation format "' . $collation . '" is not recognized has valid.');
    }

    /**
     * @param ConfigurationTableField $Field
     * @return string|int|float|null
     */
    private static function getDefaultValue(ConfigurationTableField $Field)
    {
        $type = $Field->getType();
        // default value consolidated
        $default = self::getDefaultForField($Field);

        if( null === $default ){
            return 'NULL';
        }

        if( $default instanceof \DateTime ){
            if( Constants::TYPE_DATE === $type ){
                return $default->format('Y-m-d');
            }
            if( Constants::TYPE_TIME === $type ){
                return $default->format('h:i:s');
            }
            if( Constants::TYPE_DATETIME === $type ){
                return $default->format('Y-m-d h:i:s');
            }

            throw new \LogicException('No type matching for default value of type ' . \DateTime::class . ' for field "' . $Field->getName() . '"');
        }
        if( Constants::TYPE_INTEGER === $type ){
            return (int)$default;
        }
        if( Constants::TYPE_FLOAT === $type ){
            return (float)$default;
        }
        if( Constants::TYPE_BOOLEAN === $type ){
            return (int)$default;
        }
        if( \in_array($type, [Constants::TYPE_STRING, Constants::TYPE_TEXT, Constants::TYPE_BLOB], true) ){
            return "'" . str_replace(['"', "'"], ['\"', "\'"], $default) . "'";
        }

        throw new \LogicException('Unsupported type "' . $type . '" with value "' . $default . '"');
    }

    /**
     * @param ConfigurationTableField $Field
     * @return string
     */
    private static function getColumnType(ConfigurationTableField $Field): string
    {
        $type = $Field->getType();
        $length = $Field->getLength();
        $name = $Field->getName();
        switch($type){
            case Constants::TYPE_INTEGER:
                return 'BIGINT(20)';
            case Constants::TYPE_STRING:
                if( null === $length ){
                    throw new \LogicException('You must define a length before for ' . $name . '.');
                }
                return 'VARCHAR(' . $length . ')';
            case Constants::TYPE_TEXT:
                return 'LONGTEXT';
            case Constants::TYPE_BLOB:
                return 'LONGBLOB';
            case Constants::TYPE_FLOAT:
                return 'DOUBLE';
            case Constants::TYPE_DATE:
                return 'DATE';
            case Constants::TYPE_DATETIME:
                return 'DATETIME';
            case Constants::TYPE_TIME:
                return 'TIME';
            case Constants::TYPE_BOOLEAN:
                return 'TINYINT(1)';
        }
        throw new \UnexpectedValueException('Unsupported field type ' . $type);
    }

    /**
     * get default value for field and check if necessary
     * @param ConfigurationTableField $configurationTableField
     * @return mixed
     */
    private static function getDefaultForField(ConfigurationTableField $configurationTableField)
    {
        $currentDefault = $configurationTableField->getDefault();
        $isNullable = $configurationTableField->isNullable();
        $type = $configurationTableField->getType();
        $name = $configurationTableField->getName();

        // In case default is not define…
        if( null === $currentDefault ){
            // column can be null, so we touch nothing
            if( $isNullable ){
                return $currentDefault;
            }

            // here value is not nullable, we take the better based on type

            if( Constants::TYPE_FLOAT === $type ){
                return .0;
            }

            if( Constants::TYPE_INTEGER === $type ){
                return 0;
            }

            if( \in_array($type, [Constants::TYPE_DATE, Constants::TYPE_TIME, Constants::TYPE_DATETIME], true) ){
                return null;
            }

            if( \in_array($type, [Constants::TYPE_STRING, Constants::TYPE_TEXT, Constants::TYPE_BLOB, Constants::TYPE_BOOLEAN], true) ){
                return '';
            }
        }
        // value is not null, so we perform a verification
        else{
            if( Constants::TYPE_FLOAT === $type ){
                if( \is_string($currentDefault) ){
                    $currentDefault = (float)$currentDefault;
                }
                if( !\is_float($currentDefault) ){
                    throw new \LogicException('Default value for target field "' . $name . '" is not a "' . Constants::TYPE_FLOAT  .'".');
                }

                return $currentDefault;
            }

            if( Constants::TYPE_INTEGER === $type ){
                if( \is_string($currentDefault) ){
                    $currentDefault = (float)$currentDefault;
                }
                if( !\is_int($currentDefault) ){
                    throw new \LogicException('Default value for target field "' . $name . '" is not an "' . Constants::TYPE_INTEGER . '".');
                }

                return $currentDefault;
            }

            if( \in_array($type, [Constants::TYPE_DATE, Constants::TYPE_TIME, Constants::TYPE_DATETIME], true) ){
                if( \is_string($currentDefault) || \is_int($currentDefault) ){
                    return new \DateTime($currentDefault, self::createUTC());
                }
                if( $currentDefault instanceof \DateTime ){
                    return $currentDefault;
                }

                throw new \LogicException('Default value for target field "' . $name . '" is not supported, please provide a '. \DateTime::class . ', "' . Constants::TYPE_STRING . '" or "' . Constants::TYPE_INTEGER . '".');
            }

            if( \in_array($type, [Constants::TYPE_STRING, Constants::TYPE_TEXT, Constants::TYPE_BLOB, Constants::TYPE_BOOLEAN], true) ){
                if( !\is_string($currentDefault) ){
                    throw new \LogicException('Default value for target field "' . $name . '" is not a "' . Constants::TYPE_STRING . '".');
                }

                return $currentDefault;
            }
        }

        throw new \RuntimeException('Unsupported type "' . $type . '" for field "' . $name .'"');
    }

    /**
     * @param mixed $data
     * @param AbstractConfigurationImportField $field
     * @return string|int
     */
    public static function getDataConverted($data, AbstractConfigurationImportField $field)
    {
        $name = $field->getTargetName();
        $type = $field->getType();
        $isNullable = $field->isNullable();

        if( null === $data ){
            if( $isNullable ){
                return null;
            }
            throw new \UnexpectedValueException('Field "' . $name . '" can not be nullable but data return is null.');
        }

        switch($type){
            case Constants::TYPE_INTEGER:
                return (int)$data;
            case Constants::TYPE_STRING:
                return (string)$data;
            // when date is part of data, we have to manage weirds php case conversion…
            // (details https://stackoverflow.com/questions/31827328/php-datetime-fail-to-convert-negative-iso8601-date)
            case Constants::TYPE_DATETIME:
            case Constants::TYPE_DATE:
                $date = new \DateTime((string)$data, self::createUTC());
                $timestamp = $date->getTimestamp();
                // https://fr.wikipedia.org/wiki/ISO_8601 => 1583-01-02 00:00:00
                if( $timestamp < -12212467200 ){
                    if( $isNullable ){
                        return null;
                    }

                    if( Constants::TYPE_DATETIME === $type ){
                        return '0000-00-00 00:00:00';
                    }
                    // obviously that is Constants::TYPE_DATE
                    return '0000-00-00';
                }

                if( Constants::TYPE_DATETIME === $type ){
                    return $date->format('Y-m-d H:i:s');
                }
                return $date->format('Y-m-d');
            case Constants::TYPE_TIME:
                return (new \DateTime((string)$data, self::createUTC()))->format('H:i:s');
            case Constants::TYPE_BOOLEAN:
                return (int)$data;
            case Constants::TYPE_TEXT:
                return (string)$data;
            case Constants::TYPE_FLOAT:
                return (float)$data;
            case Constants::TYPE_BLOB:
                return (string)$data;
        }

        throw new \InvalidArgumentException('Unsupported type : ' . $type . ' for field "' . $name . '" with value "' . $data . '"');
    }

    /**
     * @return \DateTimeZone
     */
    private static function createUTC(): \DateTimeZone
    {
        if( null === self::$UTC ) {
            self::$UTC = new \DateTimeZone('UTC');
        }

        return self::$UTC;
    }

    /**
     * @param string $tableName
     * @return string
     */
    public static function getSqlLockTableRead(string $tableName): string
    {
        return 'LOCK TABLES ' . self::getSqlTableNameQuoted($tableName) . ' READ';
    }

    /**
     * @param string $tableName
     * @return string
     */
    public static function getSqlLockTableWrite(string $tableName): string
    {
        return 'LOCK TABLES ' . self::getSqlTableNameQuoted($tableName) . ' WRITE';
    }

    /**
     * @return string
     */
    public static function getSqlUnlockTables(): string
    {
        return 'UNLOCK TABLES';
    }

    /**
     * @param string $tableName
     * @return string
     */
    public static function getSqlDisableKeys(string $tableName): string
    {
        return 'ALTER TABLE ' . self::getSqlTableNameQuoted($tableName) . ' DISABLE KEYS';
    }

    /**
     * @param string $tableName
     * @return string
     */
    public static function getSqlEnableKeys(string $tableName): string
    {
        return 'ALTER TABLE ' . self::getSqlTableNameQuoted($tableName) . ' ENABLE KEYS';
    }

    /**
     * @return string
     */
    public static function getSqlCurrentSchema(): string
    {
        return 'SELECT DATABASE()';
    }

    public static function getSqlRowCount(): string
    {
        return 'SELECT ROW_COUNT() ' . self::OUT_NAME_INSERTED;
    }

    /**
     * @param string $tableName
     * @param string|null $schemaName
     * @param string|null $columnName
     * @return string
     */
    public static function getSqlCountRowTable(string $tableName, ?string $schemaName = null, ?string $columnName = null): string
    {
        return 'SELECT COUNT(' . ( null === $columnName ? '*' : self::getSqlFieldNameQuoted($columnName)) . ') ' . self::OUT_NAME_RESULT .' FROM ' . self::getJoinSchemaAndTable($tableName, $schemaName);
    }

    /**
     * @param string $tableName
     * @param string $schemaName
     * @return string
     */
    public static function getSqlTableCollation(string $tableName, string $schemaName): string
    {
        return 'SELECT TABLE_COLLATION collation_name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME=' . "'" . $tableName . "'" . ' AND TABLE_SCHEMA=' . "'" . $schemaName . "'";
    }

    /**
     * @param string $tableName
     * @param string|null $schemaName
     * @param string|null $collation
     *      null is equivalent to Constants::DEFAULT_COLLATION
     * @return string
     */
    public static function getSqlAlterTableCollation(string $tableName, ?string $schemaName = null, string $collation = null): string
    {
        if( null === $collation ){
            $collation = Constants::DEFAULT_COLLATION;
        }
        $charset = self::getCharsetFromCollation($collation);
        return 'ALTER TABLE ' . self::getJoinSchemaAndTable($tableName, $schemaName) . ' CONVERT TO CHARACTER SET ' . $charset . ' COLLATE ' . $collation;
    }

    /**
     * @param string $schemaName
     * @return string
     */
    public static function getSqlSchemaDefaultCharacterName(string $schemaName): string
    {
        return self::setSqlSchemaInformation(self::OUT_SCHEMA_DEFAULT_CHARACTER_NAME, $schemaName);
    }

    /**
     * @param string $schemaName
     * @return string
     */
    public static function getSqlSchemaDefaultCollationName(string $schemaName): string
    {
        return self::setSqlSchemaInformation(self::OUT_SCHEMA_DEFAULT_COLLATION_NAME, $schemaName);
    }

    /**
     * @param string $columnName
     * @param string $schemaName$schemaName
     * @return string
     */
    private static function setSqlSchemaInformation(string $columnName, string $schemaName): string
    {
        return 'SELECT ' . self::getSqlFieldNameQuoted($columnName) . ' FROM information_schema.SCHEMATA WHERE schema_name = ' . "'" . $schemaName . "'";
    }
}