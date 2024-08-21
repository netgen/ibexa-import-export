<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\DependencyInjection\Compiler;

use LogicException;
use Netgen\IbexaImportExportBundle\Registry\Registry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function sprintf;

final class FieldTypeHandlerRegistrationPass implements CompilerPassInterface
{
    private string $handlerRegistryId = Registry::class;
    private string $handlerTag = 'ez_migration_bundle.complex_field';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has($this->handlerRegistryId)) {
            return;
        }

        $handlerRegistryDefinition = $container->getDefinition($this->handlerRegistryId);

        $handlers = $container->findTaggedServiceIds($this->handlerTag);

        foreach ($handlers as $id => $attributes) {
            $this->registerHandler($handlerRegistryDefinition, $id, $attributes);
        }
    }

    /**
     * @throws LogicException
     */
    private function registerHandler(Definition $handlerRegistryDefinition, string $id, array $attributes): void
    {
        foreach ($attributes as $attribute) {
            if (!isset($attribute['fieldtype'])) {
                throw new LogicException(
                    sprintf(
                        "'%s' service tag needs an 'fieldtype' attribute to identify the handler",
                        $this->handlerTag,
                    ),
                );
            }

            $handlerRegistryDefinition->addMethodCall(
                'register',
                [
                    $attribute['fieldtype'],
                    new Reference($id),
                ],
            );
        }
    }
}
