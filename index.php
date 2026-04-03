<?php
/**
 * Titanium PDP V3.6.0 — Con PIN per Materia e promemoria (3 Aprile 2026)
 */
session_start();
$db_dir = "database/"; $bk_dir = $db_dir . "backups/";
if (!is_dir($bk_dir)) mkdir($bk_dir, 0777, true);
$config_file = $db_dir . "config.json";

if (!file_exists($config_file)) {
    $default = ["settings" => ["l1" => "Il Coordinatore", "l2" => "Il Dirigente Scolastico", "sf1" => 1, "sf2" => 1], "alunni" => []];
    file_put_contents($config_file, json_encode($default, JSON_PRETTY_PRINT));
}

$db_global = json_decode(file_get_contents($config_file), true);
$alert_msg = ""; $alert_type = "";

// Login / Logout
if (isset($_POST['l_pass'])) {
    $pw = trim($_POST['l_pass']);
    if (isset($db_global['alunni'][$pw])) {
        if (date('Y-m-d') > $db_global['alunni'][$pw]['scadenza']) {
            $alert_msg = "ACCESSO BLOCCATO: I termini sono scaduti il " . date('d/m/Y', strtotime($db_global['alunni'][$pw]['scadenza']));
            $alert_type = "error";
        } else {
            $_SESSION['auth'] = true;
            $_SESSION['key'] = $pw;
            // Reset materie sbloccate ad ogni nuovo login
            $_SESSION['unlocked'][$pw] = [];
        }
    } else { $alert_msg = "Password non valida."; $alert_type = "error"; }
}
if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }

$auth = isset($_SESSION['auth'], $db_global['alunni'][$_SESSION['key'] ?? '']);

if ($auth) {
    $cfg = $db_global['alunni'][$_SESSION['key']];
    $file_path = $db_dir . $cfg['file'];
    $stored = file_exists($file_path) ? json_decode(file_get_contents($file_path), true) : [];
    // Errore PIN passato via sessione (redirect da POST)
    $pin_err_msg = ''; $pin_err_mat = '';
    if (!empty($_SESSION['pin_error'])) {
        $pin_err_msg = $_SESSION['pin_error'];
        $pin_err_mat = $_SESSION['pin_error_mat'] ?? '';
        unset($_SESSION['pin_error'], $_SESSION['pin_error_mat']);
    }

    // ── Gestione sblocco materia via form POST (100% compatibile tutti i browser) ──
    if (isset($_POST['action']) && $_POST['action'] === 'unlock_materia') {
        $mat    = trim($_POST['materia'] ?? '');
        $pin_in = trim($_POST['pin'] ?? '');
        $pin_ok = $cfg['pin_materie'][$mat] ?? '';
        $redirect = strtok($_SERVER['REQUEST_URI'], '?');

        if (in_array($mat, $cfg['materie']) && ($pin_ok === '' || $pin_in === $pin_ok)) {
            if (!isset($_SESSION['unlocked'][$_SESSION['key']])) {
                $_SESSION['unlocked'][$_SESSION['key']] = [];
            }
            if (!in_array($mat, $_SESSION['unlocked'][$_SESSION['key']])) {
                $_SESSION['unlocked'][$_SESSION['key']][] = $mat;
            }
        } else {
            $_SESSION['pin_error']     = 'PIN non corretto. Riprova.';
            $_SESSION['pin_error_mat'] = $mat;
        }
        session_write_close();
        header('Location: ' . $redirect);
        exit;
    }

    // ── Salvataggio dati ─────────────────────────────────────────────────────
    if (isset($_POST['salva_dati'])) {
        $check_empty = true;
        foreach (($_POST['c'] ?? []) as $v) { if (trim($v) !== '') { $check_empty = false; break; } }

        if ($check_empty) {
            $alert_msg = "ERRORE: Impossibile salvare un documento vuoto.";
            $alert_type = "error";
        } else {
            if (file_exists($file_path)) copy($file_path, $bk_dir . date('Ymd_His') . "_" . $cfg['file']);
            $data = $stored;
            $data['info'] = ['luogo' => strip_tags($_POST['luogo']), 'data' => $_POST['data_doc']];
            $unlocked_list = $_SESSION['unlocked'][$_SESSION['key']] ?? [];
            $pin_materie   = $cfg['pin_materie'] ?? [];

            foreach ($cfg['materie'] as $m) {
                $has_pin   = isset($pin_materie[$m]) && $pin_materie[$m] !== '';
                $is_unlocked = !$has_pin || in_array($m, $unlocked_list);

                if (!$is_unlocked) {
                    // Materia bloccata: conserva i dati esistenti senza modifiche
                    $data['c'][$m]  = $stored['c'][$m]  ?? '';
                    $data['f'][$m]  = $stored['f'][$m]  ?? '';
                    $data['sd'][$m] = $stored['sd'][$m] ?? 0;
                    $data['dt'][$m] = $stored['dt'][$m] ?? '';
                    continue;
                }

                $prev_c = $stored['c'][$m] ?? '';
                $data['c'][$m]  = $_POST['c'][$m] ?? '';
                $data['f'][$m]  = strip_tags($_POST['f'][$m] ?? '');
                $data['sd'][$m] = isset($_POST['sd'][$m]) ? 1 : 0;
                if ($data['c'][$m] !== $prev_c || empty($data['dt'][$m])) {
                    $data['dt'][$m] = date('d/m/Y \a\l\l\e H:i');
                }
            }
            file_put_contents($file_path, json_encode($data, JSON_PRETTY_PRINT));
            $alert_msg = "Dati salvati correttamente."; $alert_type = "success";
            $stored = $data;
        }
    }
}

// Helper per output sicuro
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function renderMarkdown($s) {
    $s = htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $s = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $s);
    return $s;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $auth ? h(str_replace(' ', '_', $cfg['nome'])) : 'Accesso PDP'; ?></title>
    <style>
        :root { --p:#2c3e50; --s:#27ae60; --e:#e74c3c; --bg:#f8fafc; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: #1e293b; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1); }
        .no-print { display: block; } .only-print { display: none; }
        .card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 25px; transition: border-color .2s, box-shadow .2s; }
        .card:hover { border-color: #cbd5e1; box-shadow: 0 10px 15px -3px rgba(0,0,0,.05); }
        .card.modified { border-left: 3px solid #f59e0b; border-radius: 8px; }
        .card.locked { border-left: 3px solid #94a3b8; background: #f8fafc; }
        .card.unlocked-now { border-left: 3px solid var(--s); }
        header { text-align: center; border-bottom: 3px solid var(--p); margin-bottom: 30px; }
        textarea { width: 100%; min-height: 120px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px; font-family: inherit; font-size: 15px; resize: vertical; transition: border-color .2s; }
        textarea:focus { outline: none; border-color: var(--p); box-shadow: 0 0 0 3px rgba(44,62,80,.1); }
        textarea:disabled { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; resize: none; }
        .rev-date { font-size: 11px; color: #64748b; font-weight: 500; margin-top: 5px; display: block; }
        .char-count { font-size: 10px; color: #94a3b8; text-align: right; margin-top: 3px; }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center; font-weight: bold; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .btn { padding: 10px 20px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; text-decoration: none; transition: opacity .2s; display: inline-block; }
        .btn:hover { opacity: .85; }
        .btn-p { background: var(--p); color: #fff; }
        .btn-s { background: var(--s); color: #fff; width: 100%; padding: 20px; font-size: 18px; margin-top: 30px; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; gap: 10px; flex-wrap: wrap; }
        .shortcut-hint { font-size: 11px; color: #94a3b8; margin-top: 6px; text-align: center; }
        .bold-btn {
            display: none;
            position: absolute; right: 42px; bottom: 8px;
            background: #f1f5f9; border: 1px solid #cbd5e1;
            border-radius: 4px; padding: 2px 8px;
            font-size: 12px; font-weight: 700; cursor: pointer;
            color: #475569; line-height: 1.6;
            transition: background .15s;
            user-select: none;
        }
        .bold-btn:hover { background: #e2e8f0; color: #1e293b; }
        .card:focus-within .bold-btn { display: block; }

        /* ── Bottone salva per card ─────────────────────────────── */
        .card-save-btn {
            display: none;
            align-items: center; gap: 4px;
            padding: 4px 11px; font-size: 12px; font-weight: 700;
            background: #f59e0b; color: #fff; border: none;
            border-radius: 6px; cursor: pointer; white-space: nowrap;
            transition: background .15s, transform .1s, opacity .2s;
            animation: pulseIn .3s ease;
        }
        .card-save-btn:hover { background: #d97706; transform: scale(1.04); }
        .card-save-btn:active { transform: scale(.97); }
        .card.modified .card-save-btn { display: inline-flex; }
        @keyframes pulseIn {
            from { opacity: 0; transform: scale(.85); }
            to   { opacity: 1; transform: scale(1); }
        }
        .print-text b { font-weight: 700; }

        /* ── PIN Lock UI ───────────────────────────────────────────── */
        .lock-badge {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 11px; font-weight: 700; padding: 3px 10px;
            border-radius: 20px; cursor: pointer;
            user-select: none; transition: background .15s, transform .1s;
        }
        .lock-badge:active { transform: scale(.96); }
        .lock-badge.locked  { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .lock-badge.unlocked{ background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        /* Contenuto bloccato: visibile a tutti, modificabile solo con PIN */
        .lock-readonly {
            position: relative;
            border: 1px solid #e2e8f0; border-radius: 6px;
            background: #f8fafc; padding: 12px 14px;
            min-height: 80px; cursor: default;
        }
        .lock-readonly-text {
            font-family: inherit; font-size: 15px; line-height: 1.6;
            color: #374151; white-space: pre-wrap; word-break: break-word;
        }
        .lock-readonly-text:empty::before {
            content: 'Nessun contenuto inserito.';
            color: #cbd5e1; font-style: italic; font-size: 13px;
        }
        .lock-unlock-bar {
            display: flex; align-items: center; justify-content: space-between;
            margin-top: 10px; gap: 10px;
        }
        .lock-unlock-hint {
            font-size: 11px; color: #94a3b8; font-style: italic;
        }
        .lock-unlock-btn {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 13px; font-size: 12px; font-weight: 700;
            background: #f1f5f9; color: #475569;
            border: 1px solid #cbd5e1; border-radius: 6px;
            cursor: pointer; white-space: nowrap;
            transition: background .15s, border-color .15s, color .15s;
        }
        .lock-unlock-btn:hover { background: #e2e8f0; border-color: #94a3b8; color: #1e293b; }

        /* ── Modal PIN ─────────────────────────────────────────────── */
        #pinModal {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.45); z-index: 9999;
            align-items: center; justify-content: center;
        }
        #pinModal.show { display: flex; }
        .pin-card {
            background: #fff; border-radius: 14px; padding: 36px 32px;
            width: 340px; box-shadow: 0 20px 60px rgba(0,0,0,.2);
            text-align: center; position: relative;
        }
        .pin-card h3 { font-size: 1.1rem; color: var(--p); margin-bottom: 6px; }
        .pin-card p  { font-size: 13px; color: #64748b; margin-bottom: 20px; }
        .pin-input-wrap { position: relative; margin-bottom: 14px; }
        .pin-input-wrap input {
            width: 100%; padding: 13px 48px 13px 16px;
            border: 2px solid #e2e8f0; border-radius: 8px;
            font-size: 20px; letter-spacing: 4px;
            font-family: monospace; text-align: center;
            transition: border-color .2s;
        }
        .pin-input-wrap input:focus { outline: none; border-color: var(--p); }
        .pin-eye { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 18px; color: #94a3b8; }
        .pin-error { color: var(--e); font-size: 12px; font-weight: 600; min-height: 18px; margin-bottom: 10px; }
        .pin-actions { display: flex; gap: 10px; }
        .pin-actions .btn { flex: 1; padding: 12px; font-size: 14px; }
        .btn-unlock { background: var(--p); color: #fff; }
        .btn-cancel { background: #f1f5f9; color: #475569; }
        .pin-close { position: absolute; top: 12px; right: 16px; background: none; border: none; font-size: 20px; cursor: pointer; color: #94a3b8; }

        @media (max-width: 600px) {
            .container { padding: 20px; }
            .card-header { flex-direction: column; gap: 8px; }
        }
        .print-text { display: none; }

        @media print {
            .no-print   { display: none !important; }
            .only-print { display: block; }
            body        { padding: 0; background: #fff; margin: 0; }
            .container  { box-shadow: none; border: none; padding: 10px; max-width: 100%; }
            .card {
                border: 1px solid #d1d5db !important;
                border-radius: 8px !important;
                padding: 10px 14px !important;
                margin-bottom: 10px !important;
                box-shadow: none !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            .card.modified  { border-left: 1px solid #d1d5db !important; }
            .card.locked    { border-left: 1px solid #d1d5db !important; background: #fff !important; }
            .card.unlocked-now { border-left: 1px solid #d1d5db !important; }
            .card-header    { margin-bottom: 6px !important; }
            .print-text {
                display: block !important;
                white-space: pre-wrap;
                font-family: inherit;
                font-size: 10.5pt;
                line-height: 1.55;
                color: #000;
                margin: 0; padding: 0;
                orphans: 3; widows: 3;
            }
            textarea { visibility: hidden !important; height: 0 !important; }
            .char-count { display: none !important; }
            .rev-date   { display: none !important; }
        }
    </style>
</head>
<body>
<div class="container">
<?php if (!$auth): ?>
    <div style="text-align:center; padding:50px 20px;">
        <h1 style="color:var(--p)">Area Riservata PDP</h1>
        <p style="color:#64748b">Piano Didattico Personalizzato</p>
        <form method="post" style="max-width:320px; margin:auto;">
            <div style="position:relative; margin-bottom:20px;">
                <input type="password" name="l_pass" id="l_pass" placeholder="Password Alunno"
                       style="width:100%; padding:15px; padding-right:48px; border-radius:8px; border:2px solid #e2e8f0; font-size:16px;"
                       required autofocus autocomplete="current-password">
                <button type="button" onclick="togglePw('l_pass','eye1')"
                        style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:20px; color:#94a3b8;"
                        title="Mostra/nascondi password"><span id="eye1">👁</span></button>
            </div>
            <button type="submit" class="btn btn-p" style="width:100%; padding:15px;">Accedi al Documento</button>
        </form>
        <?php if ($alert_msg): ?>
            <div class="alert alert-<?php echo $alert_type; ?>" style="margin-top:20px;"><?php echo h($alert_msg); ?></div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="toolbar no-print">
        <button onclick="stampa()" class="btn btn-p">📄 Stampa / Salva PDF</button>
        <div style="font-size:12px; color:#64748b; flex:1; text-align:center;">
            👤 <b><?php echo h($cfg['nome']); ?></b> &nbsp;·&nbsp; Accesso attivo fino al <?php echo date('d/m/Y', strtotime($cfg['scadenza'])); ?> alle 23:59
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <button type="submit" form="pdpForm" name="salva_dati" onclick="dirty=false"
                    style="background:var(--s); color:#fff; border:none; border-radius:6px; padding:8px 14px; font-size:13px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:5px;"
                    title="Verifica e Salva Revisione (Ctrl+S)">💾 Salva</button>
            <a href="?logout=1" class="btn" style="background:var(--e); color:#fff;">Esci</a>
        </div>
    </div>
    <?php
    // Firme: usa valori per-alunno con fallback ai globali
    $gs2 = $db_global['settings'];
    $af2 = $cfg['firme'] ?? [];
    function _fl($af, $gs, $k_al, $k_gl) { $v = $af[$k_al] ?? null; return ($v !== null && $v !== '') ? $v : ($gs[$k_gl] ?? ''); }
    $firme_attive = [];
    $f_sf1 = $af2['sf1'] ?? $cfg['sf1'] ?? $gs2['sf1'] ?? 1;
    $f_sf2 = $af2['sf2'] ?? $cfg['sf2'] ?? $gs2['sf2'] ?? 1;
    $f_l1  = _fl($af2, $gs2, 'al1', 'l1');
    $f_l2  = _fl($af2, $gs2, 'al2', 'l2');
    $f_ft1 = _fl($af2, $gs2, 'aft1', 'ft1') ?: 'autografa';
    $f_ft2 = _fl($af2, $gs2, 'aft2', 'ft2') ?: 'autografa';
    $f_n1  = _fl($af2, $gs2, 'an1', 'n1');
    $f_n2  = _fl($af2, $gs2, 'an2', 'n2');
    if ($f_sf1 && !empty($f_l1)) $firme_attive[] = h($f_l1);
    if ($f_sf2 && !empty($f_l2)) $firme_attive[] = h($f_l2);
    ?>
    <?php if (!empty($firme_attive)): ?>
    <div class="no-print" style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:10px 16px; margin-bottom:20px; font-size:12px; color:#1e40af; display:flex; align-items:center; gap:10px;">
        <span style="font-size:16px;">✍️</span>
        <span>Firme attive nel documento: <?php foreach($firme_attive as $i => $f): ?><strong><?php echo $f; ?></strong><?php if($i < count($firme_attive)-1) echo ' &nbsp;·&nbsp; '; endforeach; ?></span>
    </div>
    <?php endif; ?>

    <header>
        <h1 style="margin:0; font-size:1.6rem; letter-spacing:-.025em;"><?php echo h($cfg['titolo'] ?? 'PIANO DIDATTICO PERSONALIZZATO'); ?></h1>
        <?php
        $lbl_nome   = !empty($cfg['lbl_nome'])   ? $cfg['lbl_nome']   : 'Alunno';
        $lbl_gruppo = !empty($cfg['lbl_gruppo'])  ? $cfg['lbl_gruppo'] : 'Classe';
        ?>
        <p style="color:#64748b; font-weight:500; margin:8px 0;">
            <?php echo h($lbl_nome); ?>: <strong style="color:var(--p)"><?php echo h($cfg['nome']); ?></strong> &nbsp;|&nbsp; <?php echo h($lbl_gruppo); ?>: <?php echo h($cfg['classe']); ?>
        </p>
    </header>

    <?php if ($alert_msg): ?>
        <div class="alert alert-<?php echo $alert_type; ?> no-print"><?php echo h($alert_msg); ?></div>
    <?php endif; ?>

    <?php
    // Calcola stato lock per tutte le materie
    $pin_materie   = $cfg['pin_materie'] ?? [];
    $unlocked_list = $_SESSION['unlocked'][$_SESSION['key']] ?? [];
    $has_any_pin   = false;
    foreach ($cfg['materie'] as $m) {
        if (!empty($pin_materie[$m])) { $has_any_pin = true; break; }
    }
    ?>

    <?php if ($has_any_pin): ?>
    <div class="no-print" style="background:#fefce8; border:1px solid #fde68a; border-radius:8px; padding:10px 16px; margin-bottom:20px; font-size:12px; color:#854d0e; display:flex; align-items:center; gap:10px;">
        <span style="font-size:16px;">🔐</span>
        <span>I contenuti sono <strong>visibili a tutti</strong>. Se 🔒 Bloccato, per scrivere o modificare, clicca <em>🔑 Sblocca per modificare</em> e inserisci il PIN.</span>
    </div>
    <?php endif; ?>
    <?php if (!empty($pin_err_msg)): ?>
    <script>
    // Riapre il modal con errore dopo redirect POST
    window.addEventListener('DOMContentLoaded', function() {
        openPinModal(<?php echo json_encode($pin_err_mat); ?>);
        document.getElementById('pinError').textContent = <?php echo json_encode($pin_err_msg); ?>;
    });
    </script>
    <?php endif; ?>

    <form method="post" id="pdpForm">
        <?php foreach ($cfg['materie'] as $m):
            $has_pin   = isset($pin_materie[$m]) && $pin_materie[$m] !== '';
            $is_unlocked = !$has_pin || in_array($m, $unlocked_list);
            $card_class = $has_pin ? ($is_unlocked ? 'card unlocked-now' : 'card locked') : 'card';
        ?>
        <div class="<?php echo $card_class; ?>" id="card-<?php echo h($m); ?>">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; gap:12px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-weight:800; text-transform:uppercase; color:var(--p); font-size:1rem;"><?php echo h($m); ?></span>
                    <span class="rev-date no-print" style="margin:0;">Ultima revisione: <?php echo h($stored['dt'][$m] ?? 'Mai salvata'); ?></span>

                    <?php if ($has_pin): ?>
                    <button type="button"
                            class="lock-badge no-print <?php echo $is_unlocked ? 'unlocked' : 'locked'; ?>"
                            id="lockbtn-<?php echo h($m); ?>"
                            onclick="<?php echo $is_unlocked ? '' : "openPinModal('".h($m)."')"; ?>"
                            title="<?php echo $is_unlocked ? 'Materia sbloccata per questa sessione' : 'Clicca per inserire il PIN e sbloccare'; ?>"
                            <?php echo $is_unlocked ? 'style="cursor:default;"' : ''; ?>>
                        <?php echo $is_unlocked ? '🔓 Sbloccato' : '🔒 Bloccato'; ?>
                    </button>
                    <?php endif; ?>
                    <button type="button" class="card-save-btn no-print"
                            onclick="saveCard(this)"
                            title="Salva subito questa materia">💾 Salva</button>
                </div>

                <?php if ($is_unlocked): ?>
                <div class="no-print" style="text-align:right; flex-shrink:0;">
                    <input type="text" name="f[<?php echo h($m); ?>]"
                           value="<?php echo h($stored['f'][$m] ?? ''); ?>"
                           placeholder="Firma Digitale"
                           style="padding:6px; border:1px solid #ddd; border-radius:4px; font-size:12px; width:160px;">
                    <label style="font-size:11px; margin-left:8px; white-space:nowrap;">
                        <input type="checkbox" name="sd[<?php echo h($m); ?>]" <?php echo !empty($stored['sd'][$m]) ? 'checked' : ''; ?>>
                        Includi Data
                    </label>
                </div>
                <?php else: ?>
                <!-- Hidden inputs per preservare i valori esistenti durante il salvataggio -->
                <input type="hidden" name="f[<?php echo h($m); ?>]"  value="<?php echo h($stored['f'][$m]  ?? ''); ?>">
                <?php if (!empty($stored['sd'][$m])): ?>
                <input type="hidden" name="sd[<?php echo h($m); ?>]" value="1">
                <?php endif; ?>
                <?php endif; ?>

                <div class="only-print" style="font-size:12px; font-style:italic;">
                    <?php if (!empty($stored['f'][$m])): ?>
                        Firmato digitalmente da: <b><?php echo h($stored['f'][$m]); ?></b>
                        <?php if (!empty($stored['sd'][$m])): ?> il <?php echo h($stored['dt'][$m] ?? ''); ?><?php endif; ?>
                    <?php else: ?>
                        Firma autografa: ___________________________
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($is_unlocked): ?>
            <div style="position:relative;">
                <textarea name="c[<?php echo h($m); ?>]"
                          id="ta-<?php echo h($m); ?>" class="pdp-ta"
                          oninput="onEdit(this)"><?php echo h($stored['c'][$m] ?? ''); ?></textarea>
                <button type="button" class="bold-btn no-print"
                        onclick="applyBold('ta-<?php echo h($m); ?>')"
                        title="Grassetto (Ctrl+B)"><b>B</b></button>
            </div>
            <div class="print-text" id="pt-<?php echo h($m); ?>"><?php echo renderMarkdown($stored['c'][$m] ?? ''); ?></div>
            <div class="char-count no-print" id="cc-<?php echo h($m); ?>"></div>
            <?php else: ?>
            <!-- Contenuto visibile in sola lettura — modificabile solo con PIN -->
            <div class="lock-readonly no-print">
                <div class="lock-readonly-text"><?php echo h($stored['c'][$m] ?? ''); ?></div>
                <div class="lock-unlock-bar">
                    <span class="lock-unlock-hint" title="Scrivi e modifica sbloccando con PIN">
                        🔒 Visibile a tutti · modificabile solo con PIN
                    </span>
                    <button type="button" class="lock-unlock-btn"
                            onclick="openPinModal('<?php echo h($m); ?>')"
                            title="Scrivi e modifica sbloccando con PIN">
                        🔑 Sblocca per modificare
                    </button>
                </div>
            </div>
            <!-- Input nascosto: preserva valore al salvataggio -->
            <input type="hidden" name="c[<?php echo h($m); ?>]" value="<?php echo h($stored['c'][$m] ?? ''); ?>">
            <div class="print-text" id="pt-<?php echo h($m); ?>"><?php echo renderMarkdown($stored['c'][$m] ?? ''); ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div style="font-size:12px; line-height:1.6; color:#475569; background:#f1f5f9; padding:20px; border-radius:8px; border:1px solid #e2e8f0; margin:40px 0;">
            La validazione dei contenuti relativi a <b><?php echo h($cfg['nome']); ?></b>
            è attestata dalla firma (digitale o autografa) apposta in corrispondenza di ciascun campo. In assenza di firma il contenuto non è da ritenersi approvato.<br>
            La firma autografa in calce del Dirigente Scolastico, del Coordinatore o di altro soggetto autorizzato comporta la validazione implicita delle eventuali firme digitali presenti.
        </div>

        <div class="only-print" style="margin-top:40px;">
            <?php
            // Usa i valori per-alunno già calcolati sopra ($f_*)
            $gs    = $db_global['settings'];
            $show1 = $f_sf1 && !empty($f_l1);
            $show2 = $f_sf2 && !empty($f_l2);
            $ft1   = $f_ft1;
            $ft2   = $f_ft2;
            $n1    = $f_n1;
            $n2    = $f_n2;
            function firmaLabel($tipo, $ruolo, $nome_f) {
                if ($tipo === 'digitale')
                    return h($ruolo) . (!empty($nome_f) ? '<br><span style="font-size:9.5pt;">'.h($nome_f).'</span>' : '');
                if ($tipo === 'sostitutiva')
                    return h($ruolo) . (!empty($nome_f) ? '<br><span style="font-size:9.5pt;">'.h($nome_f).'</span>' : '') . '<br><span style="font-size:8.5pt;font-weight:normal;">(Firma sostitutiva ai sensi dell&#39;art. 3 c. 2 D.Lgs. 39/93)</span>';
                return h($ruolo); // autografa: solo ruolo
            }
            $cols = 1 + ($show1 ? 1 : 0) + ($show2 ? 1 : 0);
            ?>
            <div style="display:grid; grid-template-columns:repeat(<?php echo $cols; ?>, 1fr); align-items:end; gap:10px; margin-top:10px;">
                <div style="font-size:10pt; text-align:left; align-self:center; padding-bottom:8px;">
                    <?php echo h($stored['info']['luogo'] ?? 'Avola'); ?>, lì <?php echo date('d/m/Y', strtotime($stored['info']['data'] ?? date('Y-m-d'))); ?>
                </div>
                <?php if ($show1): ?>
                <div style="text-align:center;">
                    <?php if ($ft1 === 'autografa'): ?>
                        <div style="height:50px; border-bottom:1px solid #000;"></div>
                        <div style="padding-top:6px; font-size:10pt; line-height:1.4;"><?php echo firmaLabel($ft1, $f_l1, $n1); ?></div>
                    <?php elseif ($ft1 === 'digitale'): ?>
                        <div style="border-top:1px solid #000; padding-top:6px; font-size:10pt; line-height:1.4;"><?php echo firmaLabel($ft1, $f_l1, $n1); ?></div>
                    <?php else: ?>
                        <div style="padding-top:6px; font-size:10pt; line-height:1.4;"><?php echo firmaLabel($ft1, $f_l1, $n1); ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($show2): ?>
                <div style="text-align:center;">
                    <?php if ($ft2 === 'autografa'): ?>
                        <div style="height:50px; border-bottom:1px solid #000;"></div>
                        <div style="padding-top:6px; font-size:10pt; line-height:1.4;"><?php echo firmaLabel($ft2, $f_l2, $n2); ?></div>
                    <?php elseif ($ft2 === 'digitale'): ?>
                        <div style="border-top:1px solid #000; padding-top:6px; font-size:10pt; line-height:1.4;"><?php echo firmaLabel($ft2, $f_l2, $n2); ?></div>
                    <?php else: ?>
                        <div style="padding-top:6px; font-size:10pt; line-height:1.4;"><?php echo firmaLabel($ft2, $f_l2, $n2); ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="no-print" style="background:#f8fafc; padding:25px; border-radius:12px; border:2px dashed #e2e8f0; display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px;">
            <div>
                <label style="font-size:12px; font-weight:700;">Luogo Emissione:</label>
                <input type="text" name="luogo" value="<?php echo h($stored['info']['luogo'] ?? 'Avola'); ?>" style="width:100%; padding:10px; margin-top:5px; border:1px solid #ddd; border-radius:4px;">
            </div>
            <div>
                <label style="font-size:12px; font-weight:700;">Data Emissione:</label>
                <input type="date" name="data_doc" value="<?php echo h($stored['info']['data'] ?? date('Y-m-d')); ?>" style="width:100%; padding:10px; margin-top:5px; border:1px solid #ddd; border-radius:4px;">
            </div>
        </div>

        <button type="submit" name="salva_dati" id="saveBtn" class="btn btn-s no-print">💾 Verifica e Salva Revisione</button>
        <div class="shortcut-hint no-print">Puoi anche usare <kbd>Ctrl+S</kbd> per salvare rapidamente</div>
    </form>
<?php endif; ?>

    <div class="no-print" style="text-align:center; margin-top:50px; font-size:11px; color:#94a3b8; user-select:none;"
         ondblclick="window.open('admin.php','_blank')">
        Sviluppo: Sebastiano Basile per il <a href="https://www.3iccapuana.edu.it" target="_blank" style="color:#94a3b8; text-decoration:none;">3° I.C. Capuana-de Amicis</a> © 2026 – <a href="https://www.capuanadeamicis.it" target="_blank" style="color:#64748b; text-decoration:none;">capuanadeamicis.it</a>
        &nbsp;·&nbsp; v3.6.0 &nbsp;·&nbsp; <a href="https://github.com/sebastianobasile/pdp" target="_blank" style="color:#94a3b8; text-decoration:none;">GitHub</a>
    </div>
</div>

<!-- ── Modal PIN (form POST standard — compatibile Chrome/Safari/Firefox) ── -->
<div id="pinModal">
    <div class="pin-card">
        <button class="pin-close" type="button" onclick="closePinModal()" title="Chiudi">✕</button>
        <div style="font-size:36px; margin-bottom:12px;">🔐</div>
        <h3 id="pinModalTitle">Sblocca Materia</h3>
        <p id="pinModalSub">Inserisci il PIN per modificare questa materia.</p>
        <form id="pinForm" method="post" action="">
            <input type="hidden" name="action" value="unlock_materia">
            <input type="hidden" name="materia" id="pinMateriaField" value="">
            <div class="pin-input-wrap">
                <input type="password" id="pinInput" name="pin" placeholder="• • • •" maxlength="20"
                       autocomplete="one-time-code">
                <button type="button" class="pin-eye" onclick="togglePinEye()" id="pinEye">👁</button>
            </div>
            <div class="pin-error" id="pinError"></div>
            <div class="pin-actions">
                <button type="button" class="btn btn-cancel" onclick="closePinModal()">Annulla</button>
                <button type="submit" class="btn btn-unlock" id="pinConfirmBtn">🔓 Sblocca</button>
            </div>
        </form>
    </div>
</div>

<?php if ($auth): ?>
<script>
// ── Contatore caratteri, dirty flag, preview grassetto ──────────────────
let dirty = false;

function onEdit(ta) {
    dirty = true;
    const m = ta.name.replace(/^c\[|\]$/g, '');
    const card = ta.closest('.card');
    card && card.classList.add('modified');
    const cc = document.getElementById('cc-' + m);
    if (cc) cc.textContent = ta.value.length + ' caratteri';
    const pt = document.getElementById('pt-' + m);
    if (pt) pt.innerHTML = ta.value
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/\*\*(.+?)\*\*/gs, '<b>$1</b>');
}

document.querySelectorAll('textarea').forEach(ta => onEdit(ta));
dirty = false; // reset: l'init scan non deve marcare il form come modificato

window.addEventListener('beforeunload', e => {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
});
document.getElementById('saveBtn')?.addEventListener('click', () => dirty = false);

// ── Scorciatoie tastiera ─────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        document.getElementById('saveBtn')?.click();
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
        e.preventDefault();
        const ta = document.activeElement;
        if (ta && ta.tagName === 'TEXTAREA') applyBold(ta.id);
    }
    if (e.key === 'Escape') closePinModal();
});

// ── Grassetto Markdown ───────────────────────────────────────────────────
function applyBold(taId) {
    const ta = document.getElementById(taId);
    if (!ta) return;
    const start = ta.selectionStart, end = ta.selectionEnd;
    if (start === end) return;
    const sel = ta.value.substring(start, end);
    let newSel, offset;
    if (sel.startsWith('**') && sel.endsWith('**') && sel.length > 4) {
        newSel = sel.slice(2, -2); offset = -2;
    } else {
        newSel = '**' + sel + '**'; offset = 2;
    }
    ta.value = ta.value.substring(0, start) + newSel + ta.value.substring(end);
    ta.setSelectionRange(start, end + offset * 2);
    ta.dispatchEvent(new Event('input'));
}

<?php if ($alert_type === 'success'): ?>
dirty = false;
<?php endif; ?>

// ── PIN Modal (form POST standard, nessun fetch) ─────────────────────────
function openPinModal(materia) {
    document.getElementById('pinModalTitle').textContent = 'Sblocca: ' + materia;
    document.getElementById('pinMateriaField').value = materia;
    document.getElementById('pinInput').value = '';
    document.getElementById('pinError').textContent = '';
    document.getElementById('pinInput').type = 'password';
    document.getElementById('pinEye').textContent = '👁';
    document.getElementById('pinForm').action = window.location.pathname;
    document.getElementById('pinModal').classList.add('show');
    requestAnimationFrame(() => setTimeout(() => document.getElementById('pinInput').focus(), 50));
}

function closePinModal() {
    document.getElementById('pinModal').classList.remove('show');
}

function togglePinEye() {
    const inp = document.getElementById('pinInput');
    const eye = document.getElementById('pinEye');
    if (inp.type === 'password') { inp.type = 'text'; eye.textContent = '🙈'; }
    else { inp.type = 'password'; eye.textContent = '👁'; }
}

// Intercetta submit per validare il PIN prima dell'invio
document.getElementById('pinForm').addEventListener('submit', function(e) {
    const pin = document.getElementById('pinInput').value.trim();
    const errEl = document.getElementById('pinError');
    if (!pin) { e.preventDefault(); errEl.textContent = 'Inserisci il PIN.'; return; }
    dirty = false; // evita il popup "Vuoi uscire dal sito?"
    document.getElementById('pinConfirmBtn').textContent = '⏳ Verifica...';
    document.getElementById('pinConfirmBtn').disabled = true;
});

// Chiudi modal cliccando fuori
document.getElementById('pinModal').addEventListener('click', function(e) {
    if (e.target === this) closePinModal();
});

// ── Salvataggio rapido per card ─────────────────────────────────────────
function saveCard(btn) {
    dirty = false;
    // Feedback visivo sul bottone
    const orig = btn.innerHTML;
    btn.innerHTML = '⏳ Salvataggio...';
    btn.disabled = true;
    // Invia il form globale
    document.getElementById('saveBtn').click();
}

// ── Stampa / PDF ─────────────────────────────────────────────────────────
function prepareForPrint() {
    document.querySelectorAll('textarea.pdp-ta').forEach(ta => {
        const m = ta.id.replace('ta-', '');
        const pt = document.getElementById('pt-' + m);
        if (pt) pt.innerHTML = ta.value
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/\*\*(.+?)\*\*/gs, '<strong>$1</strong>')
            .replace(/\n/g, '<br>');
        ta.style.cssText = 'display:none!important;height:0!important;overflow:hidden!important;';
        ta.dataset.printing = '1';
    });
}

function restoreAfterPrint() {
    document.querySelectorAll('textarea[data-printing]').forEach(ta => {
        ta.style.cssText = '';
        delete ta.dataset.printing;
    });
}

window.addEventListener('beforeprint', prepareForPrint);
window.addEventListener('afterprint',  restoreAfterPrint);

function stampa() {
    prepareForPrint();
    window.print();
}
</script>
<?php endif; ?>
<script>
function togglePw(id, eyeId) {
    const inp = document.getElementById(id);
    const eye = document.getElementById(eyeId);
    if (inp.type === 'password') { inp.type = 'text'; eye.textContent = '🙈'; }
    else { inp.type = 'password'; eye.textContent = '👁'; }
}
</script>
</body>
</html>
