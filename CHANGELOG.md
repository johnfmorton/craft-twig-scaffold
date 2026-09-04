# Release Notes for Twig Scaffold

## 1.0.0 - Unreleased

### Added

- Initial release: a **Twig Scaffold** utility in the control panel that generates a starter Twig template for any entry type from its field layout. Every field type that ships with Craft (plus CKEditor and Redactor) gets a sensible render; Matrix fields become nested loops with a `{% switch %}` on block type, or `.render()` calls for partial templates. Unknown field types are named in a comment rather than dropped.
