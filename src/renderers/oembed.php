<?php

/**
 * Twig Scaffold's bundled renderer for the oEmbed plugin (wrav/oembed).
 *
 * oEmbed maintainers: to own this, copy the file into your plugin as
 * src/twig-scaffold.php, swap the string key for OembedField::class, and edit
 * freely. A file shipped by the plugin overrides this one. See INTEGRATION.md
 * in johnfmorton/craft-twig-scaffold.
 *
 * The class name is a string because oEmbed is not a dependency of Twig
 * Scaffold.
 */

return [
    'wrav\oembed\fields\OembedField' => [
        'twig' => [
            '{# render() takes options, e.g. $value.render({ width: 640, height: 360, params: { autoplay: 0 } }). $value.url is the URL as entered. #}',
            '{{ $value.render() }}',
        ],
        'guard' => '$value and $value.valid',
    ],
];
