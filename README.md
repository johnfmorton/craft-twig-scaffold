# Twig Scaffold

Twig Scaffold is a control panel utility for Craft CMS 5 that writes a starter Twig template for any entry type, straight from its field layout. Pick an entry type, click **Generate Twig**, copy the result into your templates folder, and edit from there.

It exists to skip the tedious part of templating: typing out every field handle, every Matrix loop, and every block-type branch before you can see anything on the page. The generated template is a starting point, not a finished design.

## Requirements

This plugin requires Craft CMS 5.11.0 or later, and PHP 8.2 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Twig Scaffold”. Then press “Install”.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require johnfmorton/craft-twig-scaffold

# tell Craft to install the plugin
./craft plugin/install twig-scaffold
```

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
| Matrix | A `{% for %}` loop nested as deep as the content model goes, with `{% switch %}` on block type when a field has several |
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

### Matrix fields: inline loops or partial templates

- **Inline loops** (the default) writes everything into one template: a `{% for %}` loop for each Matrix field, a `{% switch %}` on `block.type.handle` when the field has more than one block type, and the same again for Matrix fields nested inside blocks. An entry type that contains itself is cut off with a comment rather than looping forever.
- **Partial templates** writes `{{ entry.myMatrixField.render() }}` instead. Craft then renders each block with its own partial template, `templates/_partials/entry/<entryTypeHandle>.twig` (the folder follows your `partialTemplatesPath` config setting). Generate those partials with this utility too: pick the block’s entry type under **Nested entry types**.

## Permissions

Twig Scaffold shows up in the Utilities section for admins and for any user group with the **Utilities → Twig Scaffold** permission. The generator only reads your field layouts; it never touches content.
