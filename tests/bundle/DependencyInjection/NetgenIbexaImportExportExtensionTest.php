<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\Tests\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Netgen\IbexaImportExportBundle\DependencyInjection\NetgenIbexaImportExportExtension;

final class NetgenIbexaImportExportExtensionTest extends AbstractExtensionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setParameter('kernel.bundles', []);
    }

    public static function provideDefaultConfigurationCases(): iterable
    {
        return [
            [[]],
            [[
                'storage_path' => 'public/var/site/storage',
                'migrations_path' => 'var/cache/migrations',
                'php_binary_path' => null,
            ]],
        ];
    }

    public static function provideStoragePathConfigurationCases(): iterable
    {
        return [
            [[
                'storage_path' => 'public/var/site/storage',
            ], 'public/var/site/storage'],
            [[
                'storage_path' => 'custom/storage',
            ], 'custom/storage'],
        ];
    }

    public static function provideMigrationsPathConfigurationCases(): iterable
    {
        return [
            [[
                'migrations_path' => 'var/cache/migrations',
            ], 'var/cache/migrations'],
            [[
                'migrations_path' => 'custom/migrations',
            ], 'custom/migrations'],
        ];
    }

    public static function providePhpBinaryPathConfigurationCases(): iterable
    {
        return [
            [[
                'php_binary_path' => null,
            ], null],
            [[
                'php_binary_path' => '/usr/bin/php',
            ], '/usr/bin/php'],
        ];
    }

    /**
     * @dataProvider provideDefaultConfigurationCases
     */
    public function testDefaultConfiguration(array $configuration): void
    {
        $this->load($configuration);

        $this->assertContainerBuilderHasParameter('netgen_ibexa_import_export.storage_path');
        $this->assertContainerBuilderHasParameter('netgen_ibexa_import_export.migrations_path');
        $this->assertContainerBuilderHasParameter('netgen_ibexa_import_export.php_binary_path');
    }

    /**
     * @dataProvider provideStoragePathConfigurationCases
     */
    public function testStoragePathConfiguration(array $configuration, string $expected): void
    {
        $this->load($configuration);

        $this->assertContainerBuilderHasParameter('netgen_ibexa_import_export.storage_path', $expected);
    }

    /**
     * @dataProvider provideMigrationsPathConfigurationCases
     */
    public function testMigrationsPathConfiguration(array $configuration, string $expected): void
    {
        $this->load($configuration);

        $this->assertContainerBuilderHasParameter('netgen_ibexa_import_export.migrations_path', $expected);
    }

    /**
     * @dataProvider providePhpBinaryPathConfigurationCases
     */
    public function testPhpBinaryPathConfiguration(array $configuration, ?string $expected): void
    {
        $this->load($configuration);

        $this->assertContainerBuilderHasParameter('netgen_ibexa_import_export.php_binary_path', $expected);
    }

    protected function getContainerExtensions(): array
    {
        return [
            new NetgenIbexaImportExportExtension(),
        ];
    }
}
