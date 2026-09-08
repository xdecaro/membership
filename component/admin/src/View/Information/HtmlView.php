<?php
namespace Xdecaro\Component\Decaromembership\Administrator\View\Information;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Throwable;

final class HtmlView extends BaseHtmlView
{
    private const MINIMUM_CORE_UI_VERSION = '1.3.0';

    public array $info = [];
    public bool $coreUi = false;

    public function display($tpl = null): void
    {
        $this->info = $this->get('Information');
        $webAssets = Factory::getApplication()->getDocument()->getWebAssetManager();
        $webAssets->useStyle('com_decaromembership.admin');

        if (class_exists(\xdecaro\Core\Version::class)
            && version_compare((string) \xdecaro\Core\Version::VERSION, self::MINIMUM_CORE_UI_VERSION, '>=')
            && class_exists(\xdecaro\Core\Asset\AssetService::class)) {
            try {
                $this->coreUi = (new \xdecaro\Core\Asset\AssetService())->useComponents($webAssets);
            } catch (Throwable) {
                $this->coreUi = false;
            }
        }

        if ($this->coreUi) {
            $webAssets->useStyle('com_decaromembership.core-bridge');
            $this->setLayout('core');
        }

        ToolbarHelper::title(Text::_('COM_DECAROMEMBERSHIP_INFORMATION'), 'info-circle');
        parent::display($tpl);
    }
}
