# Plugin Store listing

Copy for the Twig Scaffold listing in Craft Console. The screenshots in this folder are the listing images, in order.

The Plugin Store doesn't read this file. Update it with each release, then paste the changed sections into Craft Console by hand.

**Written for:** 1.4.1. **Live listing last checked:** 2026-11-12, when it still showed the 1.3.1 copy (no Craft 6 mention).

**Changed since the live copy:** the 1.4.0 Craft 6 edits (Extensible bullet, new "Craft 5 and Craft 6" bullet, requirements line, `craft 6` keyword) and, in 1.4.1, "your `templates/` folder" → "your templates folder" in the first paragraph.

## Short description

Generates a starter Twig template for any entry type from its field layout, blocks and all.

## Categories

Templating, Development

## Keywords

twig, template, scaffold, generator, matrix, neo, super table, field layout, boilerplate, craft 6

## Long description

Everything below the rule is the Markdown for the long description field.

---

Twig Scaffold writes the first draft of an entry template so you don’t have to. Pick an entry type in **Utilities → Twig Scaffold**, click **Generate Twig**, and copy a template that already renders every field in the layout: text, images, relations, dates, options, tables, and Matrix, Neo and Super Table blocks nested as deep as your content model goes. Paste it into your templates folder and start designing instead of typing field handles.

## What you get

- **Every field, sensibly rendered.** Plain Text becomes a paragraph, Assets become `<img>` tags with `alt`, `width` and `height`, relations become linked lists, dates get a `<time>` tag, dropdowns print their label, and tables get headings and rows. Each block of Twig carries a comment naming the field and its type, so you can find your way around.
- **Block fields, your way.** Choose inline `{% for %}` loops with a `{% switch %}` on block type, or partial templates: the main template calls `render()` and Twig Scaffold generates a starter partial for every block type it relies on, at the paths Craft expects.
- **Guards where they belong.** Optional fields are wrapped in `{% if %}` so empty values don’t leave empty tags behind. Dates, links and colors are always guarded.
- **Eager loading built in.** Relation and block queries are written with `.eagerly()`, so a gallery inside a Matrix loop costs one query, not one per block.
- **Plugin fields covered.** Built-in output for CKEditor, Redactor, Neo and Super Table, plus bundled renderers for Hyper, SEO, oEmbed, Maps, Table Maker, Code Field and Linkit. Any other field type gets a comment naming it, so nothing is dropped silently.
- **Extensible.** Site developers map field classes to Twig in `config/twig-scaffold.php` (`config/craft/twig-scaffold.php` on Craft 6). Plugin authors describe their own field types with a `twig-scaffold.php` file or an event.
- **Craft 5 and Craft 6.** One release runs on both. On Craft 6 it works through Craft’s Yii2 adapter, and the generated templates are the same on either version.

## Who it’s for

Developers starting a new section, or picking up a site with a large content model. The generated template is a starting point, not a finished design: it gives you working markup for every field on the first page load, and you take it from there.

Requires Craft CMS 5.11 or later with PHP 8.2, or Craft CMS 6 with PHP 8.5. Free and MIT licensed.
