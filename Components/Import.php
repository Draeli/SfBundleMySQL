<?php
declare(strict_types=1);

namespace Draeli\Mysql\Components;

use Draeli\Mysql\Utils;

/**
 * @package Draeli\Mysql\Components
 */
class Import
{
    /**
     * @var ConfigurationImport
     */
    private $configurationImport;

    /**
     * @var ConfigurationTable
     */
    private $configurationTable;

    /**
     * @var string|null
     */
    private $tempFile;

    /**
     * @var int
     */
    private $lineCreated = 0;

    /**
     * @var int
     */
    private $lineInserted = 0;

    /**
     * @var string
     */
    private $sqlSelectSource;

    /**
     * @param ConfigurationImport $configurationImport
     * @param ConfigurationTable $configurationTable
     */
    public function __construct(ConfigurationImport $configurationImport, ConfigurationTable $configurationTable)
    {
        $this->configurationImport = $configurationImport;
        $this->configurationTable = $configurationTable;
    }

    /**
     * @return ConfigurationImport
     */
    public function getConfigurationImport(): ConfigurationImport
    {
        return $this->configurationImport;
    }

    /**
     * @return ConfigurationTable
     */
    public function getConfigurationTable(): ConfigurationTable
    {
        return $this->configurationTable;
    }

    /**
     * @param string|null $fileTempFile
     */
    public function setTempFile(?string $fileTempFile): void
    {
        $this->tempFile = $fileTempFile;
    }

    /**
     * @return string|null
     */
    public function getTempFile(): ?string
    {
        return $this->tempFile;
    }

    /**
     * Return complete reference name to the table use as target for import
     * @return string
     */
    public function getTargetTableNameReference(): string
    {
        $contigurationTable = $this->getConfigurationTable();
        $tableName = $contigurationTable->getTableName();
        $schemaName = $contigurationTable->getSchema();
        return Utils::getJoinSchemaAndTable($tableName, $schemaName);
    }

    /**
     * Number of lines created inside temp file
     * @return int
     */
    public function getLineCreated(): int
    {
        return $this->lineCreated;
    }

    /**
     * @param int $lineCreated
     */
    public function setLineCreated(int $lineCreated): void
    {
        $this->lineCreated = $lineCreated;
    }

    /**
     * Number of lines inserted during LOAD DATA
     * @return int
     */
    public function getLineInserted(): int
    {
        return $this->lineInserted;
    }

    /**
     * @param int $lineInserted
     */
    public function setLineInserted(int $lineInserted): void
    {
        $this->lineInserted = $lineInserted;
    }

    /**
     * @return string
     */
    public function getSqlSelectSource(): string
    {
        return $this->sqlSelectSource;
    }

    /**
     * @param string $sqlSelectSource
     */
    public function setSelectSqlSource(string $sqlSelectSource): void
    {
        $this->sqlSelectSource = $sqlSelectSource;
    }
}