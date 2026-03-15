# 📋 PDP — Piano Didattico Personalizzato

![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue?logo=php)
![License](https://img.shields.io/badge/Licenza-MIT-green)
![Version](https://img.shields.io/badge/Versione-1.0.0-orange)
![GitHub stars](https://img.shields.io/github/stars/sebastianobasile/pdp)
![GitHub forks](https://img.shields.io/github/forks/sebastianobasile/pdp)

**PDP** è uno strumento web leggero per la gestione digitale dei **Piani Didattici Personalizzati (PDP)** nelle scuole italiane. Permette ai docenti di redigere e aggiornare i contenuti disciplinari e agli alunni (o alle famiglie) di consultare e stampare il documento in formato ufficiale, direttamente dal browser.

Sviluppato da **Sebastiano Basile** per il [3° I.C. Capuana-de Amicis](https://www.3iccapuana.edu.it), Avola (SR).
[superscuola.com](https://www.superscuola.com)

---

[![Contributo volontario](https://img.shields.io/badge/Offrimi_un_caffè_☕-PayPal-blue)](https://paypal.me/superscuola)

Se questo strumento ti è utile, puoi supportarne lo sviluppo con un piccolo contributo volontario. Grazie! ☕

---

## ✨ Funzionalità principali

- 🔐 **Accesso per alunno** tramite password individuale con data di scadenza
- 📝 **Compilazione per discipline** con supporto alla formattazione in **grassetto** (`**testo**`)
- 💾 **Salvataggio revisioni** con backup automatico ad ogni modifica
- 🖨️ **Stampa / Esportazione PDF** ottimizzata con layout ufficiale
- ✍️ **Gestione firme** in calce al documento (autografa, digitale, sostitutiva ai sensi del D.Lgs. 39/93)
- 🛡️ **Pannello amministratore** per la gestione degli alunni e delle impostazioni globali
- 🔄 **Ripristino backup** con storico completo delle revisioni
- 📱 Interfaccia responsiva, funzionante su desktop e dispositivi mobili

---

## 📁 Struttura del progetto

```
titanium-pdp/
├── index.php              # Portale alunno (accesso e compilazione PDP)
├── admin.php              # Pannello amministratore
├── database/
│   ├── config.json        # Configurazione generale + elenco alunni (auto-generato)
│   ├── backups/           # Backup automatici dei documenti
│   └── [cognome_nome].json  # File dati di ogni alunno (auto-generati)
├── LICENSE
└── README.md
```

> ⚠️ I file nella cartella `database/` **non sono inclusi nel repository** perché contengono dati personali degli alunni. Vengono creati automaticamente al primo avvio.

---

## 🚀 Installazione

### Requisiti

| Componente | Versione minima |
|---|---|
| PHP | 7.4 o superiore (consigliato 8.x) |
| Web Server | Apache, Nginx, LiteSpeed o equivalente |
| Estensioni PHP | `json` (inclusa di default in PHP 7.4+) |

> Non è richiesto alcun database (MySQL, ecc.). Tutto viene salvato in file JSON.

---

### Installazione su server condiviso (hosting)

1. **Scarica** l'ultima versione dal pulsante verde **"Code → Download ZIP"** su GitHub, oppure clona il repository:
   ```bash
   git clone https://github.com/TUO-USERNAME/titanium-pdp.git
   ```

2. **Carica i file** sul tuo server tramite FTP/SFTP nella cartella desiderata (es. `public_html/pdp/`):
   ```
   index.php
   admin.php
   database/          ← carica anche questa cartella (deve essere scrivibile)
   database/backups/  ← e questa sottocartella
   ```

3. **Imposta i permessi** sulla cartella `database/` in modo che PHP possa scriverci:
   ```
   database/          → 755 o 775
   database/backups/  → 755 o 775
   ```
   Con FTP (es. FileZilla): clic destro sulla cartella → *Attributi/Permessi* → inserisci `755`.

4. **Apri il browser** e visita `https://tuosito.it/pdp/index.php`.
   Il file `database/config.json` viene creato automaticamente al primo accesso.

5. **Accedi al pannello admin** (vedi sezione dedicata qui sotto) e cambia subito la password di amministrazione.

---

### Installazione locale (per test)

Con **XAMPP**, **MAMP** o **Laragon**:

1. Copia la cartella del progetto in `htdocs/` (XAMPP) o `www/` (MAMP/Laragon).
2. Avvia il server locale.
3. Apri `http://localhost/titanium-pdp/index.php`.

---

## ⚙️ Configurazione iniziale

### 1. Accedere al pannello amministratore

Il pannello admin è raggiungibile in due modi:

- Navigando direttamente su: `https://tuosito.it/pdp/admin.php`
- Oppure, dalla pagina principale, facendo **doppio clic** sul testo in fondo alla pagina (footer)

**Password admin predefinita:** `Admin`

> 🔴 **IMPORTANTE:** Cambia la password admin prima di mettere il sistema in produzione.
> Aprire `admin.php`, individuare la riga:
> ```php
> $admin_pass = "Admin2026!";
> ```
> e sostituire `Admin2026!` con una password sicura a tua scelta.

---

### 2. Impostare le firme globali

Nella sezione **"Impostazioni Firme"** del pannello admin puoi configurare:

| Campo | Descrizione |
|---|---|
| Etichetta Firma 1 / 2 | Nome del ruolo in calce (es. "Il Coordinatore", "Il Dirigente Scolastico") |
| Nome firmatario | Nome e cognome della persona (per firme digitali/sostitutive) |
| Tipo firma | `autografa` · `digitale` · `sostitutiva` |
| Abilita firma | Attiva/disattiva la firma a livello globale |

I tipi di firma producono layout diversi nel documento stampato:
- **Autografa:** spazio bianco sopra la riga per la firma a mano
- **Digitale:** riga sopra il nome del firmatario (nessuno spazio)
- **Sostitutiva:** nome del firmatario con citazione legale *(art. 3 c. 2 D.Lgs. 39/93)*

---

### 3. Aggiungere un alunno

Nella sezione **"Gestione Alunni"** del pannello admin, clicca su **"+ Nuovo Alunno"** e compila:

| Campo | Descrizione |
|---|---|
| Nome Alunno | Nome completo (es. `Rossi Mario`) |
| Classe | Classe di appartenenza (es. `3ª A`) |
| Titolo documento | Opzionale (default: `PIANO DIDATTICO PERSONALIZZATO`) |
| Password | Codice di accesso che verrà fornito all'alunno/famiglia |
| Scadenza | Data entro cui l'accesso è consentito |
| Genere | Maschile / Femminile (influenza testi grammaticali nel documento) |
| Materie | Elenco discipline separate da virgola (es. `Italiano, Matematica, Inglese`) |
| Firma 1 / Firma 2 | Abilita le firme specifiche per questo alunno |

Dopo il salvataggio, il file JSON dell'alunno viene creato automaticamente.

---

## 👩‍🏫 Utilizzo da parte dei docenti / famiglie

1. Accedere a `index.php` e inserire la **password** fornita dall'amministratore.
2. Compilare o aggiornare i contenuti nelle sezioni disciplinari.
3. Usare **`Ctrl+S`** (o il pulsante "💾 Salva") per salvare il documento.
4. Per il grassetto: selezionare il testo e premere **`Ctrl+B`** oppure cliccare il pulsante **B**.
5. Cliccare **"📄 Stampa / Salva PDF"** per generare il PDF del documento ufficiale.

### Note sulla stampa
- Il PDF viene generato tramite la finestra di stampa del browser (consigliato: **Chrome** o **Edge**).
- Selezionare **"Salva come PDF"** come destinazione.
- Formato consigliato: **A4**, margini normali, senza intestazioni/piè di pagina del browser.

---

## 🔄 Backup e ripristino

Il sistema esegue **backup automatici** ad ogni salvataggio. I backup sono visibili nel pannello admin nella sezione dedicata di ogni alunno.

Per ripristinare una versione precedente:
1. Aprire il pannello admin → sezione alunno → **"Backup disponibili"**
2. Cliccare **"Ripristina"** accanto alla versione desiderata.

Per eliminare tutti i backup (per liberare spazio): pannello admin → **"Svuota backup"**.

---

## 🔒 Note sulla sicurezza

- I dati degli alunni sono salvati in file JSON nella cartella `database/`. Assicurarsi che questa cartella **non sia accessibile direttamente via browser** (configurare `.htaccess` su Apache, vedi sotto).
- La password admin è salvata in chiaro nel codice: si raccomanda di cambiarla e di non condividere il file `admin.php` pubblicamente.
- Le sessioni PHP gestiscono l'autenticazione: assicurarsi che il server abbia le sessioni correttamente configurate.

### Proteggere la cartella `database/` su Apache

Creare un file `.htaccess` nella cartella `database/` con questo contenuto:

```apache
Options -Indexes
Deny from all
```

---

## 🛠️ Personalizzazione

### Cambiare il nome dell'istituto nel footer

In `index.php`, individuare e modificare la riga:

```php
Sviluppo: Sebastiano Basile per il <a href="https://www.3iccapuana.edu.it" ...>3° I.C. Capuana-de Amicis</a>
```

### Cambiare il luogo predefinito nel documento

Il luogo predefinito è `Avola`. Per cambiarlo, cerca in `index.php`:

```php
value="<?php echo h($stored['info']['luogo'] ?? 'Avola'); ?>"
```

e sostituisci `'Avola'` con il nome del tuo comune.

---

## 📄 Licenza

Distribuito sotto licenza **MIT**. Consulta il file [LICENSE](LICENSE) per i dettagli.

Puoi usare, modificare e ridistribuire questo software liberamente, anche in ambito scolastico istituzionale, **mantenendo l'attribuzione originale**.

---

## 🤝 Contribuire

Le segnalazioni di bug e i suggerimenti di miglioramento sono benvenuti tramite le **Issues** di GitHub.

Per proporre modifiche al codice, aprire una **Pull Request** con una descrizione chiara delle modifiche apportate.

---

## 👤 Autore

**Sebastiano Basile**
3° Istituto Comprensivo Capuana-de Amicis — Avola (SR)
🌐 [www.3iccapuana.edu.it](https://www.3iccapuana.edu.it) · [www.capuanadeamicis.it](https://www.capuanadeamicis.it)

[![Contributo volontario](https://img.shields.io/badge/Offrimi_un_caffè_☕-PayPal-blue)](https://paypal.me/superscuola)
