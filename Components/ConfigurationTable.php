<?php
declare(strict_types=1);

namespace Draeli\Mysql\Components;

/**
 * @package Draeli\Mysql\Components
 */
class ConfigurationTable
{
    /**
     * @var string
     */
    private $tableName;

    /**
     * @var string
     */
    private $collation;

    /**
     * @var string
     */
    private $engine;

    /**
     * @var ConfigurationTableField[]
     */
    private $fields = [];

    /**
     * @var ConfigurationTableIndex[]
     */
    private $indexes = [];

    /**
     * @var string|null
     */
    private $schema;

    /**
     * @param string $tableName
     * @param string $collation
     * @param string $engine
     */
    public function __construct(string $tableName, string $collation, string $engine)
    {
        $this->tableName = $tableName;
        $this->collation = $collation;
        $this->engine = $engine;
    }

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * @return string
     */
    public function getCollation(): string
    {
        return $this->collation;
    }

    /**
     * @return string
     */
    public function getEngine(): string
    {
        return $this->engine;
    }

    /**
     * @param ConfigurationTableField $configurationTableField
     */
    public function addField(ConfigurationTableField $configurationTableField): void
    {
        $name = $configurationTableField->getName();
        if( $this->hasField($name) ){
            throw new \InvalidArgumentException('A field with name "' . $name . '" already exist.');
        }

        $this->fields[$name] =  $configurationTableField;
    }

    /**
     * @param string $fieldName
     * @return ConfigurationTableField
     */
    public function getField(string $fieldName): ConfigurationTableField
    {
        if( !$this->hasField($fieldName) ){
            throw new \LogicException('Field with name "' . $fieldName . '" does not exist.');
        }
        return $this->fields[$fieldName];
    }

    /**
     * @return ConfigurationTableField[]
     */
    public function getFields(): array
    {
        return array_values($this->fields);
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    public function hasField(string $fieldName): bool
    {
        return isset($this->fields[$fieldName]);
    }

    /**
     * @param string $fieldName
     */
    public function removeField(string $fieldName): void
    {
        if( $this->hasField($fieldName) ){
            unset($this->fields[$fieldName]);
        }
    }

    public function removeAllFields(): void
    {
        $this->fields = [];
    }

    /**
     * @param ConfigurationTableIndex $configurationTableIndex
     */
    public function addIndex(ConfigurationTableIndex $configurationTableIndex): void
    {
        $name = $configurationTableIndex->getName();
        if( $this->hasIndex($name) ){
            throw new \InvalidArgumentException('An index with name "' . $name . '" already exist.');
        }

        $this->indexes[$name] =  $configurationTableIndex;
    }

    /**
     * @param string $indexName
     * @return ConfigurationTableIndex
     */
    public function getIndex(string $indexName): ConfigurationTableIndex
    {
        if( !$this->hasIndex($indexName) ){
            throw new \LogicException('Index with name "' . $indexName . '" does not exist.');
        }
        return $this->indexes[$indexName];
    }

    /**
     * @return ConfigurationTableIndex[]
     */
    public function getIndexes(): array
    {
        return array_values($this->indexes);
    }

    /**
     * @param string $indexName
     * @return bool
     */
    public function hasIndex(string $indexName): bool
    {
        return isset($this->indexes[$indexName]);
    }

    /**
     * @param string $indexName
     */
    public function removeIndex(string $indexName): void
    {
        if( $this->hasIndex($indexName) ){
            unset($this->indexes[$indexName]);
        }
    }

    public function removeAllIndexes(): void
    {
        $this->indexes = [];
    }

    /**
     * @return string|null
     */
    public function getSchema(): ?string
    {
        return $this->schema;
    }

    /**
     * @param string|null $schema
     */
    public function setSchema(?string $schema): void
    {
        $this->schema = $schema;
    }
}