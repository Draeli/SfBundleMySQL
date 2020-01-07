<?php
declare(strict_types=1);

namespace Draeli\Mysql\Components;

/**
 * @package Draeli\Mysql\Components
 */
class ConfigurationImportFieldCalculated extends AbstractConfigurationImportField
{
    /**
     * @var callable
     */
    private $callbackCalcul;

    /**
     * @param string $type
     * @param bool $nullable
     * @param callable $callbackCalcul
     */
    public function __construct(string $type, bool $nullable, callable $callbackCalcul)
    {
        $this->callbackCalcul = $callbackCalcul;

        parent::__construct($type, $nullable);
    }

    /**
     * @return callable
     */
    public function getCallbackCalcul(): callable
    {
        return $this->callbackCalcul;
    }
}