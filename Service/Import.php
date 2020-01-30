<?php
declare(strict_types=1);

namespace Draeli\Mysql\Service;

use Doctrine\Common\Persistence\ManagerRegistry;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;

use Symfony\Component\Filesystem\Filesystem;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use Sonata\Exporter\Source\DoctrineDBALConnectionSourceIterator;
use Sonata\Exporter\Handler as ExporterHandler;

use Draeli\Mysql\DependencyInjection\Configuration;

use Draeli\Mysql\Components\AbstractConfigurationImportField as ComponentAbstractConfigurationImport;
use Draeli\Mysql\Components\ConfigurationImport as ComponentConfigurationImport;
use Draeli\Mysql\Components\ConfigurationImportField as ComponentConfigurationImportField;
use Draeli\Mysql\Components\ConfigurationImportIndex as ComponentConfigurationImportIndex;
use Draeli\Mysql\Components\ConfigurationTable as ComponentConfigurationTable;
use Draeli\Mysql\Components\ConfigurationTableField as ComponentConfigurationTableField;
use Draeli\Mysql\Components\ConfigurationTableIndex as ComponentConfigurationTableIndex;
use Draeli\Mysql\Components\Import as ComponentImport;
use Draeli\Mysql\Writer;
use Draeli\Mysql\Constants;
use Draeli\Mysql\Utils;

/**
 * @package Draeli\Mysql\Service
 */
class Import
{
    /**
     * @see \Draeli\Mysql\Service\Import::createConfigurationImportField
     */
    public const CONFIGURATION_FIELD_OPTION_OVERRIDE_TYPE = 'type';


    /**
     * @see \Draeli\Mysql\Service\Import::createConfigurationImportField
     */
    public const CONFIGURATION_FIELD_OPTION_OVERRIDE_NULLABLE = 'nullable';

    /**
     * @var ParameterBagInterface
     */
    private $parameterBag;

    /**
     * @var ManagerRegistry
     */
    private $managerRegistry;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @param ParameterBagInterface $parameterBag
     * @param ManagerRegistry $managerRegistry
     */
    public function __construct(ParameterBagInterface $parameterBag, ManagerRegistry $managerRegistry)
    {
        $this->parameterBag = $parameterBag;
        $this->managerRegistry = $managerRegistry;

        $this->filesystem = new Filesystem();
    }

    /**
     * Create a new instance of configuration import representation
     * Choose a call of this method rather than direct instantiation
     * @param string $sourceConnectionName
     * @param string $sourceTableName
     * @param string $targetConnectionName
     * @param string[] $fieldsAtTheOrigin
     * @return ComponentConfigurationImport
     */
    public function createConfigurationImport(string $sourceConnectionName, string $sourceTableName, string $targetConnectionName, array $fieldsAtTheOrigin): ComponentConfigurationImport
    {
        $instance = new ComponentConfigurationImport($sourceConnectionName, $sourceTableName, $targetConnectionName);

        $configurationTable = $this->getConfigurationTable($sourceConnectionName, $sourceTableName);

        $instance->setCollation($configurationTable[Configuration::NAME_IMPORT_ALIAS_TABLES_COLLATION] ?? null);

        foreach($fieldsAtTheOrigin as $fieldAtTheOrigin){
            if( !\is_string($fieldAtTheOrigin) ){
                throw new \InvalidArgumentException('Field list must define only string.');
            }

            $fieldConfiguration = $this->createConfigurationImportField($sourceConnectionName, $sourceTableName, $fieldAtTheOrigin);
            $instance->addField($fieldConfiguration);
        }

        $this->updateIndexConfigurationImport($instance);

        return $instance;
    }

    /**
     * Create instance of configuration import for a field based upon configuration
     * @param string $sourceConnectionName
     * @param string $sourceTableName
     * @param string $fieldNameAtTheOrigin
     * @param array $options All options are to use in replacement of original configuration value
     *      string self::CONFIGURATION_FIELD_OPTION_OVERRIDE_TYPE
     *      bool self::CONFIGURATION_FIELD_OPTION_OVERRIDE_NULLABLE
     * @return ComponentConfigurationImportField
     */
    public function createConfigurationImportField(string $sourceConnectionName, string $sourceTableName, string $fieldNameAtTheOrigin, array $options = []): ComponentConfigurationImportField
    {
        $configurationTable = $this->getConfigurationTable($sourceConnectionName, $sourceTableName);
        $fields = $configurationTable[Configuration::NAME_IMPORT_ALIAS_TABLES_FIELDS][$fieldNameAtTheOrigin] ?? null;
        if( null === $fields ){
            throw new \InvalidArgumentException('No field "' . $fieldNameAtTheOrigin .'" inside table "' . $sourceTableName . '" for connection "' . $sourceConnectionName . '".');
        }

        $type = $options[self::CONFIGURATION_FIELD_OPTION_OVERRIDE_TYPE] ?? $fields[Configuration::NAME_IMPORT_ALIAS_FIELD_TYPE];
        $nullable = $options[self::CONFIGURATION_FIELD_OPTION_OVERRIDE_NULLABLE] ?? $fields[Configuration::NAME_IMPORT_ALIAS_FIELD_NULLABLE];

        $instance = new ComponentConfigurationImportField($type, $nullable, $fieldNameAtTheOrigin);
        $instance->setTargetName($fields[Configuration::NAME_IMPORT_ALIAS_FIELD_NAME]);
        $instance->setLength($fields[Configuration::NAME_IMPORT_ALIAS_FIELD_LENGTH] ?? null);
        $instance->setSigned($fields[Configuration::NAME_IMPORT_ALIAS_FIELD_SIGNED] ?? null);
        $instance->setDefault($fields[Configuration::NAME_IMPORT_ALIAS_FIELD_DEFAULT] ?? null);
        $instance->setSelect($fields[Configuration::NAME_IMPORT_ALIAS_FIELD_SELECT] ?? null);

        return $instance;
    }

    /**
     * Update index list based upon fields list and known configuration
     * @param ComponentConfigurationImport $configurationImport
     * @param bool $keepUnknownIndex Index name unknown inside configuration will be kept during update if fields inside index are compatible with field list
     */
    public function updateIndexConfigurationImport(ComponentConfigurationImport $configurationImport, bool $keepUnknownIndex = true): void
    {
        $sourceConnectionName = $configurationImport->getSourceConnectionName();
        $sourceTableName = $configurationImport->getSourceTableName();
        $configurationTable = $this->getConfigurationTable($sourceConnectionName, $sourceTableName);
        $indexes = $configurationTable['indexes'] ?? [];

        // Collection of source fields name
        $fieldsSourceRegistered = [];
        foreach($configurationImport->getFields() as $ConfigurationField){
            $sourceName = $ConfigurationField->getSourceName();
            $fieldsSourceRegistered[$sourceName] = true;
        }

        // Keep a collection of eligible index for field list define in current ConfigurationImport
        $indexNameEligible = [];
        foreach ($indexes as $indexName => $indexOptions){
            if( \is_array($indexOptions) ){
            $indexFields = $indexOptions['fields'] ?? [];
            if( \count($indexFields) ){
                $isEligible = true;
                foreach($indexFields as $indexField){
                    $fieldSourceName = $indexField['field'];
                    if( !isset($fieldsSourceRegistered[$fieldSourceName]) ){
                        $isEligible = false;
                        break;
                    }
                }
                if( $isEligible ){
                    $indexNameEligible[$indexName] = true;
                }
            }
        }
        }

        // first we watch existing
        foreach($configurationImport->getIndexes() as $indexName => $ConfigurationIndex){
            $sourceName = $ConfigurationIndex->getName();

            // If index name is not part of configuration
            if( !isset($indexes[$sourceName]) ){
                // we want to keep only index defined inside configuration!
                if( false === $keepUnknownIndex ){
                    $configurationImport->removeIndex($sourceName);
                }

                // nothing more to do
                continue;
            }

            // if index is already known, we do not touch anything
            if( isset($indexNameEligible[$sourceName]) && $configurationImport->hasIndex($indexName) ){
                $indexNameEligible[$sourceName] = false;
                //continue;
            }
        }
        // finally add unknown eligible index
        foreach($indexNameEligible as $indexName => $v){
            $indexOptions = $indexes[$indexName];
            $fields = $indexOptions['fields'] ?? [];
            $instance = new ComponentConfigurationImportIndex($indexName, $indexOptions['type']);
            foreach($fields as $fieldOption){
                $instance->addField($fieldOption['field'], $fieldOption['length']);
            }

            $configurationImport->addIndex($instance);
        }
    }

    /**
     * Run an import base upon configuration object
     * @param ComponentConfigurationImport $configurationImport
     * @return ComponentImport
     */
    public function import(ComponentConfigurationImport $configurationImport): ComponentImport
    {
        if( null === $configurationImport->getFormattingDelimiter() ){
            $configurationImport->setFormattingDelimiter($this->getConfigurationFormattingDelimiter());
        }
        if( null === $configurationImport->getFormattingEnclosure() ){
            $configurationImport->setFormattingEnclosure($this->getConfigurationFormattingEnclosure());
        }
        if( null === $configurationImport->getFormattingEscapeChar() ){
            $configurationImport->setFormattingEscapeChar($this->getConfigurationFormattingEscapeChar());
        }

        $connectionSource = $this->getConnectionSource($configurationImport);
        $connectionTarget = $this->getConnectionTarget($configurationImport);

        $connectionSourceConfiguration = $connectionSource->getConfiguration();
        $connectionTargetConfiguration = $connectionTarget->getConfiguration();
        $connectionSourceLogger = $connectionSourceConfiguration->getSQLLogger();
        $connectionTargetLogger = $connectionTargetConfiguration->getSQLLogger();

        // disable loggers during import
        $connectionSourceConfiguration->setSQLLogger(null);
        $connectionTargetConfiguration->setSQLLogger(null);

        $databaseTimeoutParameters = Utils::getCurrentDatabaseTimeoutParameters();

        // to avoid "MySQL server has gone away" error
        foreach ($databaseTimeoutParameters as $databaseTimeoutParameter => $value){
            ini_set($databaseTimeoutParameter, '-1');
        }

        $callbackBefore = $configurationImport->getCallbackBefore();
        if( null !== $callbackBefore ){
            $callbackBefore($configurationImport);
        }

        $configurationTable = $this->prepareComponentConfigurationTable($configurationImport);

        $import = new ComponentImport($configurationImport, $configurationTable);

        // existing one must be erase ?
        if( $configurationImport->isEraseExisting() ) {
            $this->dropTable($connectionTarget, $configurationTable);
            $this->createTable($connectionTarget, $configurationTable);
        }

        $sqlSelect = $this->getSqlSelect($configurationImport);
        $import->setSelectSqlSource($sqlSelect);

        /*----------
        Prepare stuff necessary before import
        ----------*/
        $fieldsOrder = $configurationImport->getOrderTargetFields();
        $fieldsTarget = $configurationImport->getFieldsTargetName();
        // to be sure all fields targeted (normal field and calculated field) are declared for order
        $fieldsMissing = array_diff($fieldsTarget, $fieldsOrder);
        if( \count($fieldsMissing) ){
            throw new \LogicException('Some fields are missing inside order definition, please add : ' . implode('; ', $fieldsMissing));
        }

        // Prepare directory and file for store temp datas to load
        $directoryTempFile = $this->getConfigurationTempFileDirectory();
        if( !$this->filesystem->exists($directoryTempFile) ){
            $this->filesystem->mkdir($directoryTempFile);
        }
        $fileTempFile = $directoryTempFile . $this->getConfigurationTempFilePrefix() . $configurationTable->getTableName();
        if( file_exists($fileTempFile) ){
            @unlink($fileTempFile);
        }

        $writer = new Writer($configurationImport, $fileTempFile);
        $source = new DoctrineDBALConnectionSourceIterator($connectionSource, $sqlSelect);
        ExporterHandler::create($source, $writer)->export();

        $lineCreated = $writer->getLinesCreated();

        $import->setTempFile($fileTempFile);
        $import->setLineCreated($lineCreated);

        $tableName = $configurationTable->getTableName();

        $connectionTarget->executeQuery(Utils::getSqlLockTableWrite($tableName));

        if( $configurationImport->isDisableKeys() ){
            $connectionTarget->executeQuery(Utils::getSqlDisableKeys($tableName));
        }

        $sqlLoadData = Utils::getSqlLoadData($fileTempFile, $tableName, [
            Utils::LOAD_DATA_OPTION_TERMINATED_BY => $configurationImport->getFormattingDelimiter()
            , Utils::LOAD_DATA_OPTION_ENCLOSED_BY => $configurationImport->getFormattingEnclosure()
            , Utils::LOAD_DATA_OPTION_ESCAPED_BY => $configurationImport->getFormattingEscapeChar()
            , Utils::LOAD_DATA_OPTION_IGNORE_LINES => 1
            , Utils::LOAD_DATA_OPTION_COLUMNS => $fieldsOrder
            , Utils::LOAD_DATA_OPTION_CHARSET => Utils::getCharsetFromCollation($configurationTable->getCollation())
            , Utils::LOAD_DATA_OPTION_SCHEMA => $configurationTable->getSchema()
            , Utils::LOAD_DATA_OPTION_DUPLICATE_STRATEGY => $configurationImport->getDuplicateStrategy()
        ]);
        $connectionTarget->executeQuery($sqlLoadData);
        $stmt = $connectionTarget->executeQuery(Utils::getSqlRowCount());
        $results = $stmt->fetch(\PDO::FETCH_ASSOC);
        $lineInserted = (int)($results['inserted'] ?? 0);
        $import->setLineInserted($lineInserted);

        if( $configurationImport->isDisableKeys() ){
            $connectionTarget->executeQuery(Utils::getSqlEnableKeys($tableName));
        }

        $connectionTarget->executeQuery(Utils::getSqlUnlockTables());

        // in case we have erase…
        if( $configurationImport->isEraseExisting() ) {
            // index creation after data load to optimized
            $this->updateTableIndex($connectionTarget, $configurationTable);
        }

        $callbackAfter = $configurationImport->getCallbackAfter();
        if( null !== $callbackAfter ){
            $callbackAfter($configurationImport);
        }

        // restore timeout parameters
        foreach ($databaseTimeoutParameters as $databaseTimeoutParameter => $value){
            ini_set($databaseTimeoutParameter, $value);
        }

        // restore loggers
        $connectionSourceConfiguration->setSQLLogger($connectionSourceLogger);
        $connectionTargetConfiguration->setSQLLogger($connectionTargetLogger);

        return $import;
    }
    /**
     * @param ComponentConfigurationImport $configurationImport
     * @return Connection
     */
    private function getConnectionSource(ComponentConfigurationImport $configurationImport): Connection
    {
        $nameConnectionSource = $configurationImport->getSourceConnectionName();
        /** @var Connection $connection */
        $connection = $this->managerRegistry->getConnection($nameConnectionSource);
        return $connection;

    }

    /**
     * @param ComponentConfigurationImport $configurationImport
     * @return Connection
     */
    private function getConnectionTarget(ComponentConfigurationImport $configurationImport): Connection
    {
        $nameConnectionTarget = $configurationImport->getTargetConnectionName();
        /** @var Connection $connection */
        $connection = $this->managerRegistry->getConnection($nameConnectionTarget);
        return $connection;
    }

    /**
     * Return SQL to select all datas to import
     * @param ComponentConfigurationImport $configurationImport
     * @return string
     */
    private function getSqlSelect(ComponentConfigurationImport $configurationImport): string
    {
        $connectionSource = $this->getConnectionSource($configurationImport);
        $platform = $connectionSource->getDriver()->getDatabasePlatform();
        $fields = $configurationImport->getFields();
        if( !\count($fields) ){
            throw new \LogicException('You must define at least on field for source table ' . $configurationImport->getSourceTableName());
        }
        $fieldsSource = [];
        foreach($fields as $field){
            $select = $field->getSelect();
            $fieldQuoted = $field->getSourceAsset()->getQuotedName($platform);
            // in case there is no specific select
            if( null === $select ) {
                $fieldsSource[] =$fieldQuoted;
            }
            else{
                // /!\ there is no filter/quote on select, you are supposed to know what you do in that case!
                $fieldsSource[] = $select . ' ' . $fieldQuoted;
            }
        }
        // to get access to quoted name
        $tableAsset = new Table($configurationImport->getSourceTableName());
        $sqlCondition = $configurationImport->getSqlCondition();
        return 'SELECT ' . implode(',', $fieldsSource).' FROM ' . $tableAsset->getQuotedName($platform) . (null === $sqlCondition ? '' : ' ' . $sqlCondition);
    }

    /**
     * Prepare table configuration based on import configuration
     * @param ComponentConfigurationImport $configurationImport
     * @return ComponentConfigurationTable
     */
    public function prepareComponentConfigurationTable(ComponentConfigurationImport $configurationImport): ComponentConfigurationTable
    {
        $targetTableName = $this->getTableNameFromConfiguration($configurationImport);
        $collation = $configurationImport->getCollation() ?? Constants::DEFAULT_COLLATION;
        $engine = $configurationImport->getEngine() ?? 'MyISAM';

        $configurationTable = new ComponentConfigurationTable($targetTableName, $collation, $engine);
        $configurationTable->setSchema($configurationImport->getSchemaTarget());

        /** @var ComponentAbstractConfigurationImport[] $fields */
        $fields = [];
        foreach($configurationImport->getFields() as $Field){
            $fields[$Field->getTargetName()] = $Field;
        }
        foreach($configurationImport->getFieldsCalculated() as $Field){
            $fields[$Field->getTargetName()] = $Field;
        }

        foreach($configurationImport->getOrderTargetFields() as $fieldNameTarget){
            if( !isset($fields[$fieldNameTarget]) ){
                throw new \LogicException('You define an order for fields with a non-existing field "' . $fieldNameTarget . '".');
            }

            $Field = $fields[$fieldNameTarget];

            $configurationTableField = new ComponentConfigurationTableField($fieldNameTarget, $Field->getType(), $Field->isNullable());
            $configurationTableField->setDefault($Field->getDefault());
            $configurationTableField->setSigned($Field->getSigned());
            $configurationTableField->setLength($Field->getLength());

            $configurationTable->addField($configurationTableField);
        }

        foreach($configurationImport->getIndexes() as $index){
            $name = $index->getName();
            $type = $index->getType();
            $configurationTableIndex = new ComponentConfigurationTableIndex($name, $type);
            foreach ($index->getFields() as $fieldNameOrigin => $fieldValue){
                $configurationImportField = null;
                if( $configurationImport->hasField($fieldNameOrigin) ){
                    $configurationImportField = $configurationImport->getField($fieldNameOrigin);
                }
                if( $configurationImport->hasFieldCalculated($fieldNameOrigin) ){
                    $configurationImportField = $configurationImport->getFieldCalculated($fieldNameOrigin);
                }
                if( null === $configurationImportField ){
                    throw new \LogicException('No find field named "' . $fieldNameOrigin . '"');
                }
                $configurationTableIndex->addFieldName($configurationImportField->getTargetName(), $fieldValue);
            }

            $configurationTable->addIndex($configurationTableIndex);
        }

        return $configurationTable;
    }

    /**
     * @param Connection $connection
     * @param ComponentConfigurationTable $configurationTable
     * @param bool $errorIfNotExist
     */
    public function dropTable(Connection $connection, ComponentConfigurationTable $configurationTable, bool $errorIfNotExist = false): void
    {
        $sql = Utils::getSqlDropTable($configurationTable, $errorIfNotExist);
        $connection->executeQuery($sql);
    }

    /**
     * Do the query to create table structure
     * @param Connection $connection
     * @param ComponentConfigurationTable $configurationTable
     * @param bool $ignoreExisting
     */
    public function createTable(Connection $connection, ComponentConfigurationTable $configurationTable, bool $ignoreExisting = false): void
    {
        $structure = Utils::getSqlStructureTable($configurationTable, $ignoreExisting);
        $connection->executeQuery($structure);
    }

    /**
     * Do queries to update table structure with indexes
     * @param Connection $connection
     * @param ComponentConfigurationTable $configurationTable
     */
    public function updateTableIndex(Connection $connection, ComponentConfigurationTable $configurationTable): void
    {
        $alters = Utils::getSqlAddIndex($configurationTable);
        foreach ($alters as $alter){
            $connection->executeQuery($alter);
        }
    }

    /**
     * Return prefix name define inside configuration
     * @return string
     */
    public function getConfigurationTableNamePrefix(): string
    {
        $prefix = $this->getConfiguration()[Configuration::NAME_IMPORT_TABLE_PREFIX];
        if( \strlen($prefix) > 32 ){
            throw new \InvalidArgumentException('Table prefix length is over 32.');
        }
        return $prefix;
    }

    /**
     * Return table name based upon configuration
     * @param ComponentConfigurationImport $configurationImport
     * @return string
     */
    public function getTableNameFromConfiguration(ComponentConfigurationImport $configurationImport): string
    {
        $tableName = $configurationImport->getTableName();
        if( null === $tableName ){
            $tableNameSource = $configurationImport->getSourceTableName();
            $fields = $configurationImport->getFieldsTargetName();
            if( !\count($fields) ){
                throw new \LogicException('Table name require field define in configuration.');
            }
            $tableName = $this->getConfigurationTableNamePrefix() . hash('md5', $tableNameSource . implode('-', $fields));
        }
        if( \strlen($tableName) > 64 ){
            throw new \InvalidArgumentException('Table name length is over 64.');
        }
        return $tableName;
    }

    /**
     * Entry point for bundle configuration expose in parameter bag
     * @return array
     */
    private function getConfiguration(): array
    {
        return $this->parameterBag->get('draeli_mysql.configuration')[Configuration::NAME_IMPORT];
    }

    /**
     * Return configuration for a specific table of a specific connection
     * @param string $sourceConnectionName Connection name inside configuration
     * @param string $sourceTableName Table inside configuration
     * @return array
     */
    public function getConfigurationTable(string $sourceConnectionName, string $sourceTableName): array
    {
        $configurationAlias = $this->getConfiguration()[Configuration::NAME_IMPORT_ALIAS];

        $connectionConfiguration = $configurationAlias[$sourceConnectionName] ?? null;
        if( null === $connectionConfiguration ){
            throw new \InvalidArgumentException('Source connection name "' . $sourceConnectionName .'" does not exist.');
        }

        $tableConfiguration = $connectionConfiguration[Configuration::NAME_IMPORT_ALIAS_TABLES][$sourceTableName] ?? null;
        if( null === $tableConfiguration ){
            throw new \InvalidArgumentException('Source table name "' . $sourceTableName .'" inside "' . $sourceConnectionName . ' " connection does not exist.');
        }

        return $tableConfiguration;
    }

    /**
     * @return array
     */
    public function getConfigurationTempFile(): array
    {
        return $this->getConfiguration()[Configuration::NAME_IMPORT_TEMP_FILE];
    }

    /**
     * @return string
     */
    public function getConfigurationTempFileDirectory(): string
    {
        return $this->getConfigurationTempFile()[Configuration::NAME_IMPORT_TEMP_FILE_DIRECTORY];
    }

    /**
     * @return string
     */
    public function getConfigurationTempFilePrefix(): string
    {
        return $this->getConfigurationTempFile()[Configuration::NAME_IMPORT_TEMP_FILE_PREFIX];
    }

    /**
     * @return array
     */
    public function getConfigurationTempFileFormatting(): array
    {
        return $this->getConfigurationTempFile()[Configuration::NAME_IMPORT_TEMP_FILE_FORMATTING];
    }

    /**
     * @return string|null
     */
    public function getConfigurationFormattingDelimiter(): ?string
    {
        return $this->getConfigurationTempFileFormatting()[Configuration::NAME_IMPORT_TEMP_FILE_FORMATTING_DELIMITER];
    }

    /**
     * @return string|null
     */
    public function getConfigurationFormattingEnclosure(): ?string
    {
        return $this->getConfigurationTempFileFormatting()[Configuration::NAME_IMPORT_TEMP_FILE_FORMATTING_ENCLOSURE];
    }

    /**
     * @return string|null
     */
    public function getConfigurationFormattingEscapeChar(): ?string
    {
        return $this->getConfigurationTempFileFormatting()[Configuration::NAME_IMPORT_TEMP_FILE_FORMATTING_ESCAPE_CHAR];
    }
}