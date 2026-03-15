<?php
/**
 * PDP Admin Panel V3.4
 */
session_start();
$db_dir = "database/"; $bk_dir = $db_dir . "backups/";
$config_file = $db_dir . "config.json";
$admin_pass = "Admin2026!";

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
        $record  = ["nome" => $nome, "classe" => trim($_POST['ac']), "file" => $fname,
                    "materie" => $materie, "scadenza" => $_POST['as'], "titolo" => $titolo,
                    "genere" => ($_POST['agenere'] ?? 'M'),
                    "sf1" => isset($_POST['asf1']) ? 1 : 0,
                    "sf2" => isset($_POST['asf2']) ? 1 : 0];
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

if ($is_super && isset($_POST['save_glob'])) {
    $db['settings'] = ["l1" => strip_tags(trim($_POST['l1'])), "l2" => strip_tags(trim($_POST['l2'])), "sf1" => isset($_POST['sf1'])?1:0, "sf2" => isset($_POST['sf2'])?1:0, "ft1" => $_POST['ft1']??'autografa', "ft2" => $_POST['ft2']??'autografa', "n1" => strip_tags(trim($_POST['n1']??'')), "n2" => strip_tags(trim($_POST['n2']??''))];
    file_put_contents($config_file, json_encode($db, JSON_PRETTY_PRINT));
    $msg = "Impostazioni firme salvate.";
}

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
        .al-card { background:#fff; border:1px solid var(--border); border-radius:10px; padding:16px 18px; margin-bottom:14px; }
        .al-row { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-start; }
        .al-field { display:flex; flex-direction:column; }
        .al-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); margin-bottom:5px; white-space:nowrap; }
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

    <!-- 1. FIRME -->
    <div class="scard">
        <div class="scard-head">
            <div class="icon icon-blue">✍️</div>
            <div><h3>Configurazione Firme</h3><div class="sub">Testi visualizzati nel pié di pagina del documento stampato</div></div>
        </div>
        <div class="scard-body">
            <form method="post">
                <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:16px; align-items:end;">
                    <div>
                        <div class="field-label"><input type="checkbox" name="sf1" <?php echo @$db['settings']['sf1']?'checked':''; ?>> Firma 1</div>
                        <input type="text" name="l1" value="<?php echo h(@$db['settings']['l1']); ?>" placeholder="Es. Il Coordinatore di Classe" style="margin-bottom:6px;">
                        <select name="ft1" id="ft1" onchange="toggleNome(1)" style="width:100%; padding:8px 10px; border:1px solid var(--border); border-radius:7px; font-size:12px; color:var(--text);">
                            <option value="autografa"   <?php echo (@$db['settings']['ft1']==='autografa'  ||!isset($db['settings']['ft1']))?'selected':''; ?>>✍️ Firma autografa</option>
                            <option value="digitale"    <?php echo (@$db['settings']['ft1']==='digitale'  )?'selected':''; ?>>🖊️ Firma digitale</option>
                            <option value="sostitutiva" <?php echo (@$db['settings']['ft1']==='sostitutiva')?'selected':''; ?>>📋 Sostitutiva art. 3 c.2 D.Lgs. 39/93</option>
                        </select>
                        <div id="nome1-wrap" style="margin-top:6px; <?php echo (@$db['settings']['ft1']==='autografa'||!isset($db['settings']['ft1']))?'display:none':''; ?>">
                            <input type="text" name="n1" id="n1" value="<?php echo h(@$db['settings']['n1']); ?>" placeholder="Nome e Cognome del firmatario" style="font-size:12px;">
                        </div>
                    </div>
                    <div>
                        <div class="field-label"><input type="checkbox" name="sf2" <?php echo @$db['settings']['sf2']?'checked':''; ?>> Firma 2</div>
                        <input type="text" name="l2" value="<?php echo h(@$db['settings']['l2']); ?>" placeholder="Es. Il Dirigente Scolastico" style="margin-bottom:6px;">
                        <select name="ft2" id="ft2" onchange="toggleNome(2)" style="width:100%; padding:8px 10px; border:1px solid var(--border); border-radius:7px; font-size:12px; color:var(--text);">
                            <option value="autografa"   <?php echo (@$db['settings']['ft2']==='autografa'  ||!isset($db['settings']['ft2']))?'selected':''; ?>>✍️ Firma autografa</option>
                            <option value="digitale"    <?php echo (@$db['settings']['ft2']==='digitale'  )?'selected':''; ?>>🖊️ Firma digitale</option>
                            <option value="sostitutiva" <?php echo (@$db['settings']['ft2']==='sostitutiva')?'selected':''; ?>>📋 Sostitutiva art. 3 c.2 D.Lgs. 39/93</option>
                        </select>
                        <div id="nome2-wrap" style="margin-top:6px; <?php echo (@$db['settings']['ft2']==='autografa'||!isset($db['settings']['ft2']))?'display:none':''; ?>">
                            <input type="text" name="n2" id="n2" value="<?php echo h(@$db['settings']['n2']); ?>" placeholder="Nome e Cognome del firmatario" style="font-size:12px;">
                        </div>
                    </div>
                    <button type="submit" name="save_glob" class="btn btn-blue" style="padding:10px 24px; align-self:end;">💾 Salva</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. ALUNNI -->
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
            <?php foreach($db['alunni'] as $k => $v):
                $exp=$v['scadenza']; $days=(strtotime($exp)-strtotime($today))/86400;
                $bc=$days>30?'b-ok':($days>=0?'b-warn':'b-exp');
                $bl=$days>30?'● Attivo':($days>=0?'● In scadenza':'● Scaduto');
                $gl=$db['settings'];
            ?>
            <form method="post">
            <div class="al-card">
                <!-- Riga 1: Nome + Titolo + Classe + Genere + Scadenza + badge -->
                <div class="al-row">
                    <div class="al-field" style="flex:2; min-width:160px;">
                        <label class="al-label">Nome Alunno</label>
                        <input type="text" name="an" value="<?php echo h($v['nome']); ?>">
                    </div>
                    <div class="al-field" style="flex:2; min-width:160px;">
                        <label class="al-label">Titolo documento</label>
                        <input type="text" name="atit" value="<?php echo h($v['titolo'] ?? ''); ?>" placeholder="PIANO DIDATTICO PERSONALIZZATO">
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
                        <span class="badge <?php echo $bc; ?>" style="margin-top:4px;"><?php echo $bl; ?></span>
                    </div>
                </div>
                <!-- Riga 2: Password + Materie + Firme + Azioni -->
                <div class="al-row" style="margin-top:10px;">
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
                    <div class="al-field" style="flex:0 0 200px;">
                        <label class="al-label">Firme nel documento</label>
                        <?php if(!empty($gl['sf1'])&&!empty($gl['l1'])): ?>
                        <label style="display:flex;align-items:center;gap:6px;font-size:12px;margin-bottom:4px;cursor:pointer;">
                            <input type="checkbox" name="asf1" <?php echo ($v['sf1']??$gl['sf1'])?'checked':''; ?>>
                            <span style="color:var(--text)"><?php echo h($gl['l1']); ?></span>
                        </label>
                        <?php endif; ?>
                        <?php if(!empty($gl['sf2'])&&!empty($gl['l2'])): ?>
                        <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;">
                            <input type="checkbox" name="asf2" <?php echo ($v['sf2']??$gl['sf2'])?'checked':''; ?>>
                            <span style="color:var(--text)"><?php echo h($gl['l2']); ?></span>
                        </label>
                        <?php endif; ?>
                    </div>
                    <div class="al-field" style="flex:0 0 110px; justify-content:flex-end;">
                        <label class="al-label">&nbsp;</label>
                        <button type="submit" name="save_al" class="btn btn-save btn-sm" style="width:100%; margin-bottom:5px;">✔ Salva</button>
                        <a href="?del=<?php echo urlencode($k); ?>" class="btn btn-del btn-sm" style="width:100%; text-align:center;"
                           onclick="return confirm('Eliminare <?php echo h($v['nome']); ?>?')">✖ Elimina</a>
                    </div>
                </div>
            </div>
            </form>
            <?php endforeach; ?>

            <!-- NUOVO ALUNNO -->
            <div class="al-card" style="background:#f0fdf4; border-color:#86efac;">
                <div style="font-size:12px; font-weight:700; color:var(--s); margin-bottom:12px;">＋ Nuovo Alunno</div>
                <form method="post">
                <div class="al-row">
                    <div class="al-field" style="flex:2; min-width:160px;">
                        <label class="al-label">Nome Alunno</label>
                        <input type="text" name="an" placeholder="Nome Cognome" required>
                    </div>
                    <div class="al-field" style="flex:2; min-width:160px;">
                        <label class="al-label">Titolo documento</label>
                        <input type="text" name="atit" placeholder="PIANO DIDATTICO PERSONALIZZATO">
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
                </form>
            </div>
        </div>
    </div>

    <!-- 3. BACKUP -->
    <div class="scard">
        <div class="scard-head">
            <div class="icon icon-amber">🗂️</div>
            <div style="flex:1;"><h3>Log Backup e Ripristino</h3><div class="sub">Ultimi 15 salvataggi automatici — generati ad ogni modifica del documento</div></div>
            <?php $bk_count = count(glob($bk_dir."*.json") ?: []); if ($bk_count > 0): ?>
            <a href="?reset_bk=1" class="btn btn-del btn-sm"
               style="align-self:center; margin-left:auto;"
               onclick="return confirm('Eliminare tutti i <?php echo $bk_count; ?> backup?
Questa operazione non è reversibile.')">
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
function togglePw(id,eyeId){const i=document.getElementById(id),e=document.getElementById(eyeId);i.type=i.type==='password'?'text':'password';e.textContent=i.type==='password'?'👁':'🙈';}
function copyPw(pw){navigator.clipboard.writeText(pw).then(()=>showToast('Password copiata: '+pw)).catch(()=>prompt('Copia la password:',pw));}
function showToast(msg){const t=document.createElement('div');t.textContent=msg;Object.assign(t.style,{position:'fixed',bottom:'24px',right:'24px',background:'#1e293b',color:'#fff',padding:'12px 20px',borderRadius:'8px',fontSize:'13px',fontWeight:'600',boxShadow:'0 4px 12px rgba(0,0,0,.2)',zIndex:'9999',opacity:'0',transition:'opacity .25s'});document.body.appendChild(t);requestAnimationFrame(()=>t.style.opacity='1');setTimeout(()=>{t.style.opacity='0';setTimeout(()=>t.remove(),300);},2500);}
</script>
</body>
</html>