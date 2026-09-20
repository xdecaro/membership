# Changelog

## 1.9.11 - 2026-09-20
- Added an application-level duplicate check for renewals by member and association year.
- Replaced the raw database unique-key message with a clear user-facing duplicate-renewal message.
- Kept the existing database uniqueness constraint as the authoritative safeguard.
- No schema or existing-data rewrite is performed.

## 1.9.10 - 2026-09-20
- Administrator member relation selectors no longer hide legacy members solely because `published = 0`.
- Renewal status is labeled `Stato rinnovo`, is mandatory, and defaults to `Da rinnovare`.
- Renewal payment status is mandatory and defaults to `Non pagato`.
- Renewal publication is labeled `Pubblicato` instead of the ambiguous generic `Stato`.
- Added runtime coverage for unpublished-member relation availability and renewal defaults.
- No schema or existing-data rewrite is performed.

## 1.9.9 - 2026-09-20
- Preserved submitted form values after validation errors instead of reopening a blank record.
- Preserved the preselected member in the Member → Card flow after a failed save.
- Required-field errors now report the human field label, e.g. `Campo obbligatorio mancante: Numero tessera.`.
- Form state is consumed once on redisplay to avoid stale data leaking into later edits.
- No schema or existing-data rewrite is performed.

## 1.9.8 - 2026-09-20
- Made Card type mandatory in both the administrator form metadata and server-side record validation.
- Made Card status mandatory in both the administrator form metadata and server-side record validation.
- Added runtime regression checks that reject card creation when either required field is missing.
- Dates, annual mark, QR token and notes remain optional.
- No schema or existing-data rewrite is performed.

## 1.9.7 - 2026-09-20
- Added a dedicated **Card status** label to distinguish the card lifecycle state from Joomla publication state.
- Added card-specific localized states; Italian now uses `Attiva`, `Scaduta`, `Smarrita`, `Revocata`, `Sostituita`.
- Renamed the card publication field label to `Pubblicato` instead of the ambiguous generic `Stato`.
- Administrator lists now localize configured select values instead of showing raw internal codes.
- Preserves the complete Member → Card workflow introduced in 1.9.6; no data or schema rewrite.

## 1.9.6 - 2026-09-19
- Improved the Member → Card flow: when no current card exists, the member profile now opens a new card record with that member preselected.
- Member relation labels now resolve through People display names, with legacy/member-number fallbacks.
- Selected relation records remain visible in edit/create flows even when unpublished, preserving historical references.
- Card list member labels now use People-backed names instead of blank legacy first/last-name fields.
- Added non-destructive 1.9.6 schema marker and regression coverage.

## 1.9.5 - 2026-09-19
- Fixed the administrator member page fatal error `Call to undefined method ... HtmlView::getDatabase()` introduced in 1.9.4.
- Current-card lookup now goes through `RecordModel`, which owns database access, instead of constructing a repository inside the view.
- Preserves Cards as the authoritative source and does not rewrite membership or card data.
- Added a non-destructive 1.9.5 schema marker and regression coverage.

## 1.9.4 - 2026-09-19
- Made `#__decaromembership_cards` the authoritative source for card numbers and card lifecycle data.
- Removed duplicate editable `card_number` from the member form/list while preserving the legacy database column for compatibility.
- Added a read-only current-card summary to the member profile with a direct link to Cards management.
- Current-card lookup prefers an active published card, then the most recent published card.
- Preserved legacy `members.card_number` values as a visible warning/fallback when no Cards record exists; no destructive migration is performed.
- Added runtime and contract regression coverage for the card source boundary.

## 1.9.3 - 2026-09-19
- Prevented existing legacy members with a blank prior status from receiving invented lifecycle dates when their current status is first set.
- New members still receive automatic lifecycle dates when created as Admitted/Active, and later real status transitions retain the existing automation.
- Members now explicitly default to `published = 1`, matching the database default and the other publishable Membership entities.
- Existing records and historical dates are not rewritten automatically.
- Added a non-destructive 1.9.3 schema marker and runtime regression coverage.

## 1.9.2 - 2026-09-19
- Fixed the default publication state for new configurable records: publishable entities now explicitly default to `published = 1` in the form configuration.
- Categories therefore start published instead of submitting `0` from a blank new-record form.
- Administrator lists now render Published/Unpublished badges instead of raw `1/0` values.
- Existing unpublished records are deliberately preserved and are not re-enabled during upgrade.
- Added a non-destructive 1.9.2 schema marker and regression coverage.

## 1.9.1 - 2026-09-19
- Fixed creation of categories when Ordering is left at its default: new ordered records now start from `0` instead of attempting to save `NULL` into `NOT NULL` columns.
- Applied the same safe default to case statuses and checklist templates, which use the same ordered-record pattern.
- Added a non-destructive 1.9.1 schema marker and regression coverage for Joomla 6 package/update validation.

## 1.9.0 - 2026-09-19
- Made member category mandatory and added an optional stable category code without hardcoding association-specific categories.
- Added direct Categories access to the Membership administrator submenu.
- Added configurable new-member default status (Pending, Under review or Active).
- Added manual member numbering as the safe default and optional automatic numbering with configurable prefix and padding.
- Added conservative lifecycle date defaults for admission/activation, status changes and terminal statuses.
- Added clear form guidance when no published categories exist and an action to configure them.
- Added Joomla 6.1.3 runtime coverage for category enforcement, automatic numbering and lifecycle dates.
- Preserved People as the authoritative identity source, Organizations as optional, and all existing Finance/lifecycle history behavior.



## 1.8.0 - 2026-09-19
- Added a hierarchical Organizations selector based on the public `parent_id` relationship, preserving root/child order such as ENS → ENS Lazio → ENS Roma.
- Added live filtering by organization name, hierarchy path and type.
- Added Joomla language translations for all current Organizations types in Italian, English and French.
- Kept the selected organization visible while filtering and preserved standalone Membership behavior when Organizations is absent.
- Added UI, language, build and regression contracts without changing the Membership database schema.



## 1.7.0 - 2026-09-18
- Simplified member creation around the required People identity and a compact essential membership section.
- Added optional `organization_uuid` on members without making Organizations a package dependency.
- Added public-provider-only integration with Organizations; Membership never reads Organizations private tables.
- Kept internal `location_id` and Membership locations as standalone/legacy compatibility data.
- Added an advanced collapsible section for card, lifecycle dates, seniority, voting rights and legacy/internal location.
- Added non-destructive organization-link history with old/new organization UUID values.
- Public person-membership output resolves the organization name when Organizations is available, while preserving compatibility with People 1.5.x.
- Added clean-install, upgrade, static-contract and real Joomla 6.1.3 runtime coverage for standalone and Organizations-enabled scenarios.



## 1.6.3 - 2026-09-18
- Switched Membership administrator CSS/JS delivery to direct HtmlDocument asset URLs after the real Joomla 6.1.3 console test proved direct linking works while Web Asset activation still emitted no tags.
- Removed the stray dashboard output above the Membership title.
- Added a regression contract for direct runtime asset delivery and the dashboard output fix.
- Preserved People, Finance, lifecycle, ACL and database behavior.


## 1.6.2 - 2026-09-18
- Fixed the root cause of missing administrator styles/scripts: explicitly load the `com_decaromembership` Web Asset registry before activating its assets.
- Switched from ad-hoc `registerAndUseStyle` / `registerAndUseScript` calls to registry-backed `useStyle` / `useScript`.
- Added a regression contract for the exact console-observed condition where media files exist and return HTTP 200 but are not attached to the page.
- Preserved all 1.6.1 media-install checks and all People/Finance/lifecycle behavior.


## 1.6.1 - 2026-09-18
- Fixed administrator CSS/JS delivery by centralizing all Membership asset registration through Joomla Web Asset Manager.
- Removed duplicate manual `<link>` and `<script>` tags from Dashboard, lists, forms and Information templates.
- Added installer diagnostics for missing `/media/com_decaromembership` assets.
- Added clean-install and upgrade CI assertions that verify the actual CSS/JS files installed on Joomla 6.1.3.
- Added package validation that rejects builds missing Membership media assets.


## 1.6.0 - 2026-09-18
- Added explicit membership lifecycle dates, cessation metadata and voting-right flags.
- Added an explicit readmission case type plus current-period start and recognised prior-seniority credit, so interruptions do not have to be counted as active membership.
- Added non-destructive member lifecycle history for status, category, location and rights changes.
- Added transfer effective date and automatic location update only when a transfer is validly completed.
- Added public person-memberships and eligibility capabilities for optional People integration.
- Preserved People as the authoritative personal registry and all existing Membership/Finance behavior.


## 1.5.0 - 2026-09-15
- People 1.2.15 becomes the authoritative person registry for Membership identity/contact data.
- Added nullable unique `person_uuid` to members while preserving `member_id` as the stable key for all Membership-domain records.
- Kept all legacy member identity columns and made first/last name nullable for a non-destructive 1.4.0 → 1.5.0 transition.
- New members require a valid People person; one People UUID can belong to only one Membership member.
- Existing People links are immutable in the normal edit flow; explicit relink uses a dedicated ACL and UUID-only audit records.
- Added deterministic legacy backfill through unique Joomla `user_id` matches only; ambiguous/unavailable matches are not guessed.
- Added People-backed member list/search with one batch resolution per page and linked/unlinked filtering, without joins or direct People-table access.
- Added People selector and read-only People identity summary to the member form while preserving Membership-owned fields.
- Added Core 2.0.1+ and People 1.2.15+ package preflight requirements and diagnostics.
- Added Joomla 6.1.3 clean-install, upgrade, dependency-preflight and People runtime coverage while retaining Finance 1.3.0 regression coverage.

## 1.4.0 - 2026-09-09
- Added optional Finance 1.3.0 synchronization through the public `com_decarofinance` component service only.
- Membership dues are upserted as Finance obligations and paid Membership payments are upserted as Finance payments without direct access to Finance tables or implementation classes.
- Due and payment changes made before allocation are propagated to the existing Finance records in place.
- Paid-payment allocation is replay-safe: repeating an unchanged synchronization does not duplicate the allocation.
- Conflicting due or payment changes after allocation are rejected so financial history is not silently rewritten.
- Added Finance integration boundary tests and a Joomla 6.1.3 runtime contract test pinned to the released Finance 1.3.0 package SHA-256.
- Added the non-destructive 1.4.0 SQL schema marker and updated release/build validation.

## 1.3.0 - 2026-09-09
- Added shared Notifications and Tasks bridges without cross-product table access.
- Added Membership Analytics provider with ACL-protected metrics and datasets.
- Added Joomla Scheduled Tasks reminders for renewals, cards, membership documents and unpaid dues using existing deadline fields.
- Shared notifications are sent only when a member is linked to a Joomla user; manager tasks require an explicitly configured Joomla user.
- Preserved the legacy Membership notifications table and its data for compatibility; new automatic reminders use Notifications by xdecaro.
- Corrected Joomla SQL manifest charset declarations to `utf8` while retaining `utf8mb4` table definitions.
- Added integration plugin packaging while preserving plugin enabled state on updates.

## 1.2.0 - 2026-09-08
- Existing stable Membership release.
