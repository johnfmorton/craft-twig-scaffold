<?php

namespace johnfmorton\crafttwigscaffold\events;

use yii\base\Event;

/**
 * Lets plugins and modules register Twig renderers for field types in code
 * rather than with a `twig-scaffold.php` file.
 *
 * ```php
 * Event::on(Renderers::class, Renderers::EVENT_REGISTER_FIELD_RENDERERS, function(RegisterFieldRenderersEvent $event) {
 *     $event->renderers[MyField::class] = '<p>{{ $value }}</p>';
 * });
 * ```
 */
class RegisterFieldRenderersEvent extends Event
{
    /**
     * @var array<string, mixed> Field class name => renderer, in any of the
     * forms a `twig-scaffold.php` file accepts.
     */
    public array $renderers = [];
}
