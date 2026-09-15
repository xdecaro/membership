# Membership 1.5.0 + People 1.2.15 Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make People the authoritative person registry for Membership by adding a public batch provider to People 1.2.15 and linking each Membership member to exactly one People UUID in Membership 1.5.0 without destroying legacy Membership data.

**Architecture:** People remains the owner of identity/contact data and exposes only public provider methods. Membership stores a unique `person_uuid`, keeps its existing local `member_id` for all domain/history tables, resolves People through a local `PeopleIntegrationService`, and preserves unlinked 1.4.0 members during migration. No cross-component SQL joins or private People classes are allowed.

**Tech Stack:** Joomla 6.1.3, PHP 8.3+, MySQL/MariaDB, Joomla MVC, Joomla Web Asset Manager, xdecaro Core 2.0.1+, People 1.2.15+, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-15-membership-people-integration-design.md`

## Global Constraints

- Joomla target is `6.*` only.
- PHP minimum is `8.3.0`.
- Core minimum is `2.0.1`.
- People minimum for Membership 1.5.0 is `1.2.15` because 1.2.14 is already published and immutable.
- One People UUID may be linked to at most one Membership member.
- Existing Membership `member_id` values remain the keys used by renewals, cards, dues, payments, transfers, cases, documents, notifications and audit.
- Membership must not query `#__xdecaropeople_*` or depend on private People models/tables.
- Existing Membership 1.4.0 records must survive upgrade even when no People link can be determined.
- New Membership members require a valid People UUID.
- Existing linked UUIDs are immutable through the normal edit form; correction uses an explicit privileged relink action.
- Do not merge/release Membership 1.5.0 until Joomla runtime upgrade and Finance regression tests are green and the user explicitly approves release.

---

### Task 1: Add the People 1.2.15 batch provider contract

**Repository:** `xdecaro/people`

**Files:**
- Modify: `component/admin/src/Service/PersonProviderService.php` in `getPerson()`, `searchPeople()`, and a new `getPeopleByUuids()` method.
- Create: `tests/person-provider-batch.php`.
- Create: `tests/people-1.2.15-contract.php`.
- Modify: `VERSION`.
- Modify: `component/xdecaropeople.xml`.
- Modify: `package/pkg_xdecaropeople.xml`.
- Modify: `component/media/joomla.asset.json` only if its version field mirrors the package version.
- Modify: People package/CI workflow version assertions that are pinned to 1.2.14.

**Interfaces:**
- Consumes: existing `PersonProviderService::columns(bool $sensitive): array`, `normalizeStructuredFields(array $row, bool $sensitive): array`, and `authorise(bool $sensitive): void`.
- Produces: `public function getPeopleByUuids(array $uuids, bool $sensitive = false): array`, returning an array keyed by canonical lowercase UUID.

- [ ] **Step 1: Write the failing provider test**

Create `tests/person-provider-batch.php` with a dependency-free fake database/query harness that asserts normalization, de-duplication, one database query, UUID-keyed output, and sensitive-column behavior. The core behavior assertion must be equivalent to:

```php
$rows = $provider->getPeopleByUuids([
    '550E8400-E29B-41D4-A716-446655440000',
    '550e8400-e29b-41d4-a716-446655440000',
    '11111111-2222-4333-8444-555555555555',
]);

assert(array_keys($rows) === [
    '550e8400-e29b-41d4-a716-446655440000',
    '11111111-2222-4333-8444-555555555555',
]);
assert($fakeDb->queryCount === 1);
assert(!array_key_exists('tax_identifier', $rows['550e8400-e29b-41d4-a716-446655440000']));
```

The test must also assert that invalid/blank UUID strings are ignored rather than interpolated into SQL.

- [ ] **Step 2: Run the new test and verify RED**

Run:

```bash
php tests/person-provider-batch.php
```

Expected: FAIL because `PersonProviderService::getPeopleByUuids()` does not exist.

- [ ] **Step 3: Implement the minimal batch provider**

Add a public method with this contract:

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

    // Build one bound IN query against p.uuid using the same column set and state rule as getPerson().
    // Normalize structured fields exactly once per returned row and return results keyed by lowercase uuid.
}
```

Use bound placeholders (`:uuid0`, `:uuid1`, …), never concatenate UUID values into SQL. Keep `state >= 0` so already-linked historical members can still resolve a non-active/non-trashed person consistently with `getPerson()`.

- [ ] **Step 4: Run batch test and existing People tests**

Run:

```bash
php tests/person-provider-batch.php
php tests/core-integration-smoke.php
php tests/people-1.2.14-contract.php
php tests/person-input-ui-contract.php
php tests/person-validation-summary-contract.php
php tests/relation-reciprocity.php
```

Expected: all PASS.

- [ ] **Step 5: Add the 1.2.15 release contract test**

Create `tests/people-1.2.15-contract.php` to assert:

```php
assert(trim(file_get_contents(__DIR__ . '/../VERSION')) === '1.2.15');
$provider = file_get_contents(__DIR__ . '/../component/admin/src/Service/PersonProviderService.php');
assert(str_contains($provider, 'function getPeopleByUuids('));
assert(!str_contains($provider, 'implode($uuids'));
```

Also assert component/package manifest versions are `1.2.15` and no destructive SQL migration is introduced merely for this service method.

- [ ] **Step 6: Verify RED for release metadata**

Run:

```bash
php tests/people-1.2.15-contract.php
```

Expected: FAIL because metadata is still 1.2.14.

- [ ] **Step 7: Bump People to 1.2.15 and align packaging checks**

Update `VERSION`, component/package manifests, asset registry version if present, and workflow assertions to `1.2.15`. Do not change database schema.

- [ ] **Step 8: Run full People validation/build**

Run the repository build/validation commands used by current CI, then run the two new tests again. Expected: deterministic package build, valid ZIP, all tests PASS.

- [ ] **Step 9: Commit People batch contract**

```bash
git add component/admin/src/Service/PersonProviderService.php tests VERSION component package .github

git commit -m "People 1.2.15: add batch person provider"
```

Create a Draft PR for People 1.2.15. Keep it Draft until CI is green. Release/merge is a checkpoint because Membership 1.5.0 runtime must consume the published artifact.

---

### Task 2: Add Membership 1.5.0 schema and dependency gates

**Repository:** `xdecaro/membership`

**Files:**
- Create: `component/admin/sql/updates/mysql/1.5.0.sql`.
- Modify: `component/admin/sql/install.mysql.utf8mb4.sql` member table definition.
- Modify: `component/admin/src/Config/MemberCoreEntities.php` member field definition.
- Modify: `package/script.php`.
- Modify: `component/decaromembership.xml`.
- Modify: `package/pkg_decaromembership.xml`.
- Modify later in Task 8: `VERSION`, feed/changelog/build assertions.
- Create: `tests/people-integration-contract.php`.

**Interfaces:**
- Consumes: published Core 2.0.1 and People 1.2.15 package identities.
- Produces: nullable unique `person_uuid` on `#__decaromembership_members`; dependency constants `MINIMUM_CORE_VERSION = '2.0.1'` and `MINIMUM_PEOPLE_VERSION = '1.2.15'` in the Membership People adapter introduced in Task 3.

- [ ] **Step 1: Write the failing Membership contract test**

Create `tests/people-integration-contract.php` with assertions equivalent to:

```php
$install = file_get_contents(__DIR__ . '/../component/admin/sql/install.mysql.utf8mb4.sql');
$update = file_get_contents(__DIR__ . '/../component/admin/sql/updates/mysql/1.5.0.sql');

assert(str_contains($install, '`person_uuid` CHAR(36) NULL'));
assert(str_contains($install, 'UNIQUE KEY `uq_member_person_uuid` (`person_uuid`)'));
assert(str_contains($update, 'ADD COLUMN `person_uuid` CHAR(36) NULL'));
assert(str_contains($update, 'MODIFY `first_name` VARCHAR(190) NULL'));
assert(str_contains($update, 'MODIFY `last_name` VARCHAR(190) NULL'));

$allPhp = implode("\n", array_map('file_get_contents', glob(__DIR__ . '/../component/admin/src/**/*.php')));
assert(!str_contains($allPhp, '#__xdecaropeople_'));
```

Use a recursive file scan rather than relying on `glob('**')` if PHP globstar is unavailable.

- [ ] **Step 2: Run contract and verify RED**

```bash
php tests/people-integration-contract.php
```

Expected: FAIL because `person_uuid` and 1.5.0 migration do not exist.

- [ ] **Step 3: Add non-destructive 1.5.0 SQL**

Create `component/admin/sql/updates/mysql/1.5.0.sql` with idempotent-compatible Joomla update statements:

```sql
ALTER TABLE `#__decaromembership_members`
  ADD COLUMN `person_uuid` CHAR(36) NULL AFTER `id`,
  MODIFY `first_name` VARCHAR(190) NULL,
  MODIFY `last_name` VARCHAR(190) NULL,
  ADD UNIQUE KEY `uq_member_person_uuid` (`person_uuid`);
```

Mirror that final structure in the clean-install SQL. Do not drop or rewrite any legacy identity columns.

- [ ] **Step 4: Adjust member field metadata**

In `MemberCoreEntities::definitions()['members']`, add a special People-link field entry:

```php
'person_uuid' => [
    'label' => 'COM_DECAROMEMBERSHIP_FIELD_PERSON',
    'type' => 'people',
],
```

Remove `required => true` from legacy `first_name`/`last_name`; the model-level new-record rule will require `person_uuid` instead.

- [ ] **Step 5: Add installer preflight dependency checks before schema update**

Extend `package/script.php` with `preflight($type, $parent): bool` that reads installed extension manifests/version information and returns `false` with Joomla error messages when Core < 2.0.1 or People < 1.2.15. The preflight must not write Membership tables.

The dependency check must resolve package/component installation safely and never instantiate People classes before confirming the extension is present.

- [ ] **Step 6: Run contract and SQL/manifest validation**

Run:

```bash
php tests/people-integration-contract.php
python3 build/validate.py
```

Expected: PASS for schema/boundary assertions; existing 1.4.0 tests still pass.

- [ ] **Step 7: Commit schema/dependency gate**

```bash
git add component/admin/sql component/admin/src/Config package/script.php tests/people-integration-contract.php

git commit -m "Membership 1.5.0: add People identity link schema"
```

---

### Task 3: Add Membership public People adapter

**Repository:** `xdecaro/membership`

**Files:**
- Create: `component/admin/src/Service/PeopleIntegrationService.php`.
- Modify: `component/admin/services/provider.php` only if component-level DI registration is needed by the existing service container pattern.
- Create: `tests/people-integration-service.php`.

**Interfaces:**
- Consumes: People component public `getPersonProviderService()`, `PersonProviderService::getPerson()`, `searchPeople()`, and `getPeopleByUuids()`.
- Produces:
  - `isAvailable(): bool`
  - `dependencyStatus(): array`
  - `getPerson(string $uuid, bool $sensitive = false): ?array`
  - `searchPeople(string $search, int $limit = 50): array`
  - `getPeopleByUuids(array $uuids): array`
  - `findByUserIdUnique(int $userId): ?array`
  - `openPersonUrl(string $uuid): string`

- [ ] **Step 1: Write failing service tests**

Create tests using a fake public People provider, asserting that Membership delegates rather than accessing DB. Include:

```php
assert(PeopleIntegrationService::MINIMUM_CORE_VERSION === '2.0.1');
assert(PeopleIntegrationService::MINIMUM_PEOPLE_VERSION === '1.2.15');
assert($service->getPerson($uuid)['uuid'] === $uuid);
assert(count($service->searchPeople('Luca', 10)) === 1);
assert(array_keys($service->getPeopleByUuids([$uuid])) === [$uuid]);
assert($service->findByUserIdUnique(42)['user_id'] === 42);
```

Also assert `findByUserIdUnique()` returns `null` if People returns 0 or 2 rows for the same user id.

- [ ] **Step 2: Run and verify RED**

```bash
php tests/people-integration-service.php
```

Expected: FAIL because `PeopleIntegrationService` does not exist.

- [ ] **Step 3: Implement adapter with controlled failures**

Implement the class without importing private People classes. Resolve the installed People component via Joomla boot/component APIs and call only its public getter/service. Wrap missing component, version mismatch, ACL and provider exceptions into a Membership-specific `RuntimeException` message suitable for controller/view handling.

Do not silently downgrade to direct SQL.

- [ ] **Step 4: Verify GREEN**

```bash
php tests/people-integration-service.php
php tests/people-integration-contract.php
php tests/core-integration-smoke.php
```

Expected: PASS.

- [ ] **Step 5: Commit adapter**

```bash
git add component/admin/src/Service/PeopleIntegrationService.php component/admin/services/provider.php tests

git commit -m "Membership: add public People integration adapter"
```

---

### Task 4: Enforce member UUID validity, uniqueness, immutability and safe legacy behavior

**Repository:** `xdecaro/membership`

**Files:**
- Modify: `component/admin/src/Model/RecordModel.php` in `saveEntity()`.
- Modify: `component/admin/src/Service/RecordRepository.php` to add UUID-specific lookup helpers if they do not already fit `duplicateExists()` cleanly.
- Modify: `component/admin/src/Service/AuditService.php` only to add explicit link/relink action metadata without sensitive People fields.
- Create: `component/admin/src/Service/MemberPeopleLinkService.php` to isolate member-link business rules from the generic RecordModel.
- Create: `tests/member-people-link.php`.

**Interfaces:**
- Consumes: `PeopleIntegrationService` from Task 3 and `RecordRepository`.
- Produces:
  - `validateForSave(int $memberId, array $old, array $data): array`
  - `linkLegacyMember(int $memberId, string $personUuid, int $userId): void`
  - `relinkMember(int $memberId, string $newUuid, int $userId): void`

- [ ] **Step 1: Write failing business-rule tests**

Cover all required cases:

```php
// new member without People UUID
expectException('A People person is required for a new member');
$linker->validateForSave(0, [], ['person_uuid' => '']);

// same UUID on another member
expectException('This People person is already linked to another member');
$linker->validateForSave(0, [], ['person_uuid' => $uuid]);

// existing linked member cannot change UUID through normal save
expectException('Use the relink action');
$linker->validateForSave(10, ['person_uuid' => $uuidA], ['person_uuid' => $uuidB]);

// legacy member with no UUID may still save Membership-owned fields
$data = $linker->validateForSave(10, ['person_uuid' => null], ['person_uuid' => null, 'status' => 'active']);
assert($data['status'] === 'active');
```

Also assert UUID is canonicalized from the People response, not trusted from arbitrary input.

- [ ] **Step 2: Run and verify RED**

```bash
php tests/member-people-link.php
```

Expected: FAIL because `MemberPeopleLinkService` does not exist.

- [ ] **Step 3: Implement link service and integrate RecordModel**

Keep `RecordModel` generic. In `saveEntity()` only branch when `$entity === 'members'`, delegate to `MemberPeopleLinkService`, then continue normal repository save and audit flow.

For linked members, strip identity fields from incoming data before persistence so People-owned values cannot overwrite frozen legacy columns:

```php
foreach (['first_name','last_name','birth_date','birth_place','tax_code','address','city','province','postal_code','country','email','phone','user_id'] as $legacyField) {
    unset($data[$legacyField]);
}
```

Do not strip Membership-owned `photo`, `notes`, `member_number`, `card_number`, `first_registration_date`, `status`, `location_id`, `category_id`, or `published`.

- [ ] **Step 4: Verify unit/service and generic RecordModel regressions**

Run:

```bash
php tests/member-people-link.php
php tests/people-integration-service.php
php tests/finance-integration-contract.php
```

Expected: PASS.

- [ ] **Step 5: Commit save/link rules**

```bash
git add component/admin/src/Model/RecordModel.php component/admin/src/Service tests/member-people-link.php

git commit -m "Membership: enforce People member identity link"
```

---

### Task 5: Add deterministic legacy-member backfill and manual-link filter

**Repository:** `xdecaro/membership`

**Files:**
- Create: `component/admin/src/Service/MemberPeopleBackfillService.php`.
- Modify: `package/script.php` postflight to run backfill only after a successful dependency-checked update/install path.
- Modify: `component/admin/src/Model/RecordsModel.php` to add `filter.people_link` for `linked` / `unlinked` on members.
- Modify: `component/admin/tmpl/records/default.php` to expose the filter.
- Create: `tests/member-people-backfill.php`.

**Interfaces:**
- Consumes: `PeopleIntegrationService::searchPeople()`/`findByUserIdUnique()`, `RecordRepository`.
- Produces: `run(): array{linked:int, skipped:int, ambiguous:int}`.

- [ ] **Step 1: Write failing backfill tests**

Cover:

```php
assert($backfill->run() === ['linked' => 1, 'skipped' => 0, 'ambiguous' => 0]);
assert($repository->member(10)['person_uuid'] === $uuid);

// second run must not change anything
assert($backfill->run()['linked'] === 0);

// two People rows for same user_id => no link
assert($ambiguousBackfill->run()['ambiguous'] === 1);
assert($repository->member(11)['person_uuid'] === null);
```

Also verify that a People UUID already owned by another member is skipped rather than reassigned.

- [ ] **Step 2: Run and verify RED**

```bash
php tests/member-people-backfill.php
```

Expected: FAIL because backfill service does not exist.

- [ ] **Step 3: Implement idempotent backfill**

Load only members where `person_uuid IS NULL AND user_id IS NOT NULL AND user_id > 0`. Resolve each via the public People adapter. Update only `person_uuid`, `modified`, `modified_by`. Record an audit action `people_backfill` with the UUID but no People PII snapshot.

- [ ] **Step 4: Add linked/unlinked list filter**

`RecordsModel::populateState()` should read `people_link`. `getListQuery()` should apply:

```php
if ($entity === 'members' && $peopleLink === 'linked') {
    $query->where($db->quoteName('a.person_uuid') . ' IS NOT NULL');
}
if ($entity === 'members' && $peopleLink === 'unlinked') {
    $query->where($db->quoteName('a.person_uuid') . ' IS NULL');
}
```

The template exposes the filter only for members.

- [ ] **Step 5: Verify tests**

```bash
php tests/member-people-backfill.php
php tests/people-integration-contract.php
```

Expected: PASS.

- [ ] **Step 6: Commit migration/backfill behavior**

```bash
git add component/admin/src/Service/MemberPeopleBackfillService.php component/admin/src/Model/RecordsModel.php component/admin/tmpl/records/default.php package/script.php tests

git commit -m "Membership: backfill deterministic People links"
```

---

### Task 6: Resolve People identity in member lists without N+1 and support People-aware search

**Repository:** `xdecaro/membership`

**Files:**
- Modify: `component/admin/src/Model/RecordsModel.php`.
- Modify: `component/admin/src/View/Records/HtmlView.php`.
- Modify: `component/admin/tmpl/records/default.php`.
- Create: `tests/member-list-people.php`.

**Interfaces:**
- Consumes: `PeopleIntegrationService::getPeopleByUuids()` and `searchPeople()`.
- Produces: a view map keyed by `person_uuid`, plus a clear status for unavailable People records.

- [ ] **Step 1: Write failing list-resolution test**

Use a fake adapter that counts calls. Assert 20 linked members call batch lookup exactly once:

```php
$people = $model->resolvePeopleForItems($items);
assert($fakePeople->batchCalls === 1);
assert($people[$uuid]['display_name'] === 'Luca De Caro');
```

Also assert an unlinked member uses legacy first/last name and an unresolved linked UUID renders the diagnostic label instead of disappearing.

- [ ] **Step 2: Run and verify RED**

```bash
php tests/member-list-people.php
```

Expected: FAIL because `resolvePeopleForItems()` does not exist.

- [ ] **Step 3: Implement one-call page resolution**

Add a public model helper that collects UUIDs from current page items, calls one adapter batch method, and returns a UUID map. Do not modify the SQL query to join People.

Set default member ordering to `member_number` when present, otherwise `id`; do not default to legacy `last_name`.

- [ ] **Step 4: Add People-aware search without cross-component SQL**

When `entity=members` and `filter_search` is non-empty, call People `searchPeople()` once before building the Membership query, collect returned UUIDs, then query Membership with an OR between:

- `a.person_uuid IN (...)` for People matches;
- existing legacy field LIKE conditions restricted to unlinked legacy rows.

All UUIDs are bound parameters. If People is unavailable, retain safe Membership legacy/domain search and expose a warning in the view rather than throwing a fatal error.

- [ ] **Step 5: Update list rendering**

For members, render current People `display_name`; for unlinked legacy rows, render legacy name with an `Unlinked` badge; for unresolved linked UUIDs, render `Persona People non disponibile` with the Membership member number/id still visible.

- [ ] **Step 6: Run tests**

```bash
php tests/member-list-people.php
php tests/people-integration-service.php
php tests/finance-integration-contract.php
```

Expected: PASS.

- [ ] **Step 7: Commit list integration**

```bash
git add component/admin/src/Model/RecordsModel.php component/admin/src/View/Records/HtmlView.php component/admin/tmpl/records/default.php tests/member-list-people.php

git commit -m "Membership: resolve People identities in member lists"
```

---

### Task 7: Replace the member identity form with a People selector, read-only summary and privileged relink

**Repository:** `xdecaro/membership`

**Files:**
- Modify: `component/admin/src/View/Record/HtmlView.php`.
- Modify: `component/admin/tmpl/record/default.php`.
- Modify: `component/admin/src/Controller/RecordController.php` to add AJAX/search and relink tasks, or create a focused `PeopleController.php` if current controller becomes too large.
- Modify: `component/admin/media/js/admin.js`.
- Modify: `component/admin/media/css/admin.css`.
- Modify: `component/admin/access.xml` to add `membership.relink_person` (or a similarly explicit elevated action).
- Modify language files: `component/admin/language/{it-IT,en-GB,fr-FR}/com_decaromembership.ini`.
- Create: `tests/member-people-ui-contract.php`.

**Interfaces:**
- Consumes: `PeopleIntegrationService::searchPeople()`, `getPerson()`, `openPersonUrl()`; `MemberPeopleLinkService::relinkMember()`.
- Produces: searchable People selector for unlinked/new members, read-only People identity card for linked members, explicit relink action.

- [ ] **Step 1: Write failing UI contract test**

Assert the record template and JS contain stable hooks:

```php
assert(str_contains($template, 'data-membership-people-search'));
assert(str_contains($template, 'data-membership-person-summary'));
assert(str_contains($template, 'data-membership-person-relink'));
assert(str_contains($js, 'membershipPeopleSearch'));
```

Also assert ordinary linked-member rendering does not emit editable legacy `first_name`, `last_name`, `email`, `phone`, `birth_date`, `tax_code`, address fields or `user_id` inputs.

- [ ] **Step 2: Run and verify RED**

```bash
php tests/member-people-ui-contract.php
```

Expected: FAIL because hooks/selector do not exist.

- [ ] **Step 3: Build member-specific form branch**

In `record/default.php`, preserve the generic renderer for all non-member entities. For `entity === 'members'`, render:

- People search selector when `person_uuid` is empty;
- read-only People summary when linked;
- Membership-owned fields using the existing generic field renderer;
- legacy identity block only for existing unlinked members, visually marked as migration data;
- `Apri in People` link when the provider can resolve the person.

Do not expose sensitive fields unless the People provider returns them successfully under current ACL.

- [ ] **Step 4: Add debounced search and selection JS**

Implement a CSRF-protected AJAX search against a Membership controller endpoint. Return only UUID, display name and non-sensitive selection metadata. The browser stores the chosen UUID in `jform[person_uuid]`; the server revalidates it on save.

- [ ] **Step 5: Add privileged relink flow**

Add `membership.relink_person` to `access.xml`. The relink endpoint must check ACL server-side, require CSRF token, resolve the new UUID through People, reject duplicate ownership, and write the audit action with old/new UUID.

- [ ] **Step 6: Run UI and link tests**

```bash
php tests/member-people-ui-contract.php
php tests/member-people-link.php
php tests/people-integration-service.php
```

Expected: PASS.

- [ ] **Step 7: Commit UI/relink flow**

```bash
git add component/admin/src/View component/admin/src/Controller component/admin/tmpl component/admin/media component/admin/access.xml component/admin/language tests/member-people-ui-contract.php

git commit -m "Membership: add People member selector and relink flow"
```

---

### Task 8: Add diagnostics, version metadata and runtime regression coverage

**Repository:** `xdecaro/membership`

**Files:**
- Modify: `component/admin/src/Model/InformationModel.php`.
- Modify: `README.md`.
- Modify: `CHANGELOG.md`.
- Modify: `VERSION` to `1.5.0`.
- Modify: `component/decaromembership.xml` to `1.5.0`.
- Modify: `package/pkg_decaromembership.xml` to `1.5.0`.
- Modify: `updates/pkg_decaromembership.xml` / `updates/changelog.xml` only for release-ready metadata; do not publish a checksum before final package verification.
- Modify: `build/validate.py`.
- Modify: `.github/workflows/ci.yml`.
- Create: `tests/people-runtime.php` or extend the Joomla runtime harness with People-specific scenarios.

**Interfaces:**
- Consumes: all prior tasks and published Core 2.0.1 / People 1.2.15 artifacts with pinned SHA-256.
- Produces: Membership 1.5.0 deterministic package and a CI matrix proving clean install, upgrade and Finance compatibility.

- [ ] **Step 1: Write failing metadata/runtime contract assertions**

Update `build/validate.py`/tests so validation requires:

```text
VERSION == 1.5.0
Core minimum == 2.0.1
People minimum == 1.2.15
1.5.0.sql packaged
no #__xdecaropeople_* references in Membership PHP/SQL
no private People Model/Table imports
```

Runtime test setup must pin actual released Core 2.0.1 and People 1.2.15 package SHA-256 values rather than `latest` URLs.

- [ ] **Step 2: Run validation and verify RED**

```bash
python3 build/validate.py
php tests/people-integration-contract.php
```

Expected: FAIL until metadata and runtime harness are updated.

- [ ] **Step 3: Update Information/Diagnostics**

Expose Core and People installed/minimum versions, availability and compatibility. Missing People after installation must produce a diagnostic warning and disable People-dependent UI without causing a fatal error.

- [ ] **Step 4: Update 1.5.0 metadata/docs**

Set version `1.5.0` across VERSION/manifests/assets/build assertions. Document that People owns identity while Membership owns associative lifecycle. Add changelog entries for migration, backfill and dependency floors.

- [ ] **Step 5: Extend Joomla 6.1.3 CI runtime**

Add two primary runtime jobs:

```text
clean: Core 2.0.1 + People 1.2.15 + Membership 1.5.0
upgrade: Core 2.0.1 + People 1.2.15 + Membership 1.4.0 -> 1.5.0
```

The upgrade job must seed a 1.4.0 member, child renewal/card/due/payment/transfer rows, and a Joomla `user_id`, then verify:

- member id unchanged;
- child rows unchanged and still point to the same member id;
- deterministic user-id match fills `person_uuid`;
- legacy name fields remain preserved but are no longer authoritative;
- a second run is idempotent.

Add a clean-install scenario that creates a member linked to People and asserts legacy `first_name`/`last_name` remain NULL.

- [ ] **Step 6: Re-run Finance 1.3.0 runtime regression**

Run the existing Finance integration runtime suite unchanged except for installing required Core/People dependencies first. Verify quota/payment update-before-allocation, replay idempotence and post-allocation mutation rejection still pass.

- [ ] **Step 7: Run full validation/build twice**

```bash
python3 build/validate.py
python3 build/build.py
cp dist/SHA256SUMS.txt /tmp/membership-first-sha.txt
python3 build/build.py
cmp /tmp/membership-first-sha.txt dist/SHA256SUMS.txt
```

Expected: deterministic checksums and valid package ZIPs.

- [ ] **Step 8: Commit release-candidate metadata and CI**

```bash
git add VERSION component package updates build tests .github README.md CHANGELOG.md

git commit -m "Membership 1.5.0: verify People-backed member identity"
```

Keep PR Draft while CI runs.

---

### Task 9: End-to-end review, runtime QA and release checkpoints

**Repositories:** `xdecaro/people`, `xdecaro/membership`

**Files:** No new production behavior unless a failing test identifies a defect.

**Interfaces:** Validates the complete integration contract.

- [ ] **Step 1: Verify People 1.2.15 CI and package**

Confirm all People tests, deterministic build and Joomla runtime checks are green. Verify package SHA-256 and that the public provider method exists in the built component ZIP.

- [ ] **Step 2: Publish People 1.2.15 only at the approved release checkpoint**

Mark People PR Ready, merge, create release/feed, then verify the published package checksum matches the tested package. Membership runtime CI must pin this exact checksum.

- [ ] **Step 3: Verify Membership PR CI**

Confirm clean install, 1.4.0→1.5.0 upgrade, People missing/incompatible preflight rejection, People runtime removal diagnostics, list batch behavior, ACL behavior and Finance regressions are green.

- [ ] **Step 4: Manual Joomla administrator QA**

On Joomla 6.1.3 verify:

```text
New member -> search People -> select -> save
Linked member -> People identity is read-only and current
Rename person in People -> Membership shows new name without rewriting member row
Legacy unlinked member -> remains accessible and visibly unlinked
Relink -> only privileged user + confirmation
Members list -> one People batch lookup per page, no fatal when person unavailable
Responsive -> desktop/tablet/mobile
Appearance -> light/dark
Console -> no JS errors
```

- [ ] **Step 5: Stop before Membership merge/release for explicit user approval**

Do not mark Membership PR Ready, merge `main`, create `v1.5.0`, or publish the Joomla update feed until the user explicitly approves the runtime result.

- [ ] **Step 6: After approval, release Membership 1.5.0**

Mark PR Ready, merge, let release workflow build the final artifact, verify published SHA-256 against feed and release asset, and test Joomla update detection from 1.4.0 to 1.5.0 on a clone/test install.
