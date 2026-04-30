<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExport\Core\Executor;

use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\Core\Executor\ContentManager as BaseContentManager;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentMatcher;

use function array_merge;
use function count;
use function reset;

/**
 * Overrides {@see BaseContentManager::generateMigration()} to fix two issues with multi-language exports:
 *
 * 1. Solr-loaded Content objects only carry field data for the siteaccess language. When --lang=all
 *    is requested we reload each content from the database with all its language codes so
 *    `getFieldsByLanguage()` returns real per-language field values.
 * 2. Non-translatable fields must not be exported per-language - Ibexa rejects setting them in any
 *    language other than the main one on import.
 */
final class ContentManagerWithTranslations extends BaseContentManager
{
    public function generateMigration(array $matchConditions, $mode, array $context = [])
    {
        $data = [];
        $currentUser = $this->authenticateUserByContext($context);

        try {
            $contentCollection = $this->contentMatcher->match($matchConditions);

            /** @var Content $content */
            foreach ($contentCollection as $content) {
                $language = $this->getLanguageCodeFromContext($context);

                if ($language === 'all') {
                    $content = $this->repository->getContentService()->loadContent(
                        $content->id,
                        $content->versionInfo->languageCodes,
                    );
                }

                $location = $this->repository->getLocationService()->loadLocation($content->contentInfo->mainLocationId);
                $contentType = $this->repository->getContentTypeService()->loadContentType(
                    $content->contentInfo->contentTypeId,
                );

                $contentData = [
                    'type' => reset($this->supportedStepTypes),
                    'mode' => $mode,
                ];

                switch ($mode) {
                    case 'create':
                        $contentData = array_merge(
                            $contentData,
                            [
                                'content_type' => $contentType->identifier,
                                'parent_location' => $location->parentLocationId,
                                'priority' => $location->priority,
                                'is_hidden' => $location->invisible,
                                'sort_field' => $this->sortConverter->sortField2Hash($location->sortField),
                                'sort_order' => $this->sortConverter->sortOrder2Hash($location->sortOrder),
                                'remote_id' => $content->contentInfo->remoteId,
                                'location_remote_id' => $location->remoteId,
                                'section' => $content->contentInfo->sectionId,
                                'object_states' => $this->getObjectStates($content),
                            ],
                        );
                        $locationService = $this->repository->getLocationService();
                        $locations = $locationService->loadLocations($content->contentInfo);
                        if (count($locations) > 1) {
                            $otherParentLocations = [];
                            foreach ($locations as $otherLocation) {
                                if ($otherLocation->id !== $location->id) {
                                    $otherParentLocations[] = $otherLocation->parentLocationId;
                                }
                            }
                            $contentData['other_parent_locations'] = $otherParentLocations;
                        }

                        break;

                    case 'update':
                        $contentData = array_merge(
                            $contentData,
                            [
                                'match' => [
                                    ContentMatcher::MATCH_CONTENT_REMOTE_ID => $content->contentInfo->remoteId,
                                ],
                                'new_remote_id' => $content->contentInfo->remoteId,
                                'section' => $content->contentInfo->sectionId,
                                'object_states' => $this->getObjectStates($content),
                            ],
                        );

                        break;

                    case 'delete':
                        $contentData = array_merge(
                            $contentData,
                            [
                                'match' => [
                                    ContentMatcher::MATCH_CONTENT_REMOTE_ID => $content->contentInfo->remoteId,
                                ],
                            ],
                        );

                        break;

                    default:
                        throw new InvalidStepDefinitionException("Executor 'content' doesn't support mode '{$mode}'");
                }

                if ($mode !== 'delete') {
                    if ($language === 'all') {
                        $languages = $content->versionInfo->languageCodes;
                    } else {
                        $contentData = array_merge(
                            $contentData,
                            [
                                'lang' => $language,
                            ],
                        );
                        $languages = [$language];
                    }

                    $attributes = [];
                    foreach ($languages as $lang) {
                        foreach ($content->getFieldsByLanguage($lang) as $fieldIdentifier => $field) {
                            $fieldDefinition = $contentType->getFieldDefinition($fieldIdentifier);

                            if ($language === 'all'
                                && !$fieldDefinition->isTranslatable
                                && $lang !== $content->contentInfo->mainLanguageCode
                            ) {
                                continue;
                            }

                            $fieldValue = $this->fieldHandlerManager->fieldValueToHash(
                                $fieldDefinition->fieldTypeIdentifier,
                                $contentType->identifier,
                                $field->value,
                            );
                            if ($language === 'all') {
                                $attributes[$field->fieldDefIdentifier][$lang] = $fieldValue;
                            } else {
                                $attributes[$field->fieldDefIdentifier] = $fieldValue;
                            }
                        }
                    }

                    $contentData = array_merge(
                        $contentData,
                        [
                            'section' => $content->contentInfo->sectionId,
                            'owner' => $content->contentInfo->ownerId,
                            'modification_date' => $content->contentInfo->modificationDate->getTimestamp(),
                            'publication_date' => $content->contentInfo->publishedDate->getTimestamp(),
                            'always_available' => (bool) $content->contentInfo->alwaysAvailable,
                            'attributes' => $attributes,
                        ],
                    );
                }

                $data[] = $contentData;
            }
        } finally {
            $this->authenticateUserByReference($currentUser);
        }

        return $data;
    }
}
