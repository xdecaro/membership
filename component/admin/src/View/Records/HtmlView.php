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
    public array $peopleMap = [];
    public string $entity = 'members';
    public function display($tpl = null): void
    {
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');
        $this->entity = $this->getModel()->getEntity();
        $this->config = $this->getModel()->getConfig();
        $this->relationMaps = $this->getModel()->getRelationMaps();
        if ($this->entity === 'members') {
            $this->peopleMap = $this->getModel()->resolvePeopleForItems($this->items);
        }
        $wa = $this->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle(
            'com_decaromembership.admin',
            'com_decaromembership/css/admin.css',
            ['version' => 'auto']
        );
        $wa->registerAndUseScript(
            'com_decaromembership.admin',
            'com_decaromembership/js/admin.js',
            ['version' => 'auto'],
            ['defer' => true],
            ['core']
        );
        ToolbarHelper::title(Text::_($this->config['label']), 'users');
        $user = Factory::getApplication()->getIdentity();
        if ($user->authorise('core.create', 'com_decaromembership')) ToolbarHelper::addNew('record.add');
        if ($user->authorise('core.delete', 'com_decaromembership')) ToolbarHelper::trash('records.trash');
        if ($user->authorise('membership.export', 'com_decaromembership')) ToolbarHelper::custom('records.export', 'download', 'download', 'COM_DECAROMEMBERSHIP_EXPORT', false);
        parent::display($tpl);
    }
}
