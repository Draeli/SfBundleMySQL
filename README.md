# MySQL Bundle

Designed to simplify DBAL manipulation with MySQL in the KISS spirit.

__Prerequisites__ :  
- Symfony 4.4 or higher  
- Php 7.2 or higher  
- Composer  

## Installation

Use Composer to do installation (cf. https://getcomposer.org/doc/00-intro.md)

### Step 1
Download and install components with :
```console
$ composer require "draeli/mysql-bundle"
```

### Step 2
If you used Flex you don't need more manipulation \o/
If you don't used Flex, you need to open file  `config/bundles.php` and add the following line at the end of the configuration table : 
```php
Draeli\Mysql\DraeliMysqlBundle::class => ['all' => true],
```