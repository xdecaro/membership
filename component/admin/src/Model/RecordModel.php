<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Model;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use RuntimeException;
use Throwable;
use Xdecaro\Component\Decaromembership\Administrator\Helper\EntityRegistry;
use Xdecaro\Component\Decaromembership\Administrator\Service\AuditService;
use Xdecaro\Component\Decaromembership\Administrator\Service\MemberPeopleLinkService;
use Xdecaro\Component\Decaromembership\Administrator\Service\MembershipEligibilityService;
use Xdecaro\Component\Decaromembership\Administrator\Service\MembershipPersonHistoryService;
use Xdecaro\Component\Decaromembership\Administrator\Service\PeopleIntegrationService;
use Xdecaro\Component\Decaromembership\Administrator\Service\RecordRepository;
use Xdecaro\Component\Decaromembership\Administrator\Service\RecordValidator;

final class RecordModel extends BaseDatabaseModel
{
    public function getEntityFromRequest(): string
    {
        $entity = Factory::getApplication()->input->getCmd('entity', 'members');
        return EntityRegistry::has($entity) ? $entity : 'members';
    }

    private function repository(): RecordRepository
    {
        return new RecordRepository($this->getDatabase());
    }

    private function memberPeopleLinkService(RecordRepository $repository, AuditService $audit): MemberPeopleLinkService
    {
        return new MemberPeopleLinkService(
            $repository,
            new PeopleIntegrationService($this->getDatabase()),
            $audit
        );
    }

    public function getItem(int $id = 0): object
    {
        $id = $id ?: Factory::getApplication()->input->getInt('id');
        if ($id < 1) return (object) ['id' => 0];
        return $this->repository()->load(EntityRegistry::get($this->getEntityFromRequest())['table'], $id) ?: (object) ['id' => 0];
    }

    public function getRelationOptions(string $entity): array
    {
        if (!EntityRegistry::has($entity)) return [];
        $config = EntityRegistry::get($entity);
        $db = $this->getDatabase();

        if ($entity !== 'members') {
            return $db->setQuery(
                $db->getQuery(true)
                    ->select([$db->quoteName('id'), $db->quoteName($config['title_field'], 'title')])
                    ->from($db->quoteName($config['table']))
                    ->order($db->quoteName('title') . ' ASC')
            )->loadObjectList();
        }

        $rows = (array) $db->setQuery(
            $db->getQuery(true)
                ->select([
                    $db->quoteName('id'),
                    $db->quoteName('person_uuid'),
                    $db->quoteName('first_name'),
                    $db->quoteName('last_name'),
                    $db->quoteName('member_number'),
                ])
                ->from($db->quoteName($config['table']))
                ->where($db->quoteName('published') . ' >= 0')
                ->order($db->quoteName('member_number') . ' ASC, ' . $db->quoteName('id') . ' ASC')
        )->loadObjectList();

        $uuids = [];
        foreach ($rows as $row) {
            $uuid = strtolower(trim((string) ($row->person_uuid ?? '')));
            if ($uuid !== '') $uuids[$uuid] = $uuid;
        }

        $people = [];
        if ($uuids !== []) {
            try {
                $people = (new PeopleIntegrationService($db))->getPeopleByUuids(array_values($uuids));
            } catch (Throwable) {
                $people = [];
            }
        }

        $options = [];
        foreach ($rows as $row) {
            $uuid = strtolower(trim((string) ($row->person_uuid ?? '')));
            $person = $uuid !== '' ? ($people[$uuid] ?? null) : null;
            $title = trim((string) ($person['display_name'] ?? ''));
            if ($title === '') {
                $title = trim((string) ($row->last_name ?? '') . ' ' . (string) ($row->first_name ?? ''));
            }
            if ($title === '') {
                $title = trim((string) ($row->member_number ?? ''));
            }
            if ($title === '') {
                $title = 'Member #' . (int) $row->id;
            }
            $options[] = (object) ['id' => (int) $row->id, 'title' => $title];
        }

        usort($options, static fn(object $a, object $b): int => strnatcasecmp((string) $a->title, (string) $b->title));
        return $options;
    }

    public function saveEntity(string $entity, int $id, array $input): int
    {
        if (!EntityRegistry::has($entity)) throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_INVALID_ENTITY'));
        $config = EntityRegistry::get($entity);
        $validator = new RecordValidator();
        $data = $validator->filter($config, $input);
        $validator->validateBusinessRules($entity, $data);
        $repository = $this->repository();
        $old = $id > 0 ? $repository->load($config['table'], $id) : null;
        $audit = new AuditService($this->getDatabase());

        if ($entity === 'members') {
            $data = $this->memberPeopleLinkService($repository, $audit)->validateForSave($id, $old, $data);
            foreach (['can_vote_override', 'can_candidate_override'] as $overrideField) {
                $data[$overrideField] = ($data[$overrideField] ?? '') === '' ? null : (int) $data[$overrideField];
            }
        }

        foreach ($config['fields'] as $name => $field) {
            if (($field['unique'] ?? false) && isset($data[$name]) && $data[$name] !== '' && $data[$name] !== null && $repository->duplicateExists($config['table'], $name, $data[$name], $id)) {
                throw new RuntimeException(Text::sprintf('COM_DECAROMEMBERSHIP_ERROR_DUPLICATE', $name));
            }
        }

        $userId = (int) Factory::getApplication()->getIdentity()->id;
        $now = Factory::getDate()->toSql();
        $oldPersonUuid = $entity === 'members' ? strtolower(trim((string) ($old->person_uuid ?? ''))) : '';
        $data['modified'] = $now;
        $data['modified_by'] = $userId;
        if ($id < 1) {
            $data['created'] = $now;
            $data['created_by'] = $userId;
        }
        $id = $repository->save($config['table'], $id, $data);
        $new = $repository->load($config['table'], $id);
        $audit->record($entity, $id, $old ? 'update' : 'create', $userId, $old, $new, $now);

        if ($entity === 'members' && $old && $oldPersonUuid === '' && trim((string) ($new->person_uuid ?? '')) !== '') {
            $audit->personLink($id, 'people_link', null, strtolower((string) $new->person_uuid), $userId, $now);
        }
        if ($entity === 'members' && is_object($new)) {
            $history = new MembershipPersonHistoryService(
                $this->getDatabase(),
                new MembershipEligibilityService($this->getDatabase())
            );
            $history->recordTransition($id, $old, $new, $userId, $now);
        }
        if ($entity === 'cases' && $old && (int) ($old->status_id ?? 0) !== (int) ($new->status_id ?? 0)) {
            $audit->caseStatus($id, ($old->status_id ?? null) ? (int) $old->status_id : null, ($new->status_id ?? null) ? (int) $new->status_id : null, $userId, $now);
        }
        return $id;
    }

    public function trashEntities(string $entity, array $ids): void
    {
        if (!EntityRegistry::has($entity)) throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_INVALID_ENTITY'));
        $config = EntityRegistry::get($entity);
        if (!isset($config['fields']['published'])) throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_TRASH_UNSUPPORTED'));
        $repository = $this->repository();
        $audit = new AuditService($this->getDatabase());
        $userId = (int) Factory::getApplication()->getIdentity()->id;
        $now = Factory::getDate()->toSql();
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id < 1) continue;
            $old = $repository->load($config['table'], $id);
            if (!$old) continue;
            $repository->trash($config['table'], $id, $now, $userId);
            $audit->record($entity, $id, 'trash', $userId, $old, $repository->load($config['table'], $id), $now);
        }
    }
}
