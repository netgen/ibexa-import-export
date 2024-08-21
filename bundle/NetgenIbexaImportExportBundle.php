<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle;

use Netgen\IbexaImportExportBundle\DependencyInjection\Compiler\FieldTypeHandlerRegistrationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class NetgenIbexaImportExportBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new FieldTypeHandlerRegistrationPass());
    }
}
