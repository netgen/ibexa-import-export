<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\Controller\Import;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\LocationService;
use Netgen\IbexaImportExportBundle\Form\ImportType;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\TranslatorInterface;

use function date;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function sprintf;

final class Import extends AbstractController
{
    private const TRANSLATION_DOMAIN = 'import_export';

    public function __construct(
        private readonly ContentService $contentService,
        private readonly LocationService $locationService,
        private readonly string $migrationsPath,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function __invoke(Request $request): Response
    {
        $phpFinder = new PhpExecutableFinder();

        $phpPath = $phpFinder->find();

        if ($phpPath === false) {
            throw new RuntimeException(
                $this->translator->trans('netgen.ibexa_import_export.error.php', domain: $this::TRANSLATION_DOMAIN),
            );
        }

        if (!is_dir('../' . $this->migrationsPath)) {
            mkdir('../' . $this->migrationsPath, 0777, true);
        }

        $form = $this->createForm(ImportType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $form->get('package')->getData();

            $yamlParsed = Yaml::parseFile($uploadedFile->getRealPath());
            $importMode = $yamlParsed[0]['mode'];

            if ($importMode === 'create') {
                $parentLocationId = $form->get('parent_location')->getData();

                try {
                    $parentLocation = $this->locationService->loadLocation((int) $parentLocationId);
                } catch (NotFoundException) {
                    $this->addFlash(
                        'error',
                        $this->translator->trans(
                            'netgen.ibexa_import_export.error.import.parent_location',
                            domain: $this::TRANSLATION_DOMAIN,
                        ),
                    );

                    return $this->render(
                        '@NetgenIbexaImportExport/import.html.twig',
                        [
                            'form' => $form->createView(),
                        ],
                    );
                }
                $yamlParsed[0]['parent_location'] = $parentLocation->remoteId;
                foreach ($yamlParsed as $key => $content) {
                    $contentRemoteId = $content['remote_id'];
                    $locationRemoteId = $content['location_remote_id'];

                    try {
                        $this->contentService->loadContentByRemoteId($contentRemoteId);
                        $this->locationService->loadLocationByRemoteId($locationRemoteId);
                        unset($yamlParsed[$key]);
                    } catch (NotFoundException) {
                        // Do nothing
                    }
                }
            } else {
                foreach ($yamlParsed as $key => $content) {
                    $contentRemoteId = $content['match']['content_remote_id'];

                    try {
                        $this->contentService->loadContentByRemoteId($contentRemoteId);
                    } catch (NotFoundException) {
                        unset($content[$key]);
                    }
                }
            }

            $yaml = Yaml::dump($yamlParsed);

            $projectRoot = $this->container->getParameter('kernel.project_dir');
            $randomTimeComponent = date('YmdHis');
            $newFilePath = $projectRoot . '/' . $this->migrationsPath . '/' . $randomTimeComponent . $uploadedFile->getClientOriginalName();

            file_put_contents($newFilePath, $yaml);

            $process = new Process(
                [
                    '../bin/console',
                    'kaliop:migration:migrate',
                    '--path=' . $newFilePath,
                ],
            );
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

                $this->logger->error($error);

                $this->addFlash(
                    'error',
                    $this->translator->trans(
                        'netgen.ibexa_import_export.error.import',
                        domain: $this::TRANSLATION_DOMAIN,
                    ),
                );
            }

            $this->addFlash(
                'success',
                $this->translator->trans(
                    'netgen.ibexa_import_export.success.import',
                    domain: $this::TRANSLATION_DOMAIN,
                ),
            );

            return $this->redirectToRoute('netgen_import_export.route.admin.import');
        }

        return $this->render(
            '@NetgenIbexaImportExport/import.html.twig',
            [
                'form' => $form->createView(),
            ],
        );
    }
}
