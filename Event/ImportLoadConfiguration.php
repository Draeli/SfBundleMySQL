<?php
declare(strict_types=1);

namespace Draeli\Mysql\Event;

use Symfony\Contracts\EventDispatcher\Event;

use Draeli\Mysql\Components\ConfigurationImport as ComponentConfigurationImport;

/**
 * @package Draeli\Mysql\Event
 */
class ImportLoadConfiguration extends Event
{
    /**
     * @var ComponentConfigurationImport
     */
    private $configurationImport;

    /**
     * @param ComponentConfigurationImport $configurationImport
     */
    public function __construct(ComponentConfigurationImport $configurationImport)
    {
        $this->configurationImport = $configurationImport;
    }

    /**
     * @return ComponentConfigurationImport
     */
    public function getConfigurationImport(): ComponentConfigurationImport
    {
        return $this->configurationImport;
    }


}