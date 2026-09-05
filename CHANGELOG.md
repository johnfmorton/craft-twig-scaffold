# Release Notes for Twig Scaffold

## 1.1.0 - Unreleased

### Added

- **Bundled renderers for popular plugins.** Fields from Hyper, SEO, oEmbed, Maps, Table Maker, Code Field, and Linkit now get sensible Twig instead of the "no built-in rendering" comment. The renderers live in `src/renderers/<handle>.php` in the same format as a plugin's `twig-scaffold.php`, so a plugin author can copy one into their plugin to own it. They are the lowest-precedence source: a renderer shipped by the plugin, an event handler, or the site's `config/twig-scaffold.php` overrides them.
- **Neo support.** Neo fields become `{% for %}` loops over `level(1)` blocks with a `{% switch %}` on block type, and every block type that allows child blocks gets a nested `{% for child in block.children.all() %}` over the types it allows, as deep as the field's max levels permit. A block type that can contain itself is cut off with a comment, and very large structures are left as a generic child loop past thirty block bodies. In partial-templates mode the field renders with `.render()` and a comment lists the `templates/_partials/neoblock/<blockTypeHandle>.twig` partials to write. Neo does not need to be installed for the plugin to load.
- **Super Table support.** Super Table fields are Matrix fields in Craft 5, so they are scaffolded exactly like Matrix fields, and their entry types are listed under "Nested entry types".
- Matrix (and Super Table) fields limited to one block are written as `{% set block = entry.myField.one() %}{% if block %}...{% endif %}` instead of a loop.

### Changed

- The "Matrix fields" mode setting is now labelled "Block fields", since it also applies to Neo and Super Table.
- `INTEGRATION.md` documents the bundled layer in the precedence order and how a plugin author adopts a bundled file.

## 1.0.0 - 2026-09-04

### Added

- Initial release: a **Twig Scaffold** utility in the control panel that generates a starter Twig template for any entry type from its field layout. Every field type that ships with Craft (plus CKEditor and Redactor) gets a sensible render; Matrix fields become nested loops with a `{% switch %}` on block type, or `.render()` calls for partial templates. Unknown field types are named in a comment rather than dropped.
- **Renderers for third-party field types.** A plugin can ship `src/twig-scaffold.php`, mapping its field classes to the Twig that Twig Scaffold should write for them (a string, a list of lines, `twig` + `guard`, or a closure that can inspect the field's settings), with `$value`, `$element`, `$handle`, and `$label` placeholders. Sites can do the same for any field type, Craft's own included, in `config/twig-scaffold.php`, and modules can register renderers with the `Renderers::EVENT_REGISTER_FIELD_RENDERERS` event. Lookups match a field's class or any parent class, and an invalid renderer is reported in a Twig comment rather than breaking generation.
- **Integration guide.** `INTEGRATION.md` documents the `twig-scaffold.php` file for plugin developers: where it goes, the renderer forms, placeholders, guards, class matching, advice on writing a good scaffold, and how to test it. The README points site and plugin developers to it.
