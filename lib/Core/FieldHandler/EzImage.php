<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExport\Core\FieldHandler;

use Ibexa\Core\FieldType\Image\Value as ImageValue;
use Ibexa\Core\IO\UrlDecorator;
use Kaliop\eZMigrationBundle\API\FieldValueConverterInterface;
use Kaliop\eZMigrationBundle\Core\FieldHandler\FileFieldHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

use function basename;
use function filesize;
use function is_file;
use function is_string;
use function realpath;

final class EzImage extends FileFieldHandler implements FieldValueConverterInterface
{
    public function __construct(
        private readonly string $projectDirPath,
        private readonly string $storagePath,
        private readonly TranslatorInterface $translator,
        $ioRootDir,
        ?UrlDecorator $ioDecorator = null,
        $ioService = null,
    ) {
        parent::__construct($ioRootDir, $ioDecorator, $ioService);
    }

    public function hashToFieldValue($fieldHash, array $context = []): ImageValue
    {
        $altText = '';
        $fileName = '';

        if ($fieldHash === null) {
            return new ImageValue();
        }
        if (is_string($fieldHash)) {
            $filePath = $fieldHash;
        } else {
            $filePath = $this->referenceResolver->resolveReference($fieldHash['path']);
            if (isset($fieldHash['alt_text'])) {
                $altText = $this->referenceResolver->resolveReference($fieldHash['alt_text']);
            }
            if (isset($fieldHash['filename'])) {
                $fileName = $this->referenceResolver->resolveReference($fieldHash['filename']);
            }
        }

        $realFilePath = $this->projectDirPath . '/' . $this->storagePath . '/' . $filePath;

        if (!is_file($realFilePath) && !is_file($filePath)) {
            return new ImageValue();
        }

        if (!is_file($realFilePath) && is_file($filePath)) {
            $realFilePath = $filePath;
        }

        return new ImageValue(
            [
                'path' => $realFilePath,
                'fileSize' => filesize($realFilePath),
                'fileName' => $fileName !== '' ? $fileName : basename($realFilePath),
                'alternativeText' => $altText,
            ],
        );
    }

    public function fieldValueToHash($fieldValue, array $context = []): ?array
    {
        if ($fieldValue->uri === null) {
            return null;
        }

        return [
            'path' => realpath($this->ioRootDir) . '/' . ($this->ioDecorator ? $this->ioDecorator->undecorate($fieldValue->uri) : $fieldValue->uri),
            'filename' => $fieldValue->fileName,
            'alternativeText' => $fieldValue->alternativeText,
        ];
    }

    public function skipsField(?array $hash): bool|string
    {
        if ($hash === null) {
            return false;
        }

        $path = $hash['path'];

        if (!is_file($this->projectDirPath . '/' . $this->storagePath . '/' . $path)) {
            return $this->translator->trans(
                'netgen.ibexa_import_export.skip_field.image',
                ['image_path' => $path],
                'import_export',
            );
        }

        return false;
    }
}
