<?php
declare(strict_types=1);

namespace Draeli\Mysql;

/**
 * @package Draeli\Mysql
 */
class Constants
{
    /*
     * Supported types
     */
    public const TYPE_INTEGER = 'integer';
    public const TYPE_STRING = 'string';
    public const TYPE_TEXT = 'text';
    public const TYPE_BLOB = 'blob';
    public const TYPE_FLOAT = 'float';
    public const TYPE_DATE = 'date';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_TIME = 'time';
    public const TYPE_BOOLEAN = 'boolean';

    public const INDEX_UNIQUE = 'unique';
    public const INDEX_NORMAL = 'index';
    public const INDEX_PRIMARY = 'primary';
    public const INDEX_FULLTEXT = 'fulltext';

    // Based on this answer : https://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci#answer-766996
    public const DEFAULT_COLLATION = 'utf8_unicode_ci';
}