<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExport\Core\FieldHandler;

use DOMDocument;
use DOMElement;
use DOMNode;
use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\FieldTypeRichText\FieldType\RichText\Value;
use Kaliop\eZMigrationBundle\API\EmbeddedReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;
use Kaliop\eZMigrationBundle\API\FieldValueConverterInterface;
use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Kaliop\eZMigrationBundle\Core\FieldHandler\AbstractFieldHandler;

use function is_array;
use function is_string;
use function mb_strlen;
use function mb_substr;
use function str_starts_with;

class EzRichText extends AbstractFieldHandler implements FieldValueConverterInterface
{
    public function __construct(
        private readonly ContentService $contentService,
        private readonly LocationService $locationService,
    ) {}

    public function setReferenceResolver(ReferenceResolverInterface $referenceResolver)
    {
        if (!$referenceResolver instanceof EmbeddedReferenceResolverInterface) {
            throw new MigrationBundleException('Reference resolver injected into EzRichText field handler should implement EmbeddedReferenceResolverInterface');
        }
        parent::setReferenceResolver($referenceResolver);
    }

    /**
     * Replaces any references in an xml string to be used as the input data for an ezrichtext field.
     *
     * @param string|array $fieldValue The definition of teh field value, structured in the yml file. Either a string, or an array with key 'content'
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     *
     * @return string
     *
     * @todo replace objects and location refs in ezcontent:// and ezlocation:// links
     */
    public function hashToFieldValue($fieldValue, array $context = [])
    {
        if (is_string($fieldValue)) {
            $xmlText = $fieldValue;
        } elseif (is_array($fieldValue) && isset($fieldValue['xml'])) {
            // native export format from eZ
            $xmlText = $fieldValue['xml'];
        } else {
            $xmlText = $fieldValue['content'];
        }

        // Check if there are any references in the xml text and replace them. Please phpstorm.
        $resolver = $this->referenceResolver;

        /** @var EmbeddedReferenceResolverInterface $resolver */
        $value = $resolver->resolveEmbeddedReferences($xmlText);

        $doc = new DOMDocument();
        $doc->loadXML($value);
        $toReplace = [];

        $links = $doc->getElementsByTagName('link');

        /** @var DOMElement $link */
        foreach ($links as $link) {
            $href = $link->getAttribute('xlink:href');

            if (str_starts_with($href, 'ezlocation')) {
                $id = mb_substr($href, mb_strlen('ezlocation://'));

                try {
                    $location = $this->locationService->loadLocationByRemoteId($id);
                    $link->setAttribute('xlink:href', 'ezlocation://' . $location->id);
                } catch (NotFoundException) {
                    $toReplace[] = $link;
                }
            } elseif (str_starts_with($href, 'ezcontent')) {
                $id = mb_substr($href, mb_strlen('ezcontent://'));

                try {
                    $content = $this->contentService->loadContentByRemoteId($id);
                    $link->setAttribute('xlink:href', 'ezcontent://' . $content->id);
                } catch (NotFoundException) {
                    $toReplace[] = $link;
                }
            }
        }

        $embeds = $doc->getElementsByTagName('ezembed');
        foreach ($embeds as $embed) {
            $href = $embed->getAttribute('xlink:href');

            if (str_starts_with($href, 'ezlocation')) {
                $id = mb_substr($href, mb_strlen('ezlocation://'));

                try {
                    $location = $this->locationService->loadLocationByRemoteId($id);
                    $embed->setAttribute('xlink:href', 'ezlocation://' . $location->id);
                } catch (NotFoundException) {
                    $toReplace[] = $embed;
                }
            } elseif (str_starts_with($href, 'ezcontent')) {
                $id = mb_substr($href, mb_strlen('ezcontent://'));

                try {
                    $content = $this->contentService->loadContentByRemoteId($id);
                    $this->locationService->loadLocation((int) $content->contentInfo->mainLocationId);
                    $embed->setAttribute('xlink:href', 'ezcontent://' . $content->id);
                } catch (NotFoundException) {
                    $toReplace[] = $embed;
                }
            }
        }

        foreach ($toReplace as $child) {
            /** @var DOMNode $parent */
            $parent = $child->parentNode;
            $parent->replaceChild($doc->createTextNode($child->textContent), $child);
        }

        return $doc->saveXML();
    }

    public function fieldValueToHash($fieldValue, array $context = [])
    {
        /** @var Value $fieldValue */
        $links = $fieldValue->xml->getElementsByTagName('link');
        foreach ($links as $link) {
            $href = $link->getAttribute('xlink:href');

            if (str_starts_with($href, 'ezlocation')) {
                $id = (int) mb_substr($href, mb_strlen('ezlocation://'));

                try {
                    $location = $this->locationService->loadLocation($id);
                    $href = 'ezlocation://' . $location->remoteId;
                } catch (NotFoundException) {
                    $href = 'ezlocation://';
                }

                $link->setAttribute('xlink:href', $href);
            } elseif (str_starts_with($href, 'ezcontent')) {
                $id = (int) mb_substr($href, mb_strlen('ezcontent://'));

                try {
                    $content = $this->contentService->loadContent($id);
                    $href = 'ezcontent://' . $content->contentInfo->remoteId;
                } catch (NotFoundException) {
                    $href = 'ezcontent://';
                }

                $link->setAttribute('xlink:href', $href);
            }
        }

        $embeds = $fieldValue->xml->getElementsByTagName('ezembed');
        foreach ($embeds as $embed) {
            $href = $embed->getAttribute('xlink:href');

            if (str_starts_with($href, 'ezlocation')) {
                $id = (int) mb_substr($href, mb_strlen('ezlocation://'));

                try {
                    $location = $this->locationService->loadLocation($id);
                    $href = 'ezlocation://' . $location->remoteId;
                } catch (NotFoundException) {
                    $href = 'ezlocation://';
                }

                $embed->setAttribute('xlink:href', $href);
            } elseif (str_starts_with($href, 'ezcontent')) {
                $id = (int) mb_substr($href, mb_strlen('ezcontent://'));

                try {
                    $content = $this->contentService->loadContent($id);
                    $href = 'ezcontent://' . $content->contentInfo->remoteId;
                } catch (NotFoundException) {
                    $href = 'ezcontent://';
                }

                $embed->setAttribute('xlink:href', $href);
            }
        }

        return ['xml' => (string) $fieldValue];
    }
}
