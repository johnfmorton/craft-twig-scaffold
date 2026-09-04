# Adding Twig Scaffold support to your plugin

[Twig Scaffold](https://github.com/johnfmorton/craft-twig-scaffold) is a Craft CMS 5 control panel utility that writes a starter Twig template for an entry type from its field layout. It knows how to render every field type that ships with Craft, plus CKEditor and Redactor. For a field type from another plugin, all it can write is a comment:

```twig
{# Podcast Audio (Podcast Audio Field) #}
{# No built-in rendering for this field type. Try {{ entry.podcastAudio }} or see the field's documentation. #}
```

If your plugin provides a field type, you can ship one small file that tells Twig Scaffold what to write instead. This guide covers that file: where it goes, what it can contain, and how to test it.

**In short:** add `src/twig-scaffold.php` to your plugin, returning an array that maps each of your field classes to the Twig it should be rendered with. That's it. There is nothing to require in `composer.json`, nothing to register in your plugin's `init()`, and the file is never read unless Twig Scaffold is installed and someone clicks **Generate Twig**.

## Contents

- [Quick start](#quick-start)
- [Where the file goes](#where-the-file-goes)
- [Renderer forms](#renderer-forms)
- [Placeholders](#placeholders)
- [Guards](#guards)
- [How fields are matched](#how-fields-are-matched)
- [Writing a good scaffold](#writing-a-good-scaffold)
- [Fields with nothing to render](#fields-with-nothing-to-render)
- [Testing your file](#testing-your-file)
- [Other ways to register renderers](#other-ways-to-register-renderers)
- [Reference](#reference)

## Quick start

Say your plugin has a `PodcastAudioField` whose value is an object with a `url` property. Create `src/twig-scaffold.php`:

```php
<?php

use acme\podcast\fields\PodcastAudioField;

return [
    PodcastAudioField::class => '<audio controls src="{{ $value.url }}"></audio>',
];
```

With that file in place, a developer who scaffolds an entry type containing your field gets:

```twig
{# Podcast Audio (Podcast Audio Field) #}
<audio controls src="{{ entry.podcastAudio.url }}"></audio>
```

`$value` was replaced with the expression that reaches the field's value. Inside a Matrix loop it would have been `block.podcastAudio` instead, and the line would have been indented to match. The comment above the output is written by Twig Scaffold from the field's name and type; you don't need to include one.

## Where the file goes

Put the file at `src/twig-scaffold.php`, alongside your plugin's `templates/` and `translations/` folders. That is the plugin's base path, which is always part of the installed package, whether it was installed from Packagist, the Plugin Store, or a Composer path repository.

Twig Scaffold also checks the plugin's repository root (the folder that holds `composer.json`) as a fallback, but be aware that a root-level file is only shipped if it isn't excluded by your `.gitattributes` `export-ignore` rules. `src/` is the safe choice.

Only installed, enabled plugins are scanned. If your plugin is disabled, its file is ignored.

## Renderer forms

The file returns an array whose keys are field class names and whose values are renderers. A renderer can take any of four forms, from simplest to most capable.

### A string of Twig

```php
return [
    PodcastAudioField::class => '<audio controls src="{{ $value.url }}"></audio>',
];
```

A string may contain newlines; each line is written out and indented to match where the field appears. Leading whitespace on your lines is kept, so relative indentation inside the string survives.

### A list of lines

The same as a multi-line string, but easier to read in PHP:

```php
return [
    ChapterListField::class => [
        '<ol class="chapters">',
        '    {% for chapter in $value %}',
        '        <li>{{ chapter.title }} ({{ chapter.start|time("short") }})</li>',
        '    {% endfor %}',
        '</ol>',
    ],
];
```

A list is any array with sequential integer keys. Every item must be a string.

### `twig` with a `guard`

An associative array with a `twig` key (a string or a list of lines, as above) and an optional `guard`: a Twig condition that the output is wrapped in.

```php
return [
    ChapterListField::class => [
        'twig' => [
            '<ol class="chapters">',
            '    {% for chapter in $value %}',
            '        <li>{{ chapter.title }} ({{ chapter.start|time("short") }})</li>',
            '    {% endfor %}',
            '</ol>',
        ],
        'guard' => '$value|length',
    ],
];
```

That generates:

```twig
{% if entry.chapters|length %}
    <ol class="chapters">
        {% for chapter in entry.chapters %}
            <li>{{ chapter.title }} ({{ chapter.start|time("short") }})</li>
        {% endfor %}
    </ol>
{% endif %}
```

### A closure

When the right Twig depends on the field's settings, use a closure. It receives the field instance (with the handle, name, and required flag as they are in the layout being scaffolded), the value expression, and whether the field is required, and it returns any of the three forms above. Returning `null` tells Twig Scaffold to fall back to its built-in output for that field.

```php
use craft\base\FieldInterface;

return [
    GalleryField::class => function(FieldInterface $field, string $expression, bool $required): ?array {
        // A single-image gallery renders one <img>; a multi-image gallery loops.
        if ($field->maxImages === 1) {
            return [
                'twig' => "{{ {$expression}.one().img }}",
                'guard' => "{$expression}.exists()",
            ];
        }

        return [
            'twig' => [
                "{% for image in {$expression}.all() %}",
                '    {{ image.img }}',
                '{% endfor %}',
            ],
        ];
    },
];
```

Inside a closure you already have the expression as a PHP string, so you can build the Twig directly. The placeholders below still work in whatever the closure returns, if you prefer them.

Type-hint the closure with `craft\base\FieldInterface`, not with anything from Twig Scaffold, so your plugin has no dependency on it.

## Placeholders

These tokens are replaced in `twig` and `guard` strings, and in whatever a closure returns:

| Placeholder | Replaced with | Example |
| --- | --- | --- |
| `$value` | The expression for the field's value | `entry.podcastAudio`, or `block.podcastAudio` inside a Matrix loop |
| `$element` | The variable holding the element the field belongs to | `entry`, `block`, `block2` |
| `$handle` | The field's handle in the layout being scaffolded | `podcastAudio` |
| `$label` | The field's name in that layout, HTML-encoded | `Podcast Audio` |

Twig has no `$` syntax of its own, so the tokens can't collide with real Twig. If your field's handle or label was overridden in a particular field layout, `$handle` and `$label` reflect the override.

## Guards

A guard keeps the generated template from failing or printing empty tags when the field has no value. Whether you need one depends on what your field's value looks like when it's empty:

- If the value can be `null` and your Twig reads a property or calls a method on it (`$value.url`), guard with `$value`. Craft runs Twig in strict mode in dev environments, so `null.url` is an error, not an empty string.
- If the value is an array or a collection, guard with `$value|length` to avoid an empty wrapper element.
- If the value is a date, always guard. Twig's `date` filter treats `null` as the current time, which is a silent bug rather than an error.
- If the value is a plain string or number, a guard is optional. Twig Scaffold's own scalar renderers use `$value is not empty` for optional fields and none for required ones.

Guards given in a string or array renderer are always applied, even when the field is required in its layout. If you want to skip the guard for required fields, use a closure and check the `$required` argument.

The guard is a raw Twig expression, so anything is allowed: `$value.url is defined and $value.url` guards a value that might be an array with a missing key.

## How fields are matched

Twig Scaffold looks up a field by its exact class first, then walks its parent classes. So a renderer registered for a base class covers every field that extends it, and a renderer for a subclass overrides one for its parent. Interfaces are not matched.

Use `::class` for the keys. It compiles to a string and does not autoload anything, so it is safe even in a file that is loaded before your plugin's classes are.

Class names are compared case-insensitively, and a leading backslash is ignored.

## Writing a good scaffold

The developer will edit whatever you generate, so aim for the clearest starting point rather than the fanciest markup:

- **Show the value, plainly.** Use the simplest HTML that makes the field visible on a page. Skip framework-specific class names and site-specific wrappers; they'll be replaced anyway.
- **Use your field's documented public API.** Prefer the accessors your field's own documentation recommends (`$value.url`, `$value.all()`), so the generated Twig reads like your docs.
- **Query once.** If your value is an element query, fetch it into a variable before looping and checking emptiness, rather than calling `.exists()` and then `.all()`:

  ```php
  'twig' => [
      '{% set episodes = $value.all() %}',
      '{% if episodes|length %}',
      '    <ul>',
      '        {% for episode in episodes %}',
      '            <li>{{ episode.title }}</li>',
      '        {% endfor %}',
      '    </ul>',
      '{% endif %}',
  ],
  ```

- **Name loop variables after the handle when it helps**, but be aware that another field in the same template could use the same name. Twig `{% for %}` variables are scoped to the loop, so collisions between sibling loops are harmless; only a `{% set %}` at the top level lingers.
- **Add a comment for decisions the developer has to make.** If your field can be rendered several ways, generate the most common one and mention the alternative in a Twig comment on the line above:

  ```php
  'twig' => [
      '{# For a download link instead of a player, use {{ $value.downloadUrl }}. #}',
      '<audio controls src="{{ $value.url }}"></audio>',
  ],
  ```

- **Don't assume includes or macros exist.** The generated template must render in a fresh project with nothing but your plugin installed.
- **Keep it short.** A few lines that work beat a complete component the developer has to read through first.

## Fields with nothing to render

Some field types are control panel tools that store nothing meaningful for the front end. The right scaffold for those is a comment that tells the developer where to look instead. For example, the Bespoken text-to-speech plugin saves its audio as an Asset in a volume, not in its field, so its file is:

```php
<?php

use johnfmorton\bespoken\fields\BespokenField;

return [
    BespokenField::class => [
        '{# Bespoken saves the generated narration as an Asset in the volume chosen in its settings; nothing is stored in this field for the front end. #}',
        '{# Relate the audio to the entry with an Assets field and render that instead, e.g. <audio controls src="{{ audio.url }}"></audio>. #}',
    ],
];
```

That is still a big improvement over the default "no built-in rendering" comment: the developer learns what the field is for and what to do next, without leaving the template.

## Testing your file

1. Install Twig Scaffold in a local Craft 5 project alongside your plugin:

   ```bash
   composer require johnfmorton/craft-twig-scaffold
   ./craft plugin/install twig-scaffold
   ```

2. Add your field to an entry type's field layout.
3. Go to **Utilities → Twig Scaffold**, pick that entry type, and click **Generate Twig**.
4. Paste the output into a template and load an entry, so you see it rendered against real content. Test with an empty value too.

The file is read fresh on every click, so you can edit it and generate again without clearing any cache.

If something in the file is wrong, Twig Scaffold says so in the generated output rather than failing:

```twig
{# Podcast Audio (Podcast Audio Field) #}
{# Twig Scaffold: the renderer registered by the Podcast plugin is invalid (a renderer must be a string, an array, or a closure, int given), so the built-in output follows. #}
{# No built-in rendering for this field type. Try {{ entry.podcastAudio }} or see the field's documentation. #}
```

The same message is logged with Craft's `warning` level. A file that throws while being loaded, or that doesn't return an array, is logged and ignored in its entirety.

## Other ways to register renderers

The file is the recommended route for plugins that own a field type. Two other routes use the same renderer forms and placeholders:

- **An event**, for modules or plugins that want to describe field types they don't own. This one does reference Twig Scaffold's classes, so only use it where Twig Scaffold is a known dependency:

  ```php
  use johnfmorton\crafttwigscaffold\events\RegisterFieldRenderersEvent;
  use johnfmorton\crafttwigscaffold\services\Renderers;
  use yii\base\Event;

  Event::on(Renderers::class, Renderers::EVENT_REGISTER_FIELD_RENDERERS, function(RegisterFieldRenderersEvent $event) {
      $event->renderers[SomeField::class] = '<p>{{ $value }}</p>';
  });
  ```

- **A site-level file**, `config/twig-scaffold.php` in the Craft project, for site developers. It can describe field types whose plugins don't ship a file, and it can override any renderer, including Twig Scaffold's built-in ones for Craft's own fields. It supports Craft's multi-environment config keys (`'*'`, `'dev'`, and so on) like any other file in `config/`.

Precedence, lowest to highest: Twig Scaffold's built-in output, plugin `twig-scaffold.php` files, event handlers, the site's `config/twig-scaffold.php`. Within that, the most specific class match wins: a renderer for a subclass beats one for its parent, wherever each was registered.

## Reference

```php
<?php
// src/twig-scaffold.php

use craft\base\FieldInterface;

return [
    // Field class name => renderer. Use ::class; keys are compared case-insensitively.

    // 1. A string of Twig, possibly multi-line.
    \Vendor\Plugin\fields\SimpleField::class => '<p>{{ $value }}</p>',

    // 2. A list of Twig lines.
    \Vendor\Plugin\fields\ListField::class => [
        '<ul>',
        '    {% for item in $value %}',
        '        <li>{{ item }}</li>',
        '    {% endfor %}',
        '</ul>',
    ],

    // 3. `twig` (string or list) with an optional `guard` condition.
    \Vendor\Plugin\fields\ObjectField::class => [
        'twig' => '<a href="{{ $value.url }}">{{ $value.label }}</a>',
        'guard' => '$value',
    ],

    // 4. A closure returning any of the above, or null for the built-in output.
    \Vendor\Plugin\fields\ConfigurableField::class => function(FieldInterface $field, string $expression, bool $required): string|array|null {
        return null;
    },
];
```

Placeholders in `twig` and `guard`: `$value`, `$element`, `$handle`, `$label`.

Requirements: the same as Twig Scaffold, Craft CMS 5.11 or later on PHP 8.2 or later. Your plugin does not need to require Twig Scaffold.
