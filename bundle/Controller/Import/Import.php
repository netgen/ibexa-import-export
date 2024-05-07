<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\Controller\Import;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\LocationService;
use Netgen\IbexaImportExportBundle\Form\ImportType;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

use function date;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function sprintf;

class Import extends AbstractController
{
    private const MIGRATIONS_DIRECTORY = 'var/cache/migrations/';

    public function __construct(
        private readonly ContentService $contentService,
        private readonly LocationService $locationService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $phpFinder = new PhpExecutableFinder();

        $phpPath = $phpFinder->find();

        if ($phpPath === false) {
            throw new RuntimeException('The php executable could not be found. It is needed for executing parallel subprocesses, so add it to your PATH environment variable and try again.');
        }

        if (!is_dir('../' . self::MIGRATIONS_DIRECTORY)) {
            mkdir('../' . self::MIGRATIONS_DIRECTORY, 0777, true);
        }

        $form = $this->createForm(ImportType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $form->get('package')->getData();
            $parentLocationId = $form->get('parent_location')->getData();

            $yamlParsed = Yaml::parseFile($uploadedFile->getRealPath());
            $importMode = $yamlParsed[0]['mode'];

            if ($importMode === 'create') {
                $yamlParsed[0]['parent_location'] = $this->locationService->loadLocation($parentLocationId)->remoteId;
                foreach ($yamlParsed as $key => $content) {
                    $contentRemoteId = $content['remote_id'];
                    $locationRemoteId = $content['location_remote_id'];

                    try {
                        $this->contentService->loadContentByRemoteId($contentRemoteId);
                        $this->locationService->loadLocationByRemoteId($locationRemoteId);
                        unset($yamlParsed[$key]);
                    } catch (NotFoundException) {
                    }
                }
            } else {
                foreach ($yamlParsed as $key => $content) {
                    $contentRemoteId = $content['remote_id'];
                    $locationRemoteId = $content['location_remote_id'];

                    try {
                        $this->contentService->loadContentByRemoteId($contentRemoteId);
                        $this->locationService->loadLocationByRemoteId($locationRemoteId);
                    } catch (NotFoundException) {
                        unset($content[$key]);
                    }
                }
            }
            $yaml = Yaml::dump($yamlParsed);
            $projectRoot = $this->getParameter('kernel.project_dir');
            $randomTimeComponent = date('YmdHis'); // Current date and time in format: YearMonthDay_HourMinuteSecond
            $newFilePath = $projectRoot . '/' . $this::MIGRATIONS_DIRECTORY . $randomTimeComponent . $uploadedFile->getClientOriginalName();
            file_put_contents($newFilePath, $yaml);

            $process = new Process(['../bin/console', 'kaliop:migration:migrate', '--path=' . $newFilePath]);
            $additionalAnswers = "Y\n";
            $process->setInput($additionalAnswers);
            $process->run();

            if (!$process->isSuccessful()) {
                $error = sprintf(
                    'The command "%s" failed. Exit Code: %s(%s) Working directory: %s',
                    $process->getCommandLine(),
                    $process->getExitCode(),
                    $process->getExitCodeText(),
                    $process->getWorkingDirectory(),
                );
                $this->addFlash('error', $error);
            }

            $message = $process->getOutput();
            $this->addFlash('success', $message);

            return $this->redirectToRoute('netgen_import_export.route.admin.import');
        }

        return $this->render(
            'import.html.twig',
            [
                'form' => $form->createView(),
            ],
        );
    }
}
