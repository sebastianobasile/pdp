<?php
/**
 * PDP V3.6.0 — Con PIN per Materia e promemoria
 */
session_start();
$db_dir = "database/"; $bk_dir = $db_dir . "backups/";
$config_file = $db_dir . "config.json";
$admin_pass = "elisab";

if (isset($_POST['login'])) {
    if ($_POST['pw'] === $admin_pass) $_SESSION['super'] = true;
    else $login_err = "Password errata.";
}
if (isset($_GET['logout'])) { session_destroy(); header("Location: admin.php"); exit; }
$is_super = isset($_SESSION['super']);

$db = json_decode(file_get_contents($config_file), true);
$msg = ""; $msg_type = "ok";

if ($is_super && isset($_GET['del'])) {
    $key = $_GET['del'];
    if (isset($db['alunni'][$key])) {
        unset($db['alunni'][$key]);
        file_put_contents($config_file, json_encode($db, JSON_PRETTY_PRINT));
        $msg = "Alunno eliminato correttamente."; $msg_type = "warn";
    }
}

if ($is_super && isset($_POST['save_al'])) {
    $key     = preg_replace('/[^A-Za-z0-9]/', '', trim($_POST['ak']));
    $key_old = preg_replace('/[^A-Za-z0-9]/', '', trim($_POST['ak_old'] ?? ''));
    if ($key === '') { $msg = "ERRORE: Password non valida."; $msg_type = "err"; }
    else {
        $materie = array_values(array_filter(array_map('trim', explode(',', $_POST['am']))));
        $nome    = trim($_POST['an']);
        $fname   = strtolower(preg_replace('/\s+/', '_', $nome)) . ".json";
        $titolo  = trim($_POST['atit'] ?? '');

        // ── Gestione PIN per Materia ──────────────────────────────────────
        $raw_pm       = $_POST['pm'] ?? [];           // array [MATERIA => pin]
        $existing_pm  = $db['alunni'][$key_old]['pin_materie'] ?? $db['alunni'][$key]['pin_materie'] ?? [];
        $pin_materie  = [];
        foreach ($materie as $m) {
            if (isset($raw_pm[$m])) {
                $p = trim($raw_pm[$m]);
                if ($p === '__CLEAR__') {
                    $pin_materie[$m] = ''; // PIN rimosso esplicitamente con il tasto ✕
                } elseif ($p === '') {
                    // Campo lasciato vuoto: conserva il PIN esistente (se c'era)
                    $pin_materie[$m] = $existing_pm[$m] ?? '';
                } else {
                    // Campo contiene il PIN (invariato o modificato): salva così com'è
                    $pin_materie[$m] = $p;
                }
            } else {
                $pin_materie[$m] = $existing_pm[$m] ?? '';
            }
        }
        // ─────────────────────────────────────────────────────────────────

        // ── Firme per alunno ──────────────────────────────────────────────────
        $firme_al = [
            "sf1"  => isset($_POST['asf1'])  ? 1 : 0,
            "sf2"  => isset($_POST['asf2'])  ? 1 : 0,
            "al1"  => isset($_POST['aal1'])  ? strip_tags(trim($_POST['aal1']))  : null,
            "al2"  => isset($_POST['aal2'])  ? strip_tags(trim($_POST['aal2']))  : null,
            "aft1" => isset($_POST['aaft1']) ? $_POST['aaft1'] : null,
            "aft2" => isset($_POST['aaft2']) ? $_POST['aaft2'] : null,
            "an1"  => isset($_POST['aan1'])  ? strip_tags(trim($_POST['aan1']))  : null,
            "an2"  => isset($_POST['aan2'])  ? strip_tags(trim($_POST['aan2']))  : null,
        ];
        $record  = ["nome" => $nome, "classe" => trim($_POST['ac']), "file" => $fname,
                    "materie" => $materie, "scadenza" => $_POST['as'], "titolo" => $titolo,
                    "genere" => ($_POST['agenere'] ?? 'M'),
                    "lbl_nome"  => trim($_POST['albl_nome']  ?? ''),
                    "lbl_gruppo" => trim($_POST['albl_gruppo'] ?? ''),
                    "sf1" => $firme_al['sf1'],
                    "sf2" => $firme_al['sf2'],
                    "firme" => $firme_al,
                    "pin_materie" => $pin_materie];
        $msg_pw  = '';
        if ($key_old !== '' && $key_old !== $key && isset($db['alunni'][$key_old])) {
            unset($db['alunni'][$key_old]);
            $msg_pw = " (password cambiata: <code>$key_old</code> → <code>$key</code>)";
        }
        $db['alunni'][$key] = $record;
        file_put_contents($config_file, json_encode($db, JSON_PRETTY_PRINT));
        $msg = "Dati aggiornati per <strong>" . htmlspecialchars($nome, ENT_QUOTES) . "</strong>" . $msg_pw . ".";
    }
}

// save_glob rimosso: le firme si configurano per-alunno

if ($is_super && isset($_GET['reset_bk'])) {
    $deleted = 0;
    $bk_files = glob($bk_dir . "*.json");
    if ($bk_files) { foreach ($bk_files as $bf) { if (unlink($bf)) $deleted++; } }
    $msg = "🗑️ Eliminati <strong>$deleted</strong> file di backup."; $msg_type = "warn";
}

if ($is_super && isset($_GET['restore'])) {
    $fn = basename($_GET['restore']); $target = basename($_GET['target'] ?? '');
    $src = $bk_dir . $fn;
    if ($target && file_exists($src) && pathinfo($fn, PATHINFO_EXTENSION) === 'json') {
        copy($src, $db_dir . $target);
        $msg = "Backup <strong>" . htmlspecialchars($fn) . "</strong> ripristinato.";
        $db = json_decode(file_get_contents($config_file), true);
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pannello Admin — PDP</title>
    <style>
        :root {
            --p:#2c3e50; --s:#27ae60; --e:#e74c3c; --w:#f59e0b;
            --blue:#3498db; --bg:#eef2f7; --card:#fff;
            --border:#e2e8f0; --text:#1e293b; --muted:#64748b;
        }
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Inter',system-ui,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
        .topbar{background:var(--p);color:#fff;padding:0 30px;display:flex;align-items:center;justify-content:space-between;height:58px;position:sticky;top:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,.2);}
        .topbar-brand{font-weight:700;font-size:1rem;letter-spacing:.02em;display:flex;align-items:center;gap:10px;}
        .topbar-brand .dot{width:8px;height:8px;background:var(--s);border-radius:50%;display:inline-block;}
        .topbar-nav{display:flex;align-items:center;gap:12px;font-size:13px;}
        .topbar-nav a{color:rgba(255,255,255,.7);text-decoration:none;transition:color .2s;}
        .topbar-nav a:hover{color:#fff;}
        .topbar-nav .sep{color:rgba(255,255,255,.3);}
        .btn-logout{background:var(--e);color:#fff;border:none;padding:7px 16px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;text-decoration:none;transition:opacity .2s;}
        .btn-logout:hover{opacity:.85;}
        .page{max-width:1120px;margin:30px auto;padding:0 20px 60px;}
        .notice{padding:14px 18px;border-radius:8px;margin-bottom:24px;font-size:14px;display:flex;align-items:center;gap:10px;}
        .notice-ok{background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;}
        .notice-warn{background:#fffbeb;color:#92400e;border:1px solid #fde68a;}
        .notice-err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca;}
        .scard{background:var(--card);border-radius:12px;border:1px solid var(--border);margin-bottom:24px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06);}
        .scard-head{padding:16px 22px;border-bottom:1px solid var(--border);background:#f8fafc;display:flex;align-items:center;gap:12px;}
        .scard-head .icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
        .icon-blue{background:#dbeafe;} .icon-green{background:#dcfce7;} .icon-amber{background:#fef3c7;}
        .scard-head h3{font-size:.95rem;font-weight:700;color:var(--p);}
        .scard-head .sub{font-size:12px;color:var(--muted);margin-top:2px;}
        .scard-body{padding:22px;}
        .firme-grid{display:grid;grid-template-columns:1fr 1fr auto;gap:16px;align-items:end;}
        .field-label{font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;display:flex;align-items:center;gap:6px;}
        input[type=text],input[type=password],input[type=date],textarea{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:7px;font-family:inherit;font-size:13px;color:var(--text);background:#fff;transition:border-color .2s,box-shadow .2s;}
        input:focus,textarea:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(52,152,219,.12);}
        input[readonly]{background:#f8fafc;color:var(--muted);cursor:default;}
        .tbl{width:100%;border-collapse:collapse;}
        .tbl thead tr{background:#f8fafc;}
        .tbl th{padding:10px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);border-bottom:2px solid var(--border);white-space:nowrap;}
        .tbl td{padding:10px 14px;border-bottom:1px solid var(--border);vertical-align:middle;font-size:13px;}
        .tbl tbody tr:hover td{background:#f8fafc;}
        .tbl tbody tr:last-child td{border-bottom:none;}
        .tbl .new-row td{background:#f0fdf4;}
        .tbl .new-row td input,.tbl .new-row td textarea{background:#fff;}
        .badge{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px;}
        .b-ok{background:#dcfce7;color:#166534;} .b-warn{background:#fef9c3;color:#854d0e;} .b-exp{background:#fee2e2;color:#991b1b;}
        .btn{display:inline-flex;align-items:center;gap:5px;padding:8px 14px;border-radius:7px;border:none;font-weight:600;font-size:13px;cursor:pointer;text-decoration:none;color:#fff;transition:opacity .15s,transform .1s;white-space:nowrap;}
        .btn:hover{opacity:.88;} .btn:active{transform:scale(.97);}
        .btn-save{background:var(--s);} .btn-del{background:var(--e);} .btn-create{background:var(--p);} .btn-blue{background:var(--blue);} .btn-amber{background:var(--w);color:#1e293b;}
        .btn-sm{padding:5px 10px;font-size:12px;border-radius:5px;}
        .action-col{display:flex;flex-direction:column;gap:5px;min-width:105px;}
        .pw-wrap{display:flex;align-items:center;gap:6px;}
        .pw-wrap code{font-size:12px;background:#f1f5f9;padding:3px 7px;border-radius:4px;border:1px solid var(--border);color:var(--p);}
        .copy-btn{background:none;border:1px solid var(--border);border-radius:4px;padding:3px 7px;cursor:pointer;font-size:12px;color:var(--muted);transition:background .15s;}
        .copy-btn:hover{background:#f1f5f9;}
        .stats{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;}
        .stat-pill{background:#f1f5f9;border:1px solid var(--border);border-radius:8px;padding:7px 14px;font-size:13px;color:var(--muted);}
        .stat-pill strong{color:var(--p);}
        .backup-list{max-height:280px;overflow-y:auto;border:1px solid var(--border);border-radius:8px;}
        .backup-row{display:grid;grid-template-columns:130px 1fr auto;align-items:center;gap:16px;padding:11px 16px;border-bottom:1px solid var(--border);font-size:13px;}
        .backup-row:last-child{border-bottom:none;}
        .backup-row:hover{background:#f8fafc;}
        .backup-time{font-weight:600;color:var(--p);white-space:nowrap;}
        .backup-file{color:var(--muted);}
        .backup-file strong{color:var(--text);}
        .login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg);}
        .login-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:48px 40px;width:360px;box-shadow:0 8px 24px rgba(0,0,0,.08);text-align:center;}
        .login-logo{width:52px;height:52px;background:var(--p);border-radius:12px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:24px;margin-bottom:20px;}
        .pw-eye-wrap{position:relative;margin-bottom:16px;}
        .pw-eye-wrap input{padding-right:44px;font-size:15px;padding-top:12px;padding-bottom:12px;}
        .eye-btn{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:18px;color:var(--muted);}
        .footer{text-align:center;font-size:11px;color:#cbd5e1;margin-top:30px;padding-bottom:20px;}
        .footer a{color:#94a3b8;text-decoration:none;}
        .footer a:hover{color:var(--p);}
        @media(max-width:768px){.firme-grid{grid-template-columns:1fr;}.tbl{font-size:12px;}.tbl th,.tbl td{padding:8px 10px;}.backup-row{grid-template-columns:1fr auto;}.backup-time{grid-column:1/-1;}}

        /* ── STUDENT CARDS ── */
        .al-card { background:#fff; border:1px solid var(--border); border-radius:10px; margin-bottom:10px; overflow:hidden; transition: box-shadow .2s; }
        .al-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.07); }

        .al-card-header {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 14px; cursor: pointer;
            user-select: none; transition: background .15s;
        }
        .al-card-header:hover { background: #f8fafc; }
        .al-card-header .ah-name {
            font-weight: 700; font-size: 14px; color: var(--p); flex: 1; min-width: 0;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .al-card-header .ah-class {
            font-size: 12px; color: var(--muted); background: #f1f5f9;
            padding: 2px 8px; border-radius: 5px; flex-shrink: 0;
        }
        .al-card-header .ah-pw {
            display: flex; align-items: center; gap: 4px; flex-shrink: 0;
        }
        .al-card-header .ah-pw code {
            font-size: 12px; background: #f1f5f9; padding: 2px 7px;
            border-radius: 4px; border: 1px solid var(--border);
            color: var(--p); font-family: monospace; letter-spacing: 1px;
            min-width: 70px; display: inline-block; text-align: center;
            filter: blur(4px); transition: filter .2s; cursor: text;
        }
        .al-card-header .ah-pw code.visible { filter: blur(0); }
        .al-card-header .ah-eye, .al-card-header .ah-copy, .al-card-header .ah-link {
            background: none; border: 1px solid var(--border); border-radius: 4px;
            padding: 3px 6px; cursor: pointer; font-size: 13px;
            color: var(--muted); transition: background .15s, color .15s;
            line-height: 1.4; flex-shrink: 0;
        }
        .al-card-header .ah-eye:hover  { background: #f1f5f9; color: var(--p); }
        .al-card-header .ah-copy:hover { background: #dbeafe; color: var(--blue); border-color: var(--blue); }
        .al-card-header .ah-link:hover { background: #dcfce7; color: var(--s);    border-color: var(--s); }
        .al-card-header .ah-chevron {
            font-size: 11px; color: var(--muted); flex-shrink: 0;
            transition: transform .25s; display: inline-block;
        }
        .al-card.open .ah-chevron { transform: rotate(180deg); }
        .al-card-header .ah-pin-count {
            font-size: 10px; font-weight: 700; background: #fef3c7;
            color: #92400e; padding: 2px 7px; border-radius: 10px;
            flex-shrink: 0;
        }

        .al-card-body {
            display: none;
            padding: 0 16px 16px;
            border-top: 1px solid var(--border);
            animation: slideDown .2s ease;
        }
        .al-card.open .al-card-body { display: block; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .al-row { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-start; margin-top: 14px; }
        .al-field { display:flex; flex-direction:column; }
        .al-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); margin-bottom:5px; white-space:nowrap; }

        /* ── PIN MATERIE SECTION ── */
        .pin-section {
            margin-top: 14px; padding: 14px 16px;
            background: #fefce8; border: 1px solid #fde68a; border-radius: 8px;
        }
        .pin-section-title {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .04em; color: #92400e; margin-bottom: 10px;
            display: flex; align-items: center; gap: 6px;
        }
        .pin-grid { display: flex; flex-wrap: wrap; gap: 10px; }
        .pin-item { display: flex; flex-direction: column; gap: 4px; min-width: 130px; }
        .pin-item label { font-size: 11px; font-weight: 600; color: var(--muted); }
        .pin-item .pin-wrap { position: relative; display: flex; align-items: center; }
        .pin-item .pin-wrap input {
            flex: 1; padding: 7px 52px 7px 10px;
            font-family: monospace; font-size: 13px; letter-spacing: 2px;
        }
        .pin-item .pin-btns {
            position: absolute; right: 4px;
            display: flex; align-items: center; gap: 2px;
        }
        .pin-item .pin-btns button {
            background: none; border: none; cursor: pointer;
            font-size: 14px; color: #94a3b8; padding: 2px 4px;
            line-height: 1; border-radius: 3px; transition: color .15s, background .15s;
        }
        .pin-item .pin-btns .pin-eye-btn:hover { color: var(--blue); background: #dbeafe; }
        .pin-item .pin-btns .clear-pin:hover   { color: var(--e);    background: #fee2e2; }
        .pin-has { border-color: #f59e0b !important; background: #fffbeb !important; }
        .pin-badge { font-size: 10px; color: #92400e; font-weight: 600; }
        .pin-badge.active { color: var(--s); }

        /* ── MODALE PROMEMORIA PIN ── */
        .pin-reminder-btn {
            background: #7c3aed; color: #fff; border: none;
            border-radius: 5px; padding: 3px 9px; font-size: 12px;
            font-weight: 700; cursor: pointer; flex-shrink: 0;
            transition: opacity .15s; line-height: 1.6;
        }
        .pin-reminder-btn:hover { opacity: .85; }

        #pinReminderOverlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.5); z-index: 2000;
            align-items: center; justify-content: center;
            padding: 20px;
        }
        #pinReminderOverlay.show { display: flex; }
        .pr-modal {
            background: #fff; border-radius: 14px; width: 100%;
            max-width: 760px; max-height: 90vh; overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,.25);
            display: flex; flex-direction: column;
        }
        .pr-modal-head {
            padding: 18px 22px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 10px; position: sticky; top: 0;
            background: #fff; border-radius: 14px 14px 0 0; z-index: 1;
        }
        .pr-modal-head h2 { font-size: 1rem; font-weight: 700; color: var(--p); flex: 1; }
        .pr-modal-close {
            background: none; border: 1px solid var(--border); border-radius: 6px;
            width: 30px; height: 30px; cursor: pointer; font-size: 16px;
            display: flex; align-items: center; justify-content: center; color: var(--muted);
        }
        .pr-modal-close:hover { background: #f1f5f9; }
        .pr-modal-body { padding: 22px; }

        /* Tabs */
        .pr-tabs { display: flex; gap: 6px; margin-bottom: 20px; border-bottom: 2px solid var(--border); padding-bottom: 0; }
        .pr-tab {
            padding: 8px 16px; font-size: 13px; font-weight: 600;
            border: none; background: none; cursor: pointer; color: var(--muted);
            border-bottom: 2px solid transparent; margin-bottom: -2px;
            transition: color .15s, border-color .15s;
        }
        .pr-tab.active { color: #7c3aed; border-bottom-color: #7c3aed; }
        .pr-tab-content { display: none; }
        .pr-tab-content.active { display: block; }

        /* Tagliandi */
        .slip-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
        .slip {
            border: 2px dashed #94a3b8; border-radius: 8px; padding: 14px 16px;
            font-size: 12px; background: #fafafa; position: relative;
        }
        .slip-mat { font-size: 14px; font-weight: 800; color: var(--p); margin-bottom: 6px; }
        .slip-row { display: flex; justify-content: space-between; color: var(--muted); margin-bottom: 3px; }
        .slip-pin { font-family: monospace; font-size: 18px; font-weight: 700; color: #7c3aed;
                    letter-spacing: 3px; text-align: center; margin: 8px 0;
                    background: #f3e8ff; border-radius: 6px; padding: 6px 0; }
        .slip-url { font-size: 10px; color: #64748b; word-break: break-all; margin-top: 6px; }
        .slip-scissors { position: absolute; top: -10px; right: 10px; font-size: 16px; }
        .pr-actions { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }

        /* Text areas per email/WA */
        .pr-textarea {
            width: 100%; border: 1px solid var(--border); border-radius: 8px;
            padding: 14px; font-family: monospace; font-size: 12px;
            line-height: 1.7; resize: vertical; min-height: 200px;
            background: #f8fafc; color: var(--text);
        }
        .pr-copy-btn {
            margin-top: 10px; background: var(--blue); color: #fff;
            border: none; border-radius: 7px; padding: 9px 18px;
            font-size: 13px; font-weight: 700; cursor: pointer; transition: opacity .15s;
        }
        .pr-copy-btn:hover { opacity: .85; }

        /* Stampa tagliandi */
        @media print {
            body > *:not(#pinReminderOverlay) { display: none !important; }
            #pinReminderOverlay { display: block !important; position: static !important;
                background: none !important; padding: 0 !important; }
            .pr-modal { box-shadow: none !important; max-height: none !important; border-radius: 0; }
            .pr-modal-head, .pr-tabs, .pr-tab-content:not(#tab-stampa),
            .pr-actions, .pr-modal-close { display: none !important; }
            #tab-stampa { display: block !important; }
            .slip { border: 1.5px dashed #999; page-break-inside: avoid; background: #fff; }
            .slip-grid { grid-template-columns: repeat(3, 1fr); gap: 8px; }
        }
    </style>
</head>
<body>

<?php if (!$is_super): ?>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-logo">🔐</div>
        <h2 style="color:var(--p);font-size:1.2rem;margin-bottom:6px;">Accesso Admin</h2>
        <p style="color:var(--muted);font-size:13px;margin-bottom:24px;">Piano Didattico Personalizzato</p>
        <form method="post">
            <div class="pw-eye-wrap">
                <input type="password" name="pw" id="adm_pw" placeholder="Password amministratore" autofocus autocomplete="current-password">
                <button type="button" class="eye-btn" onclick="togglePw('adm_pw','eye_adm')" title="Mostra/nascondi"><span id="eye_adm">👁</span></button>
            </div>
            <button type="submit" name="login" class="btn btn-create" style="width:100%;justify-content:center;padding:12px;">Accedi al Pannello</button>
        </form>
        <?php if (!empty($login_err)): ?>
            <div style="color:#991b1b;margin-top:14px;font-size:13px;font-weight:600;">⚠ <?php echo h($login_err); ?></div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>

<div class="topbar">
    <div class="topbar-brand"><span class="dot"></span>Pannello Admin — PDP</div>
    <div class="topbar-nav">
        <a href="index.php">← Torna al PDP</a>
        <span class="sep">|</span>
        <a href="?logout=1" class="btn-logout">Esci</a>
    </div>
</div>

<div class="page">

    <?php if ($msg): ?>
    <div class="notice notice-<?php echo $msg_type; ?>">
        <?php echo $msg_type==='ok'?'✅':($msg_type==='warn'?'⚠️':'❌'); ?> <?php echo $msg; ?>
    </div>
    <?php endif; ?>

    <!-- 1. ALUNNI -->
    <div class="scard">
        <div class="scard-head">
            <div class="icon icon-green">👥</div>
            <div><h3>Gestione Alunni e Materie</h3><div class="sub">Crea, modifica o elimina i profili degli alunni e le relative discipline</div></div>
        </div>
        <div class="scard-body">
            <?php
            $n_tot=count($db['alunni']); $n_att=$n_exp=$n_scad=0;
            foreach($db['alunni'] as $v){ $d=(strtotime($v['scadenza'])-strtotime($today))/86400; if($d>30)$n_att++; elseif($d>0)$n_exp++; else $n_scad++; }
            ?>
            <div class="stats">
                <div class="stat-pill">Totale: <strong><?php echo $n_tot; ?></strong></div>
                <div class="stat-pill">✅ Attivi: <strong><?php echo $n_att; ?></strong></div>
                <?php if($n_exp): ?><div class="stat-pill">⚠️ In scadenza: <strong><?php echo $n_exp; ?></strong></div><?php endif; ?>
                <?php if($n_scad): ?><div class="stat-pill">🔴 Scaduti: <strong><?php echo $n_scad; ?></strong></div><?php endif; ?>
            </div>

            <!-- ALUNNI ESISTENTI -->
            <?php $card_idx = 0; foreach($db['alunni'] as $k => $v):
                $exp=$v['scadenza']; $days=(strtotime($exp)-strtotime($today))/86400;
                $bc=$days>30?'b-ok':($days>=0?'b-warn':'b-exp');
                $bl=$days>30?'Attivo':($days>=0?'In scadenza':'Scaduto');
                $gl=$db['settings'];
                $pm=$v['pin_materie'] ?? [];
                $n_pinned = count(array_filter($pm, fn($p) => $p !== ''));
                $card_id  = 'alcard_' . $card_idx++;
            ?>
            <form method="post">
            <div class="al-card" id="<?php echo $card_id; ?>">

                <!-- ── Header sempre visibile ─────────────────────────────── -->
                <div class="al-card-header" onclick="toggleCard('<?php echo $card_id; ?>')" title="Clicca per espandere/chiudere">

                    <span class="ah-name"><?php echo h($v['nome']); ?></span>
                    <span class="ah-class"><?php echo h($v['classe']); ?></span>
                    <span class="badge <?php echo $bc; ?>"><?php echo $bl; ?></span>

                    <?php if ($n_pinned > 0): ?>
                    <span class="ah-pin-count">🔐 <?php echo $n_pinned; ?> PIN</span>
                    <button type="button" class="pin-reminder-btn"
                            onclick="event.stopPropagation(); openPinReminder(<?php
                                $pr_data = [];
                                foreach ($v['materie'] as $mat) {
                                    if (!empty($pm[$mat])) {
                                        $pr_data[] = ['mat' => $mat, 'pin' => $pm[$mat]];
                                    }
                                }
                                echo h(json_encode([
                                    'nome'   => $v['nome'],
                                    'classe' => $v['classe'],
                                    'pw'     => $k,
                                    'titolo' => $v['titolo'] ?? 'PDP',
                                    'pins'   => $pr_data,
                                ]));
                            ?>)"
                            title="Genera promemoria PIN per i docenti">📋 Memo PIN</button>
                    <?php endif; ?>

                    <!-- Password con occhiolino -->
                    <span class="ah-pw" onclick="event.stopPropagation()">
                        <code id="pw_<?php echo h($k); ?>"><?php echo h($k); ?></code>
                        <button type="button" class="ah-eye"
                                onclick="toggleHeaderPw('pw_<?php echo h($k); ?>', this)"
                                title="Mostra/nascondi password">👁</button>
                        <button type="button" class="ah-copy"
                                onclick="copyPw('<?php echo h($k); ?>')"
                                title="Copia password">📋</button>
                        <a  class="ah-link"
                            href="index.php"
                            target="_blank"
                            title="Apri pagina PDP (poi inserire la password)">🔗</a>
                    </span>

                    <span class="ah-chevron">▼</span>
                </div>

                <!-- ── Body collassabile ───────────────────────────────────── -->
                <div class="al-card-body">

                    <!-- Riga 1: Nome + Titolo + Classe + Genere + Scadenza -->
                    <div class="al-row">
                        <div class="al-field" style="flex:2; min-width:160px;">
                            <label class="al-label">Nome / Identificativo</label>
                            <input type="text" name="an" value="<?php echo h($v['nome']); ?>">
                        </div>
                        <div class="al-field" style="flex:2; min-width:160px;">
                            <label class="al-label">Titolo documento</label>
                            <input type="text" name="atit" value="<?php echo h($v['titolo'] ?? ''); ?>" placeholder="PIANO DIDATTICO PERSONALIZZATO">
                        </div>
                        <div class="al-field" style="flex:0 0 100px;">
                            <label class="al-label" title="Sostituisce 'Alunno' nell'intestazione">Etich. nome</label>
                            <input type="text" name="albl_nome" value="<?php echo h($v['lbl_nome'] ?? ''); ?>" placeholder="Alunno">
                        </div>
                        <div class="al-field" style="flex:0 0 100px;">
                            <label class="al-label" title="Sostituisce 'Classe' nell'intestazione">Etich. gruppo</label>
                            <input type="text" name="albl_gruppo" value="<?php echo h($v['lbl_gruppo'] ?? ''); ?>" placeholder="Classe">
                        </div>
                        <div class="al-field" style="flex:0 0 80px;">
                            <label class="al-label">Classe</label>
                            <input type="text" name="ac" value="<?php echo h($v['classe']); ?>">
                        </div>
                        <div class="al-field" style="flex:0 0 80px;">
                            <label class="al-label">Genere</label>
                            <select name="agenere" style="padding:9px 8px; border:1px solid var(--border); border-radius:7px; font-size:13px; width:100%;">
                                <option value="M" <?php echo ($v['genere']??'M')==='M'?'selected':''; ?>>M</option>
                                <option value="F" <?php echo ($v['genere']??'M')==='F'?'selected':''; ?>>F</option>
                            </select>
                        </div>
                        <div class="al-field" style="flex:0 0 150px;">
                            <label class="al-label">Scadenza</label>
                            <input type="date" name="as" value="<?php echo h($exp); ?>">
                            <span class="badge <?php echo $bc; ?>" style="margin-top:4px;">● <?php echo $bl; ?></span>
                        </div>
                    </div>

                    <!-- Riga 2: Password + Materie + Firme + Azioni -->
                    <div class="al-row">
                        <div class="al-field" style="flex:0 0 160px;">
                            <label class="al-label">Password</label>
                            <input type="hidden" name="ak_old" value="<?php echo h($k); ?>">
                            <div class="pw-wrap">
                                <input type="text" name="ak" value="<?php echo h($k); ?>"
                                       pattern="[A-Za-z0-9]+" title="Solo lettere e numeri"
                                       style="font-family:monospace; font-size:13px;">
                                <button type="button" class="copy-btn" onclick="copyPw('<?php echo h($k); ?>')" title="Copia">📋</button>
                            </div>
                        </div>
                        <div class="al-field" style="flex:3; min-width:200px;">
                            <label class="al-label">Materie (separate da virgola)</label>
                            <textarea name="am" rows="2" style="font-size:12px; min-height:unset; resize:vertical;"><?php echo h(implode(', ',$v['materie'])); ?></textarea>
                        </div>
                        <!-- placeholder, firme ora sotto -->
                        <div style="display:none;"></div>
                        <div class="al-field" style="flex:0 0 110px; justify-content:flex-end;">
                            <label class="al-label">&nbsp;</label>
                            <button type="submit" name="save_al" class="btn btn-save btn-sm" style="width:100%; margin-bottom:5px;">✔ Salva</button>
                            <a href="?del=<?php echo urlencode($k); ?>" class="btn btn-del btn-sm" style="width:100%; text-align:center;"
                               onclick="return confirm('Eliminare <?php echo h($v['nome']); ?>?')">✖ Elimina</a>
                        </div>
                    </div>

                    <!-- ── Sezione PIN per Materia ──────────────────────────── -->
                    <div class="pin-section">
                        <div class="pin-section-title">
                            🔐 PIN per Materia
                            <?php if ($n_pinned > 0): ?>
                                <span style="background:#fde68a; padding:2px 8px; border-radius:10px; font-size:10px;">
                                    <?php echo $n_pinned; ?> materia<?php echo $n_pinned > 1 ? 'e' : ''; ?> con PIN attivo
                                </span>
                            <?php else: ?>
                                <span style="font-weight:400; color:#92400e; font-size:11px; text-transform:none; letter-spacing:0;">
                                    — nessun PIN impostato, tutte le materie liberamente modificabili
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="pin-grid">
                            <?php foreach ($v['materie'] as $mat):
                                $has_pin = isset($pm[$mat]) && $pm[$mat] !== '';
                                $safe_id = 'pin_' . $k . '_' . preg_replace('/[^A-Za-z0-9]/', '_', $mat);
                            ?>
                            <div class="pin-item">
                                <label for="<?php echo $safe_id; ?>">
                                    <?php echo h($mat); ?>
                                    <?php if ($has_pin): ?>
                                        <span class="pin-badge active">● PIN attivo</span>
                                    <?php else: ?>
                                        <span class="pin-badge">○ libera</span>
                                    <?php endif; ?>
                                </label>
                                <div class="pin-wrap">
                                    <input type="password"
                                           id="<?php echo $safe_id; ?>"
                                           name="pm[<?php echo h($mat); ?>]"
                                           value="<?php echo $has_pin ? h($pm[$mat]) : ''; ?>"
                                           placeholder="<?php echo $has_pin ? '••••  (invariato)' : 'Nessun PIN'; ?>"
                                           autocomplete="new-password"
                                           class="<?php echo $has_pin ? 'pin-has' : ''; ?>"
                                           title="<?php echo $has_pin ? 'Lascia vuoto per mantenere il PIN attuale.' : 'Imposta un PIN per bloccare questa materia'; ?>">
                                    <div class="pin-btns">
                                        <button type="button" class="pin-eye-btn"
                                                onclick="togglePinEye('<?php echo $safe_id; ?>', this)"
                                                title="Mostra/nascondi PIN">👁</button>
                                        <?php if ($has_pin): ?>
                                        <button type="button" class="clear-pin"
                                                onclick="clearPin('<?php echo $safe_id; ?>', this)"
                                                title="Rimuovi PIN">✕</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($has_pin): ?>
                                <span style="font-size:10px; color:#92400e;">💡 Vuoto = PIN invariato</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p style="font-size:11px; color:#92400e; margin-top:10px; opacity:.8;">
                            ℹ️ Ogni docente inserisce il proprio PIN per sbloccare la materia di competenza. Le materie senza PIN sono modificabili da chiunque acceda al documento.
                        </p>
                    </div>
                    <!-- ─────────────────────────────────────────────────────── -->

                    <!-- ── Configurazione Firme per Alunno ─────────────────── -->
                    <?php
                    $af  = $v['firme'] ?? [];
                    // Valori effettivi: usa quelli dell'alunno se presenti, altrimenti fallback ai globali
                    $a_sf1  = $af['sf1']  ?? $gl['sf1']  ?? 1;
                    $a_sf2  = $af['sf2']  ?? $gl['sf2']  ?? 1;
                    $a_l1   = $af['al1']  ?? $gl['l1']   ?? '';
                    $a_l2   = $af['al2']  ?? $gl['l2']   ?? '';
                    $a_ft1  = $af['aft1'] ?? $gl['ft1']  ?? 'autografa';
                    $a_ft2  = $af['aft2'] ?? $gl['ft2']  ?? 'autografa';
                    $a_n1   = $af['an1']  ?? $gl['n1']   ?? '';
                    $a_n2   = $af['an2']  ?? $gl['n2']   ?? '';
                    $fid = 'f_'.preg_replace('/[^A-Za-z0-9]/', '_', $k);
                    ?>
                    <div class="pin-section" style="background:#eff6ff; border-color:#bfdbfe; margin-top:14px;">
                        <div class="pin-section-title" style="color:#1e40af;">
                            ✍️ Configurazione Firme
                            <span style="font-weight:400; font-size:11px; text-transform:none; letter-spacing:0; color:#3b82f6;">
                                — sovrascrive le impostazioni globali per questo alunno
                            </span>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <!-- FIRMA 1 -->
                            <div>
                                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;margin-bottom:8px;">
                                    <input type="checkbox" name="asf1" <?php echo $a_sf1 ? 'checked' : ''; ?>>
                                    <span>FIRMA 1</span>
                                </label>
                                <input type="text" name="aal1" value="<?php echo h($a_l1); ?>"
                                       placeholder="<?php echo h($gl['l1'] ?? 'Es. Il Coordinatore'); ?>"
                                       style="margin-bottom:6px; font-size:12px;"
                                       title="Lascia vuoto per usare il valore globale">
                                <select name="aaft1" id="<?php echo $fid; ?>_ft1"
                                        onchange="toggleAlNome('<?php echo $fid; ?>_n1')"
                                        style="width:100%; padding:7px 8px; border:1px solid var(--border); border-radius:7px; font-size:12px; color:var(--text); margin-bottom:6px;">
                                    <option value="autografa"   <?php echo $a_ft1==='autografa'  ?'selected':''; ?>>✍️ Firma autografa</option>
                                    <option value="digitale"    <?php echo $a_ft1==='digitale'   ?'selected':''; ?>>🖊️ Firma digitale</option>
                                    <option value="sostitutiva" <?php echo $a_ft1==='sostitutiva'?'selected':''; ?>>📋 Sostitutiva art. 3 c.2 D.Lgs. 39/93</option>
                                </select>
                                <div id="<?php echo $fid; ?>_n1" style="<?php echo $a_ft1==='autografa'?'display:none':''; ?>">
                                    <input type="text" name="aan1" value="<?php echo h($a_n1); ?>"
                                           placeholder="Nome firmatario" style="font-size:12px;">
                                </div>
                            </div>
                            <!-- FIRMA 2 -->
                            <div>
                                <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;margin-bottom:8px;">
                                    <input type="checkbox" name="asf2" <?php echo $a_sf2 ? 'checked' : ''; ?>>
                                    <span>FIRMA 2</span>
                                </label>
                                <input type="text" name="aal2" value="<?php echo h($a_l2); ?>"
                                       placeholder="<?php echo h($gl['l2'] ?? 'Es. Il Dirigente'); ?>"
                                       style="margin-bottom:6px; font-size:12px;"
                                       title="Lascia vuoto per usare il valore globale">
                                <select name="aaft2" id="<?php echo $fid; ?>_ft2"
                                        onchange="toggleAlNome('<?php echo $fid; ?>_n2')"
                                        style="width:100%; padding:7px 8px; border:1px solid var(--border); border-radius:7px; font-size:12px; color:var(--text); margin-bottom:6px;">
                                    <option value="autografa"   <?php echo $a_ft2==='autografa'  ?'selected':''; ?>>✍️ Firma autografa</option>
                                    <option value="digitale"    <?php echo $a_ft2==='digitale'   ?'selected':''; ?>>🖊️ Firma digitale</option>
                                    <option value="sostitutiva" <?php echo $a_ft2==='sostitutiva'?'selected':''; ?>>📋 Sostitutiva art. 3 c.2 D.Lgs. 39/93</option>
                                </select>
                                <div id="<?php echo $fid; ?>_n2" style="<?php echo $a_ft2==='autografa'?'display:none':''; ?>">
                                    <input type="text" name="aan2" value="<?php echo h($a_n2); ?>"
                                           placeholder="Nome firmatario" style="font-size:12px;">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- ─────────────────────────────────────────────────────── -->

                </div><!-- /.al-card-body -->
            </div><!-- /.al-card -->
            </form>
            <?php endforeach; ?>

            <!-- NUOVO ALUNNO -->
            <div class="al-card" style="background:#f0fdf4; border-color:#86efac;">
                <div style="font-size:12px; font-weight:700; color:var(--s); margin-bottom:12px;">＋ Nuovo Alunno</div>
                <form method="post">
                <div class="al-row">
                    <div class="al-field" style="flex:2; min-width:160px;">
                        <label class="al-label">Nome / Identificativo</label>
                        <input type="text" name="an" placeholder="Nome Cognome" required>
                    </div>
                    <div class="al-field" style="flex:2; min-width:160px;">
                        <label class="al-label">Titolo documento</label>
                        <input type="text" name="atit" placeholder="PIANO DIDATTICO PERSONALIZZATO">
                    </div>
                    <div class="al-field" style="flex:0 0 100px;">
                        <label class="al-label" title="Sostituisce 'Alunno' nell'intestazione">Etich. nome</label>
                        <input type="text" name="albl_nome" placeholder="Alunno">
                    </div>
                    <div class="al-field" style="flex:0 0 100px;">
                        <label class="al-label" title="Sostituisce 'Classe' nell'intestazione">Etich. gruppo</label>
                        <input type="text" name="albl_gruppo" placeholder="Classe">
                    </div>
                    <div class="al-field" style="flex:0 0 80px;">
                        <label class="al-label">Classe</label>
                        <input type="text" name="ac" placeholder="Es. 2A">
                    </div>
                    <div class="al-field" style="flex:0 0 80px;">
                        <label class="al-label">Genere</label>
                        <select name="agenere" style="padding:9px 8px; border:1px solid var(--border); border-radius:7px; font-size:13px; width:100%;">
                            <option value="M">M</option>
                            <option value="F">F</option>
                        </select>
                    </div>
                    <div class="al-field" style="flex:0 0 150px;">
                        <label class="al-label">Scadenza</label>
                        <input type="date" name="as" value="<?php echo date('Y-12-31'); ?>">
                    </div>
                </div>
                <div class="al-row" style="margin-top:10px;">
                    <div class="al-field" style="flex:0 0 160px;">
                        <label class="al-label">Password</label>
                        <input type="text" name="ak" placeholder="es. mario2026" pattern="[A-Za-z0-9]+" title="Solo lettere e numeri">
                        <input type="hidden" name="ak_old" value="">
                    </div>
                    <div class="al-field" style="flex:3; min-width:200px;">
                        <label class="al-label">Materie (separate da virgola)</label>
                        <textarea name="am" rows="2" placeholder="ITALIANO, STORIA, MATEMATICA..." style="font-size:12px; min-height:unset; resize:vertical;"></textarea>
                    </div>
                    <div class="al-field" style="flex:0 0 200px;">
                        <label class="al-label">Firme nel documento</label>
                        <?php if(!empty($db['settings']['l1'])): ?>
                        <label style="display:flex;align-items:center;gap:6px;font-size:12px;margin-bottom:4px;cursor:pointer;">
                            <input type="checkbox" name="asf1" checked>
                            <span style="color:var(--text)"><?php echo h($db['settings']['l1']); ?></span>
                        </label>
                        <?php endif; ?>
                        <?php if(!empty($db['settings']['l2'])): ?>
                        <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;">
                            <input type="checkbox" name="asf2" checked>
                            <span style="color:var(--text)"><?php echo h($db['settings']['l2']); ?></span>
                        </label>
                        <?php endif; ?>
                    </div>
                    <div class="al-field" style="flex:0 0 110px; justify-content:flex-end;">
                        <label class="al-label">&nbsp;</label>
                        <button type="submit" name="save_al" class="btn btn-create btn-sm" style="width:100%;">＋ Crea</button>
                    </div>
                </div>
                <p style="font-size:11px; color:#64748b; margin-top:10px;">
                    💡 I PIN per materia possono essere impostati dopo aver creato l'alunno, aprendo il pannello di modifica.
                </p>
                </form>
            </div>
        </div>
    </div>

    <!-- 2. BACKUP -->
    <div class="scard">
        <div class="scard-head">
            <div class="icon icon-amber">🗂️</div>
            <div style="flex:1;"><h3>Log Backup e Ripristino</h3><div class="sub">Ultimi 15 salvataggi automatici — generati ad ogni modifica del documento</div></div>
            <?php $bk_count = count(glob($bk_dir."*.json") ?: []); if ($bk_count > 0): ?>
            <a href="?reset_bk=1" class="btn btn-del btn-sm"
               style="align-self:center; margin-left:auto;"
               onclick="return confirm('Eliminare tutti i <?php echo $bk_count; ?> backup?\nQuesta operazione non è reversibile.')">
                🗑️ Svuota backup (<?php echo $bk_count; ?>)
            </a>
            <?php endif; ?>
        </div>
        <div class="scard-body">
        <?php $files=glob($bk_dir."*.json"); if(empty($files)): ?>
            <p style="color:var(--muted);font-size:13px;text-align:center;padding:20px 0;">📭 Nessun backup disponibile al momento.</p>
        <?php else: usort($files,fn($a,$b)=>filemtime($b)-filemtime($a)); ?>
        <div class="backup-list">
            <?php foreach(array_slice($files,0,15) as $f):
                $fn=basename($f); $parts=explode('_',$fn,3); $target=$parts[2]??''; $size=round(filesize($f)/1024,1);
            ?>
            <div class="backup-row">
                <span class="backup-time"><?php echo date("d/m/Y H:i",filemtime($f)); ?></span>
                <span class="backup-file"><strong><?php echo h($target); ?></strong> &mdash; <?php echo $size; ?> KB</span>
                <a href="?restore=<?php echo urlencode($fn); ?>&target=<?php echo urlencode($target); ?>"
                   class="btn btn-amber btn-sm"
                   onclick="return confirm('Ripristinare il backup del <?php echo date('d/m/Y H:i',filemtime($f)); ?>?\nI dati attuali verranno sovrascritti.')">
                    ↩ Ripristina
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        </div>
    </div>

</div>
<?php endif; ?>

<div class="footer">
    Sviluppo: Sebastiano Basile per il 3° I.C. Capuana-de Amicis.it © 2026
    &nbsp;|&nbsp; <a href="index.php">← Torna al PDP</a>
</div>

<script>
function toggleNome(n) {
    const v = document.getElementById('ft'+n).value;
    document.getElementById('nome'+n+'-wrap').style.display = (v === 'autografa') ? 'none' : 'block';
}
function toggleAlNome(wrapId) {
    // wrapId è l'id del select; il div nome ha lo stesso id con suffisso già passato
    // In realtà passiamo direttamente l'id del div nome
    const sel = event.target;
    const wrap = document.getElementById(wrapId);
    if (wrap) wrap.style.display = (sel.value === 'autografa') ? 'none' : 'block';
}
function togglePw(id,eyeId){const i=document.getElementById(id),e=document.getElementById(eyeId);i.type=i.type==='password'?'text':'password';e.textContent=i.type==='password'?'👁':'🙈';}
function copyPw(pw){navigator.clipboard.writeText(pw).then(()=>showToast('Password copiata: '+pw)).catch(()=>prompt('Copia la password:',pw));}
function showToast(msg){const t=document.createElement('div');t.textContent=msg;Object.assign(t.style,{position:'fixed',bottom:'24px',right:'24px',background:'#1e293b',color:'#fff',padding:'12px 20px',borderRadius:'8px',fontSize:'13px',fontWeight:'600',boxShadow:'0 4px 12px rgba(0,0,0,.2)',zIndex:'9999',opacity:'0',transition:'opacity .25s'});document.body.appendChild(t);requestAnimationFrame(()=>t.style.opacity='1');setTimeout(()=>{t.style.opacity='0';setTimeout(()=>t.remove(),300);},2500);}

// ── Schede collassabili ──────────────────────────────────────────────────
function toggleCard(id) {
    document.getElementById(id).classList.toggle('open');
}

// ── Occhiolino password nell'header ─────────────────────────────────────
function toggleHeaderPw(codeId, btn) {
    const el = document.getElementById(codeId);
    const vis = el.classList.toggle('visible');
    btn.textContent = vis ? '🙈' : '👁';
    btn.title = vis ? 'Nascondi password' : 'Mostra password';
}

// ── Occhiolino PIN per singola materia ───────────────────────────────────
function togglePinEye(inputId, btn) {
    const inp = document.getElementById(inputId);
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.textContent = '🙈';
        btn.title = 'Nascondi PIN';
    } else {
        inp.type = 'password';
        btn.textContent = '👁';
        btn.title = 'Mostra PIN';
    }
}

// ── Rimozione PIN ────────────────────────────────────────────────────────
function clearPin(inputId, btn) {
    if (!confirm('Rimuovere il PIN da questa materia? Diventerà liberamente modificabile.')) return;
    const inp = document.getElementById(inputId);
    inp.value = '__CLEAR__';
    inp.type  = 'text';
    inp.classList.remove('pin-has');
    inp.placeholder = 'PIN rimosso — salva per confermare';
    inp.style.borderColor = '#e74c3c';
    btn.style.display = 'none';
    showToast('PIN rimosso — clicca Salva per confermare.');
}
</script>
<!-- ══ MODALE PROMEMORIA PIN ══════════════════════════════════════════════ -->
<div id="pinReminderOverlay" onclick="if(event.target===this) closePinReminder()">
    <div class="pr-modal">
        <div class="pr-modal-head">
            <span style="font-size:20px;">📋</span>
            <h2 id="prTitle">Promemoria PIN — Alunno</h2>
            <button class="pr-modal-close" onclick="closePinReminder()" title="Chiudi">✕</button>
        </div>
        <div class="pr-modal-body">
            <div class="pr-tabs">
                <button class="pr-tab active" onclick="prTab('stampa',this)">✂️ Tagliandi stampabili</button>
                <button class="pr-tab" onclick="prTab('email',this)">📧 Email</button>
                <button class="pr-tab" onclick="prTab('wa',this)">💬 WhatsApp</button>
            </div>

            <!-- TAB: TAGLIANDI -->
            <div id="tab-stampa" class="pr-tab-content active">
                <div class="pr-actions">
                    <button class="btn btn-blue btn-sm" onclick="window.print()">🖨️ Stampa / Salva PDF</button>
                    <span style="font-size:12px;color:var(--muted);align-self:center;">Stampa e ritaglia lungo le linee tratteggiate</span>
                </div>
                <div class="slip-grid" id="prSlipGrid"></div>
            </div>

            <!-- TAB: EMAIL -->
            <div id="tab-email" class="pr-tab-content">
                <p style="font-size:12px;color:var(--muted);margin-bottom:10px;">
                    Testo pronto da copiare e incollare nella tua email. Personalizza i destinatari e il mittente.
                </p>
                <textarea class="pr-textarea" id="prEmailText" readonly></textarea>
                <button class="pr-copy-btn" onclick="prCopy('prEmailText','prCopyEmail')" id="prCopyEmail">📋 Copia testo email</button>
            </div>

            <!-- TAB: WHATSAPP -->
            <div id="tab-wa" class="pr-tab-content">
                <p style="font-size:12px;color:var(--muted);margin-bottom:10px;">
                    Testo ottimizzato per WhatsApp. Invialo singolarmente a ciascun docente.
                </p>
                <div id="prWaCards"></div>
            </div>
        </div>
    </div>
</div>

<script>
// ── Dati correnti della modale ───────────────────────────────────────────
let _prCurrent = null;

function openPinReminder(data) {
    _prCurrent = data;
    document.getElementById('prTitle').textContent = '📋 Promemoria PIN — ' + data.nome + ' (' + data.classe + ')';

    const url = window.location.origin + window.location.pathname.replace('admin.php','') + 'index.php';

    // ── Tagliandi ──────────────────────────────────────────────────────
    const grid = document.getElementById('prSlipGrid');
    grid.innerHTML = '';
    data.pins.forEach(p => {
        grid.innerHTML += `
        <div class="slip">
            <span class="slip-scissors">✂️</span>
            <div class="slip-mat">📚 ${p.mat}</div>
            <div class="slip-row"><span>Alunno:</span><strong>${data.nome}</strong></div>
            <div class="slip-row"><span>Classe:</span><strong>${data.classe}</strong></div>
            <div class="slip-row"><span>Documento:</span><strong>${data.titolo}</strong></div>
            <div class="slip-pin">${p.pin}</div>
            <div class="slip-row" style="font-size:11px;"><span>Password accesso:</span><code style="font-size:11px;">${data.pw}</code></div>
            <div class="slip-url">🔗 ${url}</div>
        </div>`;
    });

    // ── Email ──────────────────────────────────────────────────────────
    const pinLines = data.pins.map(p => `  • ${p.mat.padEnd(20,' ')} → PIN: ${p.pin}`).join('\n');
    const emailText =
`Oggetto: PIN di accesso al ${data.titolo} — ${data.nome} (${data.classe})

Gentile docente,

di seguito trovate le credenziali per accedere e compilare il documento
"${data.titolo}" relativo all'alunno ${data.nome} (${data.classe}).

🔗 Link al documento:
${url}

🔑 Password di accesso: ${data.pw}

🔐 PIN per materia (da inserire per sbloccare il campo di competenza):
${pinLines}

Le materie senza PIN sono liberamente modificabili dopo l'accesso.

Grazie per la collaborazione.
Il coordinatore di classe`;

    document.getElementById('prEmailText').value = emailText;

    // ── WhatsApp ───────────────────────────────────────────────────────
    const waContainer = document.getElementById('prWaCards');
    waContainer.innerHTML = '';
    data.pins.forEach(p => {
        const waText =
`📚 *${p.mat}* — ${data.nome} (${data.classe})

Ciao! Ti invio le credenziali per compilare il *${data.titolo}*:

🔗 ${url}
🔑 Password: \`${data.pw}\`
🔐 PIN ${p.mat}: \`${p.pin}\`

Accedi, inserisci la password, poi clicca sul lucchetto della tua materia e inserisci il PIN. Grazie! 🙏`;

        const safeId = 'wa_' + p.mat.replace(/[^A-Za-z0-9]/g,'_');
        waContainer.innerHTML += `
        <div style="margin-bottom:16px;">
            <div style="font-size:12px;font-weight:700;color:var(--p);margin-bottom:6px;">📚 ${p.mat}</div>
            <textarea class="pr-textarea" id="${safeId}" readonly style="min-height:160px;">${waText}</textarea>
            <div style="display:flex;gap:8px;margin-top:8px;">
                <button class="pr-copy-btn" onclick="prCopy('${safeId}','cpbtn_${safeId}')" id="cpbtn_${safeId}">📋 Copia</button>
                <a href="https://wa.me/?text=${encodeURIComponent(waText)}" target="_blank"
                   class="pr-copy-btn" style="text-decoration:none;display:inline-flex;align-items:center;gap:4px;background:#25d366;">
                    💬 Apri WhatsApp
                </a>
            </div>
        </div>`;
    });

    document.getElementById('pinReminderOverlay').classList.add('show');
    // Torna sempre alla prima tab
    prTab('stampa', document.querySelector('.pr-tab'));
}

function closePinReminder() {
    document.getElementById('pinReminderOverlay').classList.remove('show');
}

function prTab(name, btn) {
    document.querySelectorAll('.pr-tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.pr-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

function prCopy(taId, btnId) {
    const ta = document.getElementById(taId);
    navigator.clipboard.writeText(ta.value).then(() => {
        const btn = document.getElementById(btnId);
        const orig = btn.textContent;
        btn.textContent = '✅ Copiato!';
        btn.style.background = 'var(--s)';
        setTimeout(() => { btn.textContent = orig; btn.style.background = ''; }, 2000);
    }).catch(() => {
        ta.select();
        document.execCommand('copy');
        showToast('Testo copiato!');
    });
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closePinReminder();
});
</script>
</body>
</html>
