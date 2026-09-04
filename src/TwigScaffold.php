<?php

namespace johnfmorton\crafttwigscaffold;

use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Utilities;
use johnfmorton\crafttwigscaffold\services\Generator;
use johnfmorton\crafttwigscaffold\utilities\TwigScaffoldUtility;
use yii\base\Event;

/**
 * Twig Scaffold plugin
 *
 * @method static TwigScaffold getInstance()
 * @author John F Morton <john@johnfmorton.com>
 * @copyright John F Morton
 * @license https://craftcms.github.io/license/ Craft License
 * @property-read Generator $generator
 */
class TwigScaffold extends Plugin
{
    public string $schemaVersion = '1.0.0';

    public static function config(): array
    {
        return [
            'components' => [
                'generator' => Generator::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->attachEventHandlers();
    }

    private function attachEventHandlers(): void
    {
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITIES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = TwigScaffoldUtility::class;
        });
    }
}
