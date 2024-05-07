<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExport\Core\FieldHandler;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\FieldType\BinaryFile\Value as BinaryFileValue;
use Ibexa\Core\IO\UrlDecorator;
use Kaliop\eZMigrationBundle\API\FieldValueConverterInterface;
use Kaliop\eZMigrationBundle\Core\FieldHandler\FileFieldHandler;

use function basename;
use function filesize;
use function is_file;
use function is_string;
use function realpath;

class EzBinaryFile extends FileFieldHandler implements FieldValueConverterInterface
{
    public function __construct(
        private readonly ConfigResolverInterface $configResolver,
        private readonly string $projectDirPath,
        $ioRootDir,
        ?UrlDecorator $ioDecorator = null,
        $ioService = null,
    ) {
        parent::__construct($ioRootDir, $ioDecorator, $ioService);
    }

    /**
     * @param array|string $fieldValue The path to the file or an array with 'path' key
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     *
     * @return BinaryFileValue
     *
     * @todo resolve refs more
     */
    public function hashToFieldValue($fieldValue, array $context = []): BinaryFileValue
    {
        $mimeType = '';
        $fileName = '';

        if ($fieldValue === null) {
            return new BinaryFileValue();
        } if (is_string($fieldValue)) {
            $filePath = $fieldValue;
        } else {
            $filePath = $this->referenceResolver->resolveReference($fieldValue['path']);
            if (isset($fieldValue['filename'])) {
                $fileName = $this->referenceResolver->resolveReference($fieldValue['filename']);
            }
            if (isset($fieldValue['mime_type'])) {
                $mimeType = $this->referenceResolver->resolveReference($fieldValue['mime_type']);
            }
        }

        $storagePath = $this->configResolver->hasParameter('import_export.storage.path', 'netgen') ?
            $this->configResolver->getParameter('import_export.storage.path', 'netgen') :
            'public/var/site/storage';
        $realFilePath = $this->projectDirPath . '/' . $storagePath . '/' . $fileName;

        if (!is_file($realFilePath) && !is_file($filePath)) {
            return new BinaryFileValue();
        }

        // but in the past, when using a string, this worked as well as an absolute path, so we have to support it as well
        // / @todo atm this does not work for files from content fields in cluster mode
        if (!is_file($realFilePath) && is_file($filePath)) {
            $realFilePath = $filePath;
        }

        $fieldValues = [
            'path' => $realFilePath,
            'fileSize' => filesize($realFilePath),
            'fileName' => $fileName !== '' ? $fileName : basename($realFilePath),
            // 'mimeType' => $mimeType != '' ? $mimeType : mime_content_type($realFilePath)
        ];

        // changed 2021/1/6: we do _not_ add the mimetype by default any more, as it is either buggy or
        // useless - see https://github.com/kaliop-uk/ezmigrationbundle/issues/147#issuecomment-755755241
        if ($mimeType !== '') {
            $fieldValues['mimeType'] = $mimeType;
        }

        return new BinaryFileValue($fieldValues);
    }

    /**
     * @param \Ibexa\Core\FieldType\BinaryFile\Value $fieldValue
     * @param array $context
     *
     * @return array
     *
     * @todo check if this works in ezplatform
     */
    public function fieldValueToHash($fieldValue, array $context = []): ?array
    {
        if ($fieldValue->uri === null) {
            return null;
        }
        $binaryFile = $this->ioService->loadBinaryFile($fieldValue->id);

        // / @todo we should handle clustered configurations, to give back the absolute path on disk rather than the 'virtual' one
        return [
            'path' => realpath($this->ioRootDir) . '/' . ($this->ioDecorator ? $this->ioDecorator->undecorate($binaryFile->uri) : $binaryFile->uri),
            'filename' => $fieldValue->fileName,
            'mimeType' => $fieldValue->mimeType,
        ];
    }
}
