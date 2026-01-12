<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    private string $rootNodeName;

    public function __construct(string $rootNodeName)
    {
        $this->rootNodeName = $rootNodeName;
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder($this->rootNodeName);
        $rootNode = $treeBuilder->getRootNode();

        $this->addStoragePath($rootNode);
        $this->addMigrationsPath($rootNode);
        $this->addPhpBinaryPath($rootNode);

        return $treeBuilder;
    }

    private function addStoragePath(ArrayNodeDefinition $nodeDefinition): void
    {
        $nodeDefinition
            ->children()
                ->scalarNode('storage_path')
                    ->info('Configure storage path for export mechanism')
                    ->defaultValue('public/var/site/storage')
                ->end()
            ?->end();
    }

    private function addMigrationsPath(ArrayNodeDefinition $nodeDefinition): void
    {
        $nodeDefinition
            ->children()
            ->scalarNode('migrations_path')
            ->info('Configure path where migrations will be saved')
            ->defaultValue('var/cache/migrations')
            ->end()
            ?->end();
    }

    private function addPhpBinaryPath(ArrayNodeDefinition $nodeDefinition): void
    {
        $nodeDefinition
            ->children()
                ->scalarNode('php_binary_path')
                    ->info('Configure the PHP binary path used to run console commands (leave null to auto-detect)')
                    ->defaultNull()
                ->end()
            ?->end();
    }
}
