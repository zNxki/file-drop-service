# File Drop

**Carica un file, condividi un codice.**
File Drop è un mini servizio web in PHP: carichi un file, ottieni un codice breve come `8Kf2a` e chiunque può scaricarlo da `/d/8Kf2a`. Il file scompare da solo alla scadenza.

![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777bb4)
![PostgreSQL](https://img.shields.io/badge/database-PostgreSQL-336791)
![License MIT](https://img.shields.io/badge/license-MIT-green)

## Funzionalità

- Upload con **barra di avanzamento**, velocità e tempo residuo (drag and drop incluso)
- **Upload multiplo**: scegli o trascina più file, un solo codice, download come **.zip generato al volo** (nessun file temporaneo)
- **Codice univoco** per ogni file, senza caratteri ambigui (`0 O o 1 l I`)
- Download tramite codice: `/d/{codice}`
- **QR code** nella pagina di download, per passare il link al telefono con la fotocamera
- **Download singolo o a numero limitato** (`max_downloads`): all'ultimo download il file si elimina da solo
- **Anteprima inline** per immagini, PDF, video e testo, solo per i tipi MIME in whitelist
- **Limite di dimensione** configurabile
- **Scadenza automatica**: scelta dall'utente, con massimo configurabile
- **Password opzionale** (salvata con `password_hash`)
- **API REST** per upload, informazioni, download ed eliminazione
- Database **PostgreSQL**, già incluso nel `docker-compose.yml`
- **Rate limiting integrato** per IP (su PostgreSQL) e **quote per IP** su numero di file, spazio occupato e volume giornaliero
- **Antivirus opzionale** con ClamAV: i file vengono analizzati prima di essere salvati
- **Docker**: `Dockerfile` e `docker-compose.yml` per il deploy in un comando
- Interfaccia responsive con tema chiaro e scuro automatico
- Nessun framework: solo PHP, PDO e `vlucas/phpdotenv`
- **Navigazione senza ricaricare la pagina** (stile Next.js): link e form interni sostituiscono solo `<main>`, con prefetch all'hover/tocco/viewport, cache di 30 s, barra di avanzamento, pulsanti avanti/indietro con scroll ripristinato e fallback automatico alla navigazione normale

## Requisiti

- PHP 8.2 o superiore con le estensioni `pdo_pgsql` e `fileinfo`
- Un server PostgreSQL 13 o superiore
- [Composer](https://getcomposer.org/)

## Avvio rapido

```bash
git clone https://github.com/zNxki/file-drop-service file-drop
cd file-drop
composer install
cp .env.example .env
composer serve
```

Apri <http://localhost:8000>. Il comando `composer serve` avvia il server integrato di PHP con i limiti di upload già alzati.

## Configurazione

Tutte le impostazioni sono nel file `.env`.

| Variabile | Default | Descrizione |
|---|---|---|
| `APP_DEBUG` | `false` | Mostra i dettagli degli errori 500 |
| `APP_URL` | vuoto | URL pubblico senza slash finale. Se vuoto viene dedotto dalla richiesta |
| `MAX_FILE_SIZE_MB` | `100` | Dimensione massima del file |
| `DEFAULT_EXPIRY_HOURS` | `24` | Scadenza predefinita |
| `MAX_EXPIRY_HOURS` | `720` | Scadenza massima consentita |
| `CODE_LENGTH` | `5` | Lunghezza del codice |
| `DB_HOST` | `localhost` | Host di PostgreSQL |
| `DB_PORT` | `5432` | Porta di PostgreSQL |
| `DB_NAME` | `filedrop` | Nome del database |
| `DB_USER` | `filedrop` | Utente del database |
| `DB_PASSWORD` | — | Password del database (obbligatoria con Docker) |
| `STORAGE_PATH` | `storage` | Cartella dei file caricati |
| `CLEANUP_PROBABILITY` | `0.02` | Probabilità di pulizia a ogni upload |
| `MAX_FILES` | `20` | File accettati in un solo upload (massimo 100) |
| `RATE_LIMIT_ENABLED` | `true` | Attiva il rate limiting |
| `RATE_LIMIT_WINDOW` | `60` | Durata della finestra di conteggio, in secondi |
| `RATE_LIMIT_REQUESTS` | `120` | Richieste per finestra e per IP (pagine, informazioni, stato upload, admin) |
| `RATE_LIMIT_UPLOADS` | `20` | Nuovi upload per finestra e per IP |
| `RATE_LIMIT_DOWNLOADS` | `30` | Download (e tentativi di password) per finestra e per IP |
| `RATE_LIMIT_CHUNKS` | `600` | Blocchi dell'upload a blocchi per finestra e per IP |
| `QUOTA_IP_MAX_FILES` | `50` | Upload attivi per IP (`0` = nessun limite) |
| `QUOTA_IP_STORAGE_MB` | `1024` | Spazio occupato dai file attivi di un IP (`0` = nessun limite) |
| `QUOTA_IP_DAILY_MB` | `5120` | Volume caricato da un IP nelle ultime 24 ore (`0` = nessun limite) |
| `TRUSTED_PROXIES` | vuoto | IP o reti CIDR dei reverse proxy fidati, separati da virgola |
| `CLAMAV_ENABLED` | `false` | Analizza ogni file con ClamAV prima di salvarlo |
| `CLAMAV_HOST` | `127.0.0.1` | Host di `clamd` |
| `CLAMAV_PORT` | `3310` | Porta TCP di `clamd` |
| `CLAMAV_TIMEOUT` | `120` | Timeout in secondi per ogni analisi |
| `CLAMAV_ON_ERROR` | `reject` | Cosa fare se `clamd` non risponde: `reject` rifiuta l'upload, `allow` lo accetta |

> **Limiti di PHP.** `MAX_FILE_SIZE_MB` non basta: anche `upload_max_filesize` e `post_max_size` in `php.ini` devono essere almeno uguali. Un esempio è in `public/.user.ini`.
>
> Con più file conta anche `max_file_uploads` (default PHP: 20), che deve restare **sopra** `MAX_FILES`. Oltre quel limite PHP scarta i file in eccesso senza dare errori, quindi l'app accetta al massimo `max_file_uploads - 1` file: una richiesta troncata viene rifiutata invece di perdere file in silenzio.

## Amministrazione

Il pannello è su `/admin`. La password non si imposta nel `.env`: al primo avvio, aprendo `/admin`, compare la configurazione iniziale in cui scegli nome utente e **master password** dell'amministratore (almeno 10 caratteri). Finché non l'hai creata, chiunque raggiunga il sito può farlo: completa il passaggio subito dopo il deploy.

Il pannello ha una barra laterale con queste sezioni, visibili in base ai permessi dell'account:

| Sezione | Contenuto | Permesso |
|---|---|---|
| Panoramica | statistiche, spazio occupato, download | `stats.view` |
| File | elenco con ricerca e ordinamento, eliminazione | `files.view`, `files.delete` |
| Sicurezza | limiti raggiunti e IP bloccati | `security.view`, `security.manage` |
| Account | creazione e modifica degli account | `users.manage` |
| Impostazioni | cambio password e verifica in due passaggi | sempre disponibile |

### Account e permessi

Dalla sezione Account si creano altri utenti e si scelgono i permessi con delle caselle; si possono modificare in qualsiasi momento, cambiare la password, disattivare l'account o eliminarlo (le sessioni aperte si chiudono alla richiesta successiva). L'amministratore ha sempre tutti i permessi e non può essere modificato o eliminato dagli altri. Chi ha `users.manage` ma non è amministratore non può assegnare quel permesso né modificare altri account che lo hanno.

### Verifica in due passaggi (TOTP)

Ogni account attiva la propria verifica da Impostazioni → Verifica in due passaggi: compare il **QR code** e, sotto, il **token** da inserire a mano («Inserisci chiave»). Nell'app l'account si chiama `FILE DROP: nome-utente`. L'attivazione si conferma con un primo codice a 6 cifre; il QR viene generato nel browser e il secret non passa da servizi esterni. Per disattivarla servono password e un codice valido.

- Un codice già usato non viene accettato una seconda volta (anti-replay) e la tolleranza è di ±30 secondi.
- I codici errati contano come tentativi falliti: dopo 5 errori in 15 minuti l'IP viene bloccato, e dopo 20 errori complessivi da qualsiasi IP (tentativi distribuiti) il secondo passaggio si chiude per tutti fino allo scadere della finestra. Dopo 5 errori nella stessa sessione si devono reinserire le credenziali.
- Se un utente perde il dispositivo, chi ha `users.manage` può disattivargli la 2FA dalla pagina di modifica dell'account.
- Cambiare password o attivare/disattivare la 2FA chiude le altre sessioni dello stesso account.

### Blocco manuale degli IP

Dal pannello puoi bloccare un IP o un intervallo CIDR (IPv4 fino a `/16`, IPv6 fino a `/48`; indirizzi più larghi vengono rifiutati), con una durata da 1 ora a «per sempre» e un motivo facoltativo. Il blocco vale per l'intero sito e per le API e viene controllato **prima** del rate limiting; chi è bloccato riceve `403`. Non puoi bloccare un intervallo che comprende il tuo indirizzo. Dietro un proxy il blocco usa lo stesso IP del rate limiting, quindi imposta `TRUSTED_PROXIES`.

### Limiti raggiunti

Il pannello elenca i client che nelle ultime 24 ore hanno ricevuto un `429` per rate limit (`requests`, `uploads`, `downloads`, `chunks`) o quota (file attivi, spazio, volume giornaliero), con numero di rifiuti e ultimo evento, e un pulsante «Blocca 24 h». Il registro è aggregato per client, motivo e ora (poche righe anche sotto flood) e conserva l'IP **in chiaro** per 7 giorni nella tabella `limit_events`.

## Rate limiting e quote

Il rate limiting è integrato e usa lo stesso database PostgreSQL: nessun servizio aggiuntivo. Il conteggio è per IP con una finestra fissa (`RATE_LIMIT_WINDOW`) e ogni richiesta rientra in un solo gruppo:

| Gruppo | Richieste | Variabile |
|---|---|---|
| `uploads` | `POST /api/files`, `POST /api/uploads` | `RATE_LIMIT_UPLOADS` |
| `chunks` | `PUT /api/uploads/{id}` | `RATE_LIMIT_CHUNKS` |
| `downloads` | `/d/{codice}/download`, `/api/files/{code}/download` | `RATE_LIMIT_DOWNLOADS` |
| `requests` | tutto il resto | `RATE_LIMIT_REQUESTS` |

Superato il limite la risposta è `429` con l'header `Retry-After`. Un valore `0` disattiva il singolo gruppo.

- Gli indirizzi IPv6 sono raggruppati per prefisso `/64`.
- Il gruppo `downloads` limita anche i tentativi di indovinare la password di un file.
- Con una finestra fissa, a cavallo tra due finestre un client può arrivare fino al doppio del limite.
- Se il database non è raggiungibile il rate limiting si disattiva per quella richiesta (fail-open) e l'errore finisce nel log.
- Gli asset statici serviti direttamente dal web server non passano da PHP e non vengono contati.

Le **quote per IP** si applicano ai nuovi upload: numero di upload attivi (`QUOTA_IP_MAX_FILES`; un upload multiplo conta come uno), spazio occupato dai file attivi (`QUOTA_IP_STORAGE_MB`) e volume caricato nelle ultime 24 ore, anche se i file sono già stati eliminati (`QUOTA_IP_DAILY_MB`). Superata una quota la risposta è `429`. Con l'upload a blocchi il controllo avviene all'avvio (sulla dimensione dichiarata) e di nuovo al completamento. I file caricati prima dell'aggiornamento non contano nelle quote.

Per le quote l'IP viene salvato come hash SHA-256 (colonna `uploader` e tabella `upload_log`, tenuta 48 ore); il rate limiting conserva l'IP in chiaro solo per pochi minuti nella tabella `rate_limits`.

Dietro un reverse proxy imposta `TRUSTED_PROXIES`, altrimenti tutti i client risultano avere l'IP del proxy e condividono gli stessi limiti. Per le richieste che arrivano da un proxy fidato l'IP del client viene letto da `X-Forwarded-For` (primo indirizzo da destra che non appartiene a un proxy fidato):

```env
TRUSTED_PROXIES=127.0.0.1,172.16.0.0/12
```

## Antivirus (ClamAV)

Con `CLAMAV_ENABLED=true` ogni file viene inviato a `clamd` (protocollo `INSTREAM`, via TCP) **prima** di essere salvato.

- File infetto: `422` con il nome della firma rilevata. In un upload multiplo basta un file infetto per rifiutare tutto; nell'upload a blocchi i blocchi già ricevuti vengono eliminati.
- `clamd` non raggiungibile, in timeout o con una risposta inattesa: `503` se `CLAMAV_ON_ERROR=reject` (predefinito), upload accettato se `allow`. In entrambi i casi l'errore finisce nel log.
- Un file più grande di `StreamMaxLength` di `clamd` viene trattato come errore del controllo: allinea quel valore a `MAX_FILE_SIZE_MB` (il file `docker/clamd.conf` lo porta a 4000 MB).
- **I file cifrati non vengono analizzati**: con la cifratura attiva il server riceve solo dati cifrati e non può controllarne il contenuto.
- L'analisi è sincrona: con l'upload a blocchi avviene alla chiamata `complete`, che può quindi richiedere qualche secondo in più.

## API REST

Base URL: `http://localhost:8000`. Le risposte sono JSON.

| Metodo | Endpoint | Descrizione |
|---|---|---|
| `POST` | `/api/files` | Carica un file |
| `GET` | `/api/files/{code}` | Informazioni sul file |
| `GET` | `/api/files/{code}/download` | Scarica il file |
| `DELETE` | `/api/files/{code}` | Elimina il file |
| `POST` | `/api/uploads` | Avvia un upload a blocchi (JSON: `files: [{name, size}]`, `encrypted`) |
| `GET` | `/api/uploads/{id}` | Stato dell'upload: byte ricevuti per ogni file |
| `PUT` | `/api/uploads/{id}?index=0&offset=N` | Invia un blocco (corpo binario) |
| `POST` | `/api/uploads/{id}/complete` | Completa l'upload (`expires_in`, `password`, `max_downloads`) |
| `DELETE` | `/api/uploads/{id}` | Annulla l'upload e scarta i blocchi |

### Upload

Campi `multipart/form-data`: `file` (obbligatorio), `expires_in` in secondi (facoltativo), `password` (facoltativa, da 4 a 200 caratteri), `max_downloads` (facoltativo, da 1 a 1000; assente o vuoto = illimitati).

```bash
curl -F file=@foto.jpg -F expires_in=3600 -F password=segreto -F max_downloads=1 http://localhost:8000/api/files
```

```json
{
  "data": {
    "code": "8Kf2a",
    "name": "foto.jpg",
    "size": 482113,
    "mime_type": "image/jpeg",
    "protected": true,
    "downloads": 0,
    "max_downloads": 1,
    "remaining_downloads": 1,
    "created_at": "2026-10-08T12:36:50+00:00",
    "expires_at": "2026-10-08T13:36:50+00:00",
    "url": "http://localhost:8000/d/8Kf2a",
    "download_url": "http://localhost:8000/api/files/8Kf2a/download",
    "preview_url": null,
    "delete_token": "e5b62173473e94db183614fd10e24059"
  }
}
```

Conserva `delete_token`: è l'unico modo per eliminare il file prima della scadenza e non viene più mostrato.

### Upload a blocchi con ripresa

L'interfaccia web invia i file a blocchi da 8 MB, quindi `post_max_size` e `upload_max_filesize` non limitano più la dimensione del file: conta solo `MAX_FILE_SIZE_MB`. Se la connessione cade, i blocchi già ricevuti restano sul server per 24 ore e, riselezionando lo stesso file, il caricamento riparte dal primo byte mancante. I caricamenti abbandonati vengono rimossi da `bin/cleanup.php`. Con la cifratura attiva la chiave cambia a ogni tentativo, quindi la ripresa dopo il ricaricamento della pagina non è possibile (i tentativi automatici durante la stessa sessione funzionano).

```bash
curl -s -X POST http://localhost:8000/api/uploads -d '{"files":[{"name":"grande.iso","size":3221225472}]}'
curl -X PUT --data-binary @blocco0 "http://localhost:8000/api/uploads/ID?index=0&offset=0"
curl -X POST -F expires_in=3600 http://localhost:8000/api/uploads/ID/complete
```

I blocchi vanno inviati in ordine; un `offset` diverso dai byte già ricevuti risponde `409`. La risposta di `complete` è quella di un upload normale.

### Upload multiplo e ZIP

Ripeti il campo `file[]` (con `file` PHP tiene solo l'ultimo):

```bash
curl -F 'file[]=@foto.jpg' -F 'file[]=@nota.txt' -F expires_in=3600 http://localhost:8000/api/files
```

La risposta è quella di un upload normale, con in più `item_count` e l'elenco `files` (`name`, `size`, `mime_type`); `name` è il nome dello zip (`file-drop-{codice}.zip`). Un solo file con `file` o `file[]` resta un upload singolo, senza zip.

- **Un codice, un archivio.** Il download (`/d/{codice}/download` o `/api/files/{code}/download`) restituisce un **.zip** con tutti i file. Conta come **un solo download** e rispetta password e `max_downloads` come un file singolo.
- **Generato al volo.** Lo zip usa il metodo *stored* (nessuna compressione) e il CRC32 di ogni file viene calcolato all'upload: gli header sono già completi, il server non crea file temporanei né tiene i file in memoria, e invia un `Content-Length` esatto (il browser mostra l'avanzamento).
- **Nomi.** I nomi doppi diventano `foto.jpg`, `foto (2).jpg` (anche se differiscono solo per le maiuscole). Niente cartelle: i percorsi vengono ridotti al solo nome.
- **Limiti.** `MAX_FILE_SIZE_MB` vale per il **totale** dei file; al massimo `MAX_FILES` file; il totale non può superare circa 4 GB (lo zip non usa ZIP64). Scadenza, pulizia (`cleanup`) ed eliminazione rimuovono insieme tutti i file dell'upload.
- **Niente anteprima** per gli upload multipli.
- **Cifratura.** Il server non vede nomi né contenuti, quindi non può creare lo zip: con la cifratura attiva è il **browser** a creare lo zip e poi a cifrarlo, e sul server arriva un unico file cifrato. Chi scarica ottiene `archivio.zip` dopo la decifratura. Nell'API un upload con `encrypted=1` e più file viene rifiutato (`422`).
- Un download interrotto a metà resta contato, come per i file singoli.

### Informazioni, download, eliminazione

```bash
curl http://localhost:8000/api/files/8Kf2a
curl -OJ -H "X-File-Password: segreto" http://localhost:8000/api/files/8Kf2a/download
curl -X DELETE -H "X-Delete-Token: e5b6..." http://localhost:8000/api/files/8Kf2a
```

La password si passa nell'header `X-File-Password`, mai nell'URL.

### Download singolo o limitato

Con `max_downloads=1` il link è usa e getta; con `max_downloads=N` vale per N download. Regole:

- Il download viene **contato appena parte**, in modo atomico: richieste simultanee non possono superare il limite. Un download interrotto a metà resta contato, altrimenti basterebbe interromperlo per leggere il file più volte.
- All'ultimo download il file e il record vengono **eliminati subito**. Da quel momento il codice risponde `404` (o `410` se la richiesta arriva mentre l'ultimo trasferimento è ancora in corso).
- Visitare la pagina `/d/{codice}` **non** consuma download: conta solo la richiesta a `/download`. Attenzione però a chi apre direttamente quell'URL (antivirus, bot di anteprima, "scarica con..."): consuma il download.
- Le password sbagliate non consumano download.
- Per i download non sono accettati header `Range`: una ripresa a pezzi conterebbe più volte.
- Il campo `remaining_downloads` dell'API dice quanti ne restano (`null` se illimitati).

### Anteprima inline

La pagina `/d/{codice}` mostra l'anteprima di immagini, video e file di testo; per i PDF c'è il pulsante «Apri anteprima PDF». Il contenuto è servito da `/d/{codice}/preview` con `Content-Disposition: inline`, **solo** se il MIME rilevato dal contenuto (`finfo`, non dal nome o dall'header del client) è in questa whitelist, con confronto esatto:

| Tipo | MIME |
|---|---|
| Immagini | `image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/avif` |
| PDF | `application/pdf` |
| Video | `video/mp4`, `video/webm`, `video/ogg` |
| Testo | `text/plain`, `text/csv`, `text/markdown`, `application/json` (serviti sempre come `text/plain`) |

Tutto il resto resta solo scaricabile. Restano **fuori di proposito** `image/svg+xml` e `text/html`, perché eseguirebbero script sul dominio del servizio. Le risposte inline hanno `nosniff` e `Content-Security-Policy: default-src 'none'; sandbox` (tranne i PDF, che non funzionano in un documento sandboxed), supportano `Range` per far funzionare i video in Safari e non contano come download.

L'anteprima è **disattivata** per i file protetti da password (un `<img>` non può inviare la password) e per quelli con `max_downloads` (mostrare il contenuto equivale a scaricarlo). In entrambi i casi `preview_url` è `null` e `/preview` risponde `404`. Il testo mostra al massimo i primi 64 KB.

### Errori

```json
{ "error": { "status": 404, "message": "File non trovato. Controlla il codice." } }
```

| Status | Significato |
|---|---|
| 400 | Richiesta non valida o file mancante |
| 401 / 403 | Password richiesta / errata, oppure token di eliminazione non valido |
| 404 | Codice inesistente |
| 410 | File scaduto o con i download esauriti |
| 413 | File (o totale dei file) sopra il limite |
| 422 | Parametri non validi (scadenza, password, `max_downloads`, file vuoto, troppi file, cifrato con più file) oppure file infetto |
| 429 | Troppe richieste (header `Retry-After`) o quota per IP esaurita |
| 503 | Controllo antivirus non disponibile (con `CLAMAV_ON_ERROR=reject`) |

## Struttura del progetto

```
Dockerfile             immagine PHP + Apache
docker-compose.yml     app e, in opzione, ClamAV
docker/                configurazione di Apache, PHP, ClamAV ed entrypoint
bin/cleanup.php        pulizia dei file scaduti (cron)
bin/migrate-sqlite.php importa i dati di una vecchia installazione SQLite
public/                unica cartella esposta dal web server
  index.php            front controller
  assets/              CSS e JavaScript (zip.js: zip nel browser per i file cifrati)
    vendor/qrcode.js   qrcode-generator (MIT), aggiornabile con `npm run vendor`
src/
  App.php              bootstrap e rotte
  Controller/          ApiController, WebController, AuthController, AdminController, UserController, SettingsController (PanelController è la base)
  Http/                Request, Response, Router
  Model/               StoredFile, StoredItem, User
  Repository/          FileRepository, QuotaRepository, IpBlockRepository, LimitEventRepository, UserRepository (PostgreSQL)
  Service/             FileService, CodeGenerator, QuotaService, VirusScanner (ClamAV)
  Support/             Config, Database, Preview (whitelist MIME), RateLimiter, IpRange, Totp, Permissions, AdminSession, View, ZipStream (zip al volo)
views/                 template PHP
storage/               database e file caricati (non versionati)
```

## Scadenza e pulizia

Un file scaduto non è più scaricabile: la prima richiesta lo elimina e risponde `410`. Lo stesso vale per i file con i download esauriti, che di norma sono già stati eliminati dall'ultimo download; il cron raccoglie quelli rimasti in sospeso. In più, ogni upload ha una piccola probabilità (`CLEANUP_PROBABILITY`) di ripulire tutti i file scaduti. In produzione aggiungi un cron:

```cron
*/15 * * * * php /percorso/file-drop/bin/cleanup.php
```

## Deploy

### Docker

```bash
docker compose up -d --build
```

Il servizio è su <http://localhost:8080> (porta con `APP_PORT`). L'immagine usa PHP 8.3 con Apache, i dati stanno nel volume `filedrop-data` e `bin/cleanup.php` viene eseguito in automatico ogni 15 minuti (`CLEANUP_INTERVAL`, in secondi). La configurazione si legge da `.env` se esiste (`cp .env.example .env`); `DB_HOST`, `STORAGE_PATH` e `CLAMAV_HOST` sono fissati da `docker-compose.yml`, mentre `DB_PASSWORD` è obbligatoria e va impostata nel `.env`. Il database sta nel servizio `db` (PostgreSQL 16) con il volume `filedrop-db`; per salvarlo usa `docker compose exec db pg_dump -U filedrop filedrop > backup.sql`.

Per attivare ClamAV imposta nel `.env`:

```env
COMPOSE_PROFILES=clamav
CLAMAV_ENABLED=true
```

e riavvia con `docker compose up -d`. Il container `clamav` scarica le firme al primo avvio (qualche minuto, durante il quale gli upload vengono rifiutati con `503`) e richiede circa 2 GB di RAM.

Per portare i dati di una vecchia installazione SQLite (file, elementi degli zip e registro dei download) in PostgreSQL, esegui una sola volta:

```bash
docker compose cp filedrop.sqlite app:/tmp/filedrop.sqlite
docker compose exec app php bin/migrate-sqlite.php /tmp/filedrop.sqlite
```

Gli account non si migrano: la master password si sceglie al primo accesso.

Dietro un reverse proxy aggiungi la rete del proxy a `TRUSTED_PROXIES`.

### Server tradizionale

Il document root deve essere `public/`. Esempio per Nginx:

```nginx
root /percorso/file-drop/public;
client_max_body_size 101M;

location / { try_files $uri /index.php?$query_string; }
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
}
```

Con Apache basta puntare il VirtualHost su `public/`: c'è già un `.htaccess`.

## Sicurezza

- I file sono salvati fuori da `public/` con nomi casuali e serviti come allegato, con `X-Content-Type-Options: nosniff`. Fanno eccezione le anteprime, solo per i MIME in whitelist (vedi sopra).
- Le password e i token di eliminazione sono salvati solo come hash.
- Il codice usa `random_int()`, quindi non è prevedibile.
- Rate limiting e quote per IP sono integrati (vedi sopra). Se il servizio sta dietro un proxy imposta `TRUSTED_PROXIES`; per una protezione dai flood più robusta puoi comunque affiancare un limite anche sul proxy.
- Metti il sito dietro HTTPS, altrimenti le password viaggiano in chiaro.
- Header di sicurezza su **ogni** risposta (pagine, API, download, anteprime): `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`, `Permissions-Policy` (camera, microfono, geolocalizzazione, pagamenti, USB… disattivati), `Cross-Origin-Opener-Policy` e `Cross-Origin-Resource-Policy: same-origin`, `X-Frame-Options: DENY`. Le pagine HTML hanno una `Content-Security-Policy` restrittiva (`default-src 'self'`, `object-src 'none'`, `frame-ancestors 'none'`); JSON, download e redirect ricevono `default-src 'none'`; le anteprime non-PDF restano in `sandbox`.
- `Strict-Transport-Security` viene inviato quando la richiesta è HTTPS o `APP_URL` inizia con `https://` (utile se il TLS termina sul proxy). Non include `includeSubDomains` né `preload`: aggiungili sul proxy solo se sei sicuro.
- Gli asset statici li serve Apache senza passare da PHP: `public/.htaccess` aggiunge gli header di base (serve `mod_headers`, già attivo nell'immagine Docker). Con Nginx aggiungi nel `server {}`:

  ```nginx
  add_header X-Content-Type-Options "nosniff" always;
  add_header Referrer-Policy "no-referrer" always;
  add_header Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=()" always;
  add_header Cross-Origin-Resource-Policy "same-origin" always;
  ```
- Apache non espone versione e firma (`ServerTokens Prod`, `ServerSignature Off`).

## Licenza

MIT, vedi [LICENSE](LICENSE).


## Navigazione client

`public/assets/js/router.js` intercetta click sui link e invio dei form interni e chiede la pagina con l'header `X-Fd-Nav: 1`: in quel caso `View` restituisce solo il contenuto di `<main>` più un `<div id="fd-meta">` con titolo, layout largo e script necessari (`src/Support/View.php`). Senza JavaScript il sito funziona come prima.

- Ogni script che dipende dal DOM della pagina si registra con `FileDrop.page(() => { ...; return cleanup; })`: il router lo rilancia a ogni cambio pagina e chiama la cleanup quando si esce.
- `FileDrop.guard(() => messaggio | null)` chiede conferma prima di lasciare la pagina (usato durante un upload).
- Per escludere un link o un form dal router: `data-no-router`. Sono già esclusi `/api/*`, `/assets/*`, `/d/{codice}/download` e `/d/{codice}/preview`.
- Le pagine `/admin*` non vengono mai messe in cache né prefetchate (dipendono dalla sessione e consumano i messaggi flash).
- Gli asset sono serviti con `?v=<mtime>` (`View::asset()`), così dopo un deploy il browser non mescola JS vecchio e HTML nuovo.
- Gli `<script>` dentro le view non vengono eseguiti dalla navigazione client: carica gli script da JS con `FileDrop.load(src)`.
