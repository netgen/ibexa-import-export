<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle;

use Netgen\IbexaImportExportBundle\DependencyInjection\Compiler\FieldTypeHandlerRegistrationPass;
use Netgen\IbexaImportExportBundle\DependencyInjection\Security\PolicyProvider\ImportExportPolicyProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class NetgenIbexaImportExportBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new FieldTypeHandlerRegistrationPass());

        /** @var \Ibexa\Bundle\Core\DependencyInjection\IbexaCoreExtension $ibexaCoreExtension */
        $ibexaCoreExtension = $container->getExtension('ibexa');
        $ibexaCoreExtension->addPolicyProvider(new ImportExportPolicyProvider());
    }
}
