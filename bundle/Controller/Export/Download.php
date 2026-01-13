<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\Controller\Export;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function basename;
use function is_string;
use function realpath;
use function str_starts_with;

final class Download extends AbstractController
{
    public function __construct(
        private readonly string $migrationsPath,
    ) {}

    public function __invoke(Request $request, string $file): Response
    {
        $this->denyAccessUnlessGranted('ibexa:import_export:access');

        if (!is_string($file) || $file === '' || basename($file) !== $file) {
            throw new NotFoundHttpException();
        }

        $projectRoot = $this->container->getParameter('kernel.project_dir');

        $migrationsDir = realpath($projectRoot . '/' . $this->migrationsPath);
        $filePath = $projectRoot . '/' . $this->migrationsPath . '/' . $file;
        $realFilePath = realpath($filePath);

        if ($migrationsDir === false || $realFilePath === false || !str_starts_with($realFilePath, $migrationsDir)) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($filePath);
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file);

        return $response;
    }
}
