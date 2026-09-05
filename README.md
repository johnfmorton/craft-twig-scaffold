# Twig Scaffold

Twig Scaffold is a control panel utility for Craft CMS 5 that writes a starter Twig template for any entry type, straight from its field layout. Pick an entry type, click **Generate Twig**, copy the result into your templates folder, and edit from there.

It exists to skip the tedious part of templating: typing out every field handle, every Matrix loop, and every block-type branch before you can see anything on the page. The generated template is a starting point, not a finished design.

## Requirements

This plugin requires Craft CMS 5.11.0 or later, and PHP 8.2 or later.

## Installation

Twig Scaffold is not yet listed in the Craft Plugin Store, so for now install it with Composer. The package is published on [Packagist](https://packagist.org/packages/johnfmorton/craft-twig-scaffold), so no extra `repositories` entry is needed in your project’s `composer.json`.

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require johnfmorton/craft-twig-scaffold

# tell Craft to install the plugin
./craft plugin/install twig-scaffold
```

Once the plugin is listed in the Plugin Store, you will also be able to go to the Plugin Store in your project’s control panel, search for “Twig Scaffold”, and press “Install”.

## Usage

1. In the control panel, go to **Utilities → Twig Scaffold**.
2. Choose an entry type. They are grouped by section; entry types that only exist inside Matrix or CKEditor fields are listed under **Nested entry types**.
3. Choose how Matrix fields should be written (see below) and click **Generate Twig**.
4. Click **Copy to clipboard** and paste the template into your `templates/` folder. The utility suggests a location: the section’s template setting, or the partial template path for a nested entry type.

The entry is available in the template as `entry`, which is what Craft provides in section templates and in partial templates rendered with `render()`.

### What gets generated

Every custom field in the layout gets a block of Twig with a comment naming the field and its type, so you can find your way around:

| Field type | Output |
| --- | --- |
| Plain Text | `<p>{{ entry.handle }}</p>`, with `\|nl2br` for multi-line fields |
| CKEditor, Redactor | `{{ entry.handle }}` (and a note when the field has nested entries, which render via their partial templates) |
| Assets | An `<img>` with `alt`, `width` and `height` for images, a link for other files; a single `<img>` when the field allows one asset |
| Entries, Categories, Tags, Users | A `<ul>` of linked titles (usernames for users), fetched once into a variable so an empty list costs one query |
| Matrix, Super Table | A `{% for %}` loop nested as deep as the content model goes, with `{% switch %}` on block type when a field has several; a `{% set %}` and `{% if %}` when the field allows one block |
| Neo | A `{% for %}` over `level(1)` blocks with `{% switch %}` on block type, and a nested `{% for child in block.children.all() %}` for every block type that allows children, as deep as the field’s max levels allow. A type that can contain itself is cut off with a comment |
| Content Block | The block’s fields, guarded by `{% if %}` |
| Date, Time | A `<time>` tag with a machine-readable `datetime` attribute, always guarded (Twig treats a null date as “now”) |
| Dropdown, Radio Buttons, Button Group | `{{ entry.handle.label }}` |
| Checkboxes, Multi-select | A `<ul>` of the selected labels |
| Lightswitch | `{% if entry.handle %}` with the field’s on/off labels |
| Table | A `<table>` with headings and a row loop, cells formatted by column type |
| Link | An `<a>` using the link’s `url` and `label` |
| Number, Range | The value with the field’s prefix and suffix |
| URL, Email | A link or `mailto:` link |
| Color, Country, Money, Icon, JSON | The hex value, country name, formatted amount, icon name, and pretty-printed JSON |
| Addresses | `{{ address\|address }}` for each address |
| Other field types | A comment naming the field, so nothing is dropped silently. Plain string or number values are printed. |

Fields that aren’t required are wrapped in `{% if %}` so empty values don’t leave empty tags behind. Fields whose values are objects (dates, links, colors) are always guarded. Layouts with several tabs get a comment per tab.

### Block fields: inline loops or partial templates

- **Inline loops** (the default) writes everything into one template: a `{% for %}` loop for each Matrix, Super Table or Neo field, a `{% switch %}` on `block.type.handle` when the field has more than one block type, and the same again for block fields nested inside blocks. An entry type or Neo block type that contains itself is cut off with a comment rather than looping forever.
- **Partial templates** writes `{{ entry.myMatrixField.render() }}` instead. Craft then renders each block with its own partial template: `templates/_partials/entry/<entryTypeHandle>.twig` for Matrix and Super Table blocks, and `templates/_partials/neoblock/<blockTypeHandle>.twig` for Neo blocks (the folder follows your `partialTemplatesPath` config setting). Generate the Matrix and Super Table partials with this utility too: pick the block’s entry type under **Nested entry types**. Neo block types are not listed there yet, so write those partials by hand; inside them the block is available as `neoblock`, and `{{ neoblock.children.render() }}` renders its child blocks.

## Custom field types

Twig Scaffold has built-in output for every field type that ships with Craft, plus CKEditor, Redactor, Neo and Super Table, and it bundles renderers for these popular plugins:

| Plugin | Output |
| --- | --- |
| Hyper | `{{ entry.handle.getLink() }}`, guarded by `isEmpty()`; a `<ul>` loop when the field allows multiple links |
| SEO | A comment: the SEO plugin writes the data to `<head>` through `{% hook "seo" %}`, so there is nothing to output in the body; it lists the properties for a custom meta template |
| oEmbed | `{{ entry.handle.render() }}` when the URL is valid |
| Maps | The address and coordinates, with a note on `embed()` and `img()` |
| Table Maker | A `<table>` looping the value’s columns and rows, cells formatted by column type |
| Code Field | `<pre><code class="language-…">` with the code, escaped so it displays as text |
| Linkit | `{{ entry.handle.getLink() }}` when the link is available |

Those renderers live in [`src/renderers/`](src/renderers/), one file per plugin, in the same format as a plugin’s own `twig-scaffold.php`, so a plugin author can copy one into their plugin and own it; a file shipped by the plugin itself overrides the bundled one. Super Table fields are Matrix fields in Craft 5, so they work exactly as Matrix fields do. Field Manager adds no field types, so there is nothing for it to render.

A field type from any other plugin gets a comment instead:

```twig
{# Podcast Audio (Podcast Audio Field) #}
{# No built-in rendering for this field type. Try {{ entry.podcastAudio }} or see the field's documentation. #}
```

Both plugin developers and site developers can fix that.

### Site developers: `config/twig-scaffold.php`

Create `config/twig-scaffold.php` in your project, mapping field classes to the Twig you want generated for them. `$value` stands for the expression that reaches the field's value:

```php
<?php
// config/twig-scaffold.php

return [
    // A field type from a plugin that doesn't describe itself yet.
    acme\podcast\fields\PodcastAudioField::class => [
        'twig' => '<audio controls src="{{ $value.url }}"></audio>',
        'guard' => '$value',
    ],

    // Override one of Craft's own field types, here to use your picture macro.
    craft\fields\Assets::class => [
        "{% import '_macros/media' as media %}",
        '{% for asset in $value.all() %}',
        '    {{ media.picture(asset) }}',
        '{% endfor %}',
    ],
];
```

This file has the last word: it overrides renderers shipped by plugins, the ones Twig Scaffold bundles, and its built-in output for Craft's own fields. The full format, including closures for settings-dependent output and the `$element`, `$handle`, and `$label` placeholders, is documented in [INTEGRATION.md](INTEGRATION.md).

### Plugin developers: add Twig Scaffold support to your plugin

If your plugin provides a field type, ship a `src/twig-scaffold.php` file that tells Twig Scaffold how to render it. It's a plain PHP array, it adds no dependency on Twig Scaffold, and it's only read when someone clicks **Generate Twig**:

```php
<?php
// src/twig-scaffold.php in your plugin

use acme\podcast\fields\PodcastAudioField;

return [
    PodcastAudioField::class => '<audio controls src="{{ $value.url }}"></audio>',
];
```

If Twig Scaffold already bundles a renderer for your field type, your file wins, and the bundled one is a ready-made starting point to copy.

**[Read the integration guide](INTEGRATION.md)** for the file's location, the four renderer forms (string, list of lines, `twig` + `guard`, closure), placeholders, matching by parent class, guidance on writing a good scaffold, how to adopt a bundled renderer, how to test, and the event-based alternative for modules.

## Permissions

Twig Scaffold shows up in the Utilities section for admins and for any user group with the **Utilities → Twig Scaffold** permission. The generator only reads your field layouts; it never touches content.
