# Core by xdecaro integration

Membership usa il contratto pubblico di Core by xdecaro per i riferimenti cross-product e può usare anche il design system condiviso nella sola pagina Informazioni/Diagnostica.

Identità Joomla stabile di Membership: `com_decaromembership`.

## Contratto riferimenti

`CoreIntegrationService` rimane un adapter di proprietà Membership registrato nel container Joomla.

Da Membership 1.2.0 usa il namespace canonico Core 1.3:

- `xdecaro\Core\Integration\EntityReference` per riferimenti `component/entity/id`;
- `xdecaro\Core\Integration\RelationReference` per relazioni tipizzate;
- `xdecaro\Core\Version` per verificare Core 1.3.0+ prima di usare l'integrazione.

Il namespace deprecato `Xdecaro\Core` non è usato da Membership 1.2.0.

Core resta opzionale: le operazioni dipendenti da Core sono disponibili solo con Core 1.3.0+ e, in caso contrario, falliscono con una `RuntimeException` controllata.

## UI condivisa

Membership 1.2.0 usa `xdecaro\Core\Asset\AssetService` soltanto nella vista amministrativa Informazioni.

- Core 1.3.0+ presente e registry asset disponibile: vengono caricati i componenti UI Core e il piccolo bridge token Membership -> Core.
- Core assente, troppo vecchio o asset non disponibili: la vista usa il layout locale precedente e `com_decaromembership.admin`.
- Nessun asset Core viene caricato globalmente da Membership.
- Nessuna regola di dominio Membership viene spostata in Core.

## Identificatori consumer

Le integrazioni devono usare gli identificatori Joomla pubblicati reali degli altri prodotti, non i nomi dei repository. Gli identificatori esterni vanno trattati come contratti dei rispettivi componenti e non duplicati come dipendenze interne di Membership quando non necessario.

## Confini

Soci, categorie, pratiche, rinnovi, tessere, quote, pagamenti, trasferimenti, relazioni familiari, audit e stato dei workflow restano interamente di Membership. Non leggere o scrivere tabelle private di altri prodotti come protocollo d'integrazione.
