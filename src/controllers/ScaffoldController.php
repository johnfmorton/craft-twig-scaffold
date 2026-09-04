<?php

namespace johnfmorton\crafttwigscaffold\controllers;

use Craft;
use craft\web\Controller;
use johnfmorton\crafttwigscaffold\TwigScaffold;
use johnfmorton\crafttwigscaffold\utilities\TwigScaffoldUtility;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Builds starter templates for the Twig Scaffold utility.
 */
class ScaffoldController extends Controller
{
    /**
     * Returns a starter template for an entry type.
     *
     * Expects `entryTypeId` and, optionally, `matrixMode` (`inline` or
     * `partials`) as POST params. Allowed for anyone who may use the utility.
     */
    public function actionGenerate(): ?Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        if (!Craft::$app->getUtilities()->checkAuthorization(TwigScaffoldUtility::class)) {
            throw new ForbiddenHttpException('User is not permitted to use the Twig Scaffold utility.');
        }

        $entryType = Craft::$app->getEntries()->getEntryTypeById((int)$this->request->getRequiredBodyParam('entryTypeId'));
        if ($entryType === null) {
            return $this->asFailure(Craft::t('twig-scaffold', 'Entry type not found.'));
        }

        $generator = TwigScaffold::getInstance()->generator;
        $matrixMode = (string)$this->request->getBodyParam('matrixMode', $generator::MATRIX_INLINE);

        return $this->asSuccess(data: [
            'twig' => $generator->forEntryType($entryType, $matrixMode),
            'hint' => Craft::t('twig-scaffold', 'Suggested location: {path}', [
                'path' => $generator->suggestedPath($entryType),
            ]),
        ]);
    }
}
