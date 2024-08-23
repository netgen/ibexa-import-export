<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExport\Core\FieldHandler;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Core\FieldType\Relation\Value as RelationValue;
use Kaliop\eZMigrationBundle\API\FieldDefinitionConverterInterface;
use Kaliop\eZMigrationBundle\API\FieldValueConverterInterface;
use Kaliop\eZMigrationBundle\Core\FieldHandler\AbstractFieldHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_key_exists;
use function is_array;

final class EzRelation extends AbstractFieldHandler implements FieldValueConverterInterface, FieldDefinitionConverterInterface
{
    public function __construct(
        private readonly ContentService $contentService,
        private readonly TranslatorInterface $translator,
    ) {}

    public function hashToFieldValue($fieldHash, array $context = []): RelationValue
    {
        if (is_array($fieldHash) && array_key_exists('destinationContentId', $fieldHash)) {
            // fromHash format
            $id = $fieldHash['destinationContentId'];
        } else {
            // simplified format
            $id = $fieldHash;
        }

        if ($id === null) {
            return new RelationValue();
        }

        $id = $this->referenceResolver->resolveReference($id);

        try {
            $relatedContent = $this->contentService->loadContentByRemoteId($id);

            return new RelationValue($relatedContent->id);
        } catch (NotFoundException) {
            return new RelationValue();
        }
    }

    public function fieldSettingsToHash($settingsValue, array $context = [])
    {
        if (is_array($settingsValue) && isset($settingsValue['selectionRoot']) && $settingsValue['selectionRoot'] === '') {
            $settingsValue['selectionRoot'] = null;
        }

        return $settingsValue;
    }

    public function hashToFieldSettings($settingsHash, array $context = [])
    {
        return $settingsHash;
    }

    public function fieldValueToHash($fieldValue, array $context = []): array
    {
        $destinationContentId = null;

        if ($fieldValue->destinationContentId !== null) {
            try {
                $destinationContent = $this->contentService->loadContent((int) $fieldValue->destinationContentId);
                $destinationContentId = $destinationContent->contentInfo->remoteId;
            } catch (NotFoundException) {
                // Do nothing
            }
        }

        return [
            'destinationContentId' => $destinationContentId,
        ];
    }

    public function skipsField(array $hash): bool|string
    {
        $destinationContentId = $hash['destinationContentId'];
        if ($destinationContentId !== null) {
            try {
                $this->contentService->loadContentByRemoteId($destinationContentId);
            } catch (NotFoundException) {
                return $this->translator->trans(
                    'netgen.ibexa_import_export.skip_field.relation',
                    ['remote_id' => $destinationContentId],
                    'import_export',
                );
            }
        }

        return false;
    }
}
