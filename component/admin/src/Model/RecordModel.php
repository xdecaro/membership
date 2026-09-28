<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Model;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use RuntimeException;
use Xdecaro\Component\Decaromembership\Administrator\Helper\EntityRegistry;
use Xdecaro\Component\Decaromembership\Administrator\Service\AuditService;
use Xdecaro\Component\Decaromembership\Administrator\Service\CompetitionsIntegrationService;
use Xdecaro\Component\Decaromembership\Administrator\Service\CompetitionCardNumberService;
use Xdecaro\Component\Decaromembership\Administrator\Service\CardNumberingPolicyService;
use Xdecaro\Component\Decaromembership\Administrator\Service\DclCardService;
use Xdecaro\Component\Decaromembership\Administrator\Service\DclCardStatusPolicy;
use Xdecaro\Component\Decaromembership\Administrator\Service\MemberPeopleLinkService;
use Xdecaro\Component\Decaromembership\Administrator\Service\MembershipHistoryService;
use Xdecaro\Component\Decaromembership\Administrator\Service\MemberLifecycleService;
use Xdecaro\Component\Decaromembership\Administrator\Service\OrganizationsIntegrationService;
use Xdecaro\Component\Decaromembership\Administrator\Service\PeopleIntegrationService;
use Xdecaro\Component\Decaromembership\Administrator\Service\PaymentAllocationService;
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

    public function getCurrentMemberCard(int $memberId): ?object
    {
        return $this->repository()->loadCurrentMemberCard($memberId);
    }

    public function isCompetitionsAvailable(): bool
    {
        return (new CompetitionsIntegrationService())->isAvailable();
    }

    public function getDclSeasonOptions(): array
    {
        return (new CompetitionsIntegrationService())->listDclSeasons();
    }

    public function getDclSeason(int $seasonId): ?array
    {
        return (new CompetitionsIntegrationService())->getDclSeason($seasonId);
    }

    public function getCardNumberingPolicy(string $issuerOrganizationUuid, string $scope): array
    {
        return (new CardNumberingPolicyService($this->getDatabase()))->resolve($issuerOrganizationUuid, $scope);
    }

    public function getLinkedCompetitionSeasonId(int $cardId): int
    {
        return (new DclCardService($this->getDatabase()))->getLinkedCompetitionSeasonId($cardId);
    }

    private function recalculatePaymentDues(
        RecordRepository $repository,
        AuditService $audit,
        array $dueIds,
        int $userId,
        string $now
    ): void {
        $service = new PaymentAllocationService($this->getDatabase());

        foreach (array_unique(array_filter(array_map('intval', $dueIds))) as $dueId) {
            $before = $repository->load('#__decaromembership_dues', $dueId);
            if (!$before) {
                continue;
            }

            $service->recalculateDue($dueId, $userId, $now);
            $after = $repository->load('#__decaromembership_dues', $dueId);

            if (
                $after
                && (
                    (float) ($before->paid_amount ?? 0) !== (float) ($after->paid_amount ?? 0)
                    || (string) ($before->status ?? '') !== (string) ($after->status ?? '')
                )
            ) {
                $audit->record('dues', $dueId, 'payment_recalculate', $userId, $before, $after, $now);
            }
        }
    }

    public function getItem(int $id = 0): object
    {
        $app = Factory::getApplication();
        $id = $id ?: $app->input->getInt('id');
        $entity = $this->getEntityFromRequest();
        $stateKey = 'com_decaromembership.record.' . $entity . '.' . $id . '.data';
        $submitted = $app->getUserState($stateKey);

        if (is_array($submitted)) {
            $app->setUserState($stateKey, null);
            $item = (object) $submitted;
            $item->id = $id;
            return $item;
        }

        if ($id < 1) return (object) ['id' => 0];
        return $this->repository()->load(EntityRegistry::get($entity)['table'], $id) ?: (object) ['id' => 0];
    }

    public function getRelationOptions(string $entity, int $includeId = 0): array
    {
        if (!EntityRegistry::has($entity)) return [];
        $config = EntityRegistry::get($entity);
        $db = $this->getDatabase();

        if ($entity === 'members') {
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('id'),
                    $db->quoteName('person_uuid'),
                    $db->quoteName('first_name'),
                    $db->quoteName('last_name'),
                    $db->quoteName('member_number'),
                    $db->quoteName('organization_uuid'),
                ])
                ->from($db->quoteName($config['table']));

            $rows = $db->setQuery($query)->loadObjectList();
            $uuids = [];
            foreach ($rows as $row) {
                $uuid = strtolower(trim((string) ($row->person_uuid ?? '')));
                if ($uuid !== '') {
                    $uuids[$uuid] = $uuid;
                }
            }

            $people = [];
            if ($uuids !== []) {
                try {
                    $people = (new PeopleIntegrationService($db))->getPeopleByUuids(array_values($uuids));
                } catch (\Throwable) {
                    $people = [];
                }
            }

            foreach ($rows as $row) {
                $uuid = strtolower(trim((string) ($row->person_uuid ?? '')));
                $person = $uuid !== '' ? ($people[$uuid] ?? null) : null;
                $title = trim((string) ($person['display_name'] ?? ''));
                if ($title === '' && is_array($person)) {
                    $title = trim((string) (($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? '')));
                }
                if ($title === '') {
                    $title = trim((string) (($row->last_name ?? '') . ' ' . ($row->first_name ?? '')));
                }
                if ($title === '') {
                    $memberNumber = trim((string) ($row->member_number ?? ''));
                    $title = $memberNumber !== '' ? $memberNumber : Text::sprintf('COM_DECAROMEMBERSHIP_MEMBER_FALLBACK_LABEL', (int) $row->id);
                }
                $row->title = $title;
            }

            usort($rows, static fn(object $a, object $b): int => strcasecmp((string) $a->title, (string) $b->title));
            return $rows;
        }

        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName($config['title_field'], 'title')])
            ->from($db->quoteName($config['table']))
            ->order($db->quoteName('title') . ' ASC');

        if (isset($config['fields']['published'])) {
            if ($includeId > 0) {
                $query->where(
                    '(' . $db->quoteName('published') . ' = 1 OR ' . $db->quoteName('id') . ' = :include_id)'
                )->bind(':include_id', $includeId);
            } else {
                $query->where($db->quoteName('published') . ' = 1');
            }
        }

        return $db->setQuery($query)->loadObjectList();
    }

    public function saveEntity(string $entity, int $id, array $input): int
    {
        if (!EntityRegistry::has($entity)) throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_INVALID_ENTITY'));
        $config = EntityRegistry::get($entity);
        $repository = $this->repository();
        $old = $id > 0 ? $repository->load($config['table'], $id) : null;

        if ($entity === 'transfers') {
            $today = Factory::getDate()->format('Y-m-d');

            if ($id < 1 && trim((string) ($input['requested_at'] ?? '')) === '') {
                $input['requested_at'] = $today;
            }

            if (($input['status'] ?? 'requested') === 'completed' && trim((string) ($input['completed_at'] ?? '')) === '') {
                $input['completed_at'] = $today;
            }

            if ($id < 1 && (int) ($input['member_id'] ?? 0) > 0) {
                $member = $repository->load('#__decaromembership_members', (int) $input['member_id']);
                $memberOrganization = strtolower(trim((string) ($member->organization_uuid ?? '')));
                if ($memberOrganization !== '') {
                    // The member's current organization is authoritative for a new transfer.
                    $input['from_organization_uuid'] = $memberOrganization;
                }
            }
        }

        $validator = new RecordValidator();
        $data = $validator->filter($config, $input);

        if ($entity === 'cards' && $old !== null) {
            // These fields are intentionally hidden from the simplified card form.
            // Preserve their stored values instead of treating an omitted field as
            // an instruction to clear/regenerate it.
            $technicalFields = ['issued_at', 'activated_at', 'annual_mark', 'qr_token'];
            foreach ($technicalFields as $technicalField) {
                if (!array_key_exists($technicalField, $input)) {
                    $data[$technicalField] = $old->{$technicalField} ?? null;
                }
            }
        }

        if ($entity === 'transfers') {
            $organizations = new OrganizationsIntegrationService();

            foreach (['from_organization_uuid', 'to_organization_uuid'] as $fieldName) {
                $uuid = strtolower(trim((string) ($data[$fieldName] ?? '')));
                $data[$fieldName] = $uuid === '' ? null : $uuid;

                if ($uuid !== '') {
                    if (!$organizations->isAvailable()) {
                        throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ORGANIZATIONS_UNAVAILABLE'));
                    }
                    $data[$fieldName] = $organizations->validateOptionalUuid($uuid);
                }
            }

            if (($data['arrears_amount'] ?? null) === null) {
                $data['arrears_amount'] = 0.0;
            }
        }

        if ($entity === 'card_numbering_rules') {
            $issuerUuid = strtolower(trim((string) ($data['issuer_organization_uuid'] ?? '')));
            $organizations = new OrganizationsIntegrationService();
            if (!$organizations->isAvailable()) {
                throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ORGANIZATIONS_UNAVAILABLE'));
            }
            $data['issuer_organization_uuid'] = $organizations->validateOptionalUuid($issuerUuid);

            $policyService = new CardNumberingPolicyService($this->getDatabase());
            $normalizedRule = $policyService->normalize($data);
            $data['scope'] = $normalizedRule['scope'];
            $data['numbering_mode'] = $normalizedRule['numbering_mode'];
            $data['manual_edit'] = $normalizedRule['manual_edit'];
            $data['sequence_padding'] = $normalizedRule['sequence_padding'];

            if ($data['numbering_mode'] === CardNumberingPolicyService::MODE_EXTERNAL && trim((string) ($data['source'] ?? '')) === '') {
                throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_NUMBER_SOURCE_REQUIRED'));
            }
            if ($policyService->duplicateExists((string) $data['issuer_organization_uuid'], (string) $data['scope'], $id)) {
                throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_NUMBERING_RULE_DUPLICATE'));
            }
        }

        if ($entity !== 'cards') {
            $validator->validateBusinessRules($entity, $data);
        }

        $competitionSeasonId = max(0, (int) ($input['competition_season_id'] ?? 0));
        $competitionSeasonSubmitted = array_key_exists('competition_season_id', $input);
        $competitionSeason = null;
        $numberingPolicy = null;

        if ($entity === 'cards') {
            $scope = strtolower(trim((string) ($data['scope'] ?? 'association')));
            $scope = in_array($scope, ['association', 'competition'], true) ? $scope : 'association';
            $data['scope'] = $scope;

            $oldScope = strtolower(trim((string) ($old->scope ?? '')));
            $oldCardNumber = trim((string) ($old->card_number ?? ''));
            if ($old !== null && $oldScope === 'competition' && $oldCardNumber !== '' && $scope !== 'competition') {
                throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_NUMBER_CONTEXT_IMMUTABLE'));
            }

            $memberId = (int) ($data['member_id'] ?? 0);
            $personUuid = strtolower(trim((string) ($data['person_uuid'] ?? '')));
            if ($personUuid === '' && $memberId > 0) {
                $member = $repository->load('#__decaromembership_members', $memberId);
                $personUuid = strtolower(trim((string) ($member->person_uuid ?? '')));
            }
            if ($personUuid !== '') {
                $people = new PeopleIntegrationService($this->getDatabase());
                if ($people->getPerson($personUuid, false) === null) {
                    throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_PERSON_INVALID'));
                }
                $data['person_uuid'] = $personUuid;
                $linkedMemberId = $repository->findMemberIdByPersonUuid($personUuid);
                $data['member_id'] = $linkedMemberId ?: null;
            } else {
                $data['person_uuid'] = null;
                $data['member_id'] = $memberId > 0 ? $memberId : null;
            }

            $issuerUuid = strtolower(trim((string) ($data['issuer_organization_uuid'] ?? '')));
            if ($issuerUuid !== '') {
                $organizations = new OrganizationsIntegrationService();
                if (!$organizations->isAvailable()) {
                    $oldIssuer = strtolower(trim((string) ($old->issuer_organization_uuid ?? '')));
                    if ($oldIssuer === '' || $oldIssuer !== $issuerUuid) {
                        throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ORGANIZATIONS_UNAVAILABLE'));
                    }
                } else {
                    $data['issuer_organization_uuid'] = $organizations->validateOptionalUuid($issuerUuid);
                }
            } else {
                $data['issuer_organization_uuid'] = null;
            }

            if ($scope === 'competition') {
                $data['program'] = trim((string) ($old->program ?? '')) ?: 'standard';
                $competitions = new CompetitionsIntegrationService();
                if ($competitions->isAvailable()) {
                    if ($competitionSeasonId < 1) {
                        throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_DCL_COMPETITION_SEASON_REQUIRED'));
                    }
                    $competitionSeason = $competitions->getDclSeason($competitionSeasonId);
                    if ($competitionSeason === null) {
                        throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_DCL_COMPETITION_SEASON_INVALID'));
                    }

                    $rightsHolderUuid = strtolower(trim((string) ($competitionSeason['rights_holder_organization_uuid'] ?? '')));
                    if ($rightsHolderUuid !== '') {
                        $organizations = new OrganizationsIntegrationService();
                        if (!$organizations->isAvailable()) {
                            $oldIssuer = strtolower(trim((string) ($old->issuer_organization_uuid ?? '')));
                            if ($oldIssuer === '' || $oldIssuer !== $rightsHolderUuid) {
                                throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ORGANIZATIONS_UNAVAILABLE'));
                            }
                            $data['issuer_organization_uuid'] = $oldIssuer;
                        } else {
                            $data['issuer_organization_uuid'] = $organizations->validateOptionalUuid($rightsHolderUuid);
                        }
                    }

                    $oldCompetitionSeasonId = $id > 0 ? $this->getLinkedCompetitionSeasonId($id) : 0;
                    if ($oldCardNumber !== '' && $oldScope === 'competition') {
                        if ($oldCompetitionSeasonId > 0 && $competitionSeasonId !== $oldCompetitionSeasonId) {
                            throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_NUMBER_CONTEXT_IMMUTABLE'));
                        }
                        $oldIssuer = strtolower(trim((string) ($old->issuer_organization_uuid ?? '')));
                        $currentIssuer = strtolower(trim((string) ($data['issuer_organization_uuid'] ?? '')));
                        if ($oldIssuer !== '' && $currentIssuer !== '' && $oldIssuer !== $currentIssuer) {
                            throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_NUMBER_CONTEXT_IMMUTABLE'));
                        }
                        if ($oldIssuer !== '') {
                            $data['issuer_organization_uuid'] = $oldIssuer;
                        }
                        $data['card_number'] = $oldCardNumber;
                    }

                    $seasonValue = trim((string) ($competitionSeason['season_year'] ?? ''));
                    if ($seasonValue === '') {
                        $seasonValue = trim((string) ($competitionSeason['name'] ?? ''));
                    }
                    if ($seasonValue !== '') {
                        $data['season'] = $seasonValue;
                    }

                    $seasonStart = trim((string) ($competitionSeason['start_date'] ?? ''));
                    $seasonEnd = trim((string) ($competitionSeason['end_date'] ?? ''));
                    // Competitions is authoritative for validity dates of competition cards.
                    $data['valid_from'] = $seasonStart !== '' ? $seasonStart : null;
                    $data['expires_at'] = $seasonEnd !== '' ? $seasonEnd : null;
                    $validFrom = trim((string) ($data['valid_from'] ?? ''));
                    $expiresAt = trim((string) ($data['expires_at'] ?? ''));
                    if ($seasonStart !== '' && $validFrom !== '' && $validFrom > $seasonStart) {
                        throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_DCL_VALID_FROM_SEASON'));
                    }
                    if ($seasonEnd !== '' && $expiresAt !== '' && $expiresAt < $seasonEnd) {
                        throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_DCL_EXPIRY_SEASON'));
                    }
                } elseif ($id < 1 || $this->getLinkedCompetitionSeasonId($id) < 1) {
                    throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_COMPETITIONS_UNAVAILABLE'));
                }

                if (trim((string) ($data['season'] ?? '')) === '') {
                    throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_DCL_SEASON_REQUIRED'));
                }
            } else {
                $data['program'] = 'standard';
                $data['season'] = null;
                $competitionSeasonId = 0;
                $competitionSeasonSubmitted = true;
            }

            $policyService = new CardNumberingPolicyService($this->getDatabase());
            $numberingPolicy = $policyService->resolve(
                (string) ($data['issuer_organization_uuid'] ?? ''),
                $scope
            );
            $numberingMode = (string) ($numberingPolicy['numbering_mode'] ?? CardNumberingPolicyService::MODE_MANUAL);
            $manualEdit = !empty($numberingPolicy['manual_edit']);

            if ($oldCardNumber !== '' && $oldScope === 'competition') {
                $data['card_number'] = $oldCardNumber;
            } elseif ($numberingMode === CardNumberingPolicyService::MODE_AUTOMATIC) {
                // Automatic policies never trust a manually submitted number and never renumber an existing card.
                $data['card_number'] = $oldCardNumber !== '' ? $oldCardNumber : null;
            } elseif ($numberingMode === CardNumberingPolicyService::MODE_EXTERNAL && !$manualEdit) {
                // External/imported numbers are authoritative outside the normal form.
                $data['card_number'] = $old !== null ? ($old->card_number ?? null) : null;
            }

            $today = Factory::getDate()->format('Y-m-d');
            if ($id < 1 && trim((string) ($data['issued_at'] ?? '')) === '') {
                $data['issued_at'] = $today;
            }
            if (($data['status'] ?? 'pending') === 'active' && trim((string) ($data['activated_at'] ?? '')) === '') {
                $data['activated_at'] = $today;
            }
            if (trim((string) ($data['qr_token'] ?? '')) === '') {
                $data['qr_token'] = bin2hex(random_bytes(16));
            }
            $data['published'] = 1;

            if ($scope === 'competition' && $competitionSeasonId > 0) {
                $dclCards = new DclCardService($this->getDatabase());
                if ($dclCards->duplicateCompetitionCredentialExists(
                    (string) ($data['person_uuid'] ?? ''),
                    (string) ($data['issuer_organization_uuid'] ?? ''),
                    $competitionSeasonId,
                    $id
                )) {
                    throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_DUPLICATE_COMPETITION'));
                }
            }
        }

        if ($entity === 'cards') {
            $scope = strtolower(trim((string) ($data['scope'] ?? 'association')));
            $numberingPolicy ??= (new CardNumberingPolicyService($this->getDatabase()))->resolve(
                (string) ($data['issuer_organization_uuid'] ?? ''),
                $scope
            );
            $numberingMode = (string) ($numberingPolicy['numbering_mode'] ?? CardNumberingPolicyService::MODE_MANUAL);

            if ($numberingMode === CardNumberingPolicyService::MODE_AUTOMATIC && trim((string) ($data['card_number'] ?? '')) === '') {
                $organizations = new OrganizationsIntegrationService();
                if (!$organizations->isAvailable()) {
                    throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ORGANIZATIONS_UNAVAILABLE'));
                }
                $numberService = new CompetitionCardNumberService($this->getDatabase(), $organizations);
                $padding = max(1, min(12, (int) ($numberingPolicy['sequence_padding'] ?? 7)));

                if ($scope === 'competition') {
                    if (!is_array($competitionSeason)) {
                        throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_CARD_NUMBER_COMPETITION_CONTEXT'));
                    }
                    $data['card_number'] = $numberService->allocate(
                        (string) ($data['issuer_organization_uuid'] ?? ''),
                        $competitionSeason,
                        Factory::getDate()->toSql(),
                        $padding
                    );
                } else {
                    $data['card_number'] = $numberService->allocateAssociation(
                        (string) ($data['issuer_organization_uuid'] ?? ''),
                        (int) Factory::getDate()->format('Y'),
                        Factory::getDate()->toSql(),
                        $padding
                    );
                }
            }

            $validator->validateBusinessRules($entity, $data);
            $data['status'] = DclCardStatusPolicy::normalizeLifecycleStatus(
                $data,
                Factory::getDate()->format('Y-m-d')
            );
        }

        if ($entity === 'dues' && ($data['paid_amount'] ?? null) === null) {
            $data['paid_amount'] = 0.0;
        }

        if ($entity === 'payments') {
            (new PaymentAllocationService($this->getDatabase()))->validateDueMember(
                (int) ($data['due_id'] ?? 0),
                (int) ($data['member_id'] ?? 0)
            );
        }

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

        if (
            $entity === 'renewals'
            && $repository->renewalDuplicateExists(
                (int) ($data['member_id'] ?? 0),
                (string) ($data['association_year'] ?? ''),
                $id
            )
        ) {
            throw new RuntimeException(Text::_('COM_DECAROMEMBERSHIP_ERROR_RENEWAL_DUPLICATE'));
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

        if ($entity === 'cards') {
            $dclCards = new DclCardService($this->getDatabase());
            $scope = strtolower(trim((string) ($data['scope'] ?? 'association')));
            if ($scope === 'competition' && $competitionSeasonId > 0) {
                $dclCards->linkCompetitionSeason(
                    $id,
                    (int) ($data['member_id'] ?? 0),
                    $competitionSeasonId,
                    [
                        'season' => (string) ($data['season'] ?? ''),
                        'valid_from' => $data['valid_from'] ?? null,
                        'expires_at' => $data['expires_at'] ?? null,
                        'season_name' => is_array($competitionSeason) ? ($competitionSeason['name'] ?? null) : null,
                    ],
                    $userId,
                    $now
                );
            } elseif ($scope !== 'competition' || $competitionSeasonSubmitted) {
                $dclCards->unlinkCompetitionSeason($id);
            }
        }

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

        if ($entity === 'payments') {
            $this->recalculatePaymentDues(
                $repository,
                $audit,
                [
                    (int) ($old->due_id ?? 0),
                    (int) ($new->due_id ?? 0),
                ],
                $userId,
                $now
            );
        }

        $history = new MembershipHistoryService($this->getDatabase());
        if ($entity === 'members' && $new) {
            $history->recordMemberChange($id, $old, $new, $userId, $now);
        }

        if ($entity === 'transfers' && $new && ($new->status ?? '') === 'completed' && (!$old || ($old->status ?? '') !== 'completed')) {
            $memberId = (int) ($new->member_id ?? 0);
            $destinationOrganization = strtolower(trim((string) ($new->to_organization_uuid ?? '')));
            $legacyDestinationId = (int) ($new->to_location_id ?? 0);

            if ($memberId > 0 && ($destinationOrganization !== '' || $legacyDestinationId > 0)) {
                $beforeMember = $repository->load('#__decaromembership_members', $memberId);

                if ($destinationOrganization !== '') {
                    $repository->updateMemberOrganization($memberId, $destinationOrganization, $now, $userId);
                }

                if ($legacyDestinationId > 0) {
                    $repository->updateMemberLocation($memberId, $legacyDestinationId, $now, $userId);
                }

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
            if ($entity === 'payments') {
                $this->recalculatePaymentDues(
                    $repository,
                    $audit,
                    [(int) ($old->due_id ?? 0)],
                    $userId,
                    $now
                );
            }
        }
    }
}
