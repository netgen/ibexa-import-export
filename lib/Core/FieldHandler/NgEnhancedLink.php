<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExport\Core\FieldHandler;

use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Kaliop\eZMigrationBundle\API\FieldValueConverterInterface;
use Kaliop\eZMigrationBundle\Core\FieldHandler\AbstractFieldHandler;
use Netgen\IbexaFieldTypeEnhancedLink\FieldType\Value;

use function sprintf;

final class NgEnhancedLink extends AbstractFieldHandler implements FieldValueConverterInterface
{
    public function __construct(
        private readonly ContentService $contentService,
    ) {}

    public function fieldValueToHash($fieldValue, array $context = []): array
    {
        $reference = $fieldValue->reference;

        if ($fieldValue->isTypeInternal()) {
            try {
                $reference = $this->contentService->loadContent($reference)->contentInfo->remoteId;
            } catch (NotFoundException) {
                $reference = null;
            }
        }

        return [
            'reference' => $reference,
            'label' => $fieldValue->label,
            'target' => $fieldValue->target,
            'suffix' => $fieldValue->suffix,
            'is_internal' => $fieldValue->isTypeInternal(),
        ];
    }

    public function hashToFieldValue($fieldHash, array $context = []): Value
    {
        if ($fieldHash !== null) {
            $reference = $fieldHash['reference'];

            if (isset($reference)) {
                if ($fieldHash['is_internal']) {
                    try {
                        $content = $this->contentService->loadContentByRemoteId($reference);
                        $reference = $content->id;
                    } catch (NotFoundException) {
                        $reference = null;
                    }
                }

                return new Value($reference, $fieldHash['label'], $fieldHash['target'], $fieldHash['suffix']);
            }
        }

        return new Value();
    }

    public function skipsField(array $hash): bool|string
    {
        if ($hash['is_internal']) {
            try {
                $content = $this->contentService->loadContentByRemoteId($hash['reference']);
            } catch (NotFoundException) {
                return sprintf('Internal content with remote id %s does not exist.', $hash['reference']);
            }
        }

        return false;
    }
}
