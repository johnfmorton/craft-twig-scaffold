<?php

namespace johnfmorton\crafttwigscaffold\services;

use Craft;
use craft\base\Component;
use craft\base\ElementContainerFieldInterface;
use craft\base\FieldInterface;
use craft\errors\FieldNotFoundException;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Addresses;
use craft\fields\Assets;
use craft\fields\BaseOptionsField;
use craft\fields\BaseRelationField;
use craft\fields\Categories;
use craft\fields\Color;
use craft\fields\ContentBlock;
use craft\fields\Country;
use craft\fields\Date;
use craft\fields\Email;
use craft\fields\Entries;
use craft\fields\Icon;
use craft\fields\Json;
use craft\fields\Lightswitch;
use craft\fields\Link;
use craft\fields\Matrix;
use craft\fields\MissingField;
use craft\fields\Money;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\fields\Range;
use craft\fields\Table;
use craft\fields\Tags;
use craft\fields\Time;
use craft\fields\Url;
use craft\fields\Users;
use craft\helpers\Html;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\Section;
use johnfmorton\crafttwigscaffold\TwigScaffold;

/**
 * Writes a starter Twig template for an entry type from its field layout.
 *
 * Every field in the layout gets a block of Twig that renders it in a plain,
 * sensible way: text fields in paragraphs, images as `<img>` tags, relations as
 * linked lists, dates in `<time>` tags, options by label, tables as tables, and
 * Matrix fields as loops nested as deep as the content model goes, branching on
 * block type where a Matrix field has more than one. Field types the generator
 * doesn't know are named in a comment so nothing is silently dropped.
 *
 * The output is a starting point to edit, not a finished template.
 */
class Generator extends Component
{
    /** Render Matrix fields as `{% for %}` loops, nested inline. */
    public const MATRIX_INLINE = 'inline';

    /** Render Matrix fields with `.render()`, so Craft uses each block type's partial template. */
    public const MATRIX_PARTIALS = 'partials';

    /** Nesting levels of Matrix and content block fields to descend into before giving up. */
    private const MAX_DEPTH = 10;

    private const INDENT = '    ';

    /** Base class of the CKEditor and Redactor fields; referenced by name so neither plugin is required. */
    private const HTML_FIELD_CLASS = 'craft\\htmlfield\\HtmlField';

    /** Variable names Craft or Twig defines, which a loop variable must not shadow. */
    private const RESERVED_VARIABLES = [
        'entry', 'object', 'element', 'loop', 'craft', 'now', 'today', 'tomorrow', 'yesterday',
        'currentUser', 'currentSite', 'siteName', 'siteUrl', 'systemName', 'view', 'devMode',
        '_self', '_context', '_charset',
    ];

    private string $matrixMode = self::MATRIX_INLINE;

    /**
     * Returns a starter template for the entry type.
     *
     * @param EntryType $entryType
     * @param string $matrixMode One of the `MATRIX_*` constants
     * @return string
     */
    public function forEntryType(EntryType $entryType, string $matrixMode = self::MATRIX_INLINE): string
    {
        $this->matrixMode = $matrixMode === self::MATRIX_PARTIALS ? self::MATRIX_PARTIALS : self::MATRIX_INLINE;

        $lines = $this->header($entryType);
        $lines[] = '';
        $this->appendEntryType($lines, $entryType, 'entry', 0, ['entry'], []);

        return implode("\n", $this->tidy($lines)) . "\n";
    }

    /**
     * Where the template for the entry type would normally live: the template
     * of a section that uses it, or the partial template path Craft's
     * `render()` looks for when the entry type is only used inside fields.
     *
     * @param EntryType $entryType
     * @return string
     */
    public function suggestedPath(EntryType $entryType): string
    {
        foreach ($entryType->findUsages() as $usage) {
            if (!$usage instanceof Section) {
                continue;
            }
            foreach ($usage->getSiteSettings() as $siteSettings) {
                if ($siteSettings->template) {
                    $template = ltrim($siteSettings->template, '/');
                    if (!str_contains(basename($template), '.')) {
                        $template .= '.twig';
                    }
                    return "templates/$template";
                }
            }
        }

        return $this->partialPath($entryType);
    }

    /**
     * The partial template path Craft checks first when rendering an entry of
     * this type with `render()`.
     */
    private function partialPath(EntryType $entryType): string
    {
        return sprintf(
            'templates/%s/entry/%s.twig',
            trim(Craft::$app->getConfig()->getGeneral()->partialTemplatesPath, '/'),
            $entryType->handle,
        );
    }

    /**
     * @return string[]
     */
    private function header(EntryType $entryType): array
    {
        $usages = [];
        foreach ($entryType->findUsages() as $usage) {
            if ($usage instanceof Section) {
                $usages[] = $usage->name . ' (section)';
            } elseif ($usage instanceof FieldInterface) {
                $usages[] = $usage->name . ' (' . $usage::displayName() . ' field)';
            }
        }

        $lines = [
            '{#',
            self::INDENT . self::comment($entryType->name) . ' (entry type "' . $entryType->handle . '")',
        ];
        if ($usages !== []) {
            $lines[] = self::INDENT . 'Used by: ' . self::comment(implode(', ', $usages));
        }
        $lines[] = self::INDENT . 'Starter template generated by Twig Scaffold. Edit freely; it is a starting point, not a finished template.';
        $lines[] = self::INDENT . 'The entry is available as `entry`. Suggested location: ' . $this->suggestedPath($entryType);
        $lines[] = '#}';

        return $lines;
    }

    /**
     * @param string[] $lines
     * @param string $var the Twig variable holding the entry
     * @param string[] $variables variable names already in scope
     * @param string[] $ancestors entry types and fields already being walked (cycle guard)
     */
    private function appendEntryType(array &$lines, EntryType $entryType, string $var, int $depth, array $variables, array $ancestors): void
    {
        $indent = str_repeat(self::INDENT, $depth);

        if ($entryType->hasTitleField) {
            $tag = $depth === 0 ? 'h1' : 'h2';
            $lines[] = "{$indent}<{$tag}>{{ {$var}.title }}</{$tag}>";
        }

        $ancestors[] = 'entryType:' . $entryType->id;
        $this->appendLayout($lines, $entryType->getFieldLayout(), $var, $depth, $variables, $ancestors);
    }

    /**
     * Appends a block of Twig for every custom field in the layout, tab by tab.
     *
     * @param string[] $lines
     * @param string[] $variables
     * @param string[] $ancestors
     */
    private function appendLayout(array &$lines, ?FieldLayout $layout, string $var, int $depth, array $variables, array $ancestors): void
    {
        $indent = str_repeat(self::INDENT, $depth);

        // Only tabs that hold custom fields matter here.
        $tabs = [];
        foreach ($layout?->getTabs() ?? [] as $tab) {
            $elements = array_filter($tab->getElements(), fn($element) => $element instanceof CustomField);
            if ($elements !== []) {
                $tabs[] = [$tab->name, $elements];
            }
        }
        $labelTabs = count($tabs) > 1;

        foreach ($tabs as [$tabName, $elements]) {
            if ($labelTabs) {
                $lines[] = '';
                $lines[] = "{$indent}{# Tab: " . self::comment($tabName) . ' #}';
            }

            /** @var CustomField $element */
            foreach ($elements as $element) {
                $lines[] = '';
                try {
                    // A clone of the field, with this layout's handle/label/required overrides applied.
                    $field = $element->getField();
                } catch (FieldNotFoundException) {
                    // The layout still lists a field that has been deleted.
                    $lines[] = "{$indent}{# The layout references a field that no longer exists (uid " . self::comment($element->getFieldUid()) . '). #}';
                    continue;
                }
                $lines[] = "{$indent}{# " . self::comment($field->name) . ' (' . self::comment($field::displayName()) . ') #}';
                $this->appendField($lines, $field, $element->required, $var, $depth, $variables, $ancestors);
            }
        }
    }

    /**
     * @param string[] $lines
     * @param string[] $variables
     * @param string[] $ancestors
     */
    private function appendField(array &$lines, FieldInterface $field, bool $required, string $var, int $depth, array $variables, array $ancestors): void
    {
        $indent = str_repeat(self::INDENT, $depth);
        $expr = "{$var}.{$field->handle}";

        if ($field instanceof MissingField) {
            $lines[] = "{$indent}{# The field type \"" . self::comment($field->expectedType) . '" is not installed, so nothing was generated for this field. #}';
            return;
        }

        // A renderer registered for this field type (bundled with Twig Scaffold,
        // shipped by its plugin, an event handler, or the site config) takes
        // precedence over the built-ins.
        $custom = TwigScaffold::getInstance()->renderers->resolve($field, $expr, $var, $required);
        if ($custom !== null) {
            if (isset($custom['error'])) {
                $lines[] = "{$indent}{# Twig Scaffold: the renderer registered by " . self::comment($custom['source']) . ' is invalid (' . self::comment($custom['error']) . '), so the built-in output follows. #}';
            } else {
                $this->appendGuarded($lines, $indent, $custom['guard'], $custom['twig']);
                return;
            }
        }

        if ($field instanceof Matrix) {
            $this->appendMatrix($lines, $field, $expr, $depth, $variables, $ancestors);
            return;
        }

        if ($field instanceof ContentBlock) {
            $this->appendContentBlock($lines, $field, $expr, $depth, $variables, $ancestors);
            return;
        }

        if ($field instanceof Assets) {
            $this->appendAssets($lines, $field, $expr, $depth, $variables);
            return;
        }

        if ($field instanceof BaseRelationField) {
            $this->appendRelations($lines, $field, $expr, $depth, $variables);
            return;
        }

        if ($field instanceof Addresses) {
            $address = $this->uniqueVariable(self::singular($field->handle) ?? 'address', $variables);
            $lines[] = "{$indent}{% for {$address} in {$expr}.all() %}";
            $lines[] = "{$indent}" . self::INDENT . "{{ {$address}|address }}";
            $lines[] = "{$indent}{% endfor %}";
            return;
        }

        if ($field instanceof Table) {
            $this->appendTable($lines, $field, $expr, $required, $depth, $variables);
            return;
        }

        if ($field instanceof BaseOptionsField) {
            if (str_contains($field::phpType(), 'MultiOptionsFieldData')) {
                $option = $this->uniqueVariable('option', $variables);
                $this->appendGuarded($lines, $indent, $required ? null : "{$expr}|length", [
                    '<ul>',
                    self::INDENT . "{% for {$option} in {$expr} %}",
                    str_repeat(self::INDENT, 2) . "<li>{{ {$option}.label }}</li>",
                    self::INDENT . '{% endfor %}',
                    '</ul>',
                ]);
            } else {
                $this->appendGuarded($lines, $indent, $required ? null : "{$expr}.value is not empty", [
                    "<p>{{ {$expr}.label }}</p>",
                ]);
            }
            return;
        }

        if ($field instanceof Lightswitch) {
            $this->appendToggle($lines, $indent, $expr, $field->onLabel ?: $field->name, $field->offLabel);
            return;
        }

        if ($field instanceof Date || $field instanceof Time) {
            // A null date must be guarded even when the field is required:
            // Twig's date filters treat null as "now".
            $this->appendGuarded($lines, $indent, $expr, [$this->timeTag($field, $expr)]);
            return;
        }

        [$inner, $guard] = $this->scalar($field, $expr, $required);
        if ($inner === null) {
            $lines[] = "{$indent}{# No built-in rendering for this field type. Try {{ {$expr} }} or see the field's documentation. #}";
            return;
        }
        $this->appendGuarded($lines, $indent, $guard, $inner);
    }

    /**
     * The Twig for a field whose value renders as a single element, and the
     * `{% if %}` condition it should be wrapped in (null for none). Returns a
     * null body for a field type the generator doesn't know.
     *
     * @return array{0: string[]|null, 1: string|null}
     */
    private function scalar(FieldInterface $field, string $expr, bool $required): array
    {
        $ifBlank = $required ? null : $expr;

        if (self::isHtmlField($field)) {
            $lines = [];
            if ($field instanceof ElementContainerFieldInterface && $field->getFieldLayoutProviders() !== []) {
                $lines[] = '{# Entries nested in this field render with their partial templates: '
                    . 'templates/' . trim(Craft::$app->getConfig()->getGeneral()->partialTemplatesPath, '/') . '/entry/<entryTypeHandle>.twig #}';
            }
            $lines[] = "{{ {$expr} }}";
            return [$lines, null];
        }

        if ($field instanceof PlainText) {
            return [["<p>{{ {$expr}" . ($field->multiline ? '|nl2br' : '') . ' }}</p>'], $ifBlank];
        }

        if ($field instanceof Number) {
            $prefix = $field->prefix !== null ? Html::encode($field->prefix) : '';
            $suffix = $field->suffix !== null ? Html::encode($field->suffix) : '';
            return [["<p>{$prefix}{{ {$expr} }}{$suffix}</p>"], $required ? null : "{$expr} is not null"];
        }

        if ($field instanceof Range) {
            $suffix = $field->suffix !== null ? Html::encode($field->suffix) : '';
            return [["<p>{{ {$expr} }}{$suffix}</p>"], $required ? null : "{$expr} is not null"];
        }

        if ($field instanceof Url) {
            return [["<a href=\"{{ {$expr} }}\">{{ {$expr} }}</a>"], $ifBlank];
        }

        if ($field instanceof Email) {
            return [["<a href=\"mailto:{{ {$expr} }}\">{{ {$expr} }}</a>"], $ifBlank];
        }

        // The rest are value objects: reading a property of a null value is an
        // error, so they are always guarded.
        if ($field instanceof Link) {
            return [["<a href=\"{{ {$expr}.url }}\">{{ {$expr}.label }}</a>"], $expr];
        }

        if ($field instanceof Color) {
            return [["<p style=\"color: {{ {$expr}.hex }}\">{{ {$expr}.hex }}</p>"], $expr];
        }

        if ($field instanceof Country) {
            return [["<p>{{ {$expr}.name }}</p>"], $expr];
        }

        if ($field instanceof Money) {
            return [["<p>{{ {$expr}|money }}</p>"], $expr];
        }

        if ($field instanceof Icon) {
            return [["<p>{{ {$expr} }}</p> {# the icon's name #}"], $expr];
        }

        if ($field instanceof Json) {
            return [["<pre>{{ {$expr}.json(true) }}</pre>"], $expr];
        }

        // An unknown field type: go by the PHP type its values have.
        return match (self::valueKind($field::phpType())) {
            'string', 'number' => [["<p>{{ {$expr} }}</p>"], $required ? null : "{$expr} is not empty"],
            'bool' => [[
                "{% if {$expr} %}",
                self::INDENT . '<p>' . Html::encode($field->name) . '</p>',
                '{% endif %}',
            ], null],
            default => [null, null],
        };
    }

    /**
     * @param string[] $lines
     * @param string[] $variables
     * @param string[] $ancestors
     */
    private function appendMatrix(array &$lines, Matrix $field, string $expr, int $depth, array $variables, array $ancestors): void
    {
        $indent = str_repeat(self::INDENT, $depth);
        $entryTypes = $field->getEntryTypes();

        if ($entryTypes === []) {
            $lines[] = "{$indent}{# This Matrix field has no entry types. #}";
            return;
        }

        if ($this->matrixMode === self::MATRIX_PARTIALS) {
            $partials = array_map(fn(EntryType $entryType) => $this->partialPath($entryType), $entryTypes);
            $lines[] = "{$indent}{# Renders each block with its partial template (generate those here too): " . implode(', ', $partials) . ' #}';
            $lines[] = "{$indent}{{ {$expr}.render() }}";
            return;
        }

        if ($depth >= self::MAX_DEPTH) {
            $lines[] = "{$indent}{# Nested too deep to generate; add this Matrix field by hand or render it with {{ {$expr}.render() }}. #}";
            return;
        }

        $block = $this->uniqueVariable(self::singular($field->handle) ?? 'block', $variables);
        $variables[] = $block;
        $lines[] = "{$indent}{% for {$block} in {$expr}.all() %}";

        if (count($entryTypes) === 1) {
            $this->appendBlock($lines, $entryTypes[0], $block, $depth + 1, $variables, $ancestors);
        } else {
            // Different block types carry different fields, so branch on the type.
            $lines[] = "{$indent}" . self::INDENT . "{% switch {$block}.type.handle %}";
            foreach ($entryTypes as $entryType) {
                $lines[] = "{$indent}" . str_repeat(self::INDENT, 2) . "{% case \"{$entryType->handle}\" %}";
                $this->appendBlock($lines, $entryType, $block, $depth + 3, $variables, $ancestors);
            }
            $lines[] = "{$indent}" . self::INDENT . '{% endswitch %}';
        }

        $lines[] = "{$indent}{% endfor %}";
    }

    /**
     * @param string[] $lines
     * @param string[] $variables
     * @param string[] $ancestors
     */
    private function appendBlock(array &$lines, EntryType $entryType, string $var, int $depth, array $variables, array $ancestors): void
    {
        if (in_array('entryType:' . $entryType->id, $ancestors, true)) {
            // An entry type that contains itself would loop forever.
            $lines[] = str_repeat(self::INDENT, $depth) . "{# \"{$entryType->handle}\" blocks can contain more \"{$entryType->handle}\" blocks. Add the deeper levels by hand, or render them with a partial template. #}";
            return;
        }

        $this->appendEntryType($lines, $entryType, $var, $depth, $variables, $ancestors);
    }

    /**
     * @param string[] $lines
     * @param string[] $variables
     * @param string[] $ancestors
     */
    private function appendContentBlock(array &$lines, ContentBlock $field, string $expr, int $depth, array $variables, array $ancestors): void
    {
        $indent = str_repeat(self::INDENT, $depth);

        if ($depth >= self::MAX_DEPTH || in_array('field:' . $field->id, $ancestors, true)) {
            $lines[] = "{$indent}{# Nested too deep to generate; add this content block by hand. #}";
            return;
        }

        $block = $this->uniqueVariable($field->handle, $variables);
        $variables[] = $block;
        $ancestors[] = 'field:' . $field->id;

        $lines[] = "{$indent}{% set {$block} = {$expr} %}";
        $lines[] = "{$indent}{% if {$block} %}";
        $this->appendLayout($lines, $field->getFieldLayout(), $block, $depth + 1, $variables, $ancestors);
        $lines[] = "{$indent}{% endif %}";
    }

    /**
     * @param string[] $lines
     * @param string[] $variables
     */
    private function appendAssets(array &$lines, Assets $field, string $expr, int $depth, array $variables): void
    {
        $indent = str_repeat(self::INDENT, $depth);
        $imagesOnly = $field->restrictFiles && array_values($field->allowedKinds ?? []) === ['image'];

        if ($field->maxRelations === 1) {
            $asset = $this->uniqueVariable($field->handle, $variables);
            $lines[] = "{$indent}{% set {$asset} = {$expr}.one() %}";
            $lines[] = "{$indent}{% if {$asset} %}";
        } else {
            $asset = $this->uniqueVariable(self::singular($field->handle) ?? 'asset', $variables);
            $lines[] = "{$indent}{% for {$asset} in {$expr}.all() %}";
        }

        $img = "<img src=\"{{ {$asset}.url }}\" alt=\"{{ {$asset}.alt ?? {$asset}.title }}\" width=\"{{ {$asset}.width }}\" height=\"{{ {$asset}.height }}\">";
        if ($imagesOnly) {
            $lines[] = "{$indent}" . self::INDENT . $img;
        } else {
            $lines[] = "{$indent}" . self::INDENT . "{% if {$asset}.kind == 'image' %}";
            $lines[] = "{$indent}" . str_repeat(self::INDENT, 2) . $img;
            $lines[] = "{$indent}" . self::INDENT . '{% else %}';
            $lines[] = "{$indent}" . str_repeat(self::INDENT, 2) . "<a href=\"{{ {$asset}.url }}\">{{ {$asset}.title }}</a>";
            $lines[] = "{$indent}" . self::INDENT . '{% endif %}';
        }

        $lines[] = "{$indent}" . ($field->maxRelations === 1 ? '{% endif %}' : '{% endfor %}');
    }

    /**
     * @param string[] $lines
     * @param string[] $variables
     */
    private function appendRelations(array &$lines, BaseRelationField $field, string $expr, int $depth, array $variables): void
    {
        $indent = str_repeat(self::INDENT, $depth);

        [$noun, $item] = match (true) {
            $field instanceof Users => ['user', fn(string $v) => "{{ {$v}.fullName ?: {$v}.username }}"],
            $field instanceof Tags => ['tag', fn(string $v) => "{{ {$v}.title }}"],
            $field instanceof Categories => ['category', fn(string $v) => "<a href=\"{{ {$v}.url }}\">{{ {$v}.title }}</a>"],
            $field instanceof Entries => ['relatedEntry', fn(string $v) => "<a href=\"{{ {$v}.url }}\">{{ {$v}.title }}</a>"],
            default => ['item', fn(string $v) => "<a href=\"{{ {$v}.url }}\">{{ {$v}.title }}</a>"],
        };

        if ($field->maxRelations === 1) {
            $related = $this->uniqueVariable($field->handle, $variables);
            $lines[] = "{$indent}{% set {$related} = {$expr}.one() %}";
            $lines[] = "{$indent}{% if {$related} %}";
            $lines[] = "{$indent}" . self::INDENT . '<p>' . $item($related) . '</p>';
            $lines[] = "{$indent}{% endif %}";
            return;
        }

        // Fetch once into a variable, so an empty list doesn't cost a second query.
        $singular = self::singular($field->handle);
        $list = $this->uniqueVariable($singular !== null ? $field->handle : $field->handle . 'Items', $variables);
        $variables[] = $list;
        $related = $this->uniqueVariable($singular ?? $noun, $variables);

        $lines[] = "{$indent}{% set {$list} = {$expr}.all() %}";
        $lines[] = "{$indent}{% if {$list}|length %}";
        $lines[] = "{$indent}" . self::INDENT . '<ul>';
        $lines[] = "{$indent}" . str_repeat(self::INDENT, 2) . "{% for {$related} in {$list} %}";
        $lines[] = "{$indent}" . str_repeat(self::INDENT, 3) . '<li>' . $item($related) . '</li>';
        $lines[] = "{$indent}" . str_repeat(self::INDENT, 2) . '{% endfor %}';
        $lines[] = "{$indent}" . self::INDENT . '</ul>';
        $lines[] = "{$indent}{% endif %}";
    }

    /**
     * @param string[] $lines
     * @param string[] $variables
     */
    private function appendTable(array &$lines, Table $field, string $expr, bool $required, int $depth, array $variables): void
    {
        $indent = str_repeat(self::INDENT, $depth);
        $row = $this->uniqueVariable('row', $variables);
        $columns = $field->columns;

        $headings = [];
        $cells = [];
        foreach ($columns as $key => $column) {
            $handle = !empty($column['handle']) ? $column['handle'] : (string)$key;
            $headings[] = '<th>' . Html::encode(!empty($column['heading']) ? $column['heading'] : $handle) . '</th>';
            $cell = "{$row}.{$handle}";
            $value = match ($column['type'] ?? 'singleline') {
                'date' => "{{ {$cell} ? {$cell}|date('short') }}",
                'time' => "{{ {$cell} ? {$cell}|time('short') }}",
                'lightswitch' => "{{ {$cell} ? 'Yes' : 'No' }}",
                'multiline' => "{{ {$cell}|nl2br }}",
                'url' => "<a href=\"{{ {$cell} }}\">{{ {$cell} }}</a>",
                'email' => "<a href=\"mailto:{{ {$cell} }}\">{{ {$cell} }}</a>",
                'color' => "<span style=\"color: {{ {$cell} }}\">{{ {$cell} }}</span>",
                default => "{{ {$cell} }}",
            };
            $cells[] = "<td>{$value}</td>";
        }

        $inner = ['<table>'];
        if ($headings !== []) {
            $inner[] = self::INDENT . '<thead>';
            $inner[] = str_repeat(self::INDENT, 2) . '<tr>';
            foreach ($headings as $heading) {
                $inner[] = str_repeat(self::INDENT, 3) . $heading;
            }
            $inner[] = str_repeat(self::INDENT, 2) . '</tr>';
            $inner[] = self::INDENT . '</thead>';
        }
        $inner[] = self::INDENT . '<tbody>';
        $inner[] = str_repeat(self::INDENT, 2) . "{% for {$row} in {$expr} %}";
        $inner[] = str_repeat(self::INDENT, 3) . '<tr>';
        foreach ($cells as $cell) {
            $inner[] = str_repeat(self::INDENT, 4) . $cell;
        }
        $inner[] = str_repeat(self::INDENT, 3) . '</tr>';
        $inner[] = str_repeat(self::INDENT, 2) . '{% endfor %}';
        $inner[] = self::INDENT . '</tbody>';
        $inner[] = '</table>';

        $this->appendGuarded($lines, $indent, $required ? null : "{$expr}|length", $inner);
    }

    /**
     * @param string[] $lines
     */
    private function appendToggle(array &$lines, string $indent, string $expr, string $onLabel, ?string $offLabel): void
    {
        $lines[] = "{$indent}{% if {$expr} %}";
        $lines[] = "{$indent}" . self::INDENT . '<p>' . Html::encode($onLabel) . '</p>';
        if ($offLabel !== null && $offLabel !== '') {
            $lines[] = "{$indent}{% else %}";
            $lines[] = "{$indent}" . self::INDENT . '<p>' . Html::encode($offLabel) . '</p>';
        }
        $lines[] = "{$indent}{% endif %}";
    }

    private function timeTag(Date|Time $field, string $expr): string
    {
        if ($field instanceof Time || !$field->showDate) {
            return "<time datetime=\"{{ {$expr}|date('H:i') }}\">{{ {$expr}|time('short') }}</time>";
        }
        if ($field->showTime) {
            return "<time datetime=\"{{ {$expr}|atom }}\">{{ {$expr}|datetime('long') }}</time>";
        }
        return "<time datetime=\"{{ {$expr}|date('Y-m-d') }}\">{{ {$expr}|date('long') }}</time>";
    }

    /**
     * Appends the lines, wrapped in `{% if condition %}` when there is one.
     *
     * @param string[] $lines
     * @param string[] $inner lines indented relative to the field, not the document
     */
    private function appendGuarded(array &$lines, string $indent, ?string $condition, array $inner): void
    {
        if ($condition === null) {
            foreach ($inner as $line) {
                $lines[] = $indent . $line;
            }
            return;
        }

        $lines[] = "{$indent}{% if {$condition} %}";
        foreach ($inner as $line) {
            $lines[] = $indent . self::INDENT . $line;
        }
        $lines[] = "{$indent}{% endif %}";
    }

    /**
     * A variable name that doesn't shadow a reserved name or one already in
     * scope, adding a numeric suffix when it would.
     *
     * @param string[] $variables
     */
    private function uniqueVariable(string $base, array $variables): string
    {
        $name = $base;
        $suffix = 2;
        while (in_array($name, $variables, true) || in_array($name, self::RESERVED_VARIABLES, true)) {
            $name = $base . $suffix++;
        }

        return $name;
    }

    /**
     * The singular of a plural-looking handle (`blocks` to `block`, `categories`
     * to `category`), or null when the handle doesn't look plural.
     */
    private static function singular(string $handle): ?string
    {
        if (strlen($handle) > 3 && str_ends_with($handle, 'ies')) {
            return substr($handle, 0, -3) . 'y';
        }
        foreach (['sses', 'shes', 'ches', 'xes'] as $ending) {
            if (strlen($handle) > strlen($ending) && str_ends_with($handle, $ending)) {
                return substr($handle, 0, -2);
            }
        }
        if (strlen($handle) > 1 && str_ends_with($handle, 's') && !str_ends_with($handle, 'ss')) {
            return substr($handle, 0, -1);
        }

        return null;
    }

    /**
     * Whether values of a PHP type (as `FieldInterface::phpType()` describes
     * it) print as text: 'string', 'number', 'bool', or null for anything else.
     */
    private static function valueKind(string $phpType): ?string
    {
        $kinds = [];
        foreach (explode('|', $phpType) as $part) {
            $part = strtolower(ltrim(trim($part), '?\\'));
            if ($part === '' || $part === 'null') {
                continue;
            }
            $kinds[] = match ($part) {
                'string' => 'string',
                'int', 'integer', 'float', 'double' => 'number',
                'bool', 'boolean' => 'bool',
                default => null,
            };
        }
        $kinds = array_unique($kinds);

        return count($kinds) === 1 ? $kinds[0] : null;
    }

    /**
     * Whether the field is a CKEditor or Redactor field (or anything else built
     * on the html-field package), without requiring that package.
     */
    private static function isHtmlField(FieldInterface $field): bool
    {
        return is_a($field, self::HTML_FIELD_CLASS);
    }

    /** Text safe to put inside a Twig comment. */
    private static function comment(string $text): string
    {
        return str_replace(['#}', "\r", "\n"], ['# }', ' ', ' '], $text);
    }

    /**
     * Drops blank lines that would sit right inside a tag or wrapper, and runs
     * of blank lines, so the spacing between fields stays even.
     *
     * @param string[] $lines
     * @return string[]
     */
    private function tidy(array $lines): array
    {
        $out = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            if ($lines[$i] !== '') {
                $out[] = $lines[$i];
                continue;
            }

            $prev = $out === [] ? '' : trim((string)end($out));
            $next = '';
            for ($j = $i + 1; $j < $count; $j++) {
                if ($lines[$j] !== '') {
                    $next = trim($lines[$j]);
                    break;
                }
            }

            if (
                $prev === ''
                || $next === ''
                || preg_match('/^\{%\s*(for|if|else|elseif|switch|case|default)\b/', $prev)
                || preg_match('/^<(ul|ol|table|thead|tbody|tr)>$/', $prev)
                || preg_match('/^\{%\s*end/', $next)
                || preg_match('/^<\/(ul|ol|table|thead|tbody|tr)>$/', $next)
            ) {
                continue;
            }

            $out[] = '';
        }

        return $out;
    }
}
