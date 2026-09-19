# Membership by xdecaro

Membership è il componente Joomla 6 per la gestione del dominio associativo: soci, categorie, pratiche, rinnovi, tessere, quote, pagamenti, trasferimenti, sedi, documenti, checklist, audit, report e integrazioni con l'ecosistema xdecaro.

## Identità

- Componente: `com_decaromembership`
- Pacchetto: `pkg_decaromembership`
- Versione corrente: **1.9.0**
- Joomla: `6.*`
- PHP: `8.3+`
- Core richiesto: **2.0.1+**
- People richiesto: **1.2.15+**
- Namespace: `Xdecaro\\Component\\Decaromembership`

## People

Da Membership 1.6.0 **People è l'anagrafica autorevole della persona**. Ogni nuovo socio deve essere collegato a una persona People tramite `person_uuid`; lo stesso UUID può appartenere a un solo socio Membership.

Membership mantiene il proprio `member_id` come chiave stabile per rinnovi, tessere, quote, pagamenti, trasferimenti, pratiche, documenti e audit. Nome, cognome, nascita, contatti e residenza vengono invece risolti tramite il servizio pubblico di People, senza query alle tabelle `#__xdecaropeople_*` e senza dipendenze da Model/Table private di People.

L'upgrade 1.4.0 → 1.6.0 è non distruttivo: i campi anagrafici legacy restano nel database e i soci esistenti possono rimanere temporaneamente non collegati. Il backfill automatico collega soltanto corrispondenze deterministiche basate su un `user_id` People univoco. Il normale salvataggio non può cambiare un collegamento People già esistente; la correzione passa da un'azione esplicita con ACL dedicata e audit UUID-only.

Le liste soci risolvono le identità People in batch per evitare N+1. Se People o una persona collegata non è temporaneamente disponibile, Membership mantiene accessibile il record associativo e mostra uno stato controllato invece di interrogare direttamente i dati People.

## Organizations opzionale

Da Membership 1.7.0 il collegamento a **Organizations by xdecaro** è facoltativo. La creazione base di un socio resta semplice: si seleziona la persona People e si compilano i dati associativi essenziali; il socio può essere salvato anche senza alcuna organizzazione.

Quando Organizations è installato e il suo provider pubblico è disponibile, Membership mostra un selettore opzionale e salva soltanto l'UUID stabile dell'organizzazione. Membership non legge le tabelle private di Organizations e non duplica nome, gerarchia, contatti o altri dati organizzativi.

Le vecchie `locations` Membership restano disponibili come sede interna standalone/legacy per compatibilità e storico. Non vengono eliminate né migrate automaticamente. Se un socio ha `organization_uuid`, Organizations è la fonte autorevole per la struttura; `location_id` resta un eventuale dato interno/storico.

Le variazioni del collegamento a Organizations sono tracciate nello storico Membership senza rendere Organizations una dipendenza obbligatoria.

## Selettore Organizations 1.8.0

Membership 1.8.0 mantiene Organizations opzionale ma migliora il selettore nella scheda socio. Le organizzazioni vengono ordinate secondo la gerarchia `parent_id` fornita dal provider pubblico Organizations, così una struttura come ENS → ENS Lazio → ENS Roma è leggibile senza appiattire tutte le voci.

Il campo include ricerca in tempo reale per nome, percorso gerarchico e tipo. I tipi `organization`, `association`, `club`, `federation`, `company`, `public_body`, `school`, `sponsor` e `supplier` vengono tradotti tramite il sistema lingua Joomla in IT/EN/FR. Membership continua a salvare soltanto `organization_uuid` e non replica la struttura Organizations.

## Ciclo base socio 1.9.0

Membership 1.9.0 completa la gestione dei dati essenziali del socio senza introdurre regole specifiche di una singola associazione.

La **categoria** diventa obbligatoria per il socio ed è configurabile dall'amministrazione. Le categorie hanno anche un `code` stabile opzionale, utile per integrazioni e regole future senza dipendere dal nome visualizzato. Il componente non crea categorie ENS o di altre organizzazioni in modo hardcoded: categorie come Effettivo, Sostenitore o Onorario vanno configurate nel contesto che le utilizza.

Lo **stato** del nuovo socio è obbligatorio e il valore predefinito è configurabile tra `pending`, `in_review` e `active`. Il valore prudenziale predefinito resta `pending`.

I record legacy con stato vuoto vengono normalizzati a **Pending** durante l'aggiornamento, senza inferire categorie o altri dati mancanti.

Il **numero socio** è manuale per impostazione predefinita, perché molte organizzazioni ricevono numeri ufficiali da sistemi esterni. Se l'amministratore abilita la numerazione automatica, Membership genera il numero dopo il primo salvataggio usando ID stabile, prefisso opzionale e padding configurabile, senza rinumerare i soci esistenti.

Le date del ciclo di vita vengono completate in modo conservativo: quando un socio entra per la prima volta in stato Ammesso/Attivo, Membership valorizza le date mancanti di ammissione, inizio periodo associativo e prima iscrizione; ogni variazione di stato può valorizzare la decorrenza; gli stati terminali Decaduto, Receduto, Espulso, Deceduto e Cessato valorizzano la data di cessazione se mancante. I valori inseriti esplicitamente dall'operatore restano prioritari.

## Xdecaro Core

Membership 1.9.0 richiede **Core by xdecaro 2.0.1+**. Core fornisce i contratti condivisi dell'ecosistema e rimane separato dalle regole Membership: non contiene soci, pratiche, rinnovi, tessere, quote, pagamenti o trasferimenti.

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

La CI verifica sintassi PHP, manifest/XML, build deterministica, confini tra componenti e runtime reale su Joomla 6.1.3. Per Membership 1.9.0 copre:

- installazione pulita con Core 2.0.1 e People 1.2.15 pubblicati e fissati per SHA-256;
- collegamento socio ↔ persona People e risoluzione batch dell'identità;
- creazione socio semplice senza Organizations e integrazione opzionale con Organizations 1.0.25 tramite provider pubblico;
- selettore Organizations gerarchico e ricercabile per nome, percorso e tipo, con traduzioni IT/EN/FR;
- categoria socio obbligatoria e configurabile, stato predefinito configurabile, numerazione manuale sicura o automatica opzionale;
- runtime del ciclo base con compilazione controllata delle date di ammissione, prima iscrizione, decorrenza e cessazione;
- upgrade non distruttivo 1.4.0 → 1.6.0 con conservazione di socio e record figli;
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


## Lifecycle associativo 1.6.0

Membership 1.6.0 separa esplicitamente identità People e ciclo di vita associativo. Aggiunge date domanda/ammissione/cessazione, diritti di elettorato espliciti, storico non distruttivo di stato/categoria/sede, data di efficacia dei trasferimenti e servizi pubblici di sola lettura per People. Le regole statutarie specifiche restano configurabili e non vengono hardcodate nel Core.


### Riammissione e anzianità

La riammissione è una pratica distinta (`case.type = readmission`) e non viene confusa con una nuova iscrizione. `first_registration_date` conserva la prima iscrizione conosciuta; `current_membership_start_date` indica l'inizio del periodo associativo attuale e `seniority_credit_days` conserva l'eventuale anzianità pregressa riconosciuta dalla policy dell'organizzazione. Il servizio di eleggibilità espone `seniority_days` senza conteggiare automaticamente i periodi di interruzione. La regola di riconoscimento dell'anzianità resta dell'organizzazione e non viene hardcodata nel Core.


## Asset amministrativi 1.6.1

Membership 1.6.1 corregge il caricamento dell'interfaccia amministrativa: CSS e JavaScript vengono registrati una sola volta tramite Joomla Web Asset Manager da un servizio condiviso tra Dashboard, liste, form e Informazioni. I template non inseriscono più tag `<link>` o `<script>` manuali duplicati. L'installer verifica inoltre che `/media/com_decaromembership/css/admin.css`, `core-bridge.css`, `admin.js` e `joomla.asset.json` siano realmente presenti dopo installazione/aggiornamento. La CI controlla sia installazione pulita sia upgrade su Joomla 6.1.3 e verifica fisicamente gli asset installati.


## Web Asset registry 1.6.2

Membership 1.6.2 corregge la causa individuata tramite Console Joomla: i file CSS/JS risultavano presenti e raggiungibili con HTTP 200, ma non venivano allegati al documento HTML. Il servizio amministrativo ora carica esplicitamente il registry `com_decaromembership` con `addExtensionRegistryFile()` e poi usa gli asset dichiarati in `joomla.asset.json` tramite `useStyle()` e `useScript()`. Questo evita registrazioni ad hoc con lo stesso nome degli asset del manifest e mantiene un unico contratto Web Asset Manager coerente con gli altri componenti xdecaro.


## Asset diretti 1.6.3

La Console del sito reale Joomla 6.1.3 ha confermato che `admin.css`, `core-bridge.css`, `admin.js` e `joomla.asset.json` rispondono HTTP 200 ma, anche con il registry Web Asset attivo, il CSS/JS Membership non viene inserito nel documento. Il test diretto con un foglio di stile applicato al browser ha invece trasformato immediatamente `.dm-grid-kpi` in griglia e ripristinato card, padding, bordi e background.

Membership 1.6.3 usa quindi `HtmlDocument::addStyleSheet()` e `HtmlDocument::addScript()` con URL del sito per garantire l'emissione degli asset amministrativi. Il Web Asset Manager resta utilizzato per le dipendenze Joomla e Core. È stato inoltre eliminato il testo spurio sopra la Dashboard.
