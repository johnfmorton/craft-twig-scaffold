<?php

/**
 * Twig Scaffold's bundled renderer for the Table Maker plugin (verbb/tablemaker).
 *
 * Table Maker maintainers: to own this, copy the file into your plugin as
 * src/twig-scaffold.php, swap the string key for TableMakerField::class, and
 * edit freely. A file shipped by the plugin overrides this one. See
 * INTEGRATION.md in johnfmorton/craft-twig-scaffold.
 *
 * The class name is a string because Table Maker is not a dependency of Twig
 * Scaffold. The columns are entered with the content, not set on the field,
 * so the table is written as loops rather than spelled out.
 */

return [
    'verbb\tablemaker\fields\TableMakerField' => [
        'twig' => [
            '{# $value.table is the ready-made <table> if you would rather not own the markup. #}',
            '<table>',
            '    <thead>',
            '        <tr>',
            '            {% for column in $value.columns %}',
            '                <th style="text-align: {{ column.align }}"{% if column.width %} width="{{ column.width }}"{% endif %}>{{ column.heading }}</th>',
            '            {% endfor %}',
            '        </tr>',
            '    </thead>',
            '    <tbody>',
            '        {% for row in $value.rows %}',
            '            <tr>',
            '                {% for cell in row %}',
            '                    {% set column = $value.columns[loop.index0] ?? null %}',
            '                    <td{% if column %} style="text-align: {{ column.align }}"{% endif %}>',
            '                        {% if column and column.type == "url" %}<a href="{{ cell }}">{{ cell }}</a>',
            '                        {% elseif column and column.type in ["date", "time"] %}{{ cell ? cell|date("short") }}',
            '                        {% else %}{{ cell }}{% endif %}',
            '                    </td>',
            '                {% endfor %}',
            '            </tr>',
            '        {% endfor %}',
            '    </tbody>',
            '</table>',
        ],
        'guard' => '$value.rows is defined and $value.rows|length',
    ],
];
