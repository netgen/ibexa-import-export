<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\Controller\Ajax;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\LocationService;
use Kaliop\eZMigrationBundle\Core\Matcher\TagMatcher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_key_exists;
use function count;
use function in_array;
use function is_file;
use function mb_strtolower;
use function pathinfo;
use function sprintf;

use const PATHINFO_EXTENSION;

final class Preview extends AbstractController
{
    public function __construct(
        private readonly ContentTypeService $contentTypeService,
        private readonly LocationService $locationService,
        private readonly ContentService $contentService,
        private readonly TagMatcher $tagMatcher,
        private readonly string $storagePath,
        private readonly TranslatorInterface $translator,
    ) {}

    public function __invoke(Request $request): Response
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('file');
        $originalFilename = $file->getClientOriginalName();
        $errors = [];
        $skippedContent = [];
        $skippedContentFields = [];

        $fileExtension = pathinfo($originalFilename, PATHINFO_EXTENSION);

        $isYaml = in_array(mb_strtolower($fileExtension), ['yml', 'yaml'], true);

        if ($isYaml === false) {
            $errors[] = 'Uploaded file is not a valid yaml file!';
        } else {
            $yamlParsed = Yaml::parseFile($file->getRealPath());

            if (count($yamlParsed) > 1) {
                $importStructure = 'Subtree';
            } else {
                $importStructure = 'Singular content';
            }
            $importMode = $yamlParsed[0]['mode'];

            foreach ($yamlParsed as &$content) {
                if ($importMode === 'create') {
                    try {
                        $contentType = $this->contentTypeService->loadContentTypeByIdentifier($content['content_type']);
                    } catch (NotFoundException) {
                        $skippedContent[$content['remote_id']][] = sprintf('Content type with identifier %s does not exist.', $content['content_type']);

                        continue;
                    }

                    try {
                        $this->contentService->loadContentByRemoteId($content['remote_id']);
                        $skippedContent[$content['remote_id']][] = sprintf('Content with remote id %s already exists.', $content['remote_id']);

                        continue;
                    } catch (NotFoundException) {
                        // Do nothing
                    }

                    try {
                        $this->locationService->loadLocationByRemoteId($content['location_remote_id']);
                        $skippedContent[$content['remote_id']][] = sprintf('Content with location remote id %s already exists.', $content['location_remote_id']);

                        continue;
                    } catch (NotFoundException) {
                        // Do nothing
                    }
                } elseif ($importMode === 'update') {
                    try {
                        $updateContent = $this->contentService->loadContentByRemoteId($content['match']['content_remote_id']);
                    } catch (NotFoundException) {
                        $skippedContent[$content['remote_id']][] = sprintf('Content with remote id %s does not exist.', $content['remote_id']);

                        continue;
                    }

                    try {
                        $contentType = $updateContent->getContentType();
                    } catch (NotFoundException) {
                        $skippedContent[$content['remote_id']][] = sprintf('Content type with identifier %s does not exist.', $content['content_type']);

                        continue;
                    }
                }

                if (
                    $importMode === 'create' && !array_key_exists($content['remote_id'], $skippedContent)
                    || $importMode === 'update' && !array_key_exists($content['match']['content_remote_id'], $skippedContent)
                ) {
                    $attributes = $content['attributes'];
                    foreach ($attributes as $field => $value) {
                        $fieldTypeIdentifier = $contentType->getFieldDefinition($field)->fieldTypeIdentifier;
                        if ($fieldTypeIdentifier === 'ezobjectrelation') {
                            $destinationContentId = $value['destinationContentId'];
                            if ($destinationContentId !== null) {
                                try {
                                    $this->contentService->loadContentByRemoteId($destinationContentId);
                                } catch (NotFoundException) {
                                    $skippedContentFields[$content['remote_id']][$field][] = sprintf('Destination content with remote id %s does not exist.', $destinationContentId);
                                }
                            }
                        } elseif ($fieldTypeIdentifier === 'ezobjectrelationlist') {
                            $destinationContentIds = $value['destinationContentIds'];
                            foreach ($destinationContentIds as $destinationContentId) {
                                try {
                                    $this->contentService->loadContentByRemoteId($destinationContentId);
                                } catch (NotFoundException) {
                                    $skippedContentFields[$content['remote_id']][$field][] = sprintf('Destination content with remote id %s does not exist.', $destinationContentId);
                                }
                            }
                        } elseif ($fieldTypeIdentifier === 'eztags') {
                            foreach ($value as $tag) {
                                $tagRemoteId = $tag['remote_id'];

                                try {
                                    $this->tagMatcher->match(['remote_id' => $tagRemoteId]);
                                } catch (NotFoundException) {
                                    $skippedContentFields[$content['remote_id']][$field][] = sprintf('Tag with remote id %s does not exist.', $tagRemoteId);
                                }
                            }
                        } elseif ($fieldTypeIdentifier === 'ezimage') {
                            if ($value === null) {
                                continue;
                            }

                            $path = $value['path'];

                            if (!is_file($this->container->getParameter('kernel.project_dir') . '/' . $this->storagePath . '/' . $path)) {
                                $skippedContentFields[$content['remote_id']][$field] = sprintf('Image with path %s does not exist.', $path);
                            }
                        } elseif ($fieldTypeIdentifier === 'ezbinaryfile') {
                            if ($value === null) {
                                continue;
                            }

                            $path = $value['path'];

                            if (!is_file($this->container->getParameter('kernel.project_dir') . '/' . $this->storagePath . '/' . $path)) {
                                $skippedContentFields[$content['remote_id']][$field] = sprintf('File with path %s does not exist.', $path);
                            }
                        } elseif ($fieldTypeIdentifier === 'ngenhancedlink') {
                            if ($value['is_internal']) {
                                try {
                                    $content = $this->contentService->loadContentByRemoteId($value['reference']);
                                } catch (NotFoundException) {
                                    $skippedContentFields[$content['remote_id']][$field] = sprintf('Internal content with remote id %s does not exist.', $value['reference']);
                                }
                            }
                        }
                    }
                }
            }
            if (count($skippedContent) === count($yamlParsed)) {
                $errors[] = 'No content can be imported.';
            }
        }

        $response = $this->render('@NetgenIbexaImportExport/preview.html.twig', [
            'import_structure' => $importStructure ?? null,
            'import_mode' => $importMode ?? null,
            'errors' => $errors,
            'skipped_content' => $skippedContent,
            'skipped_content_fields' => $skippedContentFields,
        ]);

        return new Response($response->getContent(), count($errors) > 0 ? Response::HTTP_BAD_REQUEST : Response::HTTP_OK);
    }
}
