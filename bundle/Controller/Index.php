<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class Index extends AbstractController
{
    public function __invoke()
    {
        return $this->render('@NetgenIbexaImportExport/index.html.twig');
    }
}
