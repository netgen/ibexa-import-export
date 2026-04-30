<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExportBundle\Controller\Ajax;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\Repository;
use Netgen\IbexaImportExportBundle\Registry\Registry;
use OutOfBoundsException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function mb_strtolower;
use function method_exists;
use function pathinfo;
use function sprintf;

use const PATHINFO_EXTENSION;

final class Preview extends AbstractController
{
    public function __construct(
        private readonly Repository $repository,
        private readonly ContentTypeService $contentTypeService,
        private readonly LocationService $locationService,
        private readonly ContentService $contentService,
        private readonly Registry $registry,
    ) {}

    public function __invoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ibexa:import_export:access');

        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
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

            if ($importMode === 'create' && $importStructure === 'Subtree') {
                $rootRemoteId = $yamlParsed[0]['remote_id'] ?? null;
                $rootName = $yamlParsed[0]['exported_content_name'] ?? null;

                if (is_string($rootRemoteId) && $rootRemoteId !== '') {
                    try {
                        $this->repository->sudo(
                            fn () => $this->contentService->loadContentByRemoteId($rootRemoteId),
                        );

                        $errors[] = sprintf(
                            'Subtree import cannot be executed because the root content %s(remote id: %s) already exists.',
                            $rootName,
                            $rootRemoteId,
                        );
                    } catch (NotFoundException) {
                        // Do nothing
                    }
                }
            }

            if (count($errors) > 0) {
                $response = $this->render('@NetgenIbexaImportExport/preview.html.twig', [
                    'import_structure' => $importStructure,
                    'import_mode' => $importMode,
                    'errors' => $errors,
                    'skipped_content' => $skippedContent,
                    'skipped_content_fields' => $skippedContentFields,
                ]);

                return new Response($response->getContent(), Response::HTTP_BAD_REQUEST);
            }

            foreach ($yamlParsed as $content) {
                $contentRemoteId = $importMode === 'update' ? $content['match']['content_remote_id'] : $content['remote_id'];

                if ($importMode === 'create') {
                    try {
                        $contentType = $this->repository->sudo(
                            fn () => $this->contentTypeService->loadContentTypeByIdentifier($content['content_type']),
                        );
                    } catch (NotFoundException) {
                        $skippedContent[$contentRemoteId]['name'] = $content['exported_content_name'];
                        $skippedContent[$contentRemoteId]['messages'][] = sprintf('Content type with identifier %s does not exist.', $content['content_type']);

                        continue;
                    }

                    try {
                        $this->repository->sudo(fn () => $this->contentService->loadContentByRemoteId($content['remote_id']));
                        $skippedContent[$contentRemoteId]['name'] = $content['exported_content_name'];
                        $skippedContent[$contentRemoteId]['messages'][] = sprintf('Content with remote id %s already exists.', $content['remote_id']);

                        continue;
                    } catch (NotFoundException) {
                        // Do nothing
                    }

                    try {
                        $this->repository->sudo(fn () => $this->locationService->loadLocationByRemoteId($content['location_remote_id']));
                        $skippedContent[$contentRemoteId]['name'] = $content['exported_content_name'];
                        $skippedContent[$contentRemoteId]['messages'][] = sprintf('Content with location remote id %s already exists.', $content['location_remote_id']);

                        continue;
                    } catch (NotFoundException) {
                        // Do nothing
                    }
                } elseif ($importMode === 'update') {
                    try {
                        $updateContent = $this->repository->sudo(
                            fn () => $this->contentService->loadContentByRemoteId($contentRemoteId),
                        );
                    } catch (NotFoundException) {
                        $skippedContent[$contentRemoteId]['name'] = $content['exported_content_name'];
                        $skippedContent[$contentRemoteId]['messages'][] = sprintf('Content with remote id %s does not exist.', $contentRemoteId);

                        continue;
                    }

                    $contentType = $updateContent->getContentType();
                }

                if (
                    $importMode === 'create' && !array_key_exists($contentRemoteId, $skippedContent)
                    || $importMode === 'update' && !array_key_exists($contentRemoteId, $skippedContent)
                ) {
                    $attributes = $content['attributes'];
                    $isMultiLanguage = !array_key_exists('lang', $content);
                    foreach ($attributes as $field => $value) {
                        $fieldTypeIdentifier = $contentType->getFieldDefinition($field)->fieldTypeIdentifier;

                        try {
                            $fieldHandler = $this->registry->get($fieldTypeIdentifier);
                        } catch (OutOfBoundsException) {
                            continue;
                        }

                        if (!method_exists($fieldHandler, 'skipsField')) {
                            continue;
                        }

                        if ($isMultiLanguage && is_array($value)) {
                            foreach ($value as $langCode => $langValue) {
                                $skipsField = $fieldHandler->skipsField($langValue);
                                if (is_string($skipsField)) {
                                    $skippedContentFields[$contentRemoteId]['fields'][$fieldTypeIdentifier . '-' . $field][] = $skipsField . ' (' . $langCode . ')';
                                    $skippedContentFields[$contentRemoteId]['name'] = $content['exported_content_name'];
                                } elseif (is_array($skipsField) && count($skipsField) > 0) {
                                    foreach ($skipsField as $skippedField) {
                                        $skippedContentFields[$contentRemoteId]['fields'][$fieldTypeIdentifier . '-' . $field][] = $skippedField . ' (' . $langCode . ')';
                                        $skippedContentFields[$contentRemoteId]['name'] = $content['exported_content_name'];
                                    }
                                }
                            }
                        } else {
                            $skipsField = $fieldHandler->skipsField($value);
                            if (is_string($skipsField)) {
                                $skippedContentFields[$contentRemoteId]['fields'][$fieldTypeIdentifier . '-' . $field][] = $skipsField;
                                $skippedContentFields[$contentRemoteId]['name'] = $content['exported_content_name'];
                            } elseif (is_array($skipsField) && count($skipsField) > 0) {
                                foreach ($skipsField as $skippedField) {
                                    $skippedContentFields[$contentRemoteId]['fields'][$fieldTypeIdentifier . '-' . $field][] = $skippedField;
                                    $skippedContentFields[$contentRemoteId]['name'] = $content['exported_content_name'];
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
