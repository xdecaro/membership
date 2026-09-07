<?php
namespace Xdecaro\Component\Decaromembership\Administrator\View\Record;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Xdecaro\Component\Decaromembership\Administrator\Helper\EntityRegistry;
final class HtmlView extends BaseHtmlView
{
    public object $item;
    public array $config = [];
    public array $relations = [];
    public string $entity = 'members';
    public function display($tpl = null): void
    {
        $model = $this->getModel();
        $this->entity = $model->getEntityFromRequest();
        $this->config = EntityRegistry::get($this->entity);
        $this->item = $model->getItem();
        foreach ($this->config['fields'] as $name => $field) {
            if (($field['type'] ?? '') === 'relation') $this->relations[$name] = $model->getRelationOptions($field['relation']);
        }
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->useStyle('com_decaromembership.admin')->useScript('com_decaromembership.admin');
        ToolbarHelper::title(Text::_($this->config['singular']), 'pencil');
        ToolbarHelper::apply('record.apply');
        ToolbarHelper::save('record.save');
        ToolbarHelper::cancel('record.cancel');
        parent::display($tpl);
    }
}
