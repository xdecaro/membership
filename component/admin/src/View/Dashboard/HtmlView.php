<?php
namespace Xdecaro\Component\Decaromembership\Administrator\View\Dashboard;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Xdecaro\Component\Decaromembership\Administrator\Service\AdminAssetService;
final class HtmlView extends BaseHtmlView
{
    public array $data = [];
    public function display($tpl = null): void
    {
        $this->data = $this->get('DashboardData');
        AdminAssetService::useAssets($this->getDocument());
        ToolbarHelper::title(Text::_('COM_DECAROMEMBERSHIP_DASHBOARD'), 'users');
        if (Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_decaromembership')) ToolbarHelper::preferences('com_decaromembership');
        parent::display($tpl);
    }
}
