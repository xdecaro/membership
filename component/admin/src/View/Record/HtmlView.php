<?php
namespace Xdecaro\Component\Decaromembership\Administrator\View\Record;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Throwable;
use Xdecaro\Component\Decaromembership\Administrator\Helper\EntityRegistry;
final class HtmlView extends BaseHtmlView
{
    public object $item;
    public array $config = [];
    public array $relations = [];
    public string $entity = 'members';
    public ?array $person = null;
    public bool $personSensitive = false;
    public bool $canRelinkPerson = false;

    public function display($tpl = null): void
    {
        $model = $this->getModel();
        $this->entity = $model->getEntityFromRequest();
        $this->config = EntityRegistry::get($this->entity);
        $this->item = $model->getItem();
        foreach ($this->config['fields'] as $name => $field) {
            if (($field['type'] ?? '') === 'relation') $this->relations[$name] = $model->getRelationOptions($field['relation']);
        }

        $app = Factory::getApplication();
        $user = $app->getIdentity();
        if ($this->entity === 'members') {
            $uuid = strtolower(trim((string) ($this->item->person_uuid ?? '')));
            $this->canRelinkPerson = $user->authorise('membership.relink_person', 'com_decaromembership');
            if ($uuid !== '') {
                try {
                    $people = $app->bootComponent('com_decaromembership')->getPeopleIntegrationService();
                    try {
                        $this->person = $people->getPerson($uuid, true);
                        $this->personSensitive = $this->person !== null;
                    } catch (Throwable $e) {
                        $this->person = $people->getPerson($uuid, false);
                    }
                } catch (Throwable $e) {
                    $this->person = null;
                }
            }
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
        ToolbarHelper::title(Text::_($this->config['singular']), 'pencil');
        ToolbarHelper::apply('record.apply');
        ToolbarHelper::save('record.save');
        ToolbarHelper::cancel('record.cancel');
        parent::display($tpl);
    }
}
