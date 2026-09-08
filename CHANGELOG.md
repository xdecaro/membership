# Changelog

## 1.1.0 - 2026-09-08

- Prima release package installabile e aggiornabile di Membership by xdecaro.
- Adozione opzionale di Core by xdecaro 1.1+ nella pagina Informazioni/Diagnostica tramite `AssetService` e `.xdecaro-scope`.
- Fallback automatico alla UI locale quando Core non è disponibile.
- Rilevamento di versione, API pubblica e UI condivisa di Core.
- Corretto l'identificatore tecnico di Competitions in `com_decarodcl`.
- Aggiunti file lingua amministratore it-IT, en-GB e fr-FR dichiarati dal manifest.
- Rimossi dal manifest i riferimenti a sorgenti frontend non ancora presenti, evitando package incompleti.
- Aggiunti build deterministico, validazione release, update server Joomla, checksum e GitHub Release workflow.
- Nessuna modifica allo schema dati o alla logica di soci, pratiche, rinnovi, tessere, pagamenti e trasferimenti.

## 1.0.0

- Baseline iniziale del componente e primo adapter opzionale verso il contratto pubblico Core.
