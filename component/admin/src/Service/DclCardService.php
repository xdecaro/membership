<?php
namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Throwable;

final class DclCardService
{
    public const COMPONENT = 'com_competitions';
    public const EXTERNAL_ENTITY = 'season';
    public const RELATION = 'card_competition_context';
    public const LEGACY_RELATION = 'dcl_card_season';

    public function __construct(private DatabaseInterface $db) {}

    public function getStatusForPersonUuid(
        string $personUuid,
        int $competitionSeasonId,
        ?string $requiredFrom = null,
        ?string $requiredUntil = null
    ): array {
        $personUuid = strtolower(trim($personUuid));
        if ($personUuid === '' || $competitionSeasonId < 1) {
            return ['status' => $personUuid === '' ? 'person_unlinked' : 'missing'];
        }

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('c.id'),
                $this->db->quoteName('c.member_id'),
                $this->db->quoteName('c.person_uuid'),
                $this->db->quoteName('c.issuer_organization_uuid'),
                $this->db->quoteName('c.card_number'),
                $this->db->quoteName('c.program'),
                $this->db->quoteName('c.season'),
                $this->db->quoteName('c.status'),
                $this->db->quoteName('c.valid_from'),
                $this->db->quoteName('c.expires_at'),
            ])
            ->from($this->db->quoteName('#__decaromembership_cards', 'c'))
            ->leftJoin(
                $this->db->quoteName('#__decaromembership_members', 'm')
                . ' ON ' . $this->db->quoteName('m.id') . ' = ' . $this->db->quoteName('c.member_id')
            )
            ->innerJoin(
                $this->db->quoteName('#__decaromembership_entity_links', 'l')
                . ' ON ' . $this->db->quoteName('l.local_entity_type') . ' = ' . $this->db->quote('cards')
                . ' AND ' . $this->db->quoteName('l.local_entity_id') . ' = ' . $this->db->quoteName('c.id')
                . ' AND ' . $this->db->quoteName('l.component') . ' = ' . $this->db->quote(self::COMPONENT)
                . ' AND ' . $this->db->quoteName('l.external_entity_type') . ' = ' . $this->db->quote(self::EXTERNAL_ENTITY)
                . ' AND ' . $this->relationCondition('l')
            )
            ->where('COALESCE(' . $this->db->quoteName('c.person_uuid') . ', ' . $this->db->quoteName('m.person_uuid') . ') = :person_uuid')
            ->where($this->db->quoteName('c.published') . ' = 1')
            ->where('(' . $this->db->quoteName('c.scope') . ' = ' . $this->db->quote('competition') . ' OR ' . $this->db->quoteName('c.program') . ' = ' . $this->db->quote('dcl') . ')')
            ->where($this->db->quoteName('l.external_entity_id') . ' = :season_id')
            ->order(
                'CASE ' . $this->db->quoteName('c.status')
                . " WHEN 'active' THEN 0 WHEN 'expired' THEN 1 WHEN 'in_review' THEN 2 WHEN 'pending' THEN 3 ELSE 4 END ASC"
            )
            ->order($this->db->quoteName('c.id') . ' DESC')
            ->bind(':person_uuid', $personUuid)
            ->bind(':season_id', $competitionSeasonId, ParameterType::INTEGER);

        $cards = (array) $this->db->setQuery($query)->loadObjectList();
        if ($cards === []) {
            return ['status' => 'missing'];
        }

        $fallback = null;
        foreach ($cards as $card) {
            $status = DclCardStatusPolicy::evaluate($card, $requiredFrom, $requiredUntil);
            $result = [
                'status' => $status,
                'card_id' => (int) ($card->id ?? 0),
                'member_id' => (int) ($card->member_id ?? 0),
                'person_uuid' => (string) ($card->person_uuid ?? ''),
                'issuer_organization_uuid' => (string) ($card->issuer_organization_uuid ?? ''),
                'card_number' => (string) ($card->card_number ?? ''),
                'season' => (string) ($card->season ?? ''),
                'valid_from' => $card->valid_from ?? null,
                'expires_at' => $card->expires_at ?? null,
            ];

            if ($status === 'valid') {
                return $result;
            }
            $fallback ??= $result;
        }

        return $fallback ?? ['status' => 'missing'];
    }

    public function previewCompetitionBatch(array $personUuids, int $competitionSeasonId): array
    {
        $season = $competitionSeasonId > 0
            ? (new CompetitionsIntegrationService())->getDclSeason($competitionSeasonId)
            : null;

        if (!is_array($season)) {
            return [
                'season' => null,
                'rows' => [],
                'counts' => ['create' => 0, 'existing' => 0, 'invalid' => 0],
                'error' => 'competition_season_invalid',
            ];
        }

        $normalized = [];
        foreach ($personUuids as $uuid) {
            $uuid = strtolower(trim((string) $uuid));
            if ($uuid !== '') {
                $normalized[$uuid] = $uuid;
            }
        }

        $peopleMap = [];
        if ($normalized !== []) {
            try {
                $peopleMap = (new PeopleIntegrationService($this->db))->getPeopleByUuids(array_values($normalized));
            } catch (Throwable) {
                $peopleMap = [];
            }
        }

        $rows = [];
        $counts = ['create' => 0, 'existing' => 0, 'invalid' => 0];
        foreach ($normalized as $uuid) {
            $person = $peopleMap[$uuid] ?? null;
            $label = is_array($person)
                ? trim((string) ($person['display_name'] ?? (($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? ''))))
                : '';
            $label = $label !== '' ? $label : $uuid;

            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid) || !is_array($person)) {
                $counts['invalid']++;
                $rows[] = ['person_uuid' => $uuid, 'label' => $label, 'status' => 'invalid', 'card_id' => 0, 'card_number' => ''];
                continue;
            }

            $existingId = $this->findCompetitionCredentialId($uuid, $competitionSeasonId);
            if ($existingId > 0) {
                $counts['existing']++;
                $rows[] = [
                    'person_uuid' => $uuid,
                    'label' => $label,
                    'status' => 'existing',
                    'card_id' => $existingId,
                    'card_number' => $this->getCardNumberById($existingId),
                ];
                continue;
            }

            $counts['create']++;
            $rows[] = ['person_uuid' => $uuid, 'label' => $label, 'status' => 'create', 'card_id' => 0, 'card_number' => ''];
        }

        return ['season' => $season, 'rows' => $rows, 'counts' => $counts, 'error' => null];
    }

    public function createCompetitionBatch(array $personUuids, int $competitionSeasonId, int $actorUserId, string $nowSql): array
    {
        $result = ['created' => 0, 'existing' => 0, 'failed' => 0, 'cards' => []];
        $seen = [];

        foreach ($personUuids as $uuid) {
            $uuid = strtolower(trim((string) $uuid));
            if ($uuid === '' || isset($seen[$uuid])) {
                continue;
            }
            $seen[$uuid] = true;

            $card = $this->createCompetitionCredential($uuid, $competitionSeasonId, $actorUserId, $nowSql);
            $status = (string) ($card['status'] ?? 'failed');
            if ($status === 'created') {
                $result['created']++;
            } elseif ($status === 'existing') {
                $result['existing']++;
            } else {
                $result['failed']++;
            }
            $result['cards'][] = $card;
        }

        return $result;
    }

    public function createCompetitionCredential(string $personUuid, int $competitionSeasonId, int $actorUserId, string $nowSql): array
    {
        $identity = Factory::getApplication()->getIdentity();
        if (!$identity->authorise('core.create', 'com_decaromembership')) {
            return ['status' => 'failed', 'card_id' => 0, 'card_number' => '', 'person_uuid' => $personUuid];
        }
        $actorUserId = (int) $identity->id;

        $personUuid = strtolower(trim($personUuid));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $personUuid) || $competitionSeasonId < 1) {
            return ['status' => 'failed', 'card_id' => 0, 'card_number' => '', 'person_uuid' => $personUuid];
        }

        try {
            if ((new PeopleIntegrationService($this->db))->getPerson($personUuid, false) === null) {
                return ['status' => 'failed', 'card_id' => 0, 'card_number' => '', 'person_uuid' => $personUuid];
            }

            $existingId = $this->findCompetitionCredentialId($personUuid, $competitionSeasonId);
            if ($existingId > 0) {
                return [
                    'status' => 'existing',
                    'card_id' => $existingId,
                    'card_number' => $this->getCardNumberById($existingId),
                    'person_uuid' => $personUuid,
                ];
            }

            $season = (new CompetitionsIntegrationService())->getDclSeason($competitionSeasonId);
            if (!is_array($season)) {
                return ['status' => 'failed', 'card_id' => 0, 'card_number' => '', 'person_uuid' => $personUuid];
            }

            $memberId = $this->findMemberId($personUuid);
            $issuerOrganizationUuid = strtolower(trim((string) ($season['rights_holder_organization_uuid'] ?? '')));
            $issuerOrganizationUuid = $issuerOrganizationUuid !== '' ? $issuerOrganizationUuid : null;
            $seasonValue = trim((string) ($season['season_year'] ?? '')) ?: trim((string) ($season['name'] ?? ''));
            $validFrom = trim((string) ($season['start_date'] ?? '')) ?: null;
            $expiresAt = trim((string) ($season['end_date'] ?? '')) ?: $validFrom;
            $today = substr($nowSql, 0, 10);
            $qrToken = bin2hex(random_bytes(16));
            $cardNumber = null;

            $policy = (new CardNumberingPolicyService($this->db))->resolve((string) $issuerOrganizationUuid, 'competition');
            if (($policy['numbering_mode'] ?? '') === CardNumberingPolicyService::MODE_AUTOMATIC) {
                if ($issuerOrganizationUuid === null) {
                    return ['status' => 'failed', 'card_id' => 0, 'card_number' => '', 'person_uuid' => $personUuid];
                }
                $organizations = new OrganizationsIntegrationService();
                if (!$organizations->isAvailable()) {
                    return ['status' => 'failed', 'card_id' => 0, 'card_number' => '', 'person_uuid' => $personUuid];
                }
                $padding = max(1, min(12, (int) ($policy['sequence_padding'] ?? 7)));
                $cardNumber = (new CompetitionCardNumberService($this->db, $organizations))->allocate(
                    $issuerOrganizationUuid,
                    $season,
                    $nowSql,
                    $padding
                );
            }

            $query = $this->db->getQuery(true)
                ->insert($this->db->quoteName('#__decaromembership_cards'))
                ->columns(array_map([$this->db, 'quoteName'], [
                    'member_id', 'person_uuid', 'issuer_organization_uuid', 'scope', 'card_number',
                    'program', 'season', 'type', 'status', 'issued_at', 'valid_from', 'expires_at',
                    'qr_token', 'published', 'created', 'created_by', 'modified', 'modified_by',
                ]))
                ->values(':member_id, :person_uuid, :issuer_organization_uuid, :scope, :card_number, :program, :season, :type, :status, :issued_at, :valid_from, :expires_at, :qr_token, 1, :created, :created_by, :modified, :modified_by');

            $nullableMemberId = $memberId > 0 ? $memberId : null;
            $scope = 'competition';
            $program = 'standard';
            $type = 'electronic';
            $status = 'pending';
            $query->bind(':member_id', $nullableMemberId, $nullableMemberId === null ? ParameterType::NULL : ParameterType::INTEGER)
                ->bind(':person_uuid', $personUuid)
                ->bind(':issuer_organization_uuid', $issuerOrganizationUuid, $issuerOrganizationUuid === null ? ParameterType::NULL : ParameterType::STRING)
                ->bind(':scope', $scope)
                ->bind(':card_number', $cardNumber, $cardNumber === null ? ParameterType::NULL : ParameterType::STRING)
                ->bind(':program', $program)
                ->bind(':season', $seasonValue)
                ->bind(':type', $type)
                ->bind(':status', $status)
                ->bind(':issued_at', $today)
                ->bind(':valid_from', $validFrom, $validFrom === null ? ParameterType::NULL : ParameterType::STRING)
                ->bind(':expires_at', $expiresAt, $expiresAt === null ? ParameterType::NULL : ParameterType::STRING)
                ->bind(':qr_token', $qrToken)
                ->bind(':created', $nowSql)
                ->bind(':created_by', $actorUserId, ParameterType::INTEGER)
                ->bind(':modified', $nowSql)
                ->bind(':modified_by', $actorUserId, ParameterType::INTEGER);

            $this->db->setQuery($query)->execute();
            $cardId = (int) $this->db->insertid();
            if ($cardId < 1) {
                return ['status' => 'failed', 'card_id' => 0, 'card_number' => '', 'person_uuid' => $personUuid];
            }

            $this->linkCompetitionSeason($cardId, $memberId, $competitionSeasonId, [
                'season' => $seasonValue,
                'valid_from' => $validFrom,
                'expires_at' => $expiresAt,
                'season_name' => $season['name'] ?? null,
                'bulk' => true,
            ], $actorUserId, $nowSql);

            try {
                (new AuditService($this->db))->record(
                    'cards',
                    $cardId,
                    'bulk_create',
                    $actorUserId,
                    null,
                    (object) [
                        'person_uuid' => $personUuid,
                        'issuer_organization_uuid' => $issuerOrganizationUuid,
                        'card_number' => $cardNumber,
                        'competition_season_id' => $competitionSeasonId,
                    ],
                    $nowSql
                );
            } catch (Throwable) {
                // Audit failure must not invalidate a successfully created credential.
            }

            return [
                'status' => 'created',
                'card_id' => $cardId,
                'card_number' => (string) ($cardNumber ?? ''),
                'person_uuid' => $personUuid,
            ];
        } catch (Throwable) {
            $existingId = $this->findCompetitionCredentialId($personUuid, $competitionSeasonId);
            if ($existingId > 0) {
                return [
                    'status' => 'existing',
                    'card_id' => $existingId,
                    'card_number' => $this->getCardNumberById($existingId),
                    'person_uuid' => $personUuid,
                ];
            }
            return ['status' => 'failed', 'card_id' => 0, 'card_number' => '', 'person_uuid' => $personUuid];
        }
    }

    public function createCompetitionDraft(string $personUuid, int $competitionSeasonId, int $actorUserId, string $nowSql): int
    {
        $result = $this->createCompetitionCredential($personUuid, $competitionSeasonId, $actorUserId, $nowSql);
        return (int) ($result['card_id'] ?? 0);
    }

    private function getCardNumberById(int $cardId): string
    {
        if ($cardId < 1) {
            return '';
        }
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('card_number'))
            ->from($this->db->quoteName('#__decaromembership_cards'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $cardId, ParameterType::INTEGER);
        return trim((string) $this->db->setQuery($query, 0, 1)->loadResult());
    }

    public function findCompetitionCredentialId(string $personUuid, int $competitionSeasonId): int
    {
        $personUuid = strtolower(trim($personUuid));
        if ($personUuid === '' || $competitionSeasonId < 1) {
            return 0;
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('c.id'))
            ->from($this->db->quoteName('#__decaromembership_cards', 'c'))
            ->leftJoin($this->db->quoteName('#__decaromembership_members', 'm') . ' ON ' . $this->db->quoteName('m.id') . ' = ' . $this->db->quoteName('c.member_id'))
            ->innerJoin(
                $this->db->quoteName('#__decaromembership_entity_links', 'l')
                . ' ON ' . $this->db->quoteName('l.local_entity_type') . ' = ' . $this->db->quote('cards')
                . ' AND ' . $this->db->quoteName('l.local_entity_id') . ' = ' . $this->db->quoteName('c.id')
                . ' AND ' . $this->db->quoteName('l.component') . ' = ' . $this->db->quote(self::COMPONENT)
                . ' AND ' . $this->db->quoteName('l.external_entity_type') . ' = ' . $this->db->quote(self::EXTERNAL_ENTITY)
                . ' AND ' . $this->relationCondition('l')
            )
            ->where('COALESCE(' . $this->db->quoteName('c.person_uuid') . ', ' . $this->db->quoteName('m.person_uuid') . ') = :person_uuid')
            ->where($this->db->quoteName('l.external_entity_id') . ' = :season_id')
            ->where($this->db->quoteName('c.published') . ' = 1')
            ->order($this->db->quoteName('c.id') . ' DESC')
            ->bind(':person_uuid', $personUuid)
            ->bind(':season_id', $competitionSeasonId, ParameterType::INTEGER);

        return (int) $this->db->setQuery($query, 0, 1)->loadResult();
    }

    private function findMemberId(string $personUuid): int
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__decaromembership_members'))
            ->where($this->db->quoteName('person_uuid') . ' = :person_uuid')
            ->bind(':person_uuid', $personUuid);

        return (int) $this->db->setQuery($query, 0, 1)->loadResult();
    }

    public function duplicateCompetitionCredentialExists(
        string $personUuid,
        string $issuerOrganizationUuid,
        int $competitionSeasonId,
        int $excludeCardId = 0
    ): bool {
        $personUuid = strtolower(trim($personUuid));
        $issuerOrganizationUuid = strtolower(trim($issuerOrganizationUuid));
        if ($personUuid === '' || $issuerOrganizationUuid === '' || $competitionSeasonId < 1) {
            return false;
        }

        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__decaromembership_cards', 'c'))
            ->innerJoin(
                $this->db->quoteName('#__decaromembership_entity_links', 'l')
                . ' ON ' . $this->db->quoteName('l.local_entity_type') . ' = ' . $this->db->quote('cards')
                . ' AND ' . $this->db->quoteName('l.local_entity_id') . ' = ' . $this->db->quoteName('c.id')
                . ' AND ' . $this->db->quoteName('l.component') . ' = ' . $this->db->quote(self::COMPONENT)
                . ' AND ' . $this->db->quoteName('l.external_entity_type') . ' = ' . $this->db->quote(self::EXTERNAL_ENTITY)
                . ' AND ' . $this->relationCondition('l')
            )
            ->where($this->db->quoteName('c.person_uuid') . ' = :person_uuid')
            ->where($this->db->quoteName('c.issuer_organization_uuid') . ' = :issuer_uuid')
            ->where($this->db->quoteName('c.published') . ' = 1')
            ->where($this->db->quoteName('c.status') . " NOT IN ('revoked','replaced')")
            ->where($this->db->quoteName('l.external_entity_id') . ' = :season_id')
            ->bind(':person_uuid', $personUuid)
            ->bind(':issuer_uuid', $issuerOrganizationUuid)
            ->bind(':season_id', $competitionSeasonId, ParameterType::INTEGER);

        if ($excludeCardId > 0) {
            $query->where($this->db->quoteName('c.id') . ' <> :exclude_card_id')
                ->bind(':exclude_card_id', $excludeCardId, ParameterType::INTEGER);
        }

        return (int) $this->db->setQuery($query)->loadResult() > 0;
    }

    /**
     * Synchronize validity dates for every competition card linked to a Competitions season.
     *
     * Competitions remains the source of truth for season dates. The card number, holder and
     * workflow status are intentionally left untouched.
     *
     * @return array{cards_updated:int,links_updated:int}
     */
    public function syncCompetitionSeasonDates(
        int $competitionSeasonId,
        ?string $validFrom,
        ?string $expiresAt,
        int $actorUserId,
        string $nowSql
    ): array {
        if ($competitionSeasonId < 1) {
            throw new \InvalidArgumentException('Competition season ID must be positive.');
        }

        $validFrom = $this->normalizeSyncDate($validFrom, 'valid_from');
        $expiresAt = $this->normalizeSyncDate($expiresAt, 'expires_at');
        if ($validFrom !== null && $expiresAt !== null && $validFrom > $expiresAt) {
            throw new \InvalidArgumentException('Competition season start date cannot be after its end date.');
        }

        $actorUserId = max(0, $actorUserId);
        $nowSql = trim($nowSql);
        if ($nowSql === '') {
            $nowSql = Factory::getDate()->toSql();
        }

        $query = $this->db->getQuery(true)
            ->select([
                $this->db->quoteName('c.id'),
                $this->db->quoteName('c.valid_from'),
                $this->db->quoteName('c.expires_at'),
                $this->db->quoteName('l.id', 'link_id'),
                $this->db->quoteName('l.metadata'),
            ])
            ->from($this->db->quoteName('#__decaromembership_cards', 'c'))
            ->innerJoin(
                $this->db->quoteName('#__decaromembership_entity_links', 'l')
                . ' ON ' . $this->db->quoteName('l.local_entity_type') . ' = ' . $this->db->quote('cards')
                . ' AND ' . $this->db->quoteName('l.local_entity_id') . ' = ' . $this->db->quoteName('c.id')
                . ' AND ' . $this->db->quoteName('l.component') . ' = ' . $this->db->quote(self::COMPONENT)
                . ' AND ' . $this->db->quoteName('l.external_entity_type') . ' = ' . $this->db->quote(self::EXTERNAL_ENTITY)
                . ' AND ' . $this->relationCondition('l')
            )
            ->where($this->db->quoteName('l.external_entity_id') . ' = :season_id')
            ->where('(' . $this->db->quoteName('c.scope') . ' = ' . $this->db->quote('competition')
                . ' OR ' . $this->db->quoteName('c.program') . ' = ' . $this->db->quote('dcl') . ')')
            ->where($this->db->quoteName('c.published') . ' <> -2')
            ->order($this->db->quoteName('c.id') . ' ASC')
            ->order($this->db->quoteName('l.id') . ' ASC')
            ->bind(':season_id', $competitionSeasonId, ParameterType::INTEGER);

        $rows = (array) $this->db->setQuery($query)->loadObjectList();
        if ($rows === []) {
            return ['cards_updated' => 0, 'links_updated' => 0];
        }

        $cards = [];
        foreach ($rows as $row) {
            $cardId = (int) ($row->id ?? 0);
            $linkId = (int) ($row->link_id ?? 0);
            if ($cardId < 1 || $linkId < 1) {
                continue;
            }

            if (!isset($cards[$cardId])) {
                $cards[$cardId] = [
                    'valid_from' => $row->valid_from !== null ? (string) $row->valid_from : null,
                    'expires_at' => $row->expires_at !== null ? (string) $row->expires_at : null,
                    'links' => [],
                ];
            }
            $cards[$cardId]['links'][] = [
                'id' => $linkId,
                'metadata' => $row->metadata !== null ? (string) $row->metadata : '',
            ];
        }

        if ($cards === []) {
            return ['cards_updated' => 0, 'links_updated' => 0];
        }

        $cardsUpdated = 0;
        $linksUpdated = 0;
        $audit = new AuditService($this->db);
        $started = false;

        try {
            $this->db->transactionStart();
            $started = true;

            foreach ($cards as $cardId => $card) {
                $oldValidFrom = $card['valid_from'];
                $oldExpiresAt = $card['expires_at'];
                $cardChanged = $oldValidFrom !== $validFrom || $oldExpiresAt !== $expiresAt;

                if ($cardChanged) {
                    $update = $this->db->getQuery(true)
                        ->update($this->db->quoteName('#__decaromembership_cards'))
                        ->set($this->db->quoteName('valid_from') . ' = :valid_from')
                        ->set($this->db->quoteName('expires_at') . ' = :expires_at')
                        ->set($this->db->quoteName('modified') . ' = :modified')
                        ->set($this->db->quoteName('modified_by') . ' = :modified_by')
                        ->where($this->db->quoteName('id') . ' = :card_id')
                        ->bind(':valid_from', $validFrom, $validFrom === null ? ParameterType::NULL : ParameterType::STRING)
                        ->bind(':expires_at', $expiresAt, $expiresAt === null ? ParameterType::NULL : ParameterType::STRING)
                        ->bind(':modified', $nowSql)
                        ->bind(':modified_by', $actorUserId, ParameterType::INTEGER)
                        ->bind(':card_id', $cardId, ParameterType::INTEGER);
                    $this->db->setQuery($update)->execute();

                    $audit->record(
                        'cards',
                        $cardId,
                        'competition_season_dates_sync',
                        $actorUserId,
                        (object) ['valid_from' => $oldValidFrom, 'expires_at' => $oldExpiresAt],
                        (object) ['valid_from' => $validFrom, 'expires_at' => $expiresAt, 'competition_season_id' => $competitionSeasonId],
                        $nowSql
                    );
                    $cardsUpdated++;
                }

                foreach ($card['links'] as $link) {
                    $metadata = json_decode((string) $link['metadata'], true);
                    if (!is_array($metadata)) {
                        $metadata = [];
                    }
                    $metadata['valid_from'] = $validFrom;
                    $metadata['expires_at'] = $expiresAt;

                    $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if (!is_string($metadataJson)) {
                        $metadataJson = '{}';
                    }
                    if ($metadataJson === (string) $link['metadata']) {
                        continue;
                    }

                    $linkUpdate = $this->db->getQuery(true)
                        ->update($this->db->quoteName('#__decaromembership_entity_links'))
                        ->set($this->db->quoteName('metadata') . ' = :metadata')
                        ->where($this->db->quoteName('id') . ' = :link_id')
                        ->bind(':metadata', $metadataJson)
                        ->bind(':link_id', $link['id'], ParameterType::INTEGER);
                    $this->db->setQuery($linkUpdate)->execute();
                    $linksUpdated++;
                }
            }

            $this->db->transactionCommit();
            $started = false;
        } catch (Throwable $e) {
            if ($started) {
                try {
                    $this->db->transactionRollback();
                } catch (Throwable) {
                }
            }
            throw $e;
        }

        return ['cards_updated' => $cardsUpdated, 'links_updated' => $linksUpdated];
    }

    public function getLinkedCompetitionSeasonId(int $cardId): int
    {
        if ($cardId < 1) {
            return 0;
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('external_entity_id'))
            ->from($this->db->quoteName('#__decaromembership_entity_links'))
            ->where($this->db->quoteName('local_entity_type') . ' = ' . $this->db->quote('cards'))
            ->where($this->db->quoteName('local_entity_id') . ' = :card_id')
            ->where($this->db->quoteName('component') . ' = ' . $this->db->quote(self::COMPONENT))
            ->where($this->db->quoteName('external_entity_type') . ' = ' . $this->db->quote(self::EXTERNAL_ENTITY))
            ->where($this->relationCondition())
            ->bind(':card_id', $cardId, ParameterType::INTEGER);

        return (int) $this->db->setQuery($query, 0, 1)->loadResult();
    }

    public function linkCompetitionSeason(
        int $cardId,
        int $memberId,
        int $seasonId,
        array $metadata,
        int $userId,
        string $created
    ): void {
        $this->unlinkCompetitionSeason($cardId);

        if ($cardId < 1 || $seasonId < 1) {
            return;
        }

        $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($metadataJson)) {
            $metadataJson = '{}';
        }

        $columns = [
            'member_id', 'local_entity_type', 'local_entity_id', 'component',
            'external_entity_type', 'external_entity_id', 'relation_type',
            'metadata', 'created', 'created_by',
        ];

        $localType = 'cards';
        $component = self::COMPONENT;
        $externalType = self::EXTERNAL_ENTITY;
        $relationType = self::RELATION;

        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__decaromembership_entity_links'))
            ->columns(array_map([$this->db, 'quoteName'], $columns))
            ->values(':member_id, :local_type, :local_id, :component, :external_type, :external_id, :relation_type, :metadata, :created, :created_by');

        $nullableMemberId = $memberId > 0 ? $memberId : null;
        $query->bind(':member_id', $nullableMemberId, $nullableMemberId === null ? ParameterType::NULL : ParameterType::INTEGER)
            ->bind(':local_type', $localType)
            ->bind(':local_id', $cardId, ParameterType::INTEGER)
            ->bind(':component', $component)
            ->bind(':external_type', $externalType)
            ->bind(':external_id', $seasonId, ParameterType::INTEGER)
            ->bind(':relation_type', $relationType)
            ->bind(':metadata', $metadataJson)
            ->bind(':created', $created)
            ->bind(':created_by', $userId, ParameterType::INTEGER);

        $this->db->setQuery($query)->execute();
    }

    public function unlinkCompetitionSeason(int $cardId): void
    {
        if ($cardId < 1) {
            return;
        }

        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__decaromembership_entity_links'))
            ->where($this->db->quoteName('local_entity_type') . ' = ' . $this->db->quote('cards'))
            ->where($this->db->quoteName('local_entity_id') . ' = :card_id')
            ->where($this->db->quoteName('component') . ' = ' . $this->db->quote(self::COMPONENT))
            ->where($this->db->quoteName('external_entity_type') . ' = ' . $this->db->quote(self::EXTERNAL_ENTITY))
            ->where($this->relationCondition())
            ->bind(':card_id', $cardId, ParameterType::INTEGER);

        $this->db->setQuery($query)->execute();
    }

    private function normalizeSyncDate(?string $value, string $field): ?string
    {
        $value = $value !== null ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Invalid competition season date for ' . $field . '.');
        }

        return $value;
    }

    private function relationCondition(string $alias = ''): string
    {
        $column = $alias !== '' ? $alias . '.relation_type' : 'relation_type';

        return $this->db->quoteName($column)
            . ' IN (' . $this->db->quote(self::RELATION) . ', ' . $this->db->quote(self::LEGACY_RELATION) . ')';
    }

}
