# Membership 1.5.0 + People 1.2.15 Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make People the authoritative person registry for Membership by adding a public batch provider in People 1.2.15 and linking each Membership member to exactly one People UUID in Membership 1.5.0 without destroying Membership 1.4.0 data.

**Architecture:** People owns identity/contact data. Membership stores one nullable unique `person_uuid`, keeps its local `member_id` as the key for every Membership-domain child table, and resolves People only through a local adapter that calls People public services. Existing 1.4.0 members may remain temporarily unlinked; new 1.5.0 members require People.

**Tech Stack:** Joomla 6.1.3, PHP 8.3+, MySQL/MariaDB, Joomla MVC, Joomla DI/Web Asset Manager, Core 2.0.1+, People 1.2.15+, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-15-membership-people-integration-design.md`

## Global Constraints

- Joomla target: `6.*` only.
- PHP minimum: `8.3.0`.
- Core minimum: `2.0.1`.
- People minimum for Membership 1.5.0: `1.2.15`.
- One People UUID maps to at most one Membership member.
- `member_id` remains the foreign key used by renewals, cards, dues, payments, transfers, cases, documents, notifications and audit.
- Membership must never query `#__xdecaropeople_*` or import private People Model/Table classes.
- Upgrade 1.4.0 -> 1.5.0 must preserve all Membership data.
- New Membership members require a valid People UUID.
- Existing linked UUIDs are immutable in the normal edit flow; correction uses an explicit elevated relink action.
- Do not merge/release Membership 1.5.0 until runtime upgrade and Finance regressions are green and the user explicitly approves release.

---

### Task 1: People 1.2.15 batch provider

**Repository:** `xdecaro/people`

**Files:**
- Modify: `component/admin/src/Service/PersonProviderService.php`.
- Create: `tests/person-provider-batch.php`.
- Create: `tests/people-1.2.15-contract.php`.
- Modify: `VERSION`.
- Modify: `component/xdecaropeople.xml`.
- Modify: `package/pkg_xdecaropeople.xml`.
- Modify: `component/media/joomla.asset.json`.
- Modify: `.github/workflows/build.yml`.
- Modify: `.github/workflows/people-1.2.2-package.yml`.
- Modify: `.github/workflows/release.yml`.

**Interfaces:**
- Produces: `PersonProviderService::getPeopleByUuids(array $uuids, bool $sensitive = false): array` returning rows keyed by canonical lowercase UUID.

- [ ] **Step 1: Write the failing batch test**

`tests/person-provider-batch.php` must prove normalization, duplicate elimination, one DB query, UUID-keyed output, invalid UUID rejection and sensitive-field gating. Core assertions:

```php
$rows = $provider->getPeopleByUuids([
    '550E8400-E29B-41D4-A716-446655440000',
    '550e8400-e29b-41d4-a716-446655440000',
    '11111111-2222-4333-8444-555555555555',
    'not-a-uuid',
]);

assert(array_keys($rows) === [
    '550e8400-e29b-41d4-a716-446655440000',
    '11111111-2222-4333-8444-555555555555',
]);
assert($fakeDb->queryCount === 1);
assert(!isset($rows['550e8400-e29b-41d4-a716-446655440000']['tax_identifier']));
```

- [ ] **Step 2: Verify RED**

```bash
php tests/person-provider-batch.php
```

Expected: FAIL because `getPeopleByUuids()` does not exist.

- [ ] **Step 3: Implement the exact batch method**

Add this method to `PersonProviderService`:

```php
public function getPeopleByUuids(array $uuids, bool $sensitive = false): array
{
    $this->authorise($sensitive);

    $normalized = [];
    foreach ($uuids as $uuid) {
        $uuid = strtolower(trim((string) $uuid));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid)) {
            continue;
        }
        $normalized[$uuid] = $uuid;
    }

    if ($normalized === []) {
        return [];
    }

    $query = $this->db->getQuery(true)
        ->select($this->columns($sensitive))
        ->from($this->db->quoteName('#__xdecaropeople_people', 'p'))
        ->where($this->db->quoteName('p.state') . ' >= 0');

    $placeholders = [];
    foreach (array_values($normalized) as $index => $uuid) {
        $placeholder = ':uuid' . $index;
        $placeholders[] = $placeholder;
        $query->bind($placeholder, $uuid);
    }
    $query->where($this->db->quoteName('p.uuid') . ' IN (' . implode(',', $placeholders) . ')');

    $found = [];
    foreach ((array) $this->db->setQuery($query)->loadAssocList() as $row) {
        $row = $this->normalizeStructuredFields($row, $sensitive);
        $key = strtolower((string) ($row['uuid'] ?? ''));
        if ($key !== '') {
            $found[$key] = $row;
        }
    }

    $result = [];
    foreach ($normalized as $uuid) {
        if (isset($found[$uuid])) {
            $result[$uuid] = $found[$uuid];
        }
    }

    return $result;
}
```

- [ ] **Step 4: Verify GREEN plus current People regression tests**

```bash
php tests/person-provider-batch.php
php tests/core-integration-smoke.php
php tests/people-1.2.14-contract.php
php tests/person-input-ui-contract.php
php tests/person-validation-summary-contract.php
php tests/relation-reciprocity.php
```

Expected: all PASS.

- [ ] **Step 5: Add the 1.2.15 metadata contract and verify RED**

`tests/people-1.2.15-contract.php` must assert:

```php
assert(trim(file_get_contents(__DIR__ . '/../VERSION')) === '1.2.15');
assert(str_contains(file_get_contents(__DIR__ . '/../component/admin/src/Service/PersonProviderService.php'), 'function getPeopleByUuids('));
assert(!is_file(__DIR__ . '/../component/admin/sql/updates/mysql/1.2.15.sql'));
```

Run:

```bash
php tests/people-1.2.15-contract.php
```

Expected: FAIL while metadata is still 1.2.14.

- [ ] **Step 6: Bump all People version metadata to 1.2.15**

Set `VERSION`, component manifest, package manifest, all four version occurrences in `component/media/joomla.asset.json`, and version assertions in the three workflow files to `1.2.15`. Keep schema unchanged.

- [ ] **Step 7: Run People build/validation twice for determinism**

Use the repository's existing build commands from CI, then compare first/second package checksums. Run both new tests again. Expected: valid deterministic package and all tests green.

- [ ] **Step 8: Commit and open a Draft People PR**

```bash
git add component package tests VERSION .github

git commit -m "People 1.2.15: add batch person provider"
```

Do not merge/release until People CI is green.

---

### Task 2: Membership 1.5.0 schema and dependency preflight

**Repository:** `xdecaro/membership`

**Files:**
- Create: `component/admin/sql/updates/mysql/1.5.0.sql`.
- Modify: `component/admin/sql/install.mysql.utf8mb4.sql`.
- Modify: `component/admin/src/Config/MemberCoreEntities.php`.
- Modify: `package/script.php`.
- Create: `tests/people-integration-contract.php`.

**Interfaces:**
- Produces: nullable unique `person_uuid` and nullable legacy `first_name`/`last_name`.

- [ ] **Step 1: Write the failing schema/boundary contract**

`tests/people-integration-contract.php` must recursively scan Membership PHP/SQL and assert:

```php
assert(str_contains($installSql, '`person_uuid` CHAR(36) NULL'));
assert(str_contains($installSql, 'UNIQUE KEY `uq_member_person_uuid` (`person_uuid`)'));
assert(str_contains($updateSql, 'ADD COLUMN `person_uuid` CHAR(36) NULL'));
assert(str_contains($updateSql, 'MODIFY `first_name` VARCHAR(190) NULL'));
assert(str_contains($updateSql, 'MODIFY `last_name` VARCHAR(190) NULL'));
assert(!str_contains($membershipSource, '#__xdecaropeople_'));
assert(!preg_match('/People\\\\Administrator\\\\(Model|Table)\\\\/', $membershipSource));
```

- [ ] **Step 2: Verify RED**

```bash
php tests/people-integration-contract.php
```

Expected: FAIL because 1.5.0 schema does not exist.

- [ ] **Step 3: Add non-destructive SQL**

Create `component/admin/sql/updates/mysql/1.5.0.sql`:

```sql
ALTER TABLE `#__decaromembership_members`
  ADD COLUMN `person_uuid` CHAR(36) NULL AFTER `id`,
  MODIFY `first_name` VARCHAR(190) NULL,
  MODIFY `last_name` VARCHAR(190) NULL,
  ADD UNIQUE KEY `uq_member_person_uuid` (`person_uuid`);
```

Change the clean-install member table to the same final shape. Do not remove any legacy column.

- [ ] **Step 4: Update member metadata**

In `MemberCoreEntities::definitions()['members']['fields']`, add:

```php
'person_uuid' => [
    'label' => 'COM_DECAROMEMBERSHIP_FIELD_PERSON',
    'type' => 'people',
],
```

Remove the `required` flag from legacy `first_name` and `last_name`.

- [ ] **Step 5: Add exact package preflight dependency checks**

In `package/script.php`, add constants and a helper that reads package/component versions from `#__extensions.manifest_cache`:

```php
private const MINIMUM_CORE_VERSION = '2.0.1';
private const MINIMUM_PEOPLE_VERSION = '1.2.15';

public function preflight($type, $parent): bool
{
    if (!in_array((string) $type, ['install', 'update', 'discover_install'], true)) {
        return true;
    }

    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $core = $this->installedVersion($db, 'package', 'pkg_xdecarocore');
    $people = $this->installedVersion($db, 'package', 'pkg_xdecaropeople');

    if ($core === null || version_compare($core, self::MINIMUM_CORE_VERSION, '<')) {
        Factory::getApplication()->enqueueMessage('Membership 1.5.0 requires Core by xdecaro 2.0.1 or newer.', 'error');
        return false;
    }
    if ($people === null || version_compare($people, self::MINIMUM_PEOPLE_VERSION, '<')) {
        Factory::getApplication()->enqueueMessage('Membership 1.5.0 requires People by xdecaro 1.2.15 or newer.', 'error');
        return false;
    }
    return true;
}
```

`installedVersion()` must query only Joomla `#__extensions`, decode `manifest_cache`, and return the `version` string or `null`.

- [ ] **Step 6: Verify GREEN**

```bash
php tests/people-integration-contract.php
python3 build/validate.py
```

Expected: schema/boundary contract PASS; existing tests still PASS.

- [ ] **Step 7: Commit**

```bash
git add component/admin/sql component/admin/src/Config package/script.php tests/people-integration-contract.php

git commit -m "Membership 1.5.0: add People identity link schema"
```

---

### Task 3: Membership People adapter and DI registration

**Repository:** `xdecaro/membership`

**Files:**
- Create: `component/admin/src/Service/PeopleIntegrationService.php`.
- Modify: `component/admin/services/provider.php`.
- Modify: `component/admin/src/Extension/MembershipComponent.php`.
- Create: `tests/people-integration-service.php`.

**Interfaces:**
- Produces:
  - `isAvailable(): bool`
  - `dependencyStatus(): array`
  - `getPerson(string $uuid, bool $sensitive = false): ?array`
  - `searchPeople(string $search, int $limit = 50): array`
  - `getPeopleByUuids(array $uuids): array`
  - `findByUserIdUnique(int $userId): ?array`
  - `openPersonUrl(string $uuid): string`

- [ ] **Step 1: Write failing adapter tests**

Tests must prove delegation to a fake public People provider and ambiguity handling:

```php
assert(PeopleIntegrationService::MINIMUM_CORE_VERSION === '2.0.1');
assert(PeopleIntegrationService::MINIMUM_PEOPLE_VERSION === '1.2.15');
assert($service->getPerson($uuid)['uuid'] === $uuid);
assert(array_keys($service->getPeopleByUuids([$uuid])) === [$uuid]);
assert($service->findByUserIdUnique(42)['user_id'] === 42);
assert($ambiguousService->findByUserIdUnique(42) === null);
```

- [ ] **Step 2: Verify RED**

```bash
php tests/people-integration-service.php
```

Expected: FAIL because the adapter does not exist.

- [ ] **Step 3: Implement the adapter API**

Use this public shape:

```php
final class PeopleIntegrationService
{
    public const MINIMUM_CORE_VERSION = '2.0.1';
    public const MINIMUM_PEOPLE_VERSION = '1.2.15';

    public function __construct(private DatabaseInterface $db) {}

    public function isAvailable(): bool
    {
        $status = $this->dependencyStatus();
        return $status['core_ok'] && $status['people_ok'];
    }

    public function getPerson(string $uuid, bool $sensitive = false): ?array
    {
        return $this->provider()->getPerson(strtolower(trim($uuid)), $sensitive);
    }

    public function searchPeople(string $search, int $limit = 50): array
    {
        return $this->provider()->searchPeople(['search' => trim($search)], $limit, false);
    }

    public function getPeopleByUuids(array $uuids): array
    {
        return $this->provider()->getPeopleByUuids($uuids, false);
    }

    public function findByUserIdUnique(int $userId): ?array
    {
        if ($userId < 1) return null;
        $rows = $this->provider()->searchPeople(['user_id' => $userId], 2, false);
        return count($rows) === 1 ? $rows[0] : null;
    }

    public function openPersonUrl(string $uuid): string
    {
        return 'index.php?option=com_xdecaropeople&task=person.edit&uuid=' . rawurlencode(strtolower(trim($uuid)));
    }
}
```

`dependencyStatus()` reads versions from Joomla `#__extensions`. `provider()` first checks `isAvailable()`, then calls `Factory::getApplication()->bootComponent('com_xdecaropeople')`, verifies `method_exists($component, 'getPersonProviderService')`, and returns that public service. Any failure becomes a controlled `RuntimeException` with no fallback SQL.

- [ ] **Step 4: Register the adapter in Membership DI/component**

In `provider.php` add:

```php
$container->share(PeopleIntegrationService::class,
    static fn(Container $c): PeopleIntegrationService => new PeopleIntegrationService($c->get(DatabaseInterface::class))
);
```

Add `setPeopleIntegrationService()` / `getPeopleIntegrationService()` to `MembershipComponent` and inject it in the `ComponentInterface` factory.

- [ ] **Step 5: Verify GREEN**

```bash
php tests/people-integration-service.php
php tests/people-integration-contract.php
php tests/core-integration-smoke.php
```

- [ ] **Step 6: Commit**

```bash
git add component/admin/src/Service/PeopleIntegrationService.php component/admin/services/provider.php component/admin/src/Extension/MembershipComponent.php tests/people-integration-service.php

git commit -m "Membership: add public People integration adapter"
```

---

### Task 4: Member link rules, uniqueness, immutability and audit

**Repository:** `xdecaro/membership`

**Files:**
- Create: `component/admin/src/Service/MemberPeopleLinkService.php`.
- Modify: `component/admin/src/Service/RecordRepository.php`.
- Modify: `component/admin/src/Model/RecordModel.php`.
- Modify: `component/admin/src/Service/AuditService.php`.
- Create: `tests/member-people-link.php`.

**Interfaces:**
- Produces:
  - `validateForSave(int $memberId, ?object $old, array $data): array`
  - `linkLegacyMember(int $memberId, string $uuid, int $userId, string $now): void`
  - `relinkMember(int $memberId, string $uuid, int $userId, string $now): void`

- [ ] **Step 1: Write failing business-rule tests**

Cover:

```php
expectException(fn() => $linker->validateForSave(0, null, ['person_uuid' => '']), 'People person is required');
expectException(fn() => $linker->validateForSave(0, null, ['person_uuid' => $usedUuid]), 'already linked');
expectException(fn() => $linker->validateForSave(10, (object)['person_uuid' => $uuidA], ['person_uuid' => $uuidB]), 'relink');

$data = $linker->validateForSave(10, (object)['person_uuid' => null], ['person_uuid' => null, 'status' => 'active']);
assert($data['status'] === 'active');
```

- [ ] **Step 2: Verify RED**

```bash
php tests/member-people-link.php
```

- [ ] **Step 3: Add repository helpers**

Add exact helpers to `RecordRepository`:

```php
public function findMemberIdByPersonUuid(string $uuid, int $excludeId = 0): ?int;
public function updateMemberPersonUuid(int $memberId, string $uuid, string $modified, int $userId): void;
public function loadUnlinkedMembersWithUserId(): array;
```

All queries use bound parameters.

- [ ] **Step 4: Implement link service**

`validateForSave()` must resolve the submitted UUID through `PeopleIntegrationService::getPerson()`, canonicalize from the returned `uuid`, reject missing People, reject duplicate ownership, reject ordinary changes to an already linked UUID, and allow a legacy member to remain unlinked.

When a member is linked, remove People-owned legacy fields before save:

```php
foreach ([
    'first_name','last_name','birth_date','birth_place','tax_code','address','city',
    'province','postal_code','country','email','phone','user_id'
] as $field) {
    unset($data[$field]);
}
```

Keep Membership-owned fields including `photo`, `notes`, `member_number`, `card_number`, `first_registration_date`, `status`, `location_id`, `category_id`, `published`.

- [ ] **Step 5: Integrate only the members branch in `RecordModel::saveEntity()`**

Before the generic unique-field loop:

```php
if ($entity === 'members') {
    $old = $id > 0 ? $repository->load($config['table'], $id) : null;
    $data = $this->memberPeopleLinkService()->validateForSave($id, $old, $data);
}
```

Keep generic entity behavior unchanged.

- [ ] **Step 6: Add safe UUID-only audit helper**

Add an `AuditService::personLink()` method that writes only `{person_uuid: ...}` old/new values for `people_link`, `people_relink`, or `people_backfill`; do not pass full People rows.

- [ ] **Step 7: Verify GREEN**

```bash
php tests/member-people-link.php
php tests/people-integration-service.php
php tests/finance-integration-contract.php
```

- [ ] **Step 8: Commit**

```bash
git add component/admin/src/Service component/admin/src/Model/RecordModel.php tests/member-people-link.php

git commit -m "Membership: enforce People member identity link"
```

---

### Task 5: Deterministic 1.4.0 member backfill

**Repository:** `xdecaro/membership`

**Files:**
- Create: `component/admin/src/Service/MemberPeopleBackfillService.php`.
- Modify: `package/script.php`.
- Create: `tests/member-people-backfill.php`.

**Interfaces:**
- Produces: `run(int $userId, string $now): array{linked:int,skipped:int,ambiguous:int}`.

- [ ] **Step 1: Write failing backfill tests**

Assert unique `user_id` links, ambiguous user does not link, already-used UUID does not reassign, and second run is idempotent:

```php
assert($backfill->run(99, '2026-09-15 14:00:00')['linked'] === 1);
assert($repository->load('#__decaromembership_members', 10)->person_uuid === $uuid);
assert($backfill->run(99, '2026-09-15 14:01:00')['linked'] === 0);
assert($ambiguous->run(99, '2026-09-15 14:02:00')['ambiguous'] === 1);
```

- [ ] **Step 2: Verify RED**

```bash
php tests/member-people-backfill.php
```

- [ ] **Step 3: Implement backfill**

Algorithm:

```php
foreach ($repository->loadUnlinkedMembersWithUserId() as $member) {
    $person = $people->findByUserIdUnique((int) $member->user_id);
    if ($person === null) { $ambiguous++; continue; }
    $uuid = strtolower((string) $person['uuid']);
    if ($repository->findMemberIdByPersonUuid($uuid) !== null) { $skipped++; continue; }
    $repository->updateMemberPersonUuid((int) $member->id, $uuid, $now, $userId);
    $audit->personLink((int) $member->id, 'people_backfill', null, $uuid, $userId, $now);
    $linked++;
}
```

Return exact counters.

- [ ] **Step 4: Run backfill from package postflight only after successful install/update**

For `install`, `update`, `discover_install`, construct the service with Joomla DB and run it after component SQL has completed. Catch only runtime integration failures, enqueue a warning, and never roll back or delete Membership records.

- [ ] **Step 5: Verify GREEN and commit**

```bash
php tests/member-people-backfill.php
php tests/people-integration-contract.php
```

```bash
git add component/admin/src/Service/MemberPeopleBackfillService.php package/script.php tests/member-people-backfill.php

git commit -m "Membership: backfill deterministic People links"
```

---

### Task 6: People-backed member list/search without N+1

**Repository:** `xdecaro/membership`

**Files:**
- Modify: `component/admin/src/Model/RecordsModel.php`.
- Modify: `component/admin/src/View/Records/HtmlView.php`.
- Modify: `component/admin/tmpl/records/default.php`.
- Create: `tests/member-list-people.php`.

**Interfaces:**
- Produces: one batch People lookup per member-list page and `filter.people_link` (`linked|unlinked|all`).

- [ ] **Step 1: Write failing list test**

```php
$map = $model->resolvePeopleForItems($items);
assert($fakePeople->batchCalls === 1);
assert($map[$uuid]['display_name'] === 'Luca De Caro');
```

Also assert linked/unlinked filter SQL and unresolved linked-person fallback label.

- [ ] **Step 2: Verify RED**

```bash
php tests/member-list-people.php
```

- [ ] **Step 3: Add page-level batch resolution**

Add:

```php
public function resolvePeopleForItems(array $items): array
{
    $uuids = [];
    foreach ($items as $item) {
        $uuid = strtolower(trim((string) ($item->person_uuid ?? '')));
        if ($uuid !== '') $uuids[$uuid] = $uuid;
    }
    return $uuids === [] ? [] : $this->people()->getPeopleByUuids(array_values($uuids));
}
```

The view calls it once after `getItems()`.

- [ ] **Step 4: Add linked/unlinked filtering**

Read `people_link` in `populateState()` and apply `IS NULL` / `IS NOT NULL` only for `entity=members`. Include it in `getStoreId()`.

- [ ] **Step 5: Add People-aware search**

For member searches, call `searchPeople($search, 200)` once, collect UUIDs, bind them into `a.person_uuid IN (...)`, and OR that with legacy LIKE conditions restricted to `a.person_uuid IS NULL`. Never join People SQL.

- [ ] **Step 6: Use stable Membership ordering**

Default member ordering becomes `member_number ASC` when populated, with `id ASC` as secondary order. Do not use legacy last name as the authoritative sort key.

- [ ] **Step 7: Render identity source clearly**

- Linked/resolved: People `display_name`.
- Legacy/unlinked: legacy name plus `Persona People non collegata` badge.
- Linked/unresolved: member number/id plus `Persona People non disponibile` badge.

- [ ] **Step 8: Verify GREEN and commit**

```bash
php tests/member-list-people.php
php tests/people-integration-service.php
```

```bash
git add component/admin/src/Model/RecordsModel.php component/admin/src/View/Records/HtmlView.php component/admin/tmpl/records/default.php tests/member-list-people.php

git commit -m "Membership: resolve People identities in member lists"
```

---

### Task 7: Member form selector, read-only People summary and privileged relink

**Repository:** `xdecaro/membership`

**Files:**
- Create: `component/admin/src/Controller/PeopleController.php`.
- Modify: `component/admin/src/View/Record/HtmlView.php`.
- Modify: `component/admin/tmpl/record/default.php`.
- Modify: `component/admin/media/js/admin.js`.
- Modify: `component/admin/media/css/admin.css`.
- Modify: `component/admin/access.xml`.
- Modify: `component/admin/language/it-IT/com_decaromembership.ini`.
- Modify: `component/admin/language/en-GB/com_decaromembership.ini`.
- Modify: `component/admin/language/fr-FR/com_decaromembership.ini`.
- Create: `tests/member-people-ui-contract.php`.

**Interfaces:**
- `PeopleController::search()` returns non-sensitive People selection rows.
- `PeopleController::relink()` performs elevated relink with CSRF + ACL.

- [ ] **Step 1: Write failing UI contract**

Assert stable hooks and absence of editable People-owned legacy fields for linked members:

```php
assert(str_contains($template, 'data-membership-people-search'));
assert(str_contains($template, 'data-membership-person-summary'));
assert(str_contains($template, 'data-membership-person-relink'));
assert(str_contains($js, 'membershipPeopleSearch'));
```

- [ ] **Step 2: Verify RED**

```bash
php tests/member-people-ui-contract.php
```

- [ ] **Step 3: Add server-side search endpoint**

`PeopleController::search()` must:

```php
$this->checkToken('get');
$user = Factory::getApplication()->getIdentity();
if (!$user->authorise('core.manage', 'com_decaromembership')) throw new RuntimeException('Not authorised', 403);
$q = Factory::getApplication()->input->getString('q', '');
$rows = $this->people()->searchPeople($q, 20);
```

Return JSON containing only `uuid`, `display_name`, `email`, `phone`; escape/render on the client.

- [ ] **Step 4: Add member-specific form rendering**

For `entity !== 'members'`, preserve the generic current template unchanged. For members:

- new/unlinked: People search selector + Membership-owned fields;
- linked: read-only People summary + Membership-owned fields;
- existing legacy unlinked: migration identity block + People selector;
- sensitive People fields appear only when `getPerson($uuid, true)` succeeds under People ACL;
- linked form does not emit editable inputs for People-owned legacy identity/contact/address fields.

- [ ] **Step 5: Add debounced JS selector**

Use a 250 ms debounce, fetch the Membership `people.search` endpoint with Joomla token, render selectable results, set hidden `jform[person_uuid]`, and render the selected summary. Server validation from Task 4 remains authoritative.

- [ ] **Step 6: Add explicit relink ACL/action**

Add `membership.relink_person` to `access.xml`. `PeopleController::relink()` must check POST CSRF, `membership.relink_person`, `member_id`, new UUID, then call `MemberPeopleLinkService::relinkMember()`. No ordinary edit-save path may change an existing UUID.

- [ ] **Step 7: Verify GREEN and commit**

```bash
php tests/member-people-ui-contract.php
php tests/member-people-link.php
```

```bash
git add component/admin/src/Controller/PeopleController.php component/admin/src/View/Record/HtmlView.php component/admin/tmpl/record/default.php component/admin/media component/admin/access.xml component/admin/language tests/member-people-ui-contract.php

git commit -m "Membership: add People member selector and relink flow"
```

---

### Task 8: Diagnostics, 1.5.0 metadata and Joomla runtime coverage

**Repository:** `xdecaro/membership`

**Files:**
- Modify: `component/admin/src/Model/InformationModel.php`.
- Modify: `VERSION`.
- Modify: `component/decaromembership.xml`.
- Modify: `package/pkg_decaromembership.xml`.
- Modify: `README.md`.
- Modify: `CHANGELOG.md`.
- Modify: `build/validate.py`.
- Modify: `.github/workflows/ci.yml`.
- Create: `tests/people-runtime.php`.

**Interfaces:**
- Produces deterministic Membership 1.5.0 package and Joomla 6.1.3 clean/upgrade test coverage.

- [ ] **Step 1: Make metadata/runtime contract fail first**

Extend validation to require:

```text
VERSION=1.5.0
Core minimum=2.0.1
People minimum=1.2.15
component/admin/sql/updates/mysql/1.5.0.sql packaged
no #__xdecaropeople_* references
no private People Model/Table imports
```

Run:

```bash
python3 build/validate.py
```

Expected: FAIL while version is 1.4.0.

- [ ] **Step 2: Add diagnostics**

`InformationModel` reports installed/minimum Core and People versions plus `available/compatible` state. Missing People after installation is a warning, never a fatal error.

- [ ] **Step 3: Set Membership version metadata to 1.5.0**

Update `VERSION`, component/package manifests, README and changelog. Do not publish feed checksum yet.

- [ ] **Step 4: Add clean-install runtime job**

Joomla 6.1.3 runtime must install pinned Core 2.0.1 and pinned published People 1.2.15, then Membership 1.5.0. Create a People person, create a Membership member linked to its UUID, and assert Membership legacy `first_name`/`last_name` are NULL while list/form show People identity.

- [ ] **Step 5: Add 1.4.0 -> 1.5.0 upgrade runtime job**

Seed Membership 1.4.0 member + renewal/card/due/payment/transfer rows + Joomla `user_id`. Upgrade to 1.5.0 and assert:

```text
member id unchanged
all child member_id values unchanged
unique People user_id backfill sets person_uuid
legacy identity values preserved
second backfill run makes no changes
```

- [ ] **Step 6: Add dependency failure runtime checks**

Attempt upgrade with missing/old Core or People and assert preflight fails before `person_uuid` exists in Membership schema.

- [ ] **Step 7: Re-run Finance 1.3.0 regressions**

Install Core/People first, then run existing Finance runtime assertions: quota update before allocation, payment update before allocation, replay idempotence, and post-allocation mutation rejection.

- [ ] **Step 8: Build twice and verify determinism**

```bash
python3 build/validate.py
python3 build/build.py
cp dist/SHA256SUMS.txt /tmp/membership-first-sha.txt
python3 build/build.py
cmp /tmp/membership-first-sha.txt dist/SHA256SUMS.txt
```

- [ ] **Step 9: Commit and keep Membership PR Draft**

```bash
git add VERSION component package build tests .github README.md CHANGELOG.md

git commit -m "Membership 1.5.0: verify People-backed member identity"
```

---

### Task 9: Release checkpoints and manual QA

**Repositories:** `xdecaro/people`, `xdecaro/membership`

- [ ] **Step 1: Verify People 1.2.15 CI and artifact**

All People tests, deterministic build and Joomla runtime checks must be green; verify SHA-256 of `pkg_xdecaropeople_1.2.15.zip`.

- [ ] **Step 2: Publish People 1.2.15 at the dependency checkpoint**

Mark People PR Ready, merge, publish `v1.2.15`, update Joomla feed, and verify the published asset digest equals the tested artifact. Pin that digest in Membership runtime CI.

- [ ] **Step 3: Verify Membership CI**

Require green: clean install, 1.4.0 -> 1.5.0 upgrade, dependency preflight, People batch list, ACL behavior, People runtime-unavailable diagnostics and Finance regressions.

- [ ] **Step 4: Manual Joomla administrator QA**

```text
Nuovo socio -> cerca People -> seleziona -> salva
Socio collegato -> identità People sola lettura
Rinomina persona in People -> nome aggiornato in Membership senza riscrivere member row
Socio legacy non collegato -> accessibile e chiaramente segnalato
Ricollega persona -> solo utente autorizzato + conferma
Lista soci -> risoluzione batch, nessun fatal se People/persona non disponibile
Desktop/tablet/mobile -> regolare
Light/dark -> regolare
Console browser -> nessun errore JavaScript
```

- [ ] **Step 5: Stop before Membership release**

Do not mark Membership PR Ready, merge, publish `v1.5.0`, or update the Joomla feed until the user explicitly approves the runtime result.

- [ ] **Step 6: After explicit approval, release Membership 1.5.0**

Merge, let the release workflow build the final artifact, verify release/feed SHA-256 equality, and test Joomla update detection from Membership 1.4.0 to 1.5.0 on a test installation.
