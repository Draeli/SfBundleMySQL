<?php
declare(strict_types=1);

namespace Draeli\Mysql\Components;

/**
 * @package Draeli\Mysql\Components
 */
class ConfigurationTableField
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
     * @var bool
     */
    private $nullable;

    /**
     * @var int|null
     */
    private $length;

    /**
     * @var bool|null
     */
    private $signed;

    /**
     * @var mixed
     */
    private $default;

    /**
     * @param string $name
     * @param string $type
     * @param bool $nullable
     */
    public function __construct(string $name, string $type, bool $nullable)
    {
        if( '' === $name ){
            throw new \InvalidArgumentException('Name can not be empty.');
        }

        if( '' === $type ){
            throw new \InvalidArgumentException('Type can not be empty.');
        }

        $this->name = $name;
        $this->type = $type;
        $this->nullable = $nullable;
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
     * @return bool
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * @return mixed
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * @param mixed $default
     */
    public function setDefault($default): void
    {
        $this->default = $default;
    }

    /**
     * @return int|null
     */
    public function getLength(): ?int
    {
        return $this->length;
    }

    /**
     * @param int|null $length
     */
    public function setLength(?int $length): void
    {
        $this->length = $length;
    }

    /**
     * @return bool|null
     */
    public function getSigned(): ?bool
    {
        return $this->signed;
    }

    /**
     * @param bool|null $signed
     */
    public function setSigned(?bool $signed): void
    {
        $this->signed = $signed;
    }
}