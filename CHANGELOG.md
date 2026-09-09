# Changelog

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
