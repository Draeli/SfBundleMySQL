# Bundle Import

Permet un import rapide d'une table à une autre entre une même base de données ou différentes base de données.

__Pré-requis__ :  
- Symfony 4.4 ou supérieur  
- Php 7.2 ou supérieur  
- Composer  

## Installation

Vous devez utiliser Composer pour faire l'installation (cf. https://getcomposer.org/doc/00-intro.md)

### Étape 1
Télécharger et installer le composant via la commande suivante :
```console
$ composer require "draeli/import-to-Mysql-bundle"
```

### Étape 2
Si vous utilisez Symfony Flex nous n'avez rien d'autre à faire.
Sinon vous devez ouvrir le fichier `config/bundles.php` et ajouter la ligne suivante à la fin du tableau de configuration : 
```php
Draeli\Mysql\DraeliMysqlBundle::class => ['all' => true],
```