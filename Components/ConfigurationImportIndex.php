<?php
declare(strict_types=1);

namespace Draeli\Mysql\Components;

/**
 * @package Draeli\Mysql\Components
 */
class ConfigurationImportIndex
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
    private $fields = [];

    /**
     * @param string $name
     * @param string $type
     */
    public function __construct(string $name, string $type)
    {
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
        return $this->fields;
    }

    /**
     * @param string $fieldNameAtTheOrigin
     * @param int $length
     */
    public function addField(string $fieldNameAtTheOrigin, int $length = 0): void
    {
        if( $this->hasField($fieldNameAtTheOrigin) ){
            throw new \LogicException('Field with origin name "' . $fieldNameAtTheOrigin . '" is already defined.');
        }

        $this->fields[$fieldNameAtTheOrigin] = $length;
    }

    /**
     * @param string $fieldNameAtTheOrigin
     * @return bool
     */
    public function hasField(string $fieldNameAtTheOrigin): bool
    {
        return isset($this->fields[$fieldNameAtTheOrigin]);
    }

    /**
     * @param string $fieldNameAtTheOrigin
     */
    public function removeField(string $fieldNameAtTheOrigin): void
    {
        if( $this->hasField($fieldNameAtTheOrigin) ){
            $this->fields[$fieldNameAtTheOrigin] = null;
        }
    }
}