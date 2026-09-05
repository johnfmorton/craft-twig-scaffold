<?php

/**
 * Twig Scaffold's bundled renderer for the Code Field plugin
 * (nystudio107/craft-code-field).
 *
 * Code Field maintainers: to own this, copy the file into your plugin as
 * src/twig-scaffold.php, swap the string key for Code::class, and edit
 * freely. A file shipped by the plugin overrides this one. See INTEGRATION.md
 * in johnfmorton/craft-twig-scaffold.
 *
 * The class name is a string because Code Field is not a dependency of Twig
 * Scaffold.
 */

return [
    'nystudio107\codefield\fields\Code' => [
        'twig' => [
            '{# Twig escapes the code so it displays as text. The language-* class is what Prism and highlight.js look for. #}',
            '<pre><code class="language-{{ $value.language }}">{{ $value.value }}</code></pre>',
        ],
        'guard' => '$value.value is not empty',
    ],
];
