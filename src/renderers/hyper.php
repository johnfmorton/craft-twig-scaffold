<?php

/**
 * Twig Scaffold's bundled renderer for the Hyper plugin (verbb/hyper).
 *
 * Hyper maintainers: to own this, copy the file into your plugin as
 * src/twig-scaffold.php, swap the string key for HyperField::class, and edit
 * freely. A file shipped by the plugin overrides this one. See INTEGRATION.md
 * in johnfmorton/craft-twig-scaffold.
 *
 * The class name is a string, and settings are read through getSettings(),
 * because Hyper is not a dependency of Twig Scaffold.
 */

use craft\base\FieldInterface;

return [
    'verbb\hyper\fields\HyperField' => function(FieldInterface $field, string $expression, bool $required): array {
        // With "Multiple links" on, the value is a collection to loop; off, it stands for its one link.
        if ($field->getSettings()['multipleLinks'] ?? false) {
            return [
                'twig' => [
                    '<ul>',
                    '    {% for link in $value %}',
                    '        <li>{{ link.getLink() }}</li>',
                    '    {% endfor %}',
                    '</ul>',
                ],
                'guard' => 'not $value.isEmpty()',
            ];
        }

        return [
            'twig' => [
                '{# getLink() writes the whole <a>: text, target, rel, classes, and custom attributes. For the parts, use $value.url, $value.text, and $value.target. #}',
                '{{ $value.getLink() }}',
            ],
            'guard' => 'not $value.isEmpty()',
        ];
    },
];
