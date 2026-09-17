# Changelog

## 1.6.0 - 2026-09-18
- Added non-destructive membership lifecycle fields for current period, ending reason, rights state, right overrides and recognised seniority.
- Added dedicated member history records for status, category and location changes.
- Added configurable eligibility policies for active/passive association rights without hardcoding ENS-specific rules.
- Added readmission as a first-class Membership case type.
- Added public `membership.people_history` v1 capability keyed by People `person_uuid`, including current Membership state, safe history, transfers and eligibility summary.
- Added public Membership eligibility and person-history services while keeping People authoritative for personal identity data.
- Improved related-member labels by resolving People identities in batch instead of relying on legacy duplicated names.
- Fixed Joomla update-feed generation so version, download URL and SHA-256 advance together.
- Added Joomla 6.1.3 runtime coverage for lifecycle, eligibility and public history.


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
