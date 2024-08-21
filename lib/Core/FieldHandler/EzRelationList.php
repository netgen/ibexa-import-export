<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExport\Core\FieldHandler;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Core\FieldType\RelationList\Value;
use Kaliop\eZMigrationBundle\API\FieldValueConverterInterface;
use Kaliop\eZMigrationBundle\Core\FieldHandler\AbstractFieldHandler;

use function count;

final class EzRelationList extends AbstractFieldHandler implements FieldValueConverterInterface
{
    public function __construct(
        private readonly ContentService $contentService,
    ) {}

    public function hashToFieldValue($fieldHash, array $context = []): Value
    {
        if ($fieldHash === null) {
            $ids = [];
        } elseif (count($fieldHash) === 1 && isset($fieldHash['destinationContentIds'])) {
            $ids = $fieldHash['destinationContentIds'];
        } else {
            $ids = $fieldHash;
        }

        foreach ($ids as $key => $id) {
            $remoteId = $this->referenceResolver->resolveReference($id);

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
                // Do nothing
            }
        }

        return ['destinationContentIds' => $destinationContentRemoteIds];
    }
}
