<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\DatabaseQuery;
use Xdecaro\Component\Decaromembership\Administrator\Helper\EntityRegistry;

final class RecordsModel extends ListModel
{
    protected function populateState($ordering = 'id', $direction = 'DESC'): void
    {
        $app = Factory::getApplication();
        $entity = $app->input->getCmd('entity', 'members');
        if (!EntityRegistry::has($entity)) { $entity = 'members'; }
        $config = EntityRegistry::get($entity);
        $allowed = array_values(array_unique(array_merge(['id'], $config['list'])));
        $order = $app->input->getCmd('order', 'id');
        if (!in_array($order, $allowed, true)) { $order = 'id'; }
        $dir = strtoupper($app->input->getCmd('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $this->setState('filter.entity', $entity);
        $this->setState('filter.search', trim($app->input->getString('filter_search', '')));
        parent::populateState($order, $dir);
    }

    protected function getStoreId($id = ''): string
    {
        $id .= ':' . $this->getState('filter.entity');
        $id .= ':' . $this->getState('filter.search');
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
        if ($search !== '') {
            $conditions = [];
            foreach ($config['search'] as $i => $column) {
                $placeholder = ':search' . $i;
                $conditions[] = $db->quoteName('a.' . $column) . ' LIKE ' . $placeholder;
                $query->bind($placeholder, '%' . $search . '%');
            }
            if ($conditions) { $query->where('(' . implode(' OR ', $conditions) . ')'); }
        }
        $order = (string) $this->getState('list.ordering', 'id');
        $dir = strtoupper((string) $this->getState('list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $allowed = array_values(array_unique(array_merge(['id'], $config['list'])));
        if (!in_array($order, $allowed, true)) { $order = 'id'; }
        $query->order($db->quoteName('a.' . $order) . ' ' . $dir);
        return $query;
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
