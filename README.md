# Membership by xdecaro

Membership è il componente Joomla 6 per la gestione centrale dell'identità associativa: soci, categorie, pratiche, rinnovi, tessere, quote, pagamenti, trasferimenti, sedi, relazioni familiari, documenti, checklist, audit, report e integrazioni con l'ecosistema xdecaro.

## Identità

- Componente: `com_decaromembership`
- Pacchetto: `pkg_decaromembership`
- Versione corrente: **1.2.0**
- Joomla: `6.*`
- Namespace: `Xdecaro\\Component\\Decaromembership`

## Xdecaro Core

Membership mantiene Core opzionale per la logica applicativa. Da 1.2.0 `CoreIntegrationService` usa il namespace canonico `xdecaro\\Core` e rende disponibili `EntityReference` / `RelationReference` soltanto con Core by xdecaro 1.3.0+.

La pagina **Informazioni/Diagnostica** usa inoltre, in modo opt-in, `xdecaro\\Core\\Asset\\AssetService` con Core 1.3.0+. Se Core manca, è troppo vecchio o gli asset non sono disponibili, Membership mantiene automaticamente il layout/CSS locale esistente.

Core non contiene soci, pratiche, rinnovi, tessere, quote, pagamenti, trasferimenti o altre regole Membership.

## Distribuzione

La release pubblica comprende:

- `com_decaromembership_<versione>.zip`;
- `pkg_decaromembership_<versione>.zip` — pacchetto consigliato per Joomla;
- `SHA256SUMS.txt`.

Il package registra l'update server Joomla `updates/pkg_decaromembership.xml`.

## Struttura repository

- `component/` — sorgenti installabili del componente;
- `package/` — manifest package;
- `updates/` — update server Joomla;
- `build/` — validazione e build deterministico;
- `tests/` — smoke test;
- `docs/` — contratti e architettura;
- `dist/` — output locale generato, non versionato.

Copyright (C) 2026 Luca De Caro. GNU GPL v2 o successiva.
