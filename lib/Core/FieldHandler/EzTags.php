<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExport\Core\FieldHandler;

use DateTimeInterface;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\FieldValueConverterInterface;
use Kaliop\eZMigrationBundle\Core\FieldHandler\AbstractFieldHandler;
use Kaliop\eZMigrationBundle\Core\Matcher\TagMatcher;
use Netgen\TagsBundle\Core\FieldType\Tags\Value as TagsValue;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_values;
use function count;
use function is_array;
use function key;
use function reset;

final class EzTags extends AbstractFieldHandler implements FieldValueConverterInterface
{
    public function __construct(
        private readonly TagMatcher $tagMatcher,
        private readonly TranslatorInterface $translator,
    ) {}

    /**
     * @param mixed $fieldHash
     *
     * @throws InvalidMatchConditionsException
     * @throws InvalidStepDefinitionException
     */
    public function hashToFieldValue($fieldHash, array $context = []): TagsValue
    {
        $tags = [];
        foreach ($fieldHash as $def) {
            if (!is_array($def)) {
                throw new InvalidStepDefinitionException('Definition of EzTags field is incorrect: each element of the tags array must be an array with one element');
            }

            $identifier = reset($def);
            $type = key($def);

            $identifier = $this->referenceResolver->resolveReference($identifier);

            try {
                foreach (
                    $this->tagMatcher->match([$type => $identifier]) as $id => $tag
                ) {
                    $tags[$id] = $tag;
                }
            } catch (NotFoundException) {
                // Do nothing
            }
        }

        return new TagsValue(array_values($tags));
    }

    public function fieldValueToHash($fieldValue, array $context = []): array
    {
        /** @var \Netgen\TagsBundle\Core\FieldType\Tags\Value $fieldValue */
        $hash = [];
        foreach ($fieldValue->tags as $tag) {
            if ($tag->id === null || $tag->id < 1) {
                $hash[] = [
                    'remote_id' => $tag->remoteId,
                    'parent_id' => $tag->parentTagId,
                    'keywords' => $tag->keywords,
                    'always_available' => $tag->alwaysAvailable,
                    'main_language_code' => $tag->mainLanguageCode,
                ];
            } else {
                $hash[] = [
                    'remote_id' => $tag->remoteId,
                    'id' => $tag->id,
                    'parent_id' => $tag->parentTagId,
                    'main_tag_id' => $tag->mainTagId,
                    'keywords' => $tag->keywords,
                    'depth' => $tag->depth,
                    'path_string' => $tag->pathString,
                    'modified' => $tag->modificationDate instanceof DateTimeInterface ?
                        $tag->modificationDate->getTimestamp() :
                        0,
                    'always_available' => $tag->alwaysAvailable,
                    'main_language_code' => $tag->mainLanguageCode,
                    'language_codes' => $tag->languageCodes,
                ];
            }
        }

        return $hash;
    }

    public function skipsField(array $hash): array|bool
    {
        $skipsField = [];

        foreach ($hash as $tag) {
            $tagRemoteId = $tag['remote_id'];

            try {
                $this->tagMatcher->match(['remote_id' => $tagRemoteId]);
            } catch (NotFoundException) {
                $skipsField[] = $this->translator->trans(
                    'netgen.ibexa_import_export.skip_field.tags',
                    ['remote_id' => $tagRemoteId],
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
