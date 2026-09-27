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
    public bool $competitionsAvailable = false;
    public array $competitionSeasonOptions = [];
    public int $selectedCompetitionSeasonId = 0;
    public ?array $selectedCompetitionSeason = null;
    public array $cardNumberingPolicy = [];

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
            $prefillPersonUuid = strtolower(trim($app->input->getString('person_uuid', '')));
            if ($prefillPersonUuid !== '') {
                $this->item->person_uuid = $prefillPersonUuid;
            }
            $prefillSeasonId = $app->input->getInt('competition_season_id');
            if ($prefillSeasonId > 0) {
                $this->item->competition_season_id = $prefillSeasonId;
                $this->item->scope = 'competition';
            }
            $prefillIssuer = strtolower(trim($app->input->getString('issuer_organization_uuid', '')));
            if ($prefillIssuer !== '') {
                $this->item->issuer_organization_uuid = $prefillIssuer;
            }
        }

        if ($this->entity === 'cards') {
            $this->competitionsAvailable = $model->isCompetitionsAvailable();
            if ($this->competitionsAvailable) {
                $this->competitionSeasonOptions = $model->getDclSeasonOptions();
            }

            $submittedSeasonId = (int) ($this->item->competition_season_id ?? 0);
            if ($submittedSeasonId > 0) {
                $this->selectedCompetitionSeasonId = $submittedSeasonId;
            } elseif ((int) ($this->item->id ?? 0) > 0) {
                $this->selectedCompetitionSeasonId = $model->getLinkedCompetitionSeasonId((int) $this->item->id);
            }

            if ($this->competitionsAvailable && $this->selectedCompetitionSeasonId > 0) {
                $this->selectedCompetitionSeason = $model->getDclSeason($this->selectedCompetitionSeasonId);

                if (is_array($this->selectedCompetitionSeason)) {
                    foreach ($this->competitionSeasonOptions as $index => $seasonOption) {
                        if ((int) ($seasonOption['id'] ?? 0) !== $this->selectedCompetitionSeasonId) {
                            continue;
                        }

                        $this->competitionSeasonOptions[$index] = array_merge($seasonOption, $this->selectedCompetitionSeason);
                        break;
                    }
                }
            }

            $cardPersonUuid = strtolower(trim((string) ($this->item->person_uuid ?? '')));
            if ($cardPersonUuid === '' && (int) ($this->item->member_id ?? 0) > 0) {
                foreach ($model->getRelationOptions('members', (int) $this->item->member_id) as $memberOption) {
                    if ((int) ($memberOption->id ?? 0) === (int) $this->item->member_id) {
                        $cardPersonUuid = strtolower(trim((string) ($memberOption->person_uuid ?? '')));
                        break;
                    }
                }
                if ($cardPersonUuid !== '') {
                    $this->item->person_uuid = $cardPersonUuid;
                }
            }
            if ($cardPersonUuid !== '') {
                try {
                    $people = $app->bootComponent('com_decaromembership')->getPeopleIntegrationService();
                    $this->person = $people->getPerson($cardPersonUuid, false);
                } catch (Throwable) {
                    $this->person = null;
                }
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


        if ($this->entity !== 'members') {
            $hasOrganizationField = false;
            foreach ($this->config['fields'] as $field) {
                if (($field['type'] ?? '') === 'organization') {
                    $hasOrganizationField = true;
                    break;
                }
            }

            if ($hasOrganizationField) {
                try {
                    $membershipComponent = $app->bootComponent('com_decaromembership');
                    $organizations = $membershipComponent->getOrganizationsIntegrationService();
                    $this->organizationsAvailable = $organizations->isAvailable();

                    if ($this->organizationsAvailable) {
                        $options = [];
                        if (!in_array($this->entity, ['cards', 'card_numbering_rules'], true)) {
                            foreach ($organizations->searchOrganizations('', 200) as $organization) {
                                $organizationUuid = strtolower(trim((string) ($organization['uuid'] ?? '')));
                                if ($organizationUuid !== '') {
                                    $options[$organizationUuid] = $organization;
                                }
                            }
                        }

                        foreach ($this->config['fields'] as $fieldName => $field) {
                            if (($field['type'] ?? '') !== 'organization') {
                                continue;
                            }

                            $selectedUuid = strtolower(trim((string) ($this->item->{$fieldName} ?? '')));
                            if ($selectedUuid === '') {
                                continue;
                            }

                            try {
                                $selectedOrganization = $organizations->getOrganization($selectedUuid);
                                if ($selectedOrganization !== null) {
                                    $options[$selectedUuid] = $selectedOrganization;
                                    if (in_array($this->entity, ['cards', 'card_numbering_rules'], true) && $fieldName === 'issuer_organization_uuid') {
                                        $this->selectedOrganization = $selectedOrganization;
                                    }
                                }
                            } catch (Throwable) {
                                // Keep the stored UUID visible even if lookup fails.
                            }
                        }

                        $this->organizationOptions = $this->buildOrganizationOptions(array_values($options));
                    }
                } catch (Throwable) {
                    $this->organizationsAvailable = false;
                    $this->organizationOptions = [];
                }
            }
        }

        if ($this->entity === 'cards') {
            $scope = strtolower(trim((string) ($this->item->scope ?? 'association')));
            $scope = $scope === 'competition' ? 'competition' : 'association';
            $issuerUuid = strtolower(trim((string) ($this->item->issuer_organization_uuid ?? '')));
            if ($scope === 'competition' && is_array($this->selectedCompetitionSeason)) {
                $seasonIssuer = strtolower(trim((string) ($this->selectedCompetitionSeason['rights_holder_organization_uuid'] ?? '')));
                if ($seasonIssuer !== '') {
                    $issuerUuid = $seasonIssuer;
                }
            }
            $this->cardNumberingPolicy = $model->getCardNumberingPolicy($issuerUuid, $scope);
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
