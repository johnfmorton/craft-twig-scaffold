<?php

/**
 * Twig Scaffold's bundled renderer for the Maps plugin (ether/simplemap).
 *
 * Maps maintainers: to own this, copy the file into your plugin as
 * src/twig-scaffold.php, swap the string key for MapField::class, and edit
 * freely. A file shipped by the plugin overrides this one. See INTEGRATION.md
 * in johnfmorton/craft-twig-scaffold.
 *
 * The class name is a string because Maps is not a dependency of Twig
 * Scaffold.
 */

return [
    'ether\simplemap\fields\MapField' => [
        'twig' => [
            '{# Maps can draw the map too: $value.embed({ id: "map" }) for an interactive map, $value.img({ width: 640, height: 400 }) for a static image; both need a map API configured in the plugin settings. Parts: $value.parts.city, $value.address(["country"], ", "). #}',
            '<p>{{ $value.address }}</p>',
            '<p>{{ $value.lat }}, {{ $value.lng }}</p>',
        ],
        'guard' => 'not $value.isValueEmpty()',
    ],
];
