# Core by xdecaro integration

Membership usa il contratto pubblico di Core by xdecaro per i riferimenti cross-product e, dalla versione 1.1.0, può usare anche il design system condiviso nella sola pagina Informazioni/Diagnostica.

Identità Joomla stabile di Membership: `com_decaromembership`.

## Contratto riferimenti

`CoreIntegrationService` rimane un adapter di proprietà Membership registrato nel container Joomla.

Usa:

- `Xdecaro\Core\Integration\EntityReference` per riferimenti `component/entity/id`;
- `Xdecaro\Core\Integration\RelationReference` per relazioni tipizzate.

Core resta opzionale: `isAvailable()` verifica le classi pubbliche e le operazioni dipendenti da Core falliscono con una `RuntimeException` controllata quando non sono disponibili.

## UI condivisa

Membership 1.1.0 usa `Xdecaro\Core\Asset\AssetService` soltanto nella vista amministrativa Informazioni.

- Core 1.1+ presente e registry asset disponibile: vengono caricati `xdecaro.components`, `.xdecaro-scope` e il piccolo bridge token Membership -> Core.
- Core assente/incompatibile: la vista usa il layout locale precedente e `com_decaromembership.admin`.
- Nessun asset Core viene caricato globalmente da Membership.
- Nessuna regola di dominio Membership viene spostata in Core.

## Identificatori consumer

Le integrazioni devono usare gli identificatori Joomla pubblicati reali, tra cui:

- Forms: `com_decaroforms`;
- Documents: `com_decarodocuments`;
- Courses: `com_decarocourses`;
- Competitions: `com_decarodcl`.

Non usare il nome del repository come sostituto dell'element Joomla.

## Confini

Soci, categorie, pratiche, rinnovi, tessere, quote, pagamenti, trasferimenti, relazioni familiari, audit e stato dei workflow restano interamente di Membership. Non leggere o scrivere tabelle private di altri prodotti come protocollo d'integrazione.
