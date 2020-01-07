<?php
declare(strict_types=1);

namespace Draeli\Mysql\Components;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Schema\AbstractAsset;

/**
 * @package Draeli\Mysql\Components
 */
class ConfigurationImportField extends AbstractConfigurationImportField
{
    /**
     * @var string
     */
    private $sourceName;

    /**
     * @var callable|null
     */
    private $callbackCleaning;

    /**
     * @var AbstractAsset
     */
    private $assetSource;

    /**
     * @param string $sourceName
     * @param string $type
     * @param bool $nullable
     */
    public function __construct(string $type, bool $nullable, string $sourceName)
    {
        if( '' === $sourceName ){
            throw new \InvalidArgumentException('Name for source field can not be empty.');
        }

        $this->sourceName = $sourceName;

        parent::__construct($type, $nullable);
    }

    /**
     * @return string
     */
    public function getSourceName(): string
    {
        return $this->sourceName;
    }

    /**
     * Get callback to call for apply a treatment on field result before import
     * @return callable|null
     */
    public function getCallbackCleaning(): ?callable
    {
        return $this->callbackCleaning;
    }

    /**
     * Callback to call for apply a treatment on field result before import
     * @param callable|null $callbackCleaning
     */
    public function setCallbackCleaning(?callable $callbackCleaning): void
    {
        $this->callbackCleaning = $callbackCleaning;
    }

    /**
     * @return AbstractAsset
     */
    public function getSourceAsset(): AbstractAsset
    {
        if( null === $this->assetSource ){
            $options = $this->getOptionsAsset();
            $this->assetSource = new Column($this->getSourceName(), Type::getType($this->getType()), $options);
        }

        return $this->assetSource;
    }
}