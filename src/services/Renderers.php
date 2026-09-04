<?php

namespace johnfmorton\crafttwigscaffold\services;

use Closure;
use Craft;
use craft\base\Component;
use craft\base\FieldInterface;
use craft\helpers\Html;
use johnfmorton\crafttwigscaffold\events\RegisterFieldRenderersEvent;
use Throwable;

/**
 * The registry of Twig renderers for field types that plugins, modules, and
 * the site itself have taught Twig Scaffold about.
 *
 * A renderer is looked up by the field's class, then its parent classes, so
 * one entry can cover a family of fields. Renderers come from three places,
 * each overriding the one before it for the same class:
 *
 * 1. A `twig-scaffold.php` file shipped in an installed plugin's `src/`
 *    folder (or its repo root).
 * 2. Handlers of the {@see self::EVENT_REGISTER_FIELD_RENDERERS} event.
 * 3. The site's own `config/twig-scaffold.php`.
 *
 * Every source uses the same shape: an array mapping a field class name to a
 * renderer, which is a Twig string, a list of Twig lines, an array with `twig`
 * and an optional `guard` condition, or a closure that returns one of those
 * (or null to fall back to the built-in output). In the Twig, `$value` stands
 * for the expression that reaches the field's value (`entry.myField`),
 * `$element` for the element variable (`entry`), `$handle` for the field
 * handle, and `$label` for the field's name, HTML-encoded.
 */
class Renderers extends Component
{
    /**
     * @event RegisterFieldRenderersEvent The event that is triggered when
     * registering field renderers.
     */
    public const EVENT_REGISTER_FIELD_RENDERERS = 'registerFieldRenderers';

    /** The file a plugin ships to describe its field types. */
    public const FILENAME = 'twig-scaffold.php';

    /**
     * @var array<string, array{renderer: mixed, source: string}>|null keyed by
     * lowercased class name
     */
    private ?array $registry = null;

    /**
     * Registers a renderer for a field class, replacing any registered before.
     *
     * @param string $class the field class name
     * @param mixed $renderer see the class docs for the accepted forms
     * @param string $source where the renderer came from, for error messages
     */
    public function register(string $class, mixed $renderer, string $source): void
    {
        $this->loadRegistry();
        $this->registry[strtolower(ltrim($class, '\\'))] = ['renderer' => $renderer, 'source' => $source];
    }

    /**
     * Returns the registered renderer for a field, resolved to Twig lines and
     * a guard condition, or null when nothing is registered for the field's
     * class or any of its parents.
     *
     * An invalid renderer (or one whose closure fails) comes back as
     * `['error' => ..., 'source' => ...]` so the caller can say so.
     *
     * @param FieldInterface $field
     * @param string $expression the Twig expression for the field's value
     * @param string $element the Twig variable holding the element
     * @param bool $required whether the field is required in its layout
     * @return array{twig: string[], guard: string|null, source: string}|array{error: string, source: string}|null
     */
    public function resolve(FieldInterface $field, string $expression, string $element, bool $required): ?array
    {
        $this->loadRegistry();

        $entry = null;
        foreach ([$field::class, ...array_values(class_parents($field))] as $class) {
            $entry = $this->registry[strtolower($class)] ?? null;
            if ($entry !== null) {
                break;
            }
        }
        if ($entry === null) {
            return null;
        }

        $renderer = $entry['renderer'];
        try {
            if ($renderer instanceof Closure) {
                $renderer = $renderer($field, $expression, $required);
                if ($renderer === null) {
                    return null;
                }
            }
            [$twig, $guard] = self::normalize($renderer);
        } catch (Throwable $e) {
            Craft::warning(sprintf('Twig Scaffold renderer for %s from %s failed: %s', $field::class, $entry['source'], $e->getMessage()), __METHOD__);
            return ['error' => $e->getMessage(), 'source' => $entry['source']];
        }

        $placeholders = [
            '$value' => $expression,
            '$element' => $element,
            '$handle' => $field->handle,
            '$label' => Html::encode($field->name),
        ];

        return [
            'twig' => array_map(fn(string $line) => strtr($line, $placeholders), $twig),
            'guard' => $guard === null ? null : strtr($guard, $placeholders),
            'source' => $entry['source'],
        ];
    }

    /**
     * Turns any accepted renderer form into Twig lines and a guard.
     *
     * @return array{0: string[], 1: string|null}
     */
    private static function normalize(mixed $renderer): array
    {
        if (is_string($renderer)) {
            return [self::lines($renderer), null];
        }

        if (!is_array($renderer)) {
            throw new \InvalidArgumentException('a renderer must be a string, an array, or a closure, ' . get_debug_type($renderer) . ' given');
        }

        if (array_is_list($renderer)) {
            return [self::lines($renderer), null];
        }

        if (!isset($renderer['twig'])) {
            throw new \InvalidArgumentException('a renderer array needs a "twig" key');
        }
        $guard = $renderer['guard'] ?? null;
        if ($guard !== null && !is_string($guard)) {
            throw new \InvalidArgumentException('"guard" must be a string');
        }

        return [self::lines($renderer['twig']), $guard === '' ? null : $guard];
    }

    /**
     * @param mixed $twig a string (possibly multi-line) or a list of strings
     * @return string[]
     */
    private static function lines(mixed $twig): array
    {
        if (is_string($twig)) {
            $twig = explode("\n", str_replace("\r\n", "\n", rtrim($twig)));
        }
        if (!is_array($twig) || !array_is_list($twig)) {
            throw new \InvalidArgumentException('"twig" must be a string or a list of strings');
        }
        foreach ($twig as $line) {
            if (!is_string($line)) {
                throw new \InvalidArgumentException('every line of "twig" must be a string');
            }
        }
        if ($twig === []) {
            throw new \InvalidArgumentException('"twig" is empty');
        }

        return $twig;
    }

    /**
     * Fills the registry from plugin files, the event, and the site config,
     * once per request.
     */
    private function loadRegistry(): void
    {
        if ($this->registry !== null) {
            return;
        }
        $this->registry = [];

        foreach (Craft::$app->getPlugins()->getAllPlugins() as $plugin) {
            $basePath = $plugin->getBasePath();
            foreach ([$basePath . DIRECTORY_SEPARATOR . self::FILENAME, dirname($basePath) . DIRECTORY_SEPARATOR . self::FILENAME] as $file) {
                if (is_file($file)) {
                    $this->loadFile($file, "the {$plugin->name} plugin");
                    break;
                }
            }
        }

        if ($this->hasEventHandlers(self::EVENT_REGISTER_FIELD_RENDERERS)) {
            $event = new RegisterFieldRenderersEvent();
            $this->trigger(self::EVENT_REGISTER_FIELD_RENDERERS, $event);
            $this->registerAll($event->renderers, 'an event handler');
        }

        $config = Craft::$app->getConfig()->getConfigFromFile('twig-scaffold');
        if (is_array($config) && $config !== []) {
            $this->registerAll($config, 'config/' . self::FILENAME);
        }
    }

    private function loadFile(string $file, string $source): void
    {
        try {
            $renderers = (static fn() => require $file)();
        } catch (Throwable $e) {
            Craft::warning("Could not load {$file}: {$e->getMessage()}", __METHOD__);
            return;
        }

        if (!is_array($renderers)) {
            Craft::warning("{$file} must return an array of field class => renderer", __METHOD__);
            return;
        }

        $this->registerAll($renderers, $source);
    }

    /**
     * @param array<mixed, mixed> $renderers
     */
    private function registerAll(array $renderers, string $source): void
    {
        foreach ($renderers as $class => $renderer) {
            if (!is_string($class) || $class === '') {
                Craft::warning("Ignoring a renderer from {$source}: keys must be field class names", __METHOD__);
                continue;
            }
            $this->registry[strtolower(ltrim($class, '\\'))] = ['renderer' => $renderer, 'source' => $source];
        }
    }
}
