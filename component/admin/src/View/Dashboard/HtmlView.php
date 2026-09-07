<?php
namespace Xdecaro\Component\Decaromembership\Administrator\View\Dashboard;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
final class HtmlView extends BaseHtmlView
{
    public array $data = [];
    public function display($tpl = null): void
    {
        $this->data = $this->get('DashboardData');
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->useStyle('com_decaromembership.admin')->useScript('com_decaromembership.admin');
        ToolbarHelper::title(Text::_('COM_DECAROMEMBERSHIP_DASHBOARD'), 'users');
        if (Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_decaromembership')) ToolbarHelper::preferences('com_decaromembership');
        parent::display($tpl);
    }
}
