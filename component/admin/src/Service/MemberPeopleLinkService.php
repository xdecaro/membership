<?php

namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

use RuntimeException;

final class MemberPeopleLinkService
{
    public function __construct(
        private RecordRepository $repository,
        private PeopleIntegrationService $people,
        private AuditService $audit
    ) {
    }

    public function validateForSave(int $memberId, ?object $old, array $data): array
    {
        $oldUuid = strtolower(trim((string) ($old->person_uuid ?? '')));
        $submittedUuid = strtolower(trim((string) ($data['person_uuid'] ?? '')));

        if ($oldUuid !== '') {
            if ($submittedUuid === '') {
                $submittedUuid = $oldUuid;
            } elseif ($submittedUuid !== $oldUuid) {
                throw new RuntimeException('Changing an existing People link requires the explicit relink action.');
            }
        }

        if ($memberId < 1 && $submittedUuid === '') {
            throw new RuntimeException('People person is required for a new member.');
        }

        if ($memberId > 0 && $old !== null && $submittedUuid === '') {
            $data['person_uuid'] = null;
            return $this->stripPeopleOwnedFields($data);
        }

        if ($submittedUuid === '') {
            $data['person_uuid'] = null;
            return $data;
        }

        $person = $this->people->getPerson($submittedUuid, false);
        if ($person === null || empty($person['uuid'])) {
            throw new RuntimeException('People person was not found.');
        }

        $canonicalUuid = strtolower(trim((string) $person['uuid']));
        $existingId = $this->repository->findMemberIdByPersonUuid($canonicalUuid, $memberId);
        if ($existingId !== null) {
            throw new RuntimeException('This People person is already linked to another Membership member.');
        }

        $data['person_uuid'] = $canonicalUuid;
        return $this->stripPeopleOwnedFields($data);
    }

    private function stripPeopleOwnedFields(array $data): array
    {
        foreach ([
            'first_name','last_name','birth_date','birth_place','tax_code','address','city',
            'province','postal_code','country','email','phone','user_id'
        ] as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    public function linkLegacyMember(int $memberId, string $uuid, int $userId, string $now): void
    {
        $member = $this->repository->load('#__decaromembership_members', $memberId);
        if ($member === null) {
            throw new RuntimeException('Membership member was not found.');
        }
        if (trim((string) ($member->person_uuid ?? '')) !== '') {
            throw new RuntimeException('Membership member is already linked to People.');
        }

        $person = $this->people->getPerson(strtolower(trim($uuid)), false);
        if ($person === null || empty($person['uuid'])) {
            throw new RuntimeException('People person was not found.');
        }
        $canonicalUuid = strtolower(trim((string) $person['uuid']));
        if ($this->repository->findMemberIdByPersonUuid($canonicalUuid, $memberId) !== null) {
            throw new RuntimeException('This People person is already linked to another Membership member.');
        }

        $this->repository->updateMemberPersonUuid($memberId, $canonicalUuid, $now, $userId);
        $this->audit->personLink($memberId, 'people_link', null, $canonicalUuid, $userId, $now);
    }

    public function relinkMember(int $memberId, string $uuid, int $userId, string $now): void
    {
        $member = $this->repository->load('#__decaromembership_members', $memberId);
        if ($member === null) {
            throw new RuntimeException('Membership member was not found.');
        }

        $oldUuid = strtolower(trim((string) ($member->person_uuid ?? '')));
        if ($oldUuid === '') {
            $this->linkLegacyMember($memberId, $uuid, $userId, $now);
            return;
        }

        $person = $this->people->getPerson(strtolower(trim($uuid)), false);
        if ($person === null || empty($person['uuid'])) {
            throw new RuntimeException('People person was not found.');
        }
        $canonicalUuid = strtolower(trim((string) $person['uuid']));
        if ($canonicalUuid === $oldUuid) {
            return;
        }
        if ($this->repository->findMemberIdByPersonUuid($canonicalUuid, $memberId) !== null) {
            throw new RuntimeException('This People person is already linked to another Membership member.');
        }

        $this->repository->updateMemberPersonUuid($memberId, $canonicalUuid, $now, $userId);
        $this->audit->personLink($memberId, 'people_relink', $oldUuid, $canonicalUuid, $userId, $now);
    }
}
