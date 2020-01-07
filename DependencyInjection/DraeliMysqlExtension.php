<?php
declare(strict_types=1);

namespace Draeli\Mysql\DependencyInjection;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Config\FileLocator;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Yaml\Yaml;

/**
 * @package Draeli\Mysql\DependencyInjection
 */
class DraeliMysqlExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('draeli_mysql.configuration', $config);
    }

    /**
     * {@inheritdoc}
     */
    public function prepend(ContainerBuilder $container)
    {
        $bundles = $container->getParameter('kernel.bundles');
        if( !isset($bundles['DoctrineBundle']) ){
            throw new \LogicException('DoctrineBundle must be registered.');
        }

//        // Prepend a default config
//        $default = Yaml::parseFile(__DIR__ . '/../Resources/config/default.yaml');
//        $container->prependExtensionConfig('draeli_mysql', $default);
    }
}