<?php
namespace Xdecaro\Component\Decaromembership\Administrator\View\Information;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Xdecaro\Component\Decaromembership\Administrator\Service\AdminAssetService;

final class HtmlView extends BaseHtmlView
{
    public array $info = [];
    public bool $coreUi = false;

    public function display($tpl = null): void
    {
        $this->info = $this->get('Information');
        $this->coreUi = AdminAssetService::useAssets($this->getDocument(), true);
        if ($this->coreUi) {
            $this->setLayout('core');
        }

        ToolbarHelper::title(Text::_('COM_DECAROMEMBERSHIP_INFORMATION'), 'info-circle');
        parent::display($tpl);
    }
}
