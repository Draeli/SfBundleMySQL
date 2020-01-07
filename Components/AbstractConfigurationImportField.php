<?php
declare(strict_types=1);

namespace Draeli\Mysql\Components;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Schema\AbstractAsset;

/**
 * @package Draeli\Mysql\Components
 */
abstract class AbstractConfigurationImportField
{
    /**
     * @var string
     */
    private $targetName;

    /**
     * @var string
     */
    private $type;

    /**
     * @var bool
     */
    private $nullable;

    /**
     * @var bool|null
     */
    private $signed;

    /**
     * @var int|null
     */
    private $length;

    /**
     * @var mixed
     */
    private $default;

    /**
     * @var AbstractAsset
     */
    private $assetTarget;

    /**
     * @param string $type
     * @param bool $nullable
     */
    public function __construct(string $type, bool $nullable)
    {
        if( '' === $type ){
            throw new \InvalidArgumentException('Type can not be empty.');
        }

        $this->type = $type;
        $this->nullable = $nullable;
    }


    /**
     * @return string
     */
    public function getTargetName(): string
    {
        return $this->targetName;
    }

    /**
     * @param string $targetName
     */
    public function setTargetName(string $targetName): void
    {
        if( '' === $targetName ){
            throw new \InvalidArgumentException('Field target name can not be empty.');
        }

        $this->targetName = $targetName;
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
        if( null !== $length && $length <= 0 ){
            throw new \InvalidArgumentException('Length must be a positive value or null.');
        }

        $this->length = $length;
    }

    /**
     * @return mixed
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * Define null to be sure to have a propre default value based on type
     * @param mixed $default
     */
    public function setDefault($default): void
    {
        $this->default = $default;
    }

    /**
     * @return AbstractAsset
     */
    public function getTargetAsset(): AbstractAsset
    {
        if( null === $this->assetTarget ){
            $options = $this->getOptionsAsset();
            $this->assetTarget = new Column($this->getTargetName(), Type::getType($this->getType()), $options);
        }

        return $this->assetTarget;
    }

    /**
     * @return array
     */
    protected function getOptionsAsset(): array
    {
        $default = $this->getDefault();
        if( null !== $default ){
            $default = (string)$default;
        }

        $options = [
            'default' => $default
            , 'notnull' => !$this->isNullable()
        ];

        $length = $this->getLength();
        if( null !== $length ){
            $options['length'] = $length;
        }

        $signed = $this->getSigned();
        if( null !== $signed ){
            $options['unsigned'] = !$signed;
        }

        return $options;
    }
}