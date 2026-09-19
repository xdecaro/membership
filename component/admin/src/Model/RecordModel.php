<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Model;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use RuntimeException;
use Xdecaro\Component\Decaromembership\Administrator\Helper\EntityRegistry;
use Xdecaro\Component\Decaromembership\Administrator\Service\AuditService;
use Xdecaro\Component\Decaromembership\Administrator\Service\MemberPeopleLinkService;
use Xdecaro\Component\Decaromembership\Administrator\Service\MembershipHistoryService;
use Xdecaro\Component\Decaromembership\Administrator\Service\MemberLifecycleService;
use Xdecaro\Component\Decaromembership\Administrator\Service\OrganizationsIntegrationService;
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
        $select = $entity === 'members'
            ? [$db->quoteName('id'), "CONCAT(" . $db->quoteName('last_name') . ", ' ', " . $db->quoteName('first_name') . ") AS " . $db->quoteName('title')]
            : [$db->quoteName('id'), $db->quoteName($config['title_field'], 'title')];
        return $db->setQuery($db->getQuery(true)->select($select)->from($db->quoteName($config['table']))->order($db->quoteName('title') . ' ASC'))->loadObjectList();
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

            // Organizations is optional. Preserve an existing link if the field is
            // not present in the request (for example while the provider is offline).
            if ($old !== null && !array_key_exists('organization_uuid', $input)) {
                $data['organization_uuid'] = $old->organization_uuid ?? null;
            }

            $organizationUuid = strtolower(trim((string) ($data['organization_uuid'] ?? '')));
            if ($organizationUuid === '') {
                $data['organization_uuid'] = null;
            } else {
                $organizations = new OrganizationsIntegrationService();
                $oldOrganizationUuid = strtolower(trim((string) ($old->organization_uuid ?? '')));

                if (!$organizations->isAvailable()) {
                    if ($oldOrganizationUuid !== '' && $organizationUuid === $oldOrganizationUuid) {
                        $data['organization_uuid'] = $oldOrganizationUuid;
                    } else {
                        throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ORGANIZATIONS_UNAVAILABLE'));
                    }
                } else {
                    $data['organization_uuid'] = $organizations->validateOptionalUuid($organizationUuid);
                }
            }

            $data = (new MemberLifecycleService())->prepareForSave(
                $id,
                $old,
                $data,
                $input,
                Factory::getDate()->format('Y-m-d')
            );
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
        $wasNew = $id < 1;
        $id = $repository->save($config['table'], $id, $data);

        if ($entity === 'members') {
            $lifecycle = new MemberLifecycleService();
            $current = $repository->load($config['table'], $id);
            $currentNumber = trim((string) ($current->member_number ?? ''));

            if ($lifecycle->isAutomaticNumbering() && $currentNumber === '') {
                $generatedNumber = $lifecycle->generateNumber($id);

                if ($repository->duplicateExists($config['table'], 'member_number', $generatedNumber, $id)) {
                    throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_MEMBER_NUMBER_COLLISION'));
                }

                $repository->updateMemberNumber($id, $generatedNumber, $now, $userId);
            }
        }

        $new = $repository->load($config['table'], $id);
        $audit->record($entity, $id, $old ? 'update' : 'create', $userId, $old, $new, $now);

        $history = new MembershipHistoryService($this->getDatabase());
        if ($entity === 'members' && $new) {
            $history->recordMemberChange($id, $old, $new, $userId, $now);
        }

        if ($entity === 'transfers' && $new && ($new->status ?? '') === 'completed' && (!$old || ($old->status ?? '') !== 'completed')) {
            $memberId = (int) ($new->member_id ?? 0);
            $destinationId = (int) ($new->to_location_id ?? 0);
            if ($memberId > 0 && $destinationId > 0) {
                $beforeMember = $repository->load('#__decaromembership_members', $memberId);
                $repository->updateMemberLocation($memberId, $destinationId, $now, $userId);
                $afterMember = $repository->load('#__decaromembership_members', $memberId);
                if ($afterMember) {
                    $history->recordMemberChange(
                        $memberId,
                        $beforeMember,
                        $afterMember,
                        $userId,
                        $now,
                        'transfer',
                        $id
                    );
                }
            }
        }

        if ($entity === 'members' && $old && $oldPersonUuid === '' && trim((string) ($new->person_uuid ?? '')) !== '') {
            $audit->personLink($id, 'people_link', null, strtolower((string) $new->person_uuid), $userId, $now);
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
