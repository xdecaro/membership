# Changelog

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
