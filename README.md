# Membership by xdecaro

Membership è il componente Joomla 6 per la gestione centrale dell'identità associativa: soci, categorie, pratiche, rinnovi, tessere, quote, pagamenti, trasferimenti, sedi, relazioni familiari, documenti, checklist, audit, report e integrazioni con l'ecosistema xdecaro.

## Identità

- Componente: `com_decaromembership`
- Pacchetto: `pkg_decaromembership`
- Versione iniziale: `1.0.0`
- Joomla: `6.*`
- Namespace: `Xdecaro\\Component\\Decaromembership`

## Principi

- Joomla MVC moderno e servizi namespaced.
- ACL e controlli lato server.
- CSRF/token Joomla sulle operazioni mutative.
- Query bindate e input filtrati.
- Lingue native Joomla: it-IT, en-GB, fr-FR.
- Integrazioni opzionali e senza dipendenze rigide.
- Aggiornamenti con SQL incrementali senza perdita dati.
- UI responsive, accessibile e compatibile con light/dark mode.

## Integrazioni previste

Forms, Documents, Courses, Competitions e futuri componenti xdecaro tramite relazioni universali componente/entità/ID/tipo relazione.

## Xdecaro Core

Membership integra in modo facoltativo il contratto pubblico di Xdecaro Core `1.0.0` tramite `CoreIntegrationService`, registrato nel contenitore Joomla. Il componente continua a funzionare senza Core; soltanto le funzioni che richiedono riferimenti tra prodotti restituiscono un errore controllato.

Core fornisce esclusivamente `EntityReference` e `RelationReference`. Soci, pratiche, rinnovi, tessere, quote, pagamenti e trasferimenti restano interamente di proprietà di Membership.

Vedi `docs/core-integration.md` per il contratto e i confini dell'integrazione.

## Struttura repository

- `component/` sorgenti installabili del componente.
- `package/` manifest del pacchetto.
- `updates/` update server Joomla.
- `tools/` strumenti di build.
- `releases/` ZIP generati dalle release.

Copyright (C) 2026 Luca De Caro. GNU GPL v2 o successiva.
