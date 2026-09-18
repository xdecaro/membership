<?php

namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Throwable;

final class AdminAssetService
{
    private const COMPONENT = 'com_decaromembership';
    private const MINIMUM_CORE_UI_VERSION = '1.3.0';

    public static function useAssets(HtmlDocument $document, bool $withCoreUi = false): bool
    {
        $webAssets = $document->getWebAssetManager();

        // Joomla only resolves extension asset URIs reliably after the component
        // Web Asset registry has been loaded. Registering an ad-hoc asset with the
        // same name as joomla.asset.json can later be replaced by the registry and
        // lose its "used" state, leaving the page unstyled.
        $webAssets->getRegistry()->addExtensionRegistryFile(self::COMPONENT);
        $webAssets->useStyle('com_decaromembership.admin');
        $webAssets->useScript('com_decaromembership.admin');

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

        $webAssets->useStyle('com_decaromembership.core-bridge');

        return true;
    }
}
