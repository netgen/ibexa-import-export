<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExport\Core\FieldHandler;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Core\FieldType\RelationList\Value;
use Kaliop\eZMigrationBundle\API\FieldValueConverterInterface;
use Kaliop\eZMigrationBundle\Core\FieldHandler\AbstractFieldHandler;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentMatcher;

use function count;

class EzRelationList extends AbstractFieldHandler implements FieldValueConverterInterface
{
    public function __construct(
        private readonly ContentMatcher $contentMatcher,
        private readonly ContentService $contentService,
    ) {}

    /**
     * @param array $fieldValue The definition of the field value, structured in the yml file
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     *
     * @return Value
     */
    public function hashToFieldValue($fieldValue, array $context = []): Value
    {
        if ($fieldValue === null) {
            $ids = [];
        } elseif (count($fieldValue) === 1 && isset($fieldValue['destinationContentIds'])) {
            // fromHash format
            $ids = $fieldValue['destinationContentIds'];
        } else {
            // simplified format
            $ids = $fieldValue;
        }

        foreach ($ids as $key => $id) {
            // 1. resolve relations
            $remoteId = $this->referenceResolver->resolveReference($id);

            // 2. resolve remote ids
            // check if content with remote id exists
            try {
                $relatedContent = $this->contentService->loadContentByRemoteId($remoteId);
                $ids[$key] = $relatedContent->id;
            } catch (NotFoundException) {
                continue;
            }
        }

        return new Value($ids);
    }

    public function fieldValueToHash($fieldValue, array $context = []): array
    {
        $destinationContentRemoteIds = [];
        foreach ($fieldValue->destinationContentIds as $destinationContentId) {
            try {
                $destinationContent = $this->contentService->loadContent((int) $destinationContentId);
                $destinationContentRemoteIds[] = $destinationContent->contentInfo->remoteId;
            } catch (NotFoundException) {
            }
        }

        return ['destinationContentIds' => $destinationContentRemoteIds];
    }
}
