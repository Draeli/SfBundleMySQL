<?php
declare(strict_types=1);

namespace Draeli\Mysql\Components;

/**
 * @package Draeli\Mysql\Components
 */
class ConfigurationImport
{
    /**
     * @var string
     */
    private $sourceConnectionName;

    /**
     * @var string
     */
    private $sourceTableName;

    /**
     * @var string
     */
    private $targetConnectionName;

    /**
     * @var ConfigurationImportField[]
     */
    private $fields = [];

    /**
     * @var ConfigurationImportFieldCalculated[]
     */
    private $fieldsCalculated = [];

    /**
     * @var ConfigurationImportIndex[]
     */
    private $indexes = [];

    /**
     * @var string|null
     */
    private $collation;

    /**
     * @var string|null
     */
    private $engine;

    /**
     * @var bool
     */
    private $eraseExisting = true;

    /**
     * @var string|null
     */
    private $sqlCondition;

    /**
     * @var string|null
     */
    private $tableName;

    /**
     * @var string|null
     */
    private $schemaSource;

    /**
     * @var string|null
     */
    private $schemaTarget;

    /**
     * To call your own logic before import
     * @var callable|null
     */
    private $callbackBefore;

    /**
     * To call your own logic after import
     * @var callable|null
     */
    private $callbackAfter;

    /**
     * @var string[]|null
     */
    private $orderTargetFields;

    /**
     * @var string|null
     */
    private $formattingDelimiter;

    /**
     * @var string|null
     */
    private $formattingEnclosure;

    /**
     * @var string|null
     */
    private $formattingEscapeChar;

    /**
     * @var string|null
     */
    private $duplicateStrategy;

    /**
     * @var bool
     */
    private $disableKeys = false;

    /**
     * @var callable|null
     */
    private $callbackLineValidation;

    /**
     * @var callable|null
     */
    private $callbackLineCleaning;

    /**
     * @param string $sourceConnectionName
     * @param string $sourceTableName
     * @param string $targetConnectionName
     */
    public function __construct(string $sourceConnectionName, string $sourceTableName, string $targetConnectionName)
    {
        if( '' === $sourceConnectionName ){
            throw new \InvalidArgumentException('Name for source connection can not be empty.');
        }
        if( '' === $sourceTableName ){
            throw new \InvalidArgumentException('Name for source table can not be empty.');
        }
        if( '' === $targetConnectionName ){
            throw new \InvalidArgumentException('Name for target connection can not be empty.');
        }

        $this->sourceConnectionName = $sourceConnectionName;
        $this->sourceTableName = $sourceTableName;
        $this->targetConnectionName = $targetConnectionName;
    }

    /**
     * @return string
     */
    public function getSourceConnectionName(): string
    {
        return $this->sourceConnectionName;
    }

    /**
     * @return string
     */
    public function getSourceTableName(): string
    {
        return $this->sourceTableName;
    }

    /**
     * @return string
     */
    public function getTargetConnectionName(): string
    {
        return $this->targetConnectionName;
    }

    /**
     * @param ConfigurationImportField $fieldConfiguration
     */
    public function addField(ConfigurationImportField $fieldConfiguration): void
    {
        $sourceName = $fieldConfiguration->getSourceName();

        if( $this->hasField($sourceName) ){
            throw new \RuntimeException('A field with name "' . $sourceName . '" already exist.');
        }

        $this->fields[$sourceName] = $fieldConfiguration;
    }

    /**
     * @param string $fieldSourceName
     * @return ConfigurationImportField
     */
    public function getField(string $fieldSourceName): ConfigurationImportField
    {
        if( $this->hasField($fieldSourceName) ){
            return $this->fields[$fieldSourceName];
        }

        throw new \InvalidArgumentException('Field ' . $fieldSourceName . ' does not exist.');
    }

    /**
     * @return ConfigurationImportField[]
     */
    public function getFields(): array
    {
        return array_values($this->fields);
    }

    /**
     * @param string $fieldSourceName
     * @return bool
     */
    public function hasField(string $fieldSourceName): bool
    {
        return isset($this->fields[$fieldSourceName]);
    }

    /**
     * @param string $fieldSourceName
     */
    public function removeField(string $fieldSourceName): void
    {
        if( $this->hasField($fieldSourceName) ){
            unset($this->fields[$fieldSourceName]);
        }
    }

    public function removeAllField(): void
    {
        $this->fields = [];
    }

    /**
     * @param ConfigurationImportFieldCalculated $fieldCalculatedConfiguration
     */
    public function addFieldCalculated(ConfigurationImportFieldCalculated $fieldCalculatedConfiguration): void
    {
        $targetName = $fieldCalculatedConfiguration->getTargetName();

        if( $this->hasFieldCalculated($targetName) ){
            throw new \RuntimeException('A field calculated with name "' . $targetName . '" already exist.');
        }

        $this->fieldsCalculated[$targetName] = $fieldCalculatedConfiguration;
    }

    /**
     * @param string $fieldName
     * @return ConfigurationImportFieldCalculated|null
     */
    public function getFieldCalculated(string $fieldName): ?ConfigurationImportFieldCalculated
    {
        if( $this->hasFieldCalculated($fieldName) ){
            return $this->fieldsCalculated[$fieldName];
        }

        return null;
    }

    /**
     * @return ConfigurationImportFieldCalculated[]
     */
    public function getFieldsCalculated(): array
    {
        return array_values($this->fieldsCalculated);
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    public function hasFieldCalculated(string $fieldName): bool
    {
        return isset($this->fieldsCalculated[$fieldName]);
    }

    /**
     * @param string $fieldName
     */
    public function removeFieldCalculated(string $fieldName): void
    {
        if( $this->hasFieldCalculated($fieldName) ){
            unset($this->fieldsCalculated[$fieldName]);
        }
    }

    public function removeAllFieldCalculated(): void
    {
        $this->fieldsCalculated = [];
    }

    /**
     * @param ConfigurationImportIndex $indexConfiguration
     */
    public function addIndex(ConfigurationImportIndex $indexConfiguration): void
    {
        $sourceName = $indexConfiguration->getName();

        if( $this->hasIndex($sourceName) ){
            throw new \RuntimeException('An index with name "' . $sourceName . '" already exist.');
        }

        $this->indexes[$sourceName] = $indexConfiguration;
    }

    /**
     * @param string $indexSourceName
     * @return ConfigurationImportIndex|null
     */
    public function getIndex(string $indexSourceName): ?ConfigurationImportIndex
    {
        if( $this->hasIndex($indexSourceName) ){
            return $this->indexes[$indexSourceName];
        }

        return null;
    }

    /**
     * @return ConfigurationImportIndex[]
     */
    public function getIndexes(): array
    {
        return array_values($this->indexes);
    }

    /**
     * @param string $indexSourceName
     * @return bool
     */
    public function hasIndex(string $indexSourceName): bool
    {
        return isset($this->indexes[$indexSourceName]);
    }

    /**
     * @param string $indexSourceName
     */
    public function removeIndex(string $indexSourceName): void
    {
        if( $this->hasIndex($indexSourceName) ){
            unset($this->indexes[$indexSourceName]);
        }
    }

    public function removeAllIndex(): void
    {
        $this->indexes = [];
    }

    /**
     * @return string|null
     */
    public function getCollation(): ?string
    {
        return $this->collation;
    }

    /**
     * @param string|null $collation
     */
    public function setCollation(?string $collation): void
    {
        $this->collation = $collation;
    }

    /**
     * @return string|null
     */
    public function getEngine(): ?string
    {
        return $this->engine;
    }

    /**
     * @param string|null $engine
     */
    public function setEngine(?string $engine): void
    {
        $this->engine = $engine;
    }

    /**
     * @return bool
     */
    public function isEraseExisting(): bool
    {
        return $this->eraseExisting;
    }

    /**
     * @param bool $eraseExisting
     */
    public function setEraseExisting(bool $eraseExisting): void
    {
        $this->eraseExisting = $eraseExisting;
    }

    /**
     * @return string|null
     */
    public function getSqlCondition(): ?string
    {
        return $this->sqlCondition;
    }

    /**
     * @param string|null $sqlCondition
     */
    public function setSqlCondition(?string $sqlCondition): void
    {
        $this->sqlCondition = $sqlCondition;
    }

    /**
     * @return string|null
     */
    public function getTableName(): ?string
    {
        return $this->tableName;
    }

    /**
     * @param string|null $tableName
     */
    public function setTableName(?string $tableName): void
    {
        $this->tableName = $tableName;
    }

    /**
     * Return all target fields (normal field and calculated field)
     * Duplicate target name verification is perform at same time
     * @return string[]
     */
    public function getFieldsTargetName(): array
    {
        $listField = [];
        foreach ($this->getFields() as $Field){
            $listField[$Field->getTargetName()] = true;
        }
        foreach($this->getFieldsCalculated() as $FieldCalculated){
            $fieldNameTarget = $FieldCalculated->getTargetName();
            if( isset($listField[$fieldNameTarget]) ){
                throw new \LogicException('Calculated field name define duplicate name with normal field!');
            }
            $listField[$fieldNameTarget] = true;
        }
        return array_keys($listField);
    }

    /**
     * Return **current** representation of fields based upon target name field
     * Unknown fields are set to null
     * @param array $targetNames
     * @return AbstractConfigurationImportField[] Key contain target name
     */
    public function getFieldsFromTargetName(array $targetNames): array
    {
        // Note : methodology below was done for optimization purpose

        // initialization
        $toReturn = [];
        foreach($targetNames as $targetName){
            $toReturn[$targetName] = null;
        }

        foreach ($this->getFields() as $Field){
            $targetName = $Field->getTargetName();
            if( \array_key_exists($targetName, $toReturn) ){
                $toReturn[$targetName] = $Field;
            }
        }

        foreach($this->getFieldsCalculated() as $FieldCalculated) {
            $targetName = $FieldCalculated->getTargetName();
            if( \array_key_exists($targetName, $toReturn) ){
                if( null !== $toReturn[$targetName] ){
                    throw new \LogicException('Field "' . $targetName . '" was define twice inside normal field and calculated field.');
                }
                $toReturn[$targetName] = $FieldCalculated;
            }
        }

        return $toReturn;
    }

    /**
     * @param string ...$targetNameField
     */
    public function setOrderTargetFields(string ...$targetNameField): void
    {
        $this->orderTargetFields = $targetNameField;
    }

    /**
     * @return string[]
     */
    public function getOrderTargetFields(): array
    {
        if( null === $this->orderTargetFields ){
            return $this->getFieldsTargetName();
        }

        return $this->orderTargetFields;
    }

    public function resetOrderTargetFields(): void
    {
        $this->orderTargetFields = null;
    }

    /**
     * @return string|null
     */
    public function getSchemaSource(): ?string
    {
        return $this->schemaSource;
    }

    /**
     * @param string|null $schemaSource
     */
    public function setSchemaSource(?string $schemaSource): void
    {
        $this->schemaSource = $schemaSource;
    }

    /**
     * @return string|null
     */
    public function getSchemaTarget(): ?string
    {
        return $this->schemaTarget;
    }

    /**
     * @param string|null $schemaTarget
     */
    public function setSchemaTarget(?string $schemaTarget): void
    {
        $this->schemaTarget = $schemaTarget;
    }

    /**
     * @return callable|null
     */
    public function getCallbackBefore(): ?callable
    {
        return $this->callbackBefore;
    }

    /**
     * @param callable|null $callbackBefore
     */
    public function setCallbackBefore(?callable $callbackBefore): void
    {
        $this->callbackBefore = $callbackBefore;
    }

    /**
     * @return callable|null
     */
    public function getCallbackAfter(): ?callable
    {
        return $this->callbackAfter;
    }

    /**
     * @param callable|null $callbackAfter
     */
    public function setCallbackAfter(?callable $callbackAfter): void
    {
        $this->callbackAfter = $callbackAfter;
    }

    /**
     * @return string|null
     */
    public function getFormattingDelimiter(): string
    {
        if( null === $this->formattingDelimiter ){
            return ',';
        }
        return $this->formattingDelimiter;
    }

    /**
     * Define delimiter to use inside temporary file
     * @param string|null $character
     */
    public function setFormattingDelimiter(?string $character = null): void
    {
        $this->formattingDelimiter = $character;
    }

    /**
     * @return string|null
     */
    public function getFormattingEnclosure(): string
    {
        if( null === $this->formattingEnclosure ){
            return '"';
        }
        return $this->formattingEnclosure;
    }

    /**
     * Define field enclosure to use inside temporary file
     * @param string $character
     */
    public function setFormattingEnclosure(?string $character = null): void
    {
        $this->formattingEnclosure = $character;
    }

    /**
     * @return string|null
     */
    public function getFormattingEscapeChar(): string
    {
        if( null === $this->formattingEscapeChar ){
            return '\\';
        }
        return $this->formattingEscapeChar;
    }

    /**
     * Define field escape char to use inside temporary file
     * @param string|null $character
     */
    public function setFormattingEscapeChar(?string $character = null): void
    {
        $this->formattingEscapeChar = $character;
    }

    /**
     * @return string|null
     */
    public function getDuplicateStrategy(): ?string
    {
        return $this->duplicateStrategy;
    }

    /**
     * @param string|null $duplicateStrategy
     */
    public function setDuplicateStrategy(?string $duplicateStrategy): void
    {
        $this->duplicateStrategy = $duplicateStrategy;
    }

    /**
     * @return bool
     */
    public function isDisableKeys(): bool
    {
        return $this->disableKeys;
    }

    /**
     * @param bool $disableKeys
     */
    public function setDisableKeys(bool $disableKeys): void
    {
        $this->disableKeys = $disableKeys;
    }

    /**
     * @return callable|null
     */
    public function getCallbackLineValidation(): ?callable
    {
        return $this->callbackLineValidation;
    }

    /**
     * Define a callback to validate a line based on datas line
     * Callback must return true if data is valid or false if data must be avoid
     * @param callable|null $callbackLineValidation
     *      Callback will receive all datas from current line
     */
    public function setCallbackLineValidation(?callable $callbackLineValidation): void
    {
        $this->callbackLineValidation = $callbackLineValidation;
    }

    /**
     * @return callable|null
     */
    public function getCallbackLineCleaning(): ?callable
    {
        return $this->callbackLineCleaning;
    }

    /**
     * Define a callback to change some value, must be used if some values have dependency
     * Callback must return null if there is no data to change or an array wich contain field to change
     * @param callable|null $callbackLineCleaning
     *      Callback will receive all datas from current line
     */
    public function setCallbackLineCleaning(?callable $callbackLineCleaning): void
    {
        $this->callbackLineCleaning = $callbackLineCleaning;
    }
}