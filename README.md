# Membership by xdecaro

Membership è il componente Joomla 6 per la gestione del dominio associativo: soci, categorie, pratiche, rinnovi, tessere, quote, pagamenti, trasferimenti, sedi, documenti, checklist, audit, report e integrazioni con l'ecosistema xdecaro.

## Identità

- Componente: `com_decaromembership`
- Pacchetto: `pkg_decaromembership`
- Versione corrente: **1.6.0**
- Joomla: `6.*`
- PHP: `8.3+`
- Core richiesto: **2.0.1+**
- People richiesto: **1.2.15+**
- Namespace: `Xdecaro\\Component\\Decaromembership`

## People

Da Membership 1.5.0 **People è l'anagrafica autorevole della persona**. Ogni nuovo socio deve essere collegato a una persona People tramite `person_uuid`; lo stesso UUID può appartenere a un solo socio Membership.

Membership mantiene il proprio `member_id` come chiave stabile per rinnovi, tessere, quote, pagamenti, trasferimenti, pratiche, documenti e audit. Nome, cognome, nascita, contatti e residenza vengono invece risolti tramite il servizio pubblico di People, senza query alle tabelle `#__xdecaropeople_*` e senza dipendenze da Model/Table private di People.

L'upgrade 1.4.0 → 1.5.0 è non distruttivo: i campi anagrafici legacy restano nel database e i soci esistenti possono rimanere temporaneamente non collegati. Il backfill automatico collega soltanto corrispondenze deterministiche basate su un `user_id` People univoco. Il normale salvataggio non può cambiare un collegamento People già esistente; la correzione passa da un'azione esplicita con ACL dedicata e audit UUID-only.

Le liste soci risolvono le identità People in batch per evitare N+1. Se People o una persona collegata non è temporaneamente disponibile, Membership mantiene accessibile il record associativo e mostra uno stato controllato invece di interrogare direttamente i dati People.

## Ciclo associativo e diritti

Membership 1.6.0 distingue l'identità personale dal ciclo associativo. La scheda socio mantiene stato, decorrenza del periodo attuale, eventuale cessazione/motivo, categoria, sede, anzianità riconosciuta e stato dei diritti. Le modifiche rilevanti di stato, categoria e sede vengono registrate in uno storico dedicato senza sovrascrivere il significato dei dati precedenti.

Le regole di elettorato attivo/passivo sono centralizzate nel servizio pubblico `MembershipEligibilityService`. I requisiti minimi di anzianità e l'eventuale obbligo di quota in regola sono configurabili: non vengono hardcodate regole specifiche ENS o di un'altra associazione. Sono disponibili override espliciti e tracciabili sul singolo socio.

La riammissione è una pratica distinta dalla nuova iscrizione. L'anzianità precedente può essere rappresentata con il credito di anzianità senza alterare la data anagrafica della persona.

## People → Membership history

Membership espone la capability pubblica `membership.people_history` v1 e il servizio `MembershipPersonHistoryService`. Dato un `person_uuid` People, il servizio restituisce soltanto dati del dominio associativo: posizione corrente, categoria/sede, stato, diritti, storico e trasferimenti. Non restituisce né duplica dati personali People.

Questo contratto consente a People di mostrare una futura tab Membership in sola lettura senza accedere alle tabelle private `#__decaromembership_*`.

## Xdecaro Core

Membership 1.6.0 richiede **Core by xdecaro 2.0.1+**. Core fornisce i contratti condivisi dell'ecosistema e rimane separato dalle regole Membership: non contiene soci, pratiche, rinnovi, tessere, quote, pagamenti o trasferimenti.

La pagina **Informazioni/Diagnostica** mostra le versioni installate e minime richieste di Core e People e lo stato di compatibilità/disponibilità delle relative API.

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

Il package registra l'update server Joomla `updates/pkg_decaromembership.xml`. Il feed pubblico resta sulla release stabile precedente finché una nuova release non viene approvata e pubblicata.

## Test di integrazione

La CI verifica sintassi PHP, manifest/XML, build deterministica, confini tra componenti e runtime reale su Joomla 6.1.3. Per Membership 1.6.0 copre:

- installazione pulita con Core 2.0.1 e People 1.2.15 pubblicati e fissati per SHA-256;
- collegamento socio ↔ persona People e risoluzione batch dell'identità;
- upgrade non distruttivo 1.4.0 → 1.5.0 con conservazione di socio e record figli;
- backfill deterministico e idempotente tramite `user_id` univoco;
- rifiuto preflight quando mancano Core o People richiesti;
- regressioni Finance 1.3.0: aggiornamento quota/pagamento prima dell'allocazione, replay idempotente e rifiuto delle modifiche successive all'allocazione.

## Struttura repository

- `component/` — sorgenti installabili del componente;
- `package/` — manifest package e installer;
- `updates/` — update server Joomla;
- `build/` — validazione e build deterministico;
- `tests/` — smoke, contract e runtime test;
- `docs/` — contratti, specifiche e piani;
- `dist/` — output locale generato, non versionato.

Copyright (C) 2026 Luca De Caro. GNU GPL v2 o successiva.
