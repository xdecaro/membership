<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\DatabaseQuery;
use Throwable;
use Xdecaro\Component\Decaromembership\Administrator\Helper\EntityRegistry;
use Xdecaro\Component\Decaromembership\Administrator\Service\PeopleIntegrationService;

final class RecordsModel extends ListModel
{
    private ?PeopleIntegrationService $peopleIntegration = null;

    private function people(): PeopleIntegrationService
    {
        return $this->peopleIntegration ??= new PeopleIntegrationService($this->getDatabase());
    }

    protected function populateState($ordering = 'id', $direction = 'DESC'): void
    {
        $app = Factory::getApplication();
        $entity = $app->input->getCmd('entity', 'members');
        if (!EntityRegistry::has($entity)) { $entity = 'members'; }
        $config = EntityRegistry::get($entity);
        $allowed = array_values(array_unique(array_merge(['id'], $config['list'])));
        $defaultOrder = $entity === 'members' ? 'member_number' : 'id';
        $defaultDirection = $entity === 'members' ? 'ASC' : 'DESC';
        $order = $app->input->getCmd('order', $defaultOrder);
        if (!in_array($order, $allowed, true)) { $order = $defaultOrder; }
        $dir = strtoupper($app->input->getCmd('dir', $defaultDirection)) === 'ASC' ? 'ASC' : 'DESC';
        $peopleLink = strtolower($app->input->getCmd('people_link', 'all'));
        if (!in_array($peopleLink, ['all', 'linked', 'unlinked'], true)) { $peopleLink = 'all'; }
        $this->setState('filter.entity', $entity);
        $this->setState('filter.search', trim($app->input->getString('filter_search', '')));
        $this->setState('filter.people_link', $entity === 'members' ? $peopleLink : 'all');
        parent::populateState($order, $dir);
    }

    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.entity');
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.people_link');
        return parent::getStoreId($id);
    }

    protected function getListQuery(): DatabaseQuery
    {
        $db = $this->getDatabase();
        $entity = (string) $this->getState('filter.entity', 'members');
        $config = EntityRegistry::get($entity);
        $query = $db->getQuery(true)->select('a.*')->from($db->quoteName($config['table'], 'a'));
        $search = (string) $this->getState('filter.search');
        if (isset($config['fields']['published'])) { $query->where($db->quoteName('a.published') . ' >= 0'); }

        if ($entity === 'members') {
            $peopleLink = (string) $this->getState('filter.people_link', 'all');
            if ($peopleLink === 'linked') {
                $query->where($db->quoteName('a.person_uuid') . ' IS NOT NULL');
            } elseif ($peopleLink === 'unlinked') {
                $query->where($db->quoteName('a.person_uuid') . ' IS NULL');
            }
        }

        if ($search !== '') {
            if ($entity === 'members') {
                $legacyConditions = [];
                foreach ($config['search'] as $i => $column) {
                    if ($column === 'person_uuid') { continue; }
                    $placeholder = ':legacy_search' . $i;
                    $legacyConditions[] = $db->quoteName('a.' . $column) . ' LIKE ' . $placeholder;
                    $query->bind($placeholder, '%' . $search . '%');
                }

                $searchConditions = [];
                if ($legacyConditions !== []) {
                    $searchConditions[] = '(' . $db->quoteName('a.person_uuid') . ' IS NULL AND (' . implode(' OR ', $legacyConditions) . '))';
                }

                try {
                    $peopleMatches = $this->people()->searchPeople($search, 200);
                } catch (Throwable $e) {
                    $peopleMatches = [];
                }

                $uuids = [];
                foreach ($peopleMatches as $person) {
                    $uuid = strtolower(trim((string) ($person['uuid'] ?? '')));
                    if ($uuid !== '') { $uuids[$uuid] = $uuid; }
                }

                if ($uuids !== []) {
                    $placeholders = [];
                    foreach (array_values($uuids) as $i => $uuid) {
                        $placeholder = ':person_uuid_' . $i;
                        $placeholders[] = $placeholder;
                        $query->bind($placeholder, $uuid);
                    }
                    $searchConditions[] = $db->quoteName('a.person_uuid') . ' IN (' . implode(',', $placeholders) . ')';
                }

                if ($searchConditions !== []) {
                    $query->where('(' . implode(' OR ', $searchConditions) . ')');
                } else {
                    $query->where('1 = 0');
                }
            } else {
                $conditions = [];
                foreach ($config['search'] as $i => $column) {
                    $placeholder = ':search' . $i;
                    $conditions[] = $db->quoteName('a.' . $column) . ' LIKE ' . $placeholder;
                    $query->bind($placeholder, '%' . $search . '%');
                }
                if ($conditions) { $query->where('(' . implode(' OR ', $conditions) . ')'); }
            }
        }

        $order = (string) $this->getState('list.ordering', $entity === 'members' ? 'member_number' : 'id');
        $dir = strtoupper((string) $this->getState('list.direction', $entity === 'members' ? 'ASC' : 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $allowed = array_values(array_unique(array_merge(['id'], $config['list'])));
        if (!in_array($order, $allowed, true)) { $order = $entity === 'members' ? 'member_number' : 'id'; }

        if ($entity === 'members' && $order === 'member_number') {
            $query->order('CASE WHEN ' . $db->quoteName('a.member_number') . " IS NULL OR " . $db->quoteName('a.member_number') . " = '' THEN 1 ELSE 0 END ASC");
            $query->order($db->quoteName('a.member_number') . ' ' . $dir);
            $query->order($db->quoteName('a.id') . ' ASC');
        } else {
            $query->order($db->quoteName('a.' . $order) . ' ' . $dir);
            if ($order !== 'id') { $query->order($db->quoteName('a.id') . ' ASC'); }
        }

        return $query;
    }

    public function resolvePeopleForItems(array $items): array
    {
        $uuids = [];
        foreach ($items as $item) {
            $uuid = strtolower(trim((string) ($item->person_uuid ?? '')));
            if ($uuid !== '') { $uuids[$uuid] = $uuid; }
        }

        if ($uuids === []) { return []; }

        try {
            return $this->people()->getPeopleByUuids(array_values($uuids));
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getRelationMaps(): array
    {
        $config = $this->getConfig(); $db = $this->getDatabase(); $maps = [];
        foreach ($config['list'] as $column) {
            $field = $config['fields'][$column] ?? null;
            if (!$field || ($field['type'] ?? '') !== 'relation' || !EntityRegistry::has($field['relation'])) { continue; }
            $rel = EntityRegistry::get($field['relation']);
            if ($field['relation'] === 'members') {
                $query = $db->getQuery(true)->select([$db->quoteName('id'), "CONCAT(".$db->quoteName('last_name').", ' ', ".$db->quoteName('first_name').") AS ".$db->quoteName('title')])->from($db->quoteName($rel['table']));
            } else {
                $query = $db->getQuery(true)->select([$db->quoteName('id'), $db->quoteName($rel['title_field'], 'title')])->from($db->quoteName($rel['table']));
            }
            foreach ($db->setQuery($query)->loadObjectList() as $row) { $maps[$column][(int)$row->id] = $row->title; }
        }
        return $maps;
    }

    public function getEntity(): string { return (string) $this->getState('filter.entity', 'members'); }
    public function getConfig(): array { return EntityRegistry::get($this->getEntity()); }
}
