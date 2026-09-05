<?php

/**
 * Twig Scaffold's bundled renderer for the Linkit plugin (presseddigital/linkit).
 *
 * Linkit maintainers: to own this, copy the file into your plugin as
 * src/twig-scaffold.php, swap the string keys for LinkitField::class, and
 * edit freely. A file shipped by the plugin overrides this one. See
 * INTEGRATION.md in johnfmorton/craft-twig-scaffold.
 *
 * The class names are strings because Linkit is not a dependency of Twig
 * Scaffold.
 */

$linkit = [
    'twig' => [
        '{# getLink() writes the <a> with the link\'s text and target. To build it yourself: <a href="{{ $value.url }}"{% if $value.target %} target="_blank" rel="noopener"{% endif %}>{{ $value.text }}</a> #}',
        '{{ $value.getLink() }}',
    ],
    'guard' => '$value and $value.isAvailable()',
];

return [
    'presseddigital\linkit\fields\LinkitField' => $linkit,
    // The namespace Linkit used before it moved to Pressed Digital.
    'fruitstudios\linkit\fields\LinkitField' => $linkit,
];
