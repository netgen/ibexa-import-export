<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExport\Core\FieldHandler;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Core\FieldType\RelationList\Value as RelationListValue;
use Kaliop\eZMigrationBundle\API\FieldValueConverterInterface;
use Kaliop\eZMigrationBundle\Core\FieldHandler\AbstractFieldHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

use function count;

final class EzRelationList extends AbstractFieldHandler implements FieldValueConverterInterface
{
    public function __construct(
        private readonly ContentService $contentService,
        private readonly TranslatorInterface $translator,
    ) {}

    public function hashToFieldValue($fieldHash, array $context = []): RelationListValue
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

        return new RelationListValue($ids);
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

    public function skipsField(array $hash): array|bool
    {
        $skipsField = [];

        $destinationContentIds = $hash['destinationContentIds'];
        foreach ($destinationContentIds as $destinationContentId) {
            try {
                $this->contentService->loadContentByRemoteId($destinationContentId);
            } catch (NotFoundException) {
                $skipsField[] = $this->translator->trans(
                    'netgen.ibexa_import_export.skip_field.relation_list',
                    ['remote_id' => $destinationContentId],
                    'import_export',
                );
            }
        }

        if (count($skipsField) > 0) {
            return $skipsField;
        }

        return false;
    }
}
