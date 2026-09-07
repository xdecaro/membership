<?php
namespace Xdecaro\Component\Decaromembership\Administrator\View\Records;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
final class HtmlView extends BaseHtmlView
{
    public array $items = [];
    public mixed $pagination;
    public mixed $state;
    public array $config = [];
    public array $relationMaps = [];
    public string $entity = 'members';
    public function display($tpl = null): void
    {
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');
        $this->entity = $this->getModel()->getEntity();
        $this->config = $this->getModel()->getConfig();
        $this->relationMaps = $this->getModel()->getRelationMaps();
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->useStyle('com_decaromembership.admin')->useScript('com_decaromembership.admin');
        ToolbarHelper::title(Text::_($this->config['label']), 'users');
        $user = Factory::getApplication()->getIdentity();
        if ($user->authorise('core.create', 'com_decaromembership')) ToolbarHelper::addNew('record.add');
        if ($user->authorise('core.delete', 'com_decaromembership')) ToolbarHelper::trash('records.trash');
        if ($user->authorise('membership.export', 'com_decaromembership')) ToolbarHelper::custom('records.export', 'download', 'download', 'COM_DECAROMEMBERSHIP_EXPORT', false);
        parent::display($tpl);
    }
}
