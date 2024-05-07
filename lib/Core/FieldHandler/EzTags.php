<?php

declare(strict_types=1);

namespace Netgen\IbexaImportExport\Core\FieldHandler;

use DateTimeInterface;
use Exception;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\FieldValueConverterInterface;
use Kaliop\eZMigrationBundle\Core\FieldHandler\AbstractFieldHandler;
use Kaliop\eZMigrationBundle\Core\Matcher\TagMatcher;
use Netgen\TagsBundle\Core\FieldType\Tags\Value;

use function array_values;
use function is_array;
use function key;
use function reset;

class EzTags extends AbstractFieldHandler implements FieldValueConverterInterface
{
    public function __construct(
        private readonly TagMatcher $tagMatcher,
    ) {}

    /**
     * Override the hashToFieldValue method to modify its behavior.
     *
     * @param array $fieldValue
     * @param array $context
     *
     * @throws Exception
     *
     * @return Value
     */
    public function hashToFieldValue($fieldValue, array $context = []): Value
    {
        $tags = [];
        foreach ($fieldValue as $def) {
            if (!is_array($def)) {
                throw new InvalidStepDefinitionException('Definition of EzTags field is incorrect: each element of the tags array must be an array with one element');
            }

            /**
             * @todo support single-value elements too? if numeric, it is a tag id, if it is a string it is... what?
             *       it could be either a tag's remote id or a keyword...
             */
            $identifier = reset($def);
            $type = key($def);

            $identifier = $this->referenceResolver->resolveReference($identifier);

            try {
                foreach (
                    $this->tagMatcher->match([$type => $identifier]) as $id => $tag
                ) {
                    $tags[$id] = $tag;
                }
            } catch (NotFoundException $e) {
            }
        }

        return new Value(array_values($tags));
    }

    public function fieldValueToHash($fieldValue, array $context = [])
    {
        /** @var Value $fieldValue */
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
}
