# Membership — Codex Repository Rules

## Xdecaro Core integration

Membership is part of the Xdecaro Joomla ecosystem and should use **Xdecaro Core** for infrastructure that is genuinely shared across multiple Xdecaro extensions.

Core is infrastructure, not Membership business logic.

Before implementing or refactoring reusable technical functionality, inspect whether the responsibility already exists in Core or clearly belongs there.

Good Core candidates include:

- shared design tokens and `.xdecaro-*` UI primitives;
- light/dark mode foundations;
- responsive administrator UI helpers;
- shared buttons, badges, cards, tables, modals, alerts and loading states;
- shared Web Asset Manager registration;
- generic JavaScript utilities;
- Joomla-compliant AJAX/CSRF helpers;
- dependency/version checks;
- common diagnostics;
- Xdecaro extension registry;
- shared information/update UI;
- genuinely generic cross-extension contracts or events.

Keep Membership-specific business logic in this repository, including:

- members and member profiles;
- membership categories;
- applications and membership workflows;
- renewals;
- cards and card status;
- fees and membership payments;
- transfers;
- offices/sections when represented by Membership domain logic;
- family relationships;
- membership checklists;
- membership audit history;
- membership reporting;
- membership-specific integrations and rules.

Do not move these domain concepts into Core.

A reusable feature belongs in Core only when it is domain-neutral and useful to more than one Xdecaro product.

## Integrations

Membership may integrate with Forms, Documents, Courses, Competitions, Events and future Xdecaro products.

Prefer stable public APIs, events or explicit contracts over direct access to another component's internal tables or classes.

Cross-component relationships should remain loosely coupled. Do not create circular dependencies.

If a universal relation mechanism becomes genuinely useful to multiple products, evaluate whether only the generic relation contract belongs in Core. Membership-specific relation semantics remain in Membership.

## Documents integration

Membership may use Documents for identity documents, certificates, applications, attachments or other stored files.

Do not duplicate a complete document-management engine inside Membership when Documents provides the required stable API.

Do not move document-domain logic into Core merely because several products use Documents.

Membership remains responsible for deciding why a document is required and how it participates in a membership workflow. Documents remains responsible for document storage/domain behavior.

## Forms integration

Membership may use Forms for configurable intake or application forms.

Do not duplicate the Forms builder or submission engine inside Membership.

Membership owns the membership workflow and consumes validated Forms data through stable integration points.

## Dependency policy

If Core becomes a mandatory runtime dependency, update the package, manifests, installer/update path and minimum Core version coherently.

Missing or incompatible dependencies must produce a controlled Joomla administrator message rather than an opaque fatal error.

Updates must preserve existing Membership data and configuration.

## Joomla and security

Continue to enforce where relevant:

- server-side ACL;
- Joomla CSRF tokens;
- filtered and validated input;
- escaped output;
- bound database queries;
- safe upload/document handling;
- authorization checks for personal/member data;
- no security-sensitive authorization decisions made only in JavaScript.

Membership data may contain personal or sensitive information. Apply least-privilege access and avoid exposing unnecessary data in logs, diagnostics or frontend output.

## Database

Use `#__` for Joomla tables.

Keep Membership-domain tables in Membership.

Do not move member, fee, renewal, transfer or membership state into Core.

Database updates must preserve existing data and configuration. Avoid destructive table recreation during normal updates when a safe migration is possible.

Check indexes, joins, duplicate queries and queries inside loops, especially for member lists, renewals, payments and reporting.

## UI and assets

Prefer Core for shared visual primitives and asset infrastructure when available, but keep Membership-specific workflows and presentation local.

Shared UI migration must preserve:

- administrator workflows;
- frontend behavior;
- accessibility;
- responsive layouts;
- light mode;
- dark mode.

Load shared Core assets through Joomla Web Asset Manager and avoid duplicate CSS/JS registration.

## Regression rule

A Core-related change is complete only when affected Membership behavior remains verified.

Check as applicable:

- clean installation;
- update installation;
- supported Joomla versions;
- members/categories;
- applications and renewals;
- fees/payments;
- transfers;
- cards;
- family relations;
- Documents/Forms integrations;
- ACL and CSRF;
- database migrations;
- PHP errors/warnings;
- JavaScript Console;
- desktop/tablet/smartphone;
- light/dark mode.

Do not combine an opportunistic Core integration with unrelated large refactors.

## Working rule

When the user says **“procedi”**, execute the requested work directly after inspecting the relevant code and dependencies.

Do not ask for another confirmation when requirements are already clear.

If a proposed technical approach is weaker than a safer or more maintainable alternative, explain the issue and use or recommend the stronger approach.
