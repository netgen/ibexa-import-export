<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class Index extends AbstractController
{
    public function __invoke(): Response
    {
        $this->denyAccessUnlessGranted('ibexa:import_export:access');

        return $this->render('@NetgenIbexaImportExport/index.html.twig');
    }
}
