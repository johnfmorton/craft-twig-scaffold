# Release Notes for Twig Scaffold

## 1.0.0 - 2026-09-04

### Added

- Initial release: a **Twig Scaffold** utility in the control panel that generates a starter Twig template for any entry type from its field layout. Every field type that ships with Craft (plus CKEditor and Redactor) gets a sensible render; Matrix fields become nested loops with a `{% switch %}` on block type, or `.render()` calls for partial templates. Unknown field types are named in a comment rather than dropped.
- **Renderers for third-party field types.** A plugin can ship `src/twig-scaffold.php`, mapping its field classes to the Twig that Twig Scaffold should write for them (a string, a list of lines, `twig` + `guard`, or a closure that can inspect the field's settings), with `$value`, `$element`, `$handle`, and `$label` placeholders. Sites can do the same for any field type, Craft's own included, in `config/twig-scaffold.php`, and modules can register renderers with the `Renderers::EVENT_REGISTER_FIELD_RENDERERS` event. Lookups match a field's class or any parent class, and an invalid renderer is reported in a Twig comment rather than breaking generation.
- **Integration guide.** `INTEGRATION.md` documents the `twig-scaffold.php` file for plugin developers: where it goes, the renderer forms, placeholders, guards, class matching, advice on writing a good scaffold, and how to test it. The README points site and plugin developers to it.
