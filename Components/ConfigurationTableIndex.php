<?php
declare(strict_types=1);

namespace Draeli\Mysql\Components;

/**
 * @package Draeli\Mysql\Components
 */
class ConfigurationTableIndex
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string[]
     */
    private $fieldsName = [];

    /**
     * @param string $name
     * @param string $type
     */
    public function __construct(string $name, string $type)
    {
        if( '' === $name ){
            throw new \InvalidArgumentException('Name can not be empty.');
        }

        if( '' === $type ){
            throw new \InvalidArgumentException('Type can not be empty.');
        }

        $this->name = $name;
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array Key contain field name and value length
     */
    public function getFields(): array
    {
        return $this->fieldsName;
    }

    /**
     * @param string $fieldName
     * @param int $fieldLength
     */
    public function addFieldName(string $fieldName, int $fieldLength): void
    {
        if( $this->hasFieldName($fieldName) ){
            throw new \InvalidArgumentException('Index contain already this field.');
        }
        $this->fieldsName[$fieldName] = $fieldLength;
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    public function hasFieldName(string $fieldName): bool
    {
        return isset($this->fieldsName[$fieldName]);
    }

    /**
     * @param string $fieldName
     */
    public function removeFieldName(string $fieldName): void
    {
        if( $this->hasFieldName($fieldName) ){
            unset($this->fieldsName[$fieldName]);
        }
    }

    /**
     * @return string[]
     */
    public function getFieldsName(): array
    {
        return array_keys($this->fieldsName);
    }

    public function removeAllFieldsName(): void
    {
        $this->fieldsName = [];
    }
}