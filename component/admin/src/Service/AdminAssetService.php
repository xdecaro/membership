<?php

namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Uri\Uri;
use Throwable;

final class AdminAssetService
{
    private const VERSION = '1.8.0';
    private const MINIMUM_CORE_UI_VERSION = '1.3.0';

    public static function useAssets(HtmlDocument $document, bool $withCoreUi = false): bool
    {
        $webAssets = $document->getWebAssetManager();
        $webAssets->useScript('core');

        $base = rtrim(Uri::root(true), '/') . '/media/com_decaromembership';

        // Runtime fallback verified on the real Joomla 6.1.3 site:
        // the Membership media files return HTTP 200, but Web Asset Manager
        // activation does not emit the component CSS/JS tags in the document.
        // Use HtmlDocument's URL API so the browser receives the exact assets.
        $document->addStyleSheet(
            $base . '/css/admin.css',
            ['version' => self::VERSION]
        );

        $document->addScript(
            $base . '/js/admin.js',
            ['version' => self::VERSION],
            ['defer' => true]
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

        $document->addStyleSheet(
            $base . '/css/core-bridge.css',
            ['version' => self::VERSION]
        );

        return true;
    }
}
