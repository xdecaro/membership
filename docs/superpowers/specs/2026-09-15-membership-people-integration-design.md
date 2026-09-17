# Membership 1.5.0 — integrazione con People

## Obiettivo

Membership deve smettere di essere la fonte autorevole dei dati anagrafici generali della persona. People diventa l'unica anagrafica centrale; Membership conserva esclusivamente il dominio associativo e collega ogni socio a una persona tramite UUID.

La regola approvata è **1 persona People = al massimo 1 socio Membership**. Lo stesso socio Membership resta stabile negli anni e accumula rinnovi, categorie, tessere, quote, pagamenti, trasferimenti, pratiche e storico senza creare nuovi record anagrafici.

Questa integrazione è prevista per Membership 1.5.0 e richiede Core 2.0.1+ e People 1.2.15+.

## Confini di responsabilità

### People possiede

People resta la fonte autorevole per identità e contatti generali, inclusi dove disponibili tramite API pubblica:

- UUID persona;
- nome e cognome;
- display name;
- data e luogo di nascita;
- sesso;
- nazionalità;
- identificativo fiscale/TIN;
- email, telefono, WhatsApp e contatto preferito;
- indirizzo principale e indirizzi aggiuntivi;
- utente Joomla collegato;
- stato della persona;
- dati di accessibilità/disabilità soggetti agli ACL People;
- riferimenti documentali di profilo;
- altri dati personali gestiti da People.

Membership non deve leggere direttamente `#__xdecaropeople_people`, né altre tabelle interne People.

### Membership possiede

Membership continua a essere la fonte autorevole per:

- identificativo locale `member_id`;
- numero socio;
- numero tessera Membership e relativo ciclo di vita;
- categoria associativa;
- sede/sezione Membership;
- stato associativo;
- prima iscrizione;
- rinnovi;
- quote e pagamenti;
- trasferimenti;
- pratiche;
- documenti richiesti dal workflow Membership;
- checklist;
- audit Membership;
- note esclusivamente associative;
- integrazioni Finance e altri processi di dominio.

Le tabelle figlie Membership continuano a riferirsi al `member_id` locale. `person_uuid` è il collegamento identitario, non sostituisce le chiavi interne di rinnovi, quote, pagamenti, pratiche e trasferimenti.

Il campo `photo` esistente resta Membership-owned in 1.5.0 soltanto per eventuale uso associativo/tessera. Non viene interpretato come foto profilo generale People e non viene sincronizzato automaticamente.

## Modello dati

A `#__decaromembership_members` viene aggiunta la colonna:

```sql
person_uuid CHAR(36) NULL
```

con indice univoco:

```sql
UNIQUE KEY uq_member_person_uuid (person_uuid)
```

L'UUID salvato deve essere quello restituito da People, normalizzato nella forma canonica minuscola a 36 caratteri con trattini. Membership non accetta UUID arbitrari digitati senza risoluzione People.

MySQL consente più valori `NULL`, quindi i record esistenti possono restare temporaneamente non collegati mentre ogni UUID valorizzato resta univoco.

Non viene introdotta una foreign key SQL verso People: i componenti devono restare aggiornabili senza vincoli DB incrociati e il collegamento viene validato attraverso l'API pubblica People.

La migrazione 1.5.0 è non distruttiva. I campi anagrafici legacy già presenti in `#__decaromembership_members` non vengono eliminati in questa release, per consentire upgrade sicuro e fallback dei record non ancora collegati.

Poiché oggi `first_name` e `last_name` sono `NOT NULL`, la migrazione 1.5.0 li rende nullable preservando tutti i valori esistenti. In questo modo un nuovo socio collegato a People può essere creato senza scrivere copie obbligatorie del nome nei campi legacy. Lo schema di installazione pulita 1.5.0 deve usare la stessa struttura.

## Politica dei campi legacy

Per un socio con `person_uuid` valorizzato:

- i dati personali mostrati all'utente provengono da People;
- Membership non riscrive copie di nome, cognome, nascita, indirizzo, email, telefono, TIN o user ID nei campi legacy;
- eventuali valori legacy restano congelati come dato storico di migrazione e non sono più fonte autorevole;
- rinominare o aggiornare una persona in People deve riflettersi in Membership senza modificare il record Membership.

Per un socio esistente senza `person_uuid`:

- Membership continua a mostrare i campi legacy necessari per non interrompere l'operatività;
- il record viene marcato nell'interfaccia come **Persona People non collegata**;
- è possibile modificare i dati strettamente associativi;
- i campi personali legacy non vengono estesi con nuova logica e l'obiettivo operativo è collegare esplicitamente il socio a People.

Per i nuovi soci creati dopo l'upgrade a 1.5.0, `person_uuid` è obbligatorio a livello applicativo.

## API e integrazione tra componenti

Membership usa esclusivamente il componente People avviato tramite l'API Joomla e il servizio pubblico `PersonProviderService`.

Non sono ammessi:

- query SQL dirette alle tabelle People;
- include di file interni People;
- dipendenze da model/table class private di People;
- duplicazione della logica anagrafica People dentro Membership.

Membership introduce un adapter locale, `PeopleIntegrationService`, che ha il compito di:

- verificare presenza/versione compatibile di Core e People;
- ottenere il `PersonProviderService` dal componente People;
- cercare persone per il selettore amministrativo;
- risolvere una persona per UUID;
- risolvere in batch gli UUID delle liste;
- trasformare errori People/ACL in messaggi Joomla controllati;
- fornire a Membership array/DTO limitati ai dati realmente necessari.

### Estensione API People richiesta

Per evitare query N+1 nelle liste Membership, People deve esporre un metodo pubblico batch per UUID:

```php
getPeopleByUuids(array $uuids, bool $sensitive = false): array
```

Il metodo deve:

- normalizzare e deduplicare gli UUID;
- applicare gli stessi ACL di `getPerson()`;
- eseguire una sola query per batch;
- restituire risultati associabili deterministicamente per UUID;
- non esporre campi sensibili quando `$sensitive === false`.

Questo contratto entra in People 1.2.15, che è quindi la versione minima richiesta da Membership 1.5.0. People 1.2.14 resta immutata come release già pubblicata.

Per il backfill tramite Joomla user ID si usa il provider pubblico People e si accetta una corrispondenza solo quando una ricerca limitata a due risultati restituisce esattamente una persona. Non si assume che il primo risultato sia automaticamente univoco.

## ACL e dati sensibili

Membership non bypassa gli ACL People.

Nelle liste Membership si usano solo dati People non sensibili, come UUID e display name, più gli eventuali contatti già ammessi dal provider standard.

Nella scheda socio, i dati sensibili vengono richiesti soltanto quando l'utente corrente è autorizzato da People (`people.view_sensitive` o privilegi equivalenti previsti da People). Se l'utente può gestire Membership ma non leggere dati sensibili People:

- la scheda socio resta utilizzabile;
- i dati sensibili non vengono mostrati;
- viene mostrato un messaggio coerente, non un fatal error;
- Membership non tenta letture SQL alternative.

## Creazione e collegamento di un socio

Il flusso amministrativo per un nuovo socio è:

1. l'operatore apre Nuovo socio;
2. cerca una persona People per nome/email tramite il provider pubblico;
3. seleziona la persona;
4. Membership valida che l'UUID esista e sia selezionabile;
5. Membership controlla che lo stesso UUID non sia già collegato ad altro socio;
6. vengono compilati soltanto i campi di dominio Membership;
7. il salvataggio crea il socio con `person_uuid` e conserva il normale `member_id` locale.

La UI mostra un riepilogo People in sola lettura e un collegamento per aprire la scheda persona in People.

## Immutabilità e correzione del collegamento

Dopo il primo collegamento, `person_uuid` non è un normale campo liberamente modificabile.

Per evitare che lo storico di quote, pagamenti, tessere e pratiche venga accidentalmente attribuito a una persona diversa:

- il selettore è modificabile solo quando `person_uuid` è vuoto;
- per correggere un collegamento errato viene prevista un'azione esplicita **Ricollega persona**;
- l'azione richiede autorizzazione amministrativa elevata su Membership;
- richiede conferma esplicita;
- applica gli stessi controlli di esistenza/unicità;
- registra nel log Membership UUID precedente e nuovo UUID senza copiare dati sensibili People nel log.

Non è previsto un semplice pulsante "Scollega" che lasci silenziosamente orfani i processi storici.

## Migrazione dei soci esistenti

L'upgrade non deve inventare corrispondenze.

Il backfill automatico collega un socio solo quando esiste una corrispondenza deterministica tramite `user_id` Joomla:

- `member.user_id > 0`;
- People restituisce esattamente una persona attiva con lo stesso `user_id`;
- quella persona non è già collegata a un altro socio.

Non si esegue matching automatico soltanto per nome, cognome, email, TIN/codice fiscale o data di nascita. Questi dati possono essere omonimi, obsoleti o incoerenti e non sono sufficienti per una migrazione automatica sicura.

Il backfill è idempotente: rieseguirlo non modifica collegamenti già corretti e non crea duplicati.

I casi non deterministici restano non collegati e vengono presentati in un filtro amministrativo dedicato per il collegamento manuale.

## Liste e ricerca Membership

La lista soci deve mostrare l'identità People aggiornata senza N+1:

1. Membership carica i record Membership della pagina corrente;
2. raccoglie tutti i `person_uuid` presenti;
3. li risolve in un'unica chiamata batch People;
4. usa `display_name` People per i soci collegati;
5. usa nome/cognome legacy soltanto per i soci ancora non collegati;
6. se una persona collegata non è più disponibile, mostra uno stato diagnostico chiaro senza perdere il record Membership.

L'ordinamento predefinito di Membership 1.5.0 non deve dipendere da `last_name` legacy per i soci collegati. Si usa un campo Membership stabile, preferibilmente `member_number` con fallback su `id`.

La ricerca per nome/email può usare `PersonProviderService::searchPeople()` per ottenere UUID People e filtrare poi i record Membership su `person_uuid`, aggiungendo il fallback legacy per i soci non ancora collegati. Nessun join SQL cross-component è ammesso.

Una vera paginazione/ordinamento alfabetico globale guidata da People, se richiesta oltre i limiti del provider corrente, richiederà un contratto pageable dedicato People e non viene simulata con ordinamenti parziali della sola pagina corrente.

## Persona archiviata, disabilitata o non disponibile

Un socio Membership non viene cancellato quando la persona People viene disabilitata, archiviata o cestinata.

- Per nuovi collegamenti si propongono solo persone attive/selezionabili.
- Un collegamento storico già esistente resta memorizzato tramite UUID.
- Se People non restituisce più la persona, Membership conserva tutti i dati associativi e mostra **Persona People non disponibile**.
- Nessun processo finanziario o storico viene cancellato o riassegnato automaticamente.

## Relazioni familiari

La tabella `#__decaromembership_relations` non viene migrata o riscritta in 1.5.0.

People possiede le relazioni generali tra persone; Membership può continuare ad avere relazioni che hanno significato strettamente associativo. La distinzione e l'eventuale migrazione delle relazioni familiari esistenti vengono trattate in un lavoro separato, per non mescolare questa integrazione identitaria con un refactor di dominio più ampio.

## Compatibilità con Finance e altri moduli Membership

Le integrazioni esistenti con Finance restano basate sul record Membership e sulle chiavi esterne già stabili. `person_uuid` non sostituisce retroattivamente `member_id` nelle quote o nei pagamenti.

Rinnovi, tessere, quote, pagamenti, trasferimenti, casi, documenti, notifiche e audit continuano a usare `member_id`.

Questo evita di cambiare contemporaneamente identità, contabilità e storico associativo.

## Dipendenze e comportamento in errore

Membership 1.5.0 richiede:

- Joomla 6;
- PHP 8.3+;
- Core by xdecaro 2.0.1+;
- People by xdecaro 1.2.15+.

### Installazione pulita e upgrade

Il preflight deve verificare Core e People **prima** di applicare modifiche schema Membership 1.5.0. Se una dipendenza manca o è troppo vecchia, installazione/upgrade si interrompono con un messaggio Joomla chiaro e senza migrazioni parziali.

Per aggiornare da Membership 1.4.0 l'amministratore installa quindi prima Core 2.0.1+ e People 1.2.15+, poi esegue l'upgrade Membership 1.5.0.

### Dipendenza rimossa dopo l'installazione

Se Core o People vengono successivamente disabilitati/rimossi, Membership deve evitare fatal error:

- i dati Membership restano intatti;
- la diagnostica indica la dipendenza mancante;
- creazione/collegamento/risoluzione People vengono disabilitati;
- le funzioni puramente associative che non richiedono una lettura People possono restare accessibili quando tecnicamente sicuro.

People è comunque una dipendenza runtime della linea Membership 1.5.x per la gestione ordinaria dell'identità.

## Audit

L'audit Membership registra:

- creazione socio con UUID People;
- primo collegamento di un socio legacy;
- ricollegamento amministrativo;
- backfill automatico effettuato.

Il log registra l'UUID e l'azione, ma non copia snapshot di campi sensibili People non necessari.

## Test richiesti

### Contract test

- Membership non contiene query o riferimenti diretti a `#__xdecaropeople_*`.
- Membership non dipende da classi private People.
- `person_uuid` è presente nello schema 1.5.0 con indice univoco.
- `first_name` e `last_name` legacy sono nullable nella linea 1.5.0.
- Core minimo è 2.0.1 e People minimo è 1.2.15.
- Il contratto batch People per UUID è disponibile.

### Unit/service test

- UUID People valido viene accettato.
- UUID inesistente viene rifiutato.
- secondo socio con stesso UUID viene rifiutato.
- nuovo socio senza UUID viene rifiutato.
- socio legacy senza UUID resta caricabile.
- ricollegamento non autorizzato viene rifiutato.
- backfill tramite user ID unico collega correttamente.
- due People con lo stesso user ID non vengono auto-collegate.
- backfill non risolto non crea collegamenti.
- backfill ripetuto è idempotente.
- assenza runtime di People genera diagnostica controllata e non fatal error.

### Runtime Joomla 6.1.3

Verificare almeno:

- installazione pulita Core 2.0.1 + People 1.2.15 + Membership 1.5.0;
- preflight che rifiuta People/Core incompatibili senza schema parziale;
- upgrade Membership 1.4.0 → 1.5.0 senza perdita di dati;
- creazione socio da persona People senza duplicare nome/cognome nei campi legacy;
- lista soci con risoluzione batch People;
- modifica nome in People riflessa in Membership senza riscrivere il socio;
- persona People non disponibile gestita senza fatal error;
- ACL sensibili rispettati;
- rinnovi, tessere, quote, pagamenti e trasferimenti ancora funzionanti;
- integrazione Finance 1.3.0 ancora valida;
- desktop/tablet/mobile e light/dark mode per il nuovo selettore/riepilogo People.

## Strategia di rilascio

Ordine obbligatorio:

1. pubblicare People 1.2.15 con il contratto batch pubblico richiesto;
2. verificare Core 2.0.1 + People 1.2.15 in Joomla 6.1.3;
3. implementare e verificare Membership 1.5.0;
4. mantenere la PR Membership in Draft finché runtime e migrazione 1.4.0 → 1.5.0 non sono verdi;
5. solo dopo approvazione esplicita: Ready, merge, release e aggiornamento feed Joomla.

## Fuori scope per 1.5.0

Non fanno parte di questa integrazione:

- eliminazione fisica immediata delle colonne anagrafiche legacy Membership;
- migrazione completa delle relazioni familiari Membership verso People;
- sostituzione di tutti i `member_id` interni con UUID People;
- join SQL cross-component;
- sincronizzazione bidirezionale dei dati personali;
- copia automatica di dati sensibili People nei log Membership;
- ordinamento alfabetico globale simulato usando solo la pagina Membership corrente;
- refactor non correlati di Finance, Forms, Documents o altri componenti.

Questi limiti mantengono la migrazione non distruttiva, verificabile e focalizzata sull'identità centrale.