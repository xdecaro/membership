<?php
namespace Xdecaro\Component\Decaromembership\Administrator\View\Records;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Xdecaro\Component\Decaromembership\Administrator\Service\AdminAssetService;
use Xdecaro\Component\Decaromembership\Administrator\Service\DclCardStatusPolicy;
final class HtmlView extends BaseHtmlView
{
    public array $items = [];
    public mixed $pagination;
    public mixed $state;
    public array $config = [];
    public array $relationMaps = [];
    public array $peopleMap = [];
    public array $organizationMap = [];
    public string $entity = 'members';
    public function display($tpl = null): void
    {
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');
        $this->entity = $this->getModel()->getEntity();
        $this->config = $this->getModel()->getConfig();
        $this->relationMaps = $this->getModel()->getRelationMaps();
        if (in_array($this->entity, ['members', 'cards'], true)) {
            $this->peopleMap = $this->getModel()->resolvePeopleForItems($this->items);
        }
        if ($this->entity === 'cards') {
            $today = Factory::getDate()->format('Y-m-d');
            foreach ($this->items as $item) {
                $item->status = DclCardStatusPolicy::normalizeLifecycleStatus($item, $today);
            }
        }

        $organizationFields = [];
        foreach ($this->config['fields'] as $fieldName => $field) {
            if (($field['type'] ?? '') === 'organization') {
                $organizationFields[] = $fieldName;
            }
        }
        if ($organizationFields !== []) {
            try {
                $organizations = Factory::getApplication()->bootComponent('com_decaromembership')->getOrganizationsIntegrationService();
                if ($organizations->isAvailable()) {
                    $seen = [];
                    foreach ($this->items as $item) {
                        foreach ($organizationFields as $fieldName) {
                            $uuid = strtolower(trim((string) ($item->{$fieldName} ?? '')));
                            if ($uuid === '' || isset($seen[$uuid])) {
                                continue;
                            }
                            $seen[$uuid] = true;
                            $organization = $organizations->getOrganization($uuid);
                            if (is_array($organization)) {
                                $name = trim((string) ($organization['name'] ?? $uuid));
                                $short = trim((string) ($organization['short_name'] ?? ''));
                                $this->organizationMap[$uuid] = $short !== '' && strcasecmp($short, $name) !== 0
                                    ? $name . ' (' . $short . ')'
                                    : $name;
                            }
                        }
                    }
                }
            } catch (\Throwable) {
                $this->organizationMap = [];
            }
        }
        AdminAssetService::useAssets($this->getDocument());
        ToolbarHelper::title(Text::_($this->config['label']), 'users');
        $user = Factory::getApplication()->getIdentity();
        if ($user->authorise('core.create', 'com_decaromembership')) {
            ToolbarHelper::addNew('record.add');
            if ($this->entity === 'cards') {
                ToolbarHelper::link('index.php?option=com_decaromembership&view=cardbulk', Text::_('COM_DECAROMEMBERSHIP_CARDBULK_TOOLBAR'), 'copy');
            }
        }
        if ($user->authorise('core.delete', 'com_decaromembership')) ToolbarHelper::trash('records.trash');
        if ($user->authorise('membership.export', 'com_decaromembership')) ToolbarHelper::custom('records.export', 'download', 'download', 'COM_DECAROMEMBERSHIP_EXPORT', false);
        parent::display($tpl);
    }
}
