<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExport\Core\FieldHandler;

use Ibexa\Core\FieldType\BinaryFile\Value as BinaryFileValue;
use Ibexa\Core\IO\UrlDecorator;
use Kaliop\eZMigrationBundle\API\FieldValueConverterInterface;
use Kaliop\eZMigrationBundle\Core\FieldHandler\FileFieldHandler;

use function basename;
use function filesize;
use function is_file;
use function is_string;
use function realpath;
use function sprintf;

final class EzBinaryFile extends FileFieldHandler implements FieldValueConverterInterface
{
    public function __construct(
        private readonly string $projectDirPath,
        private readonly string $storagePath,
        $ioRootDir,
        ?UrlDecorator $ioDecorator = null,
        $ioService = null,
    ) {
        parent::__construct($ioRootDir, $ioDecorator, $ioService);
    }

    public function hashToFieldValue($fieldHash, array $context = []): BinaryFileValue
    {
        $mimeType = '';
        $fileName = '';

        if ($fieldHash === null) {
            return new BinaryFileValue();
        } if (is_string($fieldHash)) {
            $filePath = $fieldHash;
        } else {
            $filePath = $this->referenceResolver->resolveReference($fieldHash['path']);
            if (isset($fieldHash['filename'])) {
                $fileName = $this->referenceResolver->resolveReference($fieldHash['filename']);
            }
            if (isset($fieldHash['mime_type'])) {
                $mimeType = $this->referenceResolver->resolveReference($fieldHash['mime_type']);
            }
        }

        $realFilePath = $this->projectDirPath . '/' . $this->storagePath . '/' . $filePath;

        if (!is_file($realFilePath) && !is_file($filePath)) {
            return new BinaryFileValue();
        }

        if (!is_file($realFilePath) && is_file($filePath)) {
            $realFilePath = $filePath;
        }

        $fieldValues = [
            'path' => $realFilePath,
            'fileSize' => filesize($realFilePath),
            'fileName' => $fileName !== '' ? $fileName : basename($realFilePath),
        ];

        if ($mimeType !== '') {
            $fieldValues['mimeType'] = $mimeType;
        }

        return new BinaryFileValue($fieldValues);
    }

    public function fieldValueToHash($fieldValue, array $context = []): ?array
    {
        if ($fieldValue->uri === null) {
            return null;
        }
        $binaryFile = $this->ioService->loadBinaryFile($fieldValue->id);

        return [
            'path' => realpath($this->ioRootDir) . '/' . ($this->ioDecorator ? $this->ioDecorator->undecorate($binaryFile->uri) : $binaryFile->uri),
            'filename' => $fieldValue->fileName,
            'mimeType' => $fieldValue->mimeType,
        ];
    }

    public function skipsField(?array $hash): bool|string
    {
        if ($hash === null) {
            return false;
        }

        $path = $hash['path'];

        if (!is_file($this->projectDirPath . '/' . $this->storagePath . '/' . $path)) {
            return sprintf('File with path %s does not exist.', $path);
        }

        return false;
    }
}
