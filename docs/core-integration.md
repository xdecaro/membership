# Xdecaro Core integration

Membership uses the Xdecaro Core cross-product reference contract when linking members and membership workflows to other Xdecaro products.

Current Joomla component element: `com_decaromembership`.

Use:

- `Xdecaro\Core\Integration\EntityReference` for `component/entity/id` references;
- `Xdecaro\Core\Integration\RelationReference` for typed links between references.

## Optional runtime adapter

Membership registers `Xdecaro\Component\Decaromembership\Administrator\Service\CoreIntegrationService` in the Joomla DI container.

The adapter intentionally keeps Core optional during this transition:

- `isAvailable()` reports whether the Core public reference classes can be resolved;
- `createEntityReference()` creates a Membership-owned Core entity reference;
- `createRelationReference()` creates a typed relation from a Membership entity to another product's published entity;
- when Core is unavailable, Core-dependent calls throw a controlled `RuntimeException` rather than causing an opaque class-not-found fatal error.

Do not bypass this boundary by probing Core internals.

Membership remains the owner of members, categories, applications, renewals, cards, fees, payments, transfers, family relationships and membership workflow state.

Typical integrations include:

- Membership application/renewal -> Forms submission with relation type `source_submission`;
- Membership member -> Courses enrollment with relation type such as `participant`;
- Membership member -> Competitions player/participation, using Competitions component element `com_decarodcl`;
- Membership member/application -> Documents managed document through the Documents public API;
- Membership member -> Events registration/participant when Events publishes its stable entity API.

Membership decides why another entity is related to a member or membership process. The other product remains responsible for its own domain and authorization.

Do not read or write another product's private tables as the integration API. Optional integrations must fail gracefully when unavailable.

Entity type and relation type names become stable public contracts once published; evolve them through backward-compatible changes whenever possible.
