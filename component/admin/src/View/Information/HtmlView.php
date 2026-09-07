<?php
namespace Xdecaro\Component\Decaromembership\Administrator\View\Information;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
final class HtmlView extends BaseHtmlView
{
    public array $info = [];
    public function display($tpl = null): void
    {
        $this->info = $this->get('Information');
        Factory::getApplication()->getDocument()->getWebAssetManager()->useStyle('com_decaromembership.admin');
        ToolbarHelper::title(Text::_('COM_DECAROMEMBERSHIP_INFORMATION'), 'info-circle');
        parent::display($tpl);
    }
}
