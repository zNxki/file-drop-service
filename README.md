# File Drop

**Carica un file, condividi un codice.**
File Drop è un mini servizio web in PHP: carichi un file, ottieni un codice breve come `8Kf2a` e chiunque può scaricarlo da `/d/8Kf2a`. Il file scompare da solo alla scadenza.

![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777bb4)
![SQLite](https://img.shields.io/badge/database-SQLite-003b57)
![License MIT](https://img.shields.io/badge/license-MIT-green)

## Funzionalità

- Upload con **barra di avanzamento**, velocità e tempo residuo (drag and drop incluso)
- **Codice univoco** per ogni file, senza caratteri ambigui (`0 O o 1 l I`)
- Download tramite codice: `/d/{codice}`
- **QR code** nella pagina di download, per passare il link al telefono con la fotocamera
- **Download singolo o a numero limitato** (`max_downloads`): all'ultimo download il file si elimina da solo
- **Anteprima inline** per immagini, PDF, video e testo, solo per i tipi MIME in whitelist
- **Limite di dimensione** configurabile
- **Scadenza automatica**: scelta dall'utente, con massimo configurabile
- **Password opzionale** (salvata con `password_hash`)
- **API REST** per upload, informazioni, download ed eliminazione
- Database **SQLite**, nessun server da installare
- Interfaccia responsive con tema chiaro e scuro automatico
- Nessun framework: solo PHP, PDO e `vlucas/phpdotenv`

## Requisiti

- PHP 8.2 o superiore con le estensioni `pdo_sqlite` e `fileinfo`
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
| `DB_PATH` | `storage/filedrop.sqlite` | Percorso del database |
| `STORAGE_PATH` | `storage` | Cartella dei file caricati |
| `CLEANUP_PROBABILITY` | `0.02` | Probabilità di pulizia a ogni upload |

> **Limiti di PHP.** `MAX_FILE_SIZE_MB` non basta: anche `upload_max_filesize` e `post_max_size` in `php.ini` devono essere almeno uguali. Un esempio è in `public/.user.ini`.

## API REST

Base URL: `http://localhost:8000`. Le risposte sono JSON.

| Metodo | Endpoint | Descrizione |
|---|---|---|
| `POST` | `/api/files` | Carica un file |
| `GET` | `/api/files/{code}` | Informazioni sul file |
| `GET` | `/api/files/{code}/download` | Scarica il file |
| `DELETE` | `/api/files/{code}` | Elimina il file |

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
| 413 | File sopra il limite |
| 422 | Parametri non validi (scadenza, password, `max_downloads`, file vuoto) |

## Struttura del progetto

```
bin/cleanup.php        pulizia dei file scaduti (cron)
public/                unica cartella esposta dal web server
  index.php            front controller
  assets/              CSS e JavaScript
    vendor/qrcode.js   qrcode-generator (MIT), aggiornabile con `npm run vendor`
src/
  App.php              bootstrap e rotte
  Controller/          ApiController, WebController
  Http/                Request, Response, Router
  Model/               StoredFile
  Repository/          FileRepository (SQLite)
  Service/             FileService, CodeGenerator
  Support/             Config, Database, Preview (whitelist MIME), View
views/                 template PHP
storage/               database e file caricati (non versionati)
```

## Scadenza e pulizia

Un file scaduto non è più scaricabile: la prima richiesta lo elimina e risponde `410`. Lo stesso vale per i file con i download esauriti, che di norma sono già stati eliminati dall'ultimo download; il cron raccoglie quelli rimasti in sospeso. In più, ogni upload ha una piccola probabilità (`CLEANUP_PROBABILITY`) di ripulire tutti i file scaduti. In produzione aggiungi un cron:

```cron
*/15 * * * * php /percorso/file-drop/bin/cleanup.php
```

## Deploy

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
- Non c'è rate limiting: se esponi il servizio su internet, aggiungilo a livello di web server o proxy.
- Metti il sito dietro HTTPS, altrimenti le password viaggiano in chiaro.

## Licenza

MIT, vedi [LICENSE](LICENSE).
