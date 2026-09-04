<?php

return [
    'Twig Scaffold' => 'Twig Scaffold',
    'Utility intro' => 'Pick an entry type and Twig Scaffold writes a starter Twig template that renders every field in its layout: text, images, relations, dates, options, tables, and Matrix blocks nested as deep as the content model goes. Copy it into your templates folder and edit from there.',
    'There are no entry types to scaffold yet.' => 'There are no entry types to scaffold yet.',
    'Entry type' => 'Entry type',
    'Entry type instructions' => 'Grouped by section. Entry types used only inside Matrix or CKEditor fields are listed under “Nested entry types”; scaffold those to write the partial templates Craft renders them with.',
    'Nested entry types' => 'Nested entry types',
    'Unused entry types' => 'Unused entry types',
    'Matrix fields' => 'Matrix fields',
    'Matrix mode instructions' => '**Inline loops** writes a `{% for %}` loop for every Matrix field, branching on block type, so the whole entry renders from one template. **Partial templates** writes `{{ entry.myMatrixField.render() }}` instead, and Craft renders each block with its own partial template in `templates/_partials/entry/`, which you can generate here too.',
    'Inline loops' => 'Inline loops',
    'Partial templates' => 'Partial templates',
    'Generate Twig' => 'Generate Twig',
    'Generated template' => 'Generated template',
    'Suggested location: {path}' => 'Suggested location: {path}',
    'Copy to clipboard' => 'Copy to clipboard',
    'Copied to clipboard.' => 'Copied to clipboard.',
    'Could not copy. Select the text and copy it yourself.' => 'Could not copy. Select the text and copy it yourself.',
    'Could not generate the template.' => 'Could not generate the template.',
    'Entry type not found.' => 'Entry type not found.',
];
