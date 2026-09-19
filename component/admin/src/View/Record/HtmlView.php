<?php
namespace Xdecaro\Component\Decaromembership\Administrator\View\Record;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Throwable;
use Xdecaro\Component\Decaromembership\Administrator\Helper\EntityRegistry;
use Xdecaro\Component\Decaromembership\Administrator\Service\AdminAssetService;
final class HtmlView extends BaseHtmlView
{
    public object $item;
    public array $config = [];
    public array $relations = [];
    public string $entity = 'members';
    public ?array $person = null;
    public bool $personSensitive = false;
    public bool $canRelinkPerson = false;
    public bool $organizationsAvailable = false;
    public array $organizationOptions = [];
    public ?array $selectedOrganization = null;
    public bool $memberNumberAutomatic = false;
    public string $memberNumberPrefix = '';
    public int $memberNumberPadding = 6;
    public string $memberDefaultStatus = 'pending';
    public bool $hasMemberCategories = false;
    public ?object $currentMemberCard = null;
    public string $legacyCardNumber = '';

    public function display($tpl = null): void
    {
        $model = $this->getModel();
        $this->entity = $model->getEntityFromRequest();
        $this->config = EntityRegistry::get($this->entity);
        $this->item = $model->getItem();

        $app = Factory::getApplication();
        if ($this->entity === 'cards' && (int) ($this->item->id ?? 0) < 1) {
            $memberId = $app->input->getInt('member_id');
            if ($memberId > 0) {
                $this->item->member_id = $memberId;
            }
        }

        foreach ($this->config['fields'] as $name => $field) {
            if (($field['type'] ?? '') === 'relation') {
                $selectedId = (int) ($this->item->{$name} ?? 0);
                $this->relations[$name] = $model->getRelationOptions($field['relation'], $selectedId);
            }
        }

        $user = $app->getIdentity();
        if ($this->entity === 'members') {
            $params = ComponentHelper::getParams('com_decaromembership');
            $this->memberNumberAutomatic = (string) $params->get('member_number_mode', 'manual') === 'automatic';
            $this->memberNumberPrefix = trim((string) $params->get('member_number_prefix', ''));
            $this->memberNumberPadding = max(1, min(12, (int) $params->get('member_number_padding', 6)));
            $configuredStatus = (string) $params->get('member_default_status', 'pending');
            $this->memberDefaultStatus = in_array($configuredStatus, ['pending', 'in_review', 'active'], true) ? $configuredStatus : 'pending';
            $this->hasMemberCategories = !empty($this->relations['category_id']);
            $memberId = (int) ($this->item->id ?? 0);
            $this->legacyCardNumber = trim((string) ($this->item->card_number ?? ''));
            if ($memberId > 0) {
                $this->currentMemberCard = $model->getCurrentMemberCard($memberId);
            }

            if ($memberId < 1 && trim((string) ($this->item->status ?? '')) === '') {
                $this->item->status = $this->memberDefaultStatus;
            }

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

            try {
                $membershipComponent = $app->bootComponent('com_decaromembership');
                $organizations = $membershipComponent->getOrganizationsIntegrationService();
                $this->organizationsAvailable = $organizations->isAvailable();

                if ($this->organizationsAvailable) {
                    $options = [];
                    foreach ($organizations->searchOrganizations('', 200) as $organization) {
                        $organizationUuid = strtolower(trim((string) ($organization['uuid'] ?? '')));
                        if ($organizationUuid !== '') {
                            $options[$organizationUuid] = $organization;
                        }
                    }

                    $selectedUuid = strtolower(trim((string) ($this->item->organization_uuid ?? '')));
                    if ($selectedUuid !== '') {
                        try {
                            $this->selectedOrganization = $organizations->getOrganization($selectedUuid);
                            if ($this->selectedOrganization !== null) {
                                $options[$selectedUuid] = $this->selectedOrganization;
                            }
                        } catch (Throwable) {
                            $this->selectedOrganization = null;
                        }
                    }

                    $this->organizationOptions = $this->buildOrganizationOptions(array_values($options));
                }
            } catch (Throwable) {
                $this->organizationsAvailable = false;
                $this->organizationOptions = [];
                $this->selectedOrganization = null;
            }
        }

        AdminAssetService::useAssets($this->getDocument());
        ToolbarHelper::title(Text::_($this->config['singular']), 'pencil');
        ToolbarHelper::apply('record.apply');
        ToolbarHelper::save('record.save');
        ToolbarHelper::cancel('record.cancel');
        parent::display($tpl);
    }

    private function buildOrganizationOptions(array $organizations): array
    {
        $byId = [];
        foreach ($organizations as $organization) {
            $id = (int) ($organization['id'] ?? 0);
            if ($id < 1) {
                continue;
            }

            $byId[$id] = $organization;
        }

        $children = [];
        $roots = [];

        foreach ($byId as $id => $organization) {
            $parentId = (int) ($organization['parent_id'] ?? 0);

            if ($parentId > 0 && isset($byId[$parentId]) && $parentId !== $id) {
                $children[$parentId][] = $id;
            } else {
                $roots[] = $id;
            }
        }

        $sortIds = static function (array &$ids) use ($byId): void {
            usort($ids, static fn(int $a, int $b): int => strcasecmp(
                (string) ($byId[$a]['name'] ?? ''),
                (string) ($byId[$b]['name'] ?? '')
            ));
        };

        $sortIds($roots);
        foreach ($children as &$ids) {
            $sortIds($ids);
        }
        unset($ids);

        $result = [];
        $visited = [];

        $walk = function (int $id, int $depth, array $path) use (&$walk, &$result, &$visited, $byId, $children): void {
            if (isset($visited[$id]) || !isset($byId[$id])) {
                return;
            }

            $visited[$id] = true;
            $organization = $byId[$id];
            $name = trim((string) ($organization['name'] ?? ''));
            $currentPath = $path;
            if ($name !== '') {
                $currentPath[] = $name;
            }

            $organization['depth'] = min(12, max(0, $depth));
            $organization['path'] = implode(' › ', $currentPath);
            $result[] = $organization;

            foreach ($children[$id] ?? [] as $childId) {
                $walk($childId, $depth + 1, $currentPath);
            }
        };

        foreach ($roots as $rootId) {
            $walk($rootId, 0, []);
        }

        // Orphans/cycles must remain selectable instead of disappearing.
        $remaining = array_values(array_diff(array_keys($byId), array_keys($visited)));
        $sortIds($remaining);
        foreach ($remaining as $id) {
            $walk($id, 0, []);
        }

        return $result;
    }

}
