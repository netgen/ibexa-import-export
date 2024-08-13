<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExport\Core\FieldHandler;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\FieldType\Image\Value;
use Ibexa\Core\FieldType\Image\Value as ImageValue;
use Ibexa\Core\IO\UrlDecorator;
use Kaliop\eZMigrationBundle\API\FieldValueConverterInterface;
use Kaliop\eZMigrationBundle\Core\FieldHandler\FileFieldHandler;

use function basename;
use function filesize;
use function is_file;
use function is_string;
use function realpath;

class EzImage extends FileFieldHandler implements FieldValueConverterInterface
{
    public function __construct(
        private readonly ConfigResolverInterface $configResolver,
        private readonly string $projectDirPath,
        private readonly string $storagePath,
        $ioRootDir,
        ?UrlDecorator $ioDecorator = null,
        $ioService = null,
    ) {
        parent::__construct($ioRootDir, $ioDecorator, $ioService);
    }

    /**
     * Creates a value object to use as the field value when setting an image field type.
     *
     * @param array|string $fieldValue The path to the file or an array with 'path' and 'alt_text' keys
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     *
     * @return ImageValue
     *
     * @todo resolve refs more
     */
    public function hashToFieldValue($fieldValue, array $context = []): ImageValue
    {
        $altText = '';
        $fileName = '';

        if ($fieldValue === null) {
            return new ImageValue();
        }
        if (is_string($fieldValue)) {
            $filePath = $fieldValue;
        } else {
            $filePath = $this->referenceResolver->resolveReference($fieldValue['path']);
            if (isset($fieldValue['alt_text'])) {
                $altText = $this->referenceResolver->resolveReference($fieldValue['alt_text']);
            }
            if (isset($fieldValue['filename'])) {
                $fileName = $this->referenceResolver->resolveReference($fieldValue['filename']);
            }
        }

        $realFilePath = $this->projectDirPath . '/' . $this->storagePath . '/' . $fileName;

        if (!is_file($realFilePath) && !is_file($filePath)) {
            return new ImageValue();
        }

        // but in the past, when using a string, this worked as well as an absolute path, so we have to support it as well
        // / @todo atm this does not work for files from content fields in cluster mode
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

    /**
     * @param Value $fieldValue
     * @param array $context
     *
     * @return array
     *
     * @todo check out if this works in ezplatform
     */
    public function fieldValueToHash($fieldValue, array $context = []): ?array
    {
        if ($fieldValue->uri === null) {
            return null;
        }

        // / @todo we should handle clustered configurations, to give back the absolute path on disk rather than the 'virtual' one
        return [
            'path' => realpath($this->ioRootDir) . '/' . ($this->ioDecorator ? $this->ioDecorator->undecorate($fieldValue->uri) : $fieldValue->uri),
            'filename' => $fieldValue->fileName,
            'alternativeText' => $fieldValue->alternativeText,
        ];
    }
}
