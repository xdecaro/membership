<?php

namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Throwable;

final class AdminAssetService
{
    private const MINIMUM_CORE_UI_VERSION = '1.3.0';

    public static function useAssets(HtmlDocument $document, bool $withCoreUi = false): bool
    {
        $webAssets = $document->getWebAssetManager();

        $webAssets->registerAndUseStyle(
            'com_decaromembership.admin',
            'com_decaromembership/css/admin.css',
            ['version' => 'auto']
        );

        $webAssets->registerAndUseScript(
            'com_decaromembership.admin',
            'com_decaromembership/js/admin.js',
            ['version' => 'auto'],
            ['defer' => true],
            ['core']
        );

        if (!$withCoreUi
            || !class_exists(\xdecaro\Core\Version::class)
            || version_compare((string) \xdecaro\Core\Version::VERSION, self::MINIMUM_CORE_UI_VERSION, '<')
            || !class_exists(\xdecaro\Core\Asset\AssetService::class)) {
            return false;
        }

        try {
            $coreUi = (new \xdecaro\Core\Asset\AssetService())->useComponents($webAssets);
        } catch (Throwable) {
            return false;
        }

        if (!$coreUi) {
            return false;
        }

        $webAssets->registerAndUseStyle(
            'com_decaromembership.core-bridge',
            'com_decaromembership/css/core-bridge.css',
            ['version' => 'auto'],
            [],
            ['com_decaromembership.admin']
        );

        return true;
    }
}
