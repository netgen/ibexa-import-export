<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class NetgenIbexaImportExportExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('monolog')) {
            return;
        }

        $container->prependExtensionConfig('monolog', [
            'channels' => ['netgen_ibexa_import_export'],
        ]);
    }

    public function getAlias(): string
    {
        return 'netgen_ibexa_import_export';
    }

    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        return new Configuration($this->getAlias());
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $locator = new FileLocator(__DIR__ . '/../Resources/config');

        $loader = new DelegatingLoader(
            new LoaderResolver(
                [
                    new GlobFileLoader($container, $locator),
                    new YamlFileLoader($container, $locator),
                ],
            ),
        );

        $loader->load('services/*.yaml', 'glob');
        $loader->load('default_settings.yaml');

        $this->processExtensionConfiguration($configs, $container);
    }

    private function processExtensionConfiguration(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration($configs, $container);

        $configuration = $this->processConfiguration($configuration, $configs);

        $this->processStoragePathConfiguration($configuration, $container);
        $this->processMigrationsPathConfiguration($configuration, $container);
        $this->processPhpBinaryPathConfiguration($configuration, $container);
    }

    private function processStoragePathConfiguration(array $configuration, ContainerBuilder $container): void
    {
        $container->setParameter(
            'netgen_ibexa_import_export.storage_path',
            $configuration['storage_path'],
        );
    }

    private function processMigrationsPathConfiguration(array $configuration, ContainerBuilder $container): void
    {
        $container->setParameter(
            'netgen_ibexa_import_export.migrations_path',
            $configuration['migrations_path'],
        );
    }

    private function processPhpBinaryPathConfiguration(array $configuration, ContainerBuilder $container): void
    {
        $container->setParameter(
            'netgen_ibexa_import_export.php_binary_path',
            $configuration['php_binary_path'],
        );
    }
}
