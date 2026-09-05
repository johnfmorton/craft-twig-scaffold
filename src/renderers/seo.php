<?php

/**
 * Twig Scaffold's bundled renderer for the SEO plugin (ether/seo).
 *
 * SEO maintainers: to own this, copy the file into your plugin as
 * src/twig-scaffold.php, swap the string key for SeoField::class, and edit
 * freely. A file shipped by the plugin overrides this one. See INTEGRATION.md
 * in johnfmorton/craft-twig-scaffold.
 *
 * The class name is a string, and settings are read through getSettings(),
 * because SEO is not a dependency of Twig Scaffold.
 */

use craft\base\FieldInterface;

return [
    'ether\seo\fields\SeoField' => function(FieldInterface $field, string $expression, bool $required): array {
        // The field's data goes in the <head>, which the plugin's hook writes,
        // so a body template has nothing to output. Say so, and point at the
        // properties for a custom meta template.
        $lines = [
            '{# SEO data belongs in the <head>. The SEO plugin writes it there when your layout calls {% hook "seo" %}, so there is normally nothing to output here. #}',
        ];
        if ($field->handle !== 'seo') {
            $lines[] = '{# The hook reads the field with the handle "seo". This one is "$handle", so in a custom meta template (SEO settings, Meta Template) fetch it with getSeoField("$handle"). #}';
        }
        $properties = '$value.title, $value.description, $value.canonical, and $value.robots';
        if (!($field->getSettings()['hideSocial'] ?? false)) {
            $properties .= ', plus $value.social.facebook and $value.social.twitter (each with title, description, and image)';
        }
        $lines[] = "{# To write your own tags, use {$properties}. #}";

        return $lines;
    },
];
