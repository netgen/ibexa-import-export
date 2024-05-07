<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class Index extends AbstractController
{
    public function __invoke(Request $request): Response
    {
        return $this->render('index.html.twig');
    }
}
