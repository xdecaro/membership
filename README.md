# Membership by xdecaro

Membership è il componente Joomla 6 per la gestione centrale dell'identità associativa: soci, categorie, pratiche, rinnovi, tessere, quote, pagamenti, trasferimenti, sedi, relazioni familiari, documenti, checklist, audit, report e integrazioni con l'ecosistema xdecaro.

## Identità

- Componente: `com_decaromembership`
- Pacchetto: `pkg_decaromembership`
- Versione corrente: **1.4.0**
- Joomla: `6.*`
- Namespace: `Xdecaro\\Component\\Decaromembership`

## Xdecaro Core

Membership mantiene Core opzionale per la logica applicativa. Da 1.2.0 `CoreIntegrationService` usa il namespace canonico `xdecaro\\Core` e rende disponibili `EntityReference` / `RelationReference` soltanto con Core by xdecaro 1.3.0+.

La pagina **Informazioni/Diagnostica** usa inoltre, in modo opt-in, `xdecaro\\Core\\Asset\\AssetService` con Core 1.3.0+. Se Core manca, è troppo vecchio o gli asset non sono disponibili, Membership mantiene automaticamente il layout/CSS locale esistente.

Core non contiene soci, pratiche, rinnovi, tessere, quote, pagamenti, trasferimenti o altre regole Membership.

## Finance

Da Membership 1.4.0 l'integrazione con **Finance by xdecaro 1.3.0+** è opzionale e passa esclusivamente dal servizio pubblico esposto da `com_decarofinance`.

Membership non legge né scrive tabelle `#__decarofinance_*` e non dipende da classi di implementazione Finance. Le quote Membership vengono sincronizzate come obbligazioni Finance e i pagamenti Membership in stato `paid` come pagamenti Finance tramite chiavi esterne stabili.

Prima dell'allocazione, una quota o un pagamento modificato in Membership aggiorna in place il corrispondente record Finance. Dopo l'allocazione, un replay invariato è idempotente, mentre modifiche finanziarie conflittuali vengono rifiutate per evitare la riscrittura silenziosa dello storico contabile.

L'assenza di Finance non blocca Membership: le funzioni Finance restano semplicemente non disponibili finché `com_decarofinance` non è installato e abilitato.

## Distribuzione

La release pubblica comprende:

- `com_decaromembership_<versione>.zip`;
- `plg_xdecaroanalytics_decaromembership_<versione>.zip`;
- `plg_task_decaromembership_<versione>.zip`;
- `pkg_decaromembership_<versione>.zip` — pacchetto consigliato per Joomla;
- `SHA256SUMS.txt`.

Il package registra l'update server Joomla `updates/pkg_decaromembership.xml`.

## Test di integrazione

La CI verifica sintassi PHP, manifest/XML, build deterministica, confini tra componenti e installazione reale su Joomla 6.1.3. Per Membership 1.4.0 la prova runtime installa il package pubblico Finance 1.3.0 fissato per SHA-256 e verifica:

- aggiornamento della quota prima dell'allocazione;
- aggiornamento del pagamento prima dell'allocazione;
- replay idempotente della stessa allocazione;
- rifiuto delle modifiche finanziarie successive all'allocazione.

## Struttura repository

- `component/` — sorgenti installabili del componente;
- `package/` — manifest package;
- `updates/` — update server Joomla;
- `build/` — validazione e build deterministico;
- `tests/` — smoke, contract e runtime test;
- `docs/` — contratti e architettura;
- `dist/` — output locale generato, non versionato.

Copyright (C) 2026 Luca De Caro. GNU GPL v2 o successiva.
