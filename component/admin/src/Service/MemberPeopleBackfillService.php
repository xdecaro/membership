<?php

namespace Xdecaro\Component\Decaromembership\Administrator\Service;

defined('_JEXEC') or die;

final class MemberPeopleBackfillService
{
    public function __construct(
        private RecordRepository $repository,
        private PeopleIntegrationService $people,
        private AuditService $audit
    ) {
    }

    /**
     * @return array{linked:int,skipped:int,ambiguous:int}
     */
    public function run(int $userId, string $now): array
    {
        $linked = 0;
        $skipped = 0;
        $ambiguous = 0;

        foreach ($this->repository->loadUnlinkedMembersWithUserId() as $member) {
            $person = $this->people->findByUserIdUnique((int) $member->user_id);
            if ($person === null || empty($person['uuid'])) {
                $ambiguous++;
                continue;
            }

            $uuid = strtolower(trim((string) $person['uuid']));
            if ($uuid === '') {
                $ambiguous++;
                continue;
            }

            if ($this->repository->findMemberIdByPersonUuid($uuid) !== null) {
                $skipped++;
                continue;
            }

            $this->repository->updateMemberPersonUuid((int) $member->id, $uuid, $now, $userId);
            $this->audit->personLink((int) $member->id, 'people_backfill', null, $uuid, $userId, $now);
            $linked++;
        }

        return [
            'linked' => $linked,
            'skipped' => $skipped,
            'ambiguous' => $ambiguous,
        ];
    }
}
