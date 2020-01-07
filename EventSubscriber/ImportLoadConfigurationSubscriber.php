<?php
declare(strict_types=1);

namespace Draeli\Mysql\EventSubscriber;

use Symfony\Bridge\Doctrine\ManagerRegistry;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Draeli\Mysql\Event\ImportLoadConfiguration as EventImportLoadConfiguration;

use Draeli\Mysql\Service\Import;

/**
 * @package Draeli\Mysql\EventSubscriber
 */
class ImportLoadConfigurationSubscriber implements EventSubscriberInterface
{
    /**
     * @var ManagerRegistry
     */
    private $managerRegistry;

    /**
     * @var Import
     */
    private $serviceImport;

    /**
     * @param ManagerRegistry $managerRegistry
     * @param Import $serviceImport
     */
    public function __construct(ManagerRegistry $managerRegistry, Import $serviceImport)
    {
        $this->managerRegistry = $managerRegistry;
        $this->serviceImport = $serviceImport;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            EventImportLoadConfiguration::class => [
                ['completeFromConfiguration', 250]
                , ['checkConfiguration', 0]
            ]
        ];
    }

    /**
     * @param EventImportLoadConfiguration $event
     */
    public function completeFromConfiguration(EventImportLoadConfiguration $event): void
    {
        $configurationImport = $event->getConfigurationImport();
        $sourceConnectioName = $configurationImport->getSourceConnectionName();
        $sourceTableName = $configurationImport->getSourceTableName();
        $targetConnectioName = $configurationImport->getTargetConnectionName();

//        $sourceConnection = $this->managerRegistry->getConnection($sourceConnectioName);
        $tableConfiguration = $this->serviceImport->getConfigurationTable($sourceConnectioName, $sourceTableName);
        dd($tableConfiguration);
    }

    /**
     * @param EventImportLoadConfiguration $event
     */
    public function checkConfiguration(EventImportLoadConfiguration $event): void
    {

    }
}