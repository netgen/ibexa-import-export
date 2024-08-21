<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExport\Core\FieldHandler;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Core\FieldType\Relation\Value;
use Kaliop\eZMigrationBundle\API\FieldDefinitionConverterInterface;
use Kaliop\eZMigrationBundle\API\FieldValueConverterInterface;
use Kaliop\eZMigrationBundle\Core\FieldHandler\AbstractFieldHandler;

use function array_key_exists;
use function is_array;

final class EzRelation extends AbstractFieldHandler implements FieldValueConverterInterface, FieldDefinitionConverterInterface
{
    public function __construct(
        private readonly ContentService $contentService,
    ) {}

    public function hashToFieldValue($fieldHash, array $context = []): Value
    {
        if (is_array($fieldHash) && array_key_exists('destinationContentId', $fieldHash)) {
            // fromHash format
            $id = $fieldHash['destinationContentId'];
        } else {
            // simplified format
            $id = $fieldHash;
        }

        if ($id === null) {
            return new Value();
        }

        $id = $this->referenceResolver->resolveReference($id);

        try {
            $relatedContent = $this->contentService->loadContentByRemoteId($id);

            return new Value($relatedContent->id);
        } catch (NotFoundException) {
            return new Value();
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
}
