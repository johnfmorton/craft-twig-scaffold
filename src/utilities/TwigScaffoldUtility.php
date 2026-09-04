<?php

namespace johnfmorton\crafttwigscaffold\utilities;

use Craft;
use craft\base\ElementContainerFieldInterface;
use craft\base\Utility;
use craft\models\EntryType;

/**
 * Twig Scaffold utility: pick an entry type, get a starter template.
 */
class TwigScaffoldUtility extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('twig-scaffold', 'Twig Scaffold');
    }

    public static function id(): string
    {
        return 'twig-scaffold';
    }

    public static function icon(): ?string
    {
        return 'file-code';
    }

    public static function contentHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('twig-scaffold/_utility.twig', [
            'entryTypeOptions' => self::entryTypeOptions(),
        ]);
    }

    /**
     * Options for the entry type select: one group per section, then the
     * entry types only used inside fields (Matrix, CKEditor), then any unused.
     *
     * @return array<int, array{optgroup: string}|array{value: int, label: string}>
     */
    private static function entryTypeOptions(): array
    {
        $entries = Craft::$app->getEntries();
        $options = [];
        $inSections = [];

        foreach ($entries->getAllSections() as $section) {
            $entryTypes = $section->getEntryTypes();
            if ($entryTypes === []) {
                continue;
            }
            $options[] = ['optgroup' => $section->name];
            foreach ($entryTypes as $entryType) {
                $options[] = ['value' => $entryType->id, 'label' => $entryType->name];
                $inSections[$entryType->id] = true;
            }
        }

        // Which fields use each entry type, in one pass rather than one per type.
        $usedByFields = [];
        $fields = Craft::$app->getFields();
        foreach ($fields->getNestedEntryFieldTypes() as $type) {
            /** @var ElementContainerFieldInterface $field */
            foreach ($fields->getFieldsByType($type) as $field) {
                foreach ($field->getFieldLayoutProviders() as $provider) {
                    if ($provider instanceof EntryType) {
                        $usedByFields[$provider->id][] = $field->name;
                    }
                }
            }
        }

        $nested = [];
        $unused = [];
        foreach ($entries->getAllEntryTypes() as $entryType) {
            if (isset($inSections[$entryType->id])) {
                continue;
            }
            if (isset($usedByFields[$entryType->id])) {
                $nested[] = [
                    'value' => $entryType->id,
                    'label' => sprintf('%s (%s)', $entryType->name, implode(', ', array_unique($usedByFields[$entryType->id]))),
                ];
            } else {
                $unused[] = ['value' => $entryType->id, 'label' => $entryType->name];
            }
        }

        if ($nested !== []) {
            $options[] = ['optgroup' => Craft::t('twig-scaffold', 'Nested entry types')];
            array_push($options, ...$nested);
        }
        if ($unused !== []) {
            $options[] = ['optgroup' => Craft::t('twig-scaffold', 'Unused entry types')];
            array_push($options, ...$unused);
        }

        return $options;
    }
}
