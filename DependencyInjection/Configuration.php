<?php
declare(strict_types=1);

namespace Draeli\Mysql\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use Draeli\Mysql\Constants;

/**
 * @package Draeli\Mysql\DependencyInjection
 */
class Configuration implements ConfigurationInterface
{
    public const NAME_IMPORT = 'import';
    public const NAME_IMPORT_TEMP_FILE = 'temp_file';
    public const NAME_IMPORT_TEMP_FILE_DIRECTORY = 'directory';
    public const NAME_IMPORT_TEMP_FILE_PREFIX = 'prefix';
    public const NAME_IMPORT_TEMP_FILE_FORMATTING = 'formatting';
    public const NAME_IMPORT_TEMP_FILE_FORMATTING_DELIMITER = 'delimiter';
    public const NAME_IMPORT_TEMP_FILE_FORMATTING_ENCLOSURE = 'enclosure';
    public const NAME_IMPORT_TEMP_FILE_FORMATTING_ESCAPE_CHAR = 'escape_char';
    public const NAME_IMPORT_ALIAS = 'alias';
    public const NAME_IMPORT_ALIAS_TABLES = 'tables';
    public const NAME_IMPORT_TABLE_PREFIX = 'table_prefix';

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('draeli_mysql');
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode(self::NAME_IMPORT)
                    ->children()
                        ->arrayNode(self::NAME_IMPORT_TEMP_FILE)
                            ->children()
                                ->scalarNode(self::NAME_IMPORT_TEMP_FILE_DIRECTORY)
                                    ->isRequired()
                                    ->validate()
                                        ->ifTrue(static function($v){
                                            return DIRECTORY_SEPARATOR !== substr($v, -1, 1);
                                        })
                                        ->thenInvalid('Your OS need an end directory with ' . DIRECTORY_SEPARATOR)
                                    ->end()
                                ->end()
                                ->scalarNode(self::NAME_IMPORT_TEMP_FILE_PREFIX)
                                    ->isRequired()
                                    ->validate()
                                        ->ifTrue(static function($v){
                                            return !preg_match('/^[0-9a-z_]+$/iU', $v);
                                        })
                                        ->thenInvalid('Prefix for option "temp_file" can not be empty and must contain only alphanumeric and underscore')
                                    ->end()
                                ->end()
                                ->arrayNode(self::NAME_IMPORT_TEMP_FILE_FORMATTING)
                                    ->children()
                                        ->scalarNode(self::NAME_IMPORT_TEMP_FILE_FORMATTING_DELIMITER)
                                            ->validate()
                                                ->ifTrue(self::getCloseMax1Char())
                                                ->thenInvalid('Delimiter must contain 0 or 1 character.')
                                            ->end()
                                        ->end()
                                        ->scalarNode(self::NAME_IMPORT_TEMP_FILE_FORMATTING_ENCLOSURE)
                                            ->validate()
                                                ->ifTrue(self::getCloseMax1Char())
                                                ->thenInvalid('Enclosure must contain 0 or 1 character.')
                                            ->end()
                                        ->end()
                                        ->scalarNode(self::NAME_IMPORT_TEMP_FILE_FORMATTING_ESCAPE_CHAR)
                                            ->validate()
                                                ->ifTrue(self::getCloseMax1Char())
                                                ->thenInvalid('Escape char must contain 0 or 1 character.')
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode(self::NAME_IMPORT_ALIAS)
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()
                                    ->arrayNode(self::NAME_IMPORT_ALIAS_TABLES)
                                        ->useAttributeAsKey('name')
                                        ->arrayPrototype()
                                            ->children()
                                                ->arrayNode('fields')
                                                    // to prevent declaration of table without field
                                                    ->requiresAtLeastOneElement()
                                                    ->arrayPrototype()
                                                        ->children()
                                                            ->scalarNode('name')
                                                                ->isRequired()
                                                                ->cannotBeEmpty()
                                                                ->info('Column name on MySQL side')
                                                            ->end()
                                                            ->enumNode('type')
                                                                ->isRequired()
                                                                ->cannotBeEmpty()
                                                                ->values([Constants::TYPE_INTEGER, Constants::TYPE_STRING, Constants::TYPE_FLOAT, Constants::TYPE_DATE, Constants::TYPE_DATETIME, Constants::TYPE_TIME, Constants::TYPE_TEXT, Constants::TYPE_BLOB, Constants::TYPE_BOOLEAN])
                                                                ->info('- "' . Constants::TYPE_INTEGER . '" (BIGINT)
- "' . Constants::TYPE_STRING . '" (VARCHAR)
- "' . Constants::TYPE_FLOAT . '" (DOUBLE PRECISION)
- "' . Constants::TYPE_DATE . '" (DATE) Always import with UTC Timezone
- "' . Constants::TYPE_DATETIME . '" (DATETIME) Always import with UTC Timezone
- "' . Constants::TYPE_TIME . '" (TIME) Always import with UTC Timezone
- "' . Constants::TYPE_TEXT . '" (LONGTEXT)
- "' . Constants::TYPE_BLOB . '" (LONGBLOB)
- "' . Constants::TYPE_BOOLEAN . '" (TINYINT)')
                                                            ->end()
                                                            ->booleanNode('nullable')
                                                                ->isRequired()
                                                            ->end()
                                                            ->booleanNode('signed')
                                                                ->info('Option only for types "' . Constants::TYPE_INTEGER . '";"' . Constants::TYPE_FLOAT . '".')
                                                            ->end()
                                                            ->integerNode('length')
                                                                ->min(1)
                                                                ->max(255)
                                                                ->info('Option only for type "' . Constants::TYPE_STRING . '".')
                                                            ->end()
                                                            ->scalarNode('default')
                                                            ->end()
                                                        ->end()
                                                        ->validate()
                                                            ->always()
                                                            ->then(static function($v){
                                                                $name = $v['name'];
                                                                $type = $v['type'];
                                                                $signed = $v['signed'] ?? null;
                                                                $length = $v['length'] ?? null;

                                                                // check configuration dedicated to this type is right
                                                                if (\in_array($type, [Constants::TYPE_INTEGER, Constants::TYPE_FLOAT], true) ) {
                                                                    if( null === $signed ) {
                                                                        throw new \InvalidArgumentException('Field "' . $name . '" defined as "' . Constants::TYPE_INTEGER .'" or "' . Constants::TYPE_FLOAT . '", you must define "signed" configuration.');
                                                                    }
                                                                }
                                                                // check other type does not define too much configuration
                                                                else{
                                                                    if( null !== $signed ){
                                                                        throw new \InvalidArgumentException('Field "' . $name . '" define with configuration "signed" which is forbidden in that case, please remove it.');
                                                                    }
                                                                }

                                                                if( Constants::TYPE_STRING === $type) {
                                                                    if( null === $length ){
                                                                        throw new \InvalidArgumentException('Field "' . $name . '" defined as string, you must define "length" configuration.');
                                                                    }
                                                                }
                                                                else{
                                                                    if( null !== $length ){
                                                                        throw new \InvalidArgumentException('Field "' . $name . '" define with configuration "length" which is forbidden in that case, please remove it.');
                                                                    }
                                                                }

                                                                return $v;
                                                            })
                                                        ->end()
                                                    ->end()
                                                ->end()
                                                ->arrayNode('indexes')
                                                    ->arrayPrototype()
                                                        ->children()
                                                            ->arrayNode('fields')
                                                                ->isRequired()
                                                                ->info('Array of fields or string if there is only one field. To avoid "limit" index problem (aka "specified-key-was-too-long-max-key-length-is-1000-bytes", you can provide an array with field origin name and with length as value.')
                                                                ->beforeNormalization()
                                                                    ->always()
                                                                    ->then(static function($v){
                                                                        // If there is only one field for index given as string
                                                                        if( \is_string($v) ){
                                                                            $v = (array)$v;
                                                                        }
                                                                        if( \is_array($v) ) {
                                                                            // one or more index given as collection
                                                                            $tmp = [];
                                                                            foreach ($v as $k => $v2) {
                                                                                // short write to give field name as index and index length as value
                                                                                if (\is_string($k) && \is_int($v2)) {
                                                                                    $tmp[] = ['field' => $k, 'length' => $v2];
                                                                                }
                                                                                // collection…
                                                                                elseif( \is_int($k) ){
                                                                                    // value is only a string
                                                                                    if( \is_string($v2) ){
                                                                                        $tmp[] = ['field' => $v2];
                                                                                    }
                                                                                    elseif( \is_array($v2) ){
                                                                                        $tmp[] = $v2;
                                                                                    }
                                                                                }
                                                                            }
                                                                            $v = $tmp;
                                                                        }
                                                                        return $v;
                                                                    })
                                                                ->end()
                                                                ->arrayPrototype()
                                                                    ->children()
                                                                        ->scalarNode('field')
                                                                            ->isRequired()
                                                                        ->end()
                                                                        ->integerNode('length')
                                                                            ->min(0)
                                                                            ->defaultValue(0)
                                                                        ->end()
                                                                    ->end()
                                                                ->end()
                                                            ->end()
                                                            ->enumNode('type')
                                                                ->isRequired()
                                                                ->cannotBeEmpty()
                                                                ->values([Constants::INDEX_PRIMARY, Constants::INDEX_UNIQUE, Constants::INDEX_NORMAL, Constants::INDEX_FULLTEXT])
                                                            ->end()
                                                        ->end()
                                                    ->end()
                                                ->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->scalarNode(self::NAME_IMPORT_TABLE_PREFIX)
                            ->cannotBeEmpty()
                            ->defaultValue('_tmp_import_')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('default_connection_target')
                    ->defaultNull()
                ->end()
            ->end()
        ;
        return $treeBuilder;
    }

    /**
     * @return callable
     */
    public static function getCloseMax1Char(): callable
    {
        return static function($v){
            return \strlen($v) > 1;
        };
    }
}