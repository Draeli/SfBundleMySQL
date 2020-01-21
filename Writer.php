<?php
declare(strict_types=1);

namespace Draeli\Mysql;

use Sonata\Exporter\Writer\CsvWriter as BaseWriter;
use Sonata\Exporter\Writer\TypedWriterInterface;

use Draeli\Mysql\Components\AbstractConfigurationImportField;
use Draeli\Mysql\Components\ConfigurationImport;
use Draeli\Mysql\Components\ConfigurationImportField;
use Draeli\Mysql\Components\ConfigurationImportFieldCalculated;
use Draeli\Mysql\Utils;

/**
 * Import writer
 * @package Draeli\Mysql
 */
class Writer implements TypedWriterInterface
{
    /** @var ConfigurationImport */
    private $configurationImport;

    /** @var BaseWriter */
    private $typedWriter;

    /** @var int */
    private $lineCreated = 0;

    /** @var string[] */
    private $fieldsOrder;

    /** @var AbstractConfigurationImportField[] */
    private $fieldsTarget;

    /** @var callable|null */
    private $callbackLineCleaning;

    /** @var callable|null */
    private $callbackLineValidation;

    /**
     * @param ConfigurationImport $configurationImport
     * @param string $filename
     */
    public function __construct(
        ConfigurationImport $configurationImport
        , string $filename
    )
    {
        // clone to avoid object change after export preparation
        $this->configurationImport = clone $configurationImport;

        $delimiter = $this->configurationImport->getFormattingDelimiter();
        $enclosure = $this->configurationImport->getFormattingEnclosure();
        $escape = $this->configurationImport->getFormattingEscapeChar();
        $withBom = false;
        $terminate = "\n";
        $this->typedWriter = new BaseWriter($filename, $delimiter, $enclosure, $escape, false, , $terminate);

        // /!\ To avoid weird behaviour, calculated field must appear in last position
        // Contain columns name to use for first line of file witch will be create in write method
        $this->fieldsOrder = $this->configurationImport->getOrderTargetFields();

        $this->fieldsTarget = $configurationImport->getFieldsFromTargetName($this->fieldsOrder);

        // Prepare callback type conversion
        $this->fieldsConversionType = [];
        foreach ($this->fieldsOrder as $fieldNameTarget){
            $field = $this->fieldsTarget[$fieldNameTarget];
            $this->fieldsConversionType[$fieldNameTarget] = static function($data)use($field){
                return Utils::getDataConverted($data, $field);
            };
        }

        $this->callbackLineCleaning = $configurationImport->getCallbackLineCleaning();
        $this->callbackLineValidation = $configurationImport->getCallbackLineValidation();
    }

    /**
     * @param array $data
     */
    public function write(array $data): void
    {
        $result = [];
        // manage field result by order
        foreach($this->fieldsOrder as $fieldName){
            $field = $this->fieldsTarget[$fieldName];

            if( $field instanceof ConfigurationImportField ){
                $sourceName = $field->getSourceName();
                $value = $data[$sourceName];

                $callbackCleaning = $field->getCallbackCleaning();
                if( null !== $callbackCleaning ){
                    // to clean/manage value before his conversion
                    $value = $callbackCleaning($value);
                }
            }
            elseif( $field instanceof ConfigurationImportFieldCalculated ){
                $value = $field->getCallbackCalcul()($data);
            }
            else{
                throw new \UnexpectedValueException('Unsupported field type.');
            }

            // ensure value is converted according with field definition
            $value = $this->fieldsConversionType[$fieldName]($value);

            $result[$fieldName] = $value;
        }

        $callbackLineCleaning = $this->callbackLineCleaning;
        if( null !== $callbackLineCleaning ){
            // to adjust some fields for special case where dependency between fields is required
            $datasToChange = $callbackLineCleaning($result);
            if( !\is_array($datasToChange) && null !== $datasToChange ){
                throw new \InvalidArgumentException('Callback for "getCallbackLineCleaning" must return array or null.');
            }
            if( \is_array($datasToChange) ){
                $unknownColumns = array_diff_key($datasToChange, $result);
                if( \count($unknownColumns) ){
                    throw new \LogicException('Columns "' . implode(';', $unknownColumns) . '" are unknown.');
                }
                $result = array_merge($result, $datasToChange);
            }
        }

        $callbackLineValidation = $this->callbackLineValidation;
        // add line only if there is no callback or callback return true
        if( (null === $callbackLineValidation) || $callbackLineValidation($result) ){
            ++$this->lineCreated;
            foreach($result as &$v){
                if( null === $v ){
                    // https://dev.mysql.com/doc/refman/8.0/en/problems-with-null.html => "To load a NULL value into a column, use \N in the data file"
                    $v = '\N';
                }
            }
            unset($v);
            $this->typedWriter->write($result);
        }
    }

    public function getDefaultMimeType(): string
    {
        return $this->typedWriter->getDefaultMimeType();
    }

    public function getFormat(): string
    {
        return $this->typedWriter->getFormat();
    }

    public function getLinesCreated(): int
    {
        return $this->lineCreated;
    }

    public function open()
    {
        $this->typedWriter->open();

        $this->typedWriter->write($this->fieldsOrder);
    }

    public function close()
    {
        $this->typedWriter->close();
    }
}