<?php
session_start();

// ─── Fichiers de données ───────────────────────────────────────────────────────
$XML_FILE   = __DIR__ . '/taches.xml';
$USERS_FILE = __DIR__ . '/users.xml';

// ─── Gestion des utilisateurs (XML) ───────────────────────────────────────────
function usersLoad(string $file): SimpleXMLElement {
    if (!file_exists($file)) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><users/>');
        // Compte par défaut : sebastien / toto11__
        $u = $xml->addChild('user');
        $u->addChild('login', 'sebastien');
        $u->addChild('password', password_hash('toto11__', PASSWORD_DEFAULT));
        xmlSave($xml, $file);
    }
    return simplexml_load_file($file);
}

function userExists(SimpleXMLElement $xml, string $login): bool {
    foreach ($xml->user as $u) {
        if ((string)$u->login === $login) return true;
    }
    return false;
}

function userVerify(string $file, string $login, string $password): bool {
    $xml = usersLoad($file);
    foreach ($xml->user as $u) {
        if ((string)$u->login === $login) {
            return password_verify($password, (string)$u->password);
        }
    }
    return false;
}

// ─── Persistance XML générique ────────────────────────────────────────────────
function xmlLoad(string $file): SimpleXMLElement {
    if (!file_exists($file)) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><taches/>');
        $xml->addAttribute('next_id', '1');
        $xml->asXML($file);
    }
    return simplexml_load_file($file);
}

function xmlSave(SimpleXMLElement $xml, string $file): void {
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    $dom->save($file);
}

// ─── Traitement des actions ────────────────────────────────────────────────────
$action     = $_POST['action'] ?? $_GET['action'] ?? '';
$message    = '';
$auth_error = '';
$admin_msg  = '';

// -- Connexion utilisateur
if ($action === 'login') {
    $login = trim($_POST['login'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if (userVerify($USERS_FILE, $login, $pass)) {
        $_SESSION['can_add']     = true;
        $_SESSION['logged_user'] = $login;
    } else {
        $auth_error = '⚠ Identifiants incorrects.';
    }
}

// -- Déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ?');
    exit;
}

// -- Admin : ajout d'un utilisateur
if ($action === 'admin_add_user' && !empty($_SESSION['can_add'])) {
    $new_login = trim($_POST['new_login'] ?? '');
    $new_pass  = $_POST['new_password'] ?? '';
    if ($new_login === '' || $new_pass === '') {
        $admin_msg = ['type' => 'error', 'text' => '⚠ Login et mot de passe requis.'];
    } else {
        $users = usersLoad($USERS_FILE);
        if (userExists($users, $new_login)) {
            $admin_msg = ['type' => 'error', 'text' => '⚠ Ce login existe déjà.'];
        } else {
            $u = $users->addChild('user');
            $u->addChild('login', htmlspecialchars($new_login, ENT_XML1));
            $u->addChild('password', password_hash($new_pass, PASSWORD_DEFAULT));
            xmlSave($users, $USERS_FILE);
            $admin_msg = ['type' => 'success', 'text' => "✓ Utilisateur « $new_login » ajouté."];
        }
    }
}

// -- Admin : suppression d'un utilisateur
if ($action === 'admin_del_user' && !empty($_SESSION['can_add'])) {
    $del_login = $_GET['login'] ?? '';
    if ($del_login === ($_SESSION['logged_user'] ?? '')) {
        $admin_msg = ['type' => 'error', 'text' => '⚠ Impossible de supprimer votre propre compte.'];
    } else {
        $users = usersLoad($USERS_FILE);
        foreach ($users->user as $u) {
            if ((string)$u->login === $del_login) {
                $dom = dom_import_simplexml($u);
                $dom->parentNode->removeChild($dom);
                break;
            }
        }
        xmlSave($users, $USERS_FILE);
        header('Location: ?admin=1&deleted=1');
        exit;
    }
}

// -- Ajout de tâche (protégé)
if ($action === 'ajouter') {
    if (empty($_SESSION['can_add'])) {
        $auth_error = '⚠ Vous devez être connecté pour ajouter une tâche.';
        $action = '';
    }
}
if ($action === 'ajouter') {
    $lib  = trim($_POST['libelle'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($lib !== '') {
        $xml = xmlLoad($XML_FILE);
        $id  = (int)$xml['next_id'];
        $t   = $xml->addChild('tache');
        $t->addChild('nro_tache',   $id);
        $t->addChild('libelle',     htmlspecialchars($lib,  ENT_XML1));
        $t->addChild('description', htmlspecialchars($desc, ENT_XML1));
        $xml['next_id'] = $id + 1;
        xmlSave($xml, $XML_FILE);
        $message = '✓ Tâche ajoutée.';
    }
}

// -- Modification
if ($action === 'modifier' && isset($_POST['nro_tache'])) {
    if (empty($_SESSION['can_add'])) {
        $auth_error = '⚠ Vous devez être connecté pour modifier une tâche.';
    } else {
        $nro  = (int)$_POST['nro_tache'];
        $lib  = trim($_POST['libelle'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $xml  = xmlLoad($XML_FILE);
        foreach ($xml->tache as $t) {
            if ((int)$t->nro_tache === $nro) {
                $t->libelle     = $lib;
                $t->description = $desc;
                break;
            }
        }
        xmlSave($xml, $XML_FILE);
        $message = '✓ Tâche modifiée.';
    }
}

// -- Suppression
if ($action === 'supprimer' && isset($_GET['id'])) {
    if (empty($_SESSION['can_add'])) {
        $auth_error = '⚠ Vous devez être connecté pour supprimer une tâche.';
    } else {
        $nro = (int)$_GET['id'];
        $xml = xmlLoad($XML_FILE);
        foreach ($xml->tache as $t) {
            if ((int)$t->nro_tache === $nro) {
                $dom = dom_import_simplexml($t);
                $dom->parentNode->removeChild($dom);
                break;
            }
        }
        xmlSave($xml, $XML_FILE);
        $message = '✓ Tâche supprimée.';
    }
}

// -- Charger tâche à éditer
$edit = null;
if (isset($_GET['edit'])) {
    $nro = (int)$_GET['edit'];
    $xml = xmlLoad($XML_FILE);
    foreach ($xml->tache as $t) {
        if ((int)$t->nro_tache === $nro) {
            $edit = [
                'nro_tache'   => (int)$t->nro_tache,
                'libelle'     => (string)$t->libelle,
                'description' => (string)$t->description,
            ];
            break;
        }
    }
}

// -- Charger toutes les tâches
$taches = [];
$xml = xmlLoad($XML_FILE);
foreach ($xml->tache as $t) {
    $taches[] = [
        'nro_tache'   => (int)$t->nro_tache,
        'libelle'     => (string)$t->libelle,
        'description' => (string)$t->description,
    ];
}

// -- Charger tous les utilisateurs (pour l'admin)
$users_list = [];
$users_xml  = usersLoad($USERS_FILE);
foreach ($users_xml->user as $u) {
    $users_list[] = (string)$u->login;
}

// -- Page admin ?
$show_admin = isset($_GET['admin']) && !empty($_SESSION['can_add']);
if (isset($_GET['deleted'])) {
    $admin_msg = ['type' => 'success', 'text' => '✓ Utilisateur supprimé.'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gestionnaire de Tâches</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600&family=Geist+Mono:wght@400;500&display=swap');

  :root {
    --bg:        #f0f2f8;
    --surface:   rgba(255,255,255,0.72);
    --surface2:  rgba(255,255,255,0.5);
    --border:    rgba(99,102,241,0.15);
    --border2:   rgba(99,102,241,0.3);
    --accent:    #6366f1;
    --accent-h:  #4f46e5;
    --accent-s:  rgba(99,102,241,0.12);
    --danger:    #ef4444;
    --danger-s:  rgba(239,68,68,0.1);
    --success:   #10b981;
    --success-s: rgba(16,185,129,0.1);
    --edit-c:    #0ea5e9;
    --edit-s:    rgba(14,165,233,0.1);
    --text:      #1e1b4b;
    --text2:     #4338ca;
    --muted:     #94a3b8;
    --sans:      'Geist', sans-serif;
    --mono:      'Geist Mono', monospace;
    --shadow:    0 4px 24px rgba(99,102,241,0.08), 0 1px 4px rgba(99,102,241,0.06);
    --shadow-lg: 0 8px 40px rgba(99,102,241,0.14), 0 2px 8px rgba(99,102,241,0.08);
    --radius:    14px;
    --radius-sm: 8px;
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: var(--sans);
    background: var(--bg);
    background-image:
      radial-gradient(ellipse 80% 50% at 20% -10%, rgba(99,102,241,0.12) 0%, transparent 60%),
      radial-gradient(ellipse 60% 40% at 80% 110%, rgba(139,92,246,0.10) 0%, transparent 60%);
    background-attachment: fixed;
    color: var(--text);
    min-height: 100vh;
    padding: 2.5rem 1.5rem;
    max-width: 960px;
    margin: 0 auto;
  }

  h1 {
    font-size: 1.6rem; font-weight: 600; color: var(--text);
    letter-spacing: -0.03em;
    display: flex; align-items: center; gap: 0.6rem;
    margin-bottom: 0.25rem;
  }
  .subtitle {
    color: var(--muted); font-size: 0.82rem; font-weight: 400;
    margin-bottom: 1.8rem;
    display: flex; align-items: center; justify-content: space-between;
  }

  .nav-admin { display: flex; gap: 0.5rem; align-items: center; }
  .nav-admin a {
    font-size: 0.75rem; font-weight: 500; color: var(--accent);
    text-decoration: none; padding: 0.25rem 0.7rem;
    border: 1px solid var(--border2); border-radius: 20px;
    transition: background .15s;
  }
  .nav-admin a:hover { background: var(--accent-s); }
  .nav-admin a.active { background: var(--accent-s); }

  .card {
    background: var(--surface); backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid var(--border); border-radius: var(--radius);
    padding: 1.4rem 1.6rem; margin-bottom: 1.4rem;
    box-shadow: var(--shadow); transition: box-shadow .2s;
  }
  .card:hover { box-shadow: var(--shadow-lg); }
  .card h2 {
    font-size: 0.78rem; font-weight: 600; color: var(--accent);
    letter-spacing: 0.06em; text-transform: uppercase;
    margin-bottom: 1.1rem; display: flex; align-items: center; gap: 0.4rem;
  }
  .card h2::before {
    content: ''; width: 3px; height: 14px;
    background: var(--accent); border-radius: 2px; display: inline-block;
  }

  .form-row { display: flex; gap: 0.8rem; flex-wrap: wrap; align-items: flex-end; }
  .form-group { display: flex; flex-direction: column; gap: 0.35rem; flex: 1; min-width: 160px; }
  label { font-size: 0.72rem; font-weight: 500; color: var(--text2); letter-spacing: 0.04em; text-transform: uppercase; }
  input[type=text], input[type=password], textarea {
    background: var(--surface2); border: 1.5px solid var(--border);
    border-radius: var(--radius-sm); color: var(--text);
    font-family: var(--sans); font-size: 0.9rem; font-weight: 400;
    padding: 0.55rem 0.85rem; outline: none;
    transition: border-color .18s, box-shadow .18s, background .18s; width: 100%;
  }
  input[type=text]:focus, input[type=password]:focus, textarea:focus {
    border-color: var(--accent); background: rgba(255,255,255,0.9);
    box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
  }
  textarea { resize: vertical; min-height: 60px; }

  .btn {
    font-family: var(--sans); font-size: 0.82rem; font-weight: 500;
    border: none; border-radius: var(--radius-sm); padding: 0.55rem 1.15rem;
    cursor: pointer; transition: all .18s; white-space: nowrap;
    display: inline-flex; align-items: center; gap: 0.3rem; text-decoration: none;
  }
  .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 2px 8px rgba(99,102,241,0.3); }
  .btn-primary:hover { background: var(--accent-h); box-shadow: 0 4px 14px rgba(99,102,241,0.4); transform: translateY(-1px); }
  .btn-edit    { background: var(--edit-s); color: var(--edit-c); border: 1px solid rgba(14,165,233,0.2); }
  .btn-edit:hover { background: rgba(14,165,233,0.18); }
  .btn-delete  { background: var(--danger-s); color: var(--danger); border: 1px solid rgba(239,68,68,0.2); }
  .btn-delete:hover { background: rgba(239,68,68,0.18); }
  .btn-cancel  { background: transparent; color: var(--muted); border: 1px solid var(--border); }
  .btn-cancel:hover { background: rgba(0,0,0,0.04); }
  .btn-sm { padding: 0.3rem 0.7rem; font-size: 0.75rem; }

  .msg {
    border-radius: var(--radius-sm); padding: 0.6rem 1rem; margin-bottom: 1.2rem;
    font-size: 0.84rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem;
  }
  .msg-success { background: var(--success-s); border: 1px solid rgba(16,185,129,0.25); color: var(--success); }
  .msg-error   { background: var(--danger-s);  border: 1px solid rgba(239,68,68,0.25);  color: var(--danger); }

  .logged-bar {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 0.8rem; background: var(--success-s);
    border: 1px solid rgba(16,185,129,0.2); border-radius: var(--radius-sm);
    padding: 0.45rem 0.9rem;
  }
  .logged-bar span { font-size: 0.78rem; color: var(--success); }

  .search-bar { display: grid; grid-template-columns: 90px 1fr 1fr; gap: 0.7rem; margin-bottom: 1rem; }
  .search-bar input {
    background: var(--surface2); border: 1.5px solid var(--border);
    border-radius: var(--radius-sm); color: var(--text); font-size: 0.84rem;
    padding: 0.45rem 0.75rem; outline: none; font-family: var(--sans);
    transition: border-color .18s, box-shadow .18s; width: 100%;
  }
  .search-bar input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
  .search-bar input::placeholder { color: var(--muted); }
  .search-bar .lbl {
    font-size: 0.68rem; font-weight: 600; color: var(--text2);
    letter-spacing: 0.05em; text-transform: uppercase; display: block; margin-bottom: 0.3rem;
  }

  .table-wrap { overflow-x: auto; border-radius: var(--radius-sm); }
  table { width: 100%; border-collapse: collapse; }
  thead tr { background: rgba(99,102,241,0.05); border-bottom: 1.5px solid var(--border2); }
  th {
    font-size: 0.7rem; font-weight: 600; color: var(--text2);
    letter-spacing: 0.06em; text-transform: uppercase; text-align: left; padding: 0.7rem 1rem;
  }
  th.sortable { cursor: pointer; user-select: none; white-space: nowrap; transition: color .15s; }
  th.sortable:hover { color: var(--accent); }
  th.sortable::after  { content: ' ⇅'; opacity: .25; font-size: .68rem; }
  th.sort-asc::after  { content: ' ↑'; opacity: 1; color: var(--accent); }
  th.sort-desc::after { content: ' ↓'; opacity: 1; color: var(--accent); }
  tbody tr { border-bottom: 1px solid rgba(99,102,241,0.07); transition: background .15s; }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: rgba(99,102,241,0.04); }
  td { padding: 0.75rem 1rem; font-size: 0.88rem; vertical-align: middle; }
  td.num { font-family: var(--mono); font-size: 0.78rem; font-weight: 500; }
  td.num span { background: var(--accent-s); color: var(--accent); border-radius: 6px; padding: 0.2rem 0.5rem; display: inline-block; }
  td.desc { color: var(--muted); font-size: 0.84rem; }
  .actions { display: flex; gap: 0.4rem; }

  .user-table { width: 100%; border-collapse: collapse; }
  .user-table th {
    font-size: 0.7rem; font-weight: 600; color: var(--text2);
    letter-spacing: 0.06em; text-transform: uppercase; text-align: left;
    padding: 0.6rem 0.9rem; background: rgba(99,102,241,0.05);
    border-bottom: 1.5px solid var(--border2);
  }
  .user-table td { padding: 0.65rem 0.9rem; font-size: 0.88rem; border-bottom: 1px solid rgba(99,102,241,0.07); vertical-align: middle; }
  .user-table tr:last-child td { border-bottom: none; }
  .user-table tr:hover td { background: rgba(99,102,241,0.03); }
  .badge-you {
    font-size: 0.68rem; font-weight: 600; color: var(--accent);
    background: var(--accent-s); border-radius: 20px;
    padding: 0.1rem 0.55rem; margin-left: 0.4rem; vertical-align: middle;
  }

  .empty {
    color: var(--muted); font-size: 0.88rem; text-align: center;
    padding: 3rem 1rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
  }
  .empty::before { content: '📋'; font-size: 2rem; }

  mark { background: rgba(99,102,241,0.15); color: var(--accent); border-radius: 3px; padding: 0 2px; font-weight: 500; }
  #compteur {
    font-size: 0.72rem; font-weight: 400; color: var(--muted);
    background: rgba(99,102,241,0.08); border-radius: 20px;
    padding: 0.15rem 0.6rem; margin-left: 0.3rem; vertical-align: middle;
  }
</style>
</head>
<body>

<h1>// GESTIONNAIRE DE TÂCHES</h1>
<div class="subtitle">
  <span>projet · CRUD simple · recherche en temps réel</span>
  <?php if (!empty($_SESSION['can_add'])): ?>
    <div class="nav-admin">
      <a href="?" <?= !$show_admin ? 'class="active"' : '' ?>>Tâches</a>
      <a href="?admin=1" <?= $show_admin ? 'class="active"' : '' ?>>⚙ Utilisateurs</a>
    </div>
  <?php endif; ?>
</div>

<?php if ($message): ?>
  <div class="msg msg-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($auth_error): ?>
  <div class="msg msg-error"><?= htmlspecialchars($auth_error) ?></div>
<?php endif; ?>

<?php if ($show_admin): ?>

  <?php if ($admin_msg): ?>
    <div class="msg msg-<?= $admin_msg['type'] ?>"><?= htmlspecialchars($admin_msg['text']) ?></div>
  <?php endif; ?>

  <div class="card">
    <h2>+ NOUVEL UTILISATEUR</h2>
    <form method="post">
      <input type="hidden" name="action" value="admin_add_user">
      <div class="form-row">
        <div class="form-group" style="max-width:200px">
          <label>LOGIN *</label>
          <input type="text" name="new_login" required placeholder="ex: alice" autocomplete="off">
        </div>
        <div class="form-group" style="max-width:220px">
          <label>MOT DE PASSE *</label>
          <input type="password" name="new_password" required placeholder="••••••••" autocomplete="new-password">
        </div>
        <div style="display:flex;align-items:flex-end">
          <button type="submit" class="btn btn-primary">Ajouter</button>
        </div>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>~ UTILISATEURS <span style="color:var(--muted);font-weight:400">(<?= count($users_list) ?>)</span></h2>
    <table class="user-table">
      <thead>
        <tr>
          <th>LOGIN</th>
          <th style="width:140px">ACTION</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users_list as $ulogin): ?>
          <tr>
            <td>
              <?= htmlspecialchars($ulogin) ?>
              <?php if ($ulogin === ($_SESSION['logged_user'] ?? '')): ?>
                <span class="badge-you">vous</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($ulogin !== ($_SESSION['logged_user'] ?? '')): ?>
                <a href="?admin=1&action=admin_del_user&login=<?= urlencode($ulogin) ?>"
                   class="btn btn-delete btn-sm"
                   onclick="return confirm('Supprimer « <?= htmlspecialchars($ulogin, ENT_QUOTES) ?> » ?')">Supprimer</a>
              <?php else: ?>
                <span style="font-size:.75rem;color:var(--muted)">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php else: ?>

  <!-- ── Bandeau info accès ── -->
  <div style="background:#fef2f2;border:1.5px solid #fca5a5;border-radius:10px;padding:.75rem 1.1rem;margin-bottom:1.2rem;color:#b91c1c;font-size:.84rem;font-weight:500;">
    ⚠ Il est possible de demander un accès au gestionnaire du projet en le contactant par email.
  </div>

  <!-- ── Formulaire Ajout / Édition ── -->
  <div class="card">
    <h2><?= $edit ? '~ MODIFIER LA TÂCHE #'.(int)$edit['nro_tache'] : '+ NOUVELLE TÂCHE' ?></h2>

    <?php if (!$edit && empty($_SESSION['can_add'])): ?>
      <p style="font-size:.83rem;color:var(--muted);margin-bottom:.9rem;">
        🔒 L'ajout de tâche est protégé. Connectez-vous pour continuer.
      </p>
      <form method="post">
        <input type="hidden" name="action" value="login">
        <div class="form-row">
          <div class="form-group" style="max-width:200px">
            <label>LOGIN</label>
            <input type="text" name="login" required placeholder="Identifiant" autocomplete="username">
          </div>
          <div class="form-group" style="max-width:200px">
            <label>MOT DE PASSE</label>
            <input type="password" name="password" required placeholder="••••••••" autocomplete="current-password">
          </div>
          <div style="display:flex;align-items:flex-end">
            <button type="submit" class="btn btn-primary">🔓 Se connecter</button>
          </div>
        </div>
      </form>

    <?php else: ?>
      <?php if (!$edit && !empty($_SESSION['can_add'])): ?>
        <div class="logged-bar">
          <span>✓ Connecté en tant que <strong><?= htmlspecialchars($_SESSION['logged_user'] ?? '') ?></strong></span>
          <a href="?logout" class="btn btn-cancel btn-sm">Se déconnecter</a>
        </div>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="action" value="<?= $edit ? 'modifier' : 'ajouter' ?>">
        <?php if ($edit): ?>
          <input type="hidden" name="nro_tache" value="<?= (int)$edit['nro_tache'] ?>">
        <?php endif; ?>
        <div class="form-row">
          <div class="form-group" style="max-width:220px">
            <label>LIBELLÉ *</label>
            <input type="text" name="libelle" required
                   value="<?= htmlspecialchars($edit['libelle'] ?? '') ?>"
                   placeholder="Nom de la tâche">
          </div>
          <div class="form-group">
            <label>DESCRIPTION</label>
            <input type="text" name="description"
                   value="<?= htmlspecialchars($edit['description'] ?? '') ?>"
                   placeholder="Détail optionnel">
          </div>
          <div style="display:flex;gap:.5rem;align-items:flex-end">
            <button type="submit" class="btn btn-primary"><?= $edit ? 'Enregistrer' : 'Ajouter' ?></button>
            <?php if ($edit): ?>
              <a href="?" class="btn btn-cancel">Annuler</a>
            <?php endif; ?>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <!-- ── Liste + Recherche ── -->
  <div class="card">
    <h2>~ LISTE DES TÂCHES <span style="color:var(--muted);font-weight:400" id="compteur"></span></h2>
    <div class="search-bar">
      <div>
        <span class="lbl">N° TÂCHE</span>
        <input type="text" id="s_nro" placeholder="ex: 3" oninput="filtrer()">
      </div>
      <div>
        <span class="lbl">LIBELLÉ</span>
        <input type="text" id="s_lib" placeholder="Rechercher…" oninput="filtrer()">
      </div>
      <div>
        <span class="lbl">DESCRIPTION</span>
        <input type="text" id="s_desc" placeholder="Rechercher…" oninput="filtrer()">
      </div>
    </div>
    <table id="tableTaches">
      <thead>
        <tr>
          <th class="sortable" style="width:80px" onclick="trier(0)">N° TÂCHE</th>
          <th class="sortable" onclick="trier(1)">LIBELLÉ</th>
          <th class="sortable" onclick="trier(2)">DESCRIPTION</th>
          <?php if (!empty($_SESSION['can_add'])): ?>
          <th style="width:120px">ACTIONS</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($taches)): ?>
          <tr><td colspan="<?= !empty($_SESSION['can_add']) ? 4 : 3 ?>" class="empty">Aucune tâche. Commencez par en ajouter une.</td></tr>
        <?php else: ?>
          <?php foreach ($taches as $t): ?>
            <tr
              data-nro="<?= $t['nro_tache'] ?>"
              data-lib="<?= strtolower(htmlspecialchars($t['libelle'])) ?>"
              data-desc="<?= strtolower(htmlspecialchars($t['description'] ?? '')) ?>"
            >
              <td class="num"><span>#<?= $t['nro_tache'] ?></span></td>
              <td><?= htmlspecialchars($t['libelle']) ?></td>
              <td class="desc"><?= htmlspecialchars($t['description'] ?? '') ?></td>
              <?php if (!empty($_SESSION['can_add'])): ?>
              <td>
                <div class="actions">
                  <a href="?edit=<?= $t['nro_tache'] ?>" class="btn btn-edit">Édit.</a>
                  <a href="?action=supprimer&id=<?= $t['nro_tache'] ?>"
                     class="btn btn-delete"
                     onclick="return confirm('Supprimer la tâche #<?= $t['nro_tache'] ?> ?')">Suppr.</a>
                </div>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<?php endif; ?>

<script>
let sortCol = -1, sortDir = 1;
function trier(col) {
  const ths   = document.querySelectorAll('#tableTaches thead th');
  const tbody = document.querySelector('#tableTaches tbody');
  const rows  = Array.from(tbody.querySelectorAll('tr[data-nro]'));
  if (sortCol === col) { sortDir *= -1; } else { sortCol = col; sortDir = 1; }
  ths.forEach((th, i) => {
    th.classList.remove('sort-asc', 'sort-desc');
    if (i === col) th.classList.add(sortDir === 1 ? 'sort-asc' : 'sort-desc');
  });
  rows.sort((a, b) => {
    const ca = a.cells[col].textContent.trim();
    const cb = b.cells[col].textContent.trim();
    const na = parseFloat(ca), nb = parseFloat(cb);
    if (!isNaN(na) && !isNaN(nb)) return (na - nb) * sortDir;
    return ca.localeCompare(cb, 'fr', {sensitivity: 'base'}) * sortDir;
  });
  rows.forEach(r => tbody.appendChild(r));
}
function esc(str) { return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
function highlight(text, term) {
  if (!term) return text;
  return text.replace(new RegExp('(' + esc(term) + ')', 'gi'), '<mark>$1</mark>');
}
function filtrer() {
  const sNro  = document.getElementById('s_nro')?.value.trim().toLowerCase()  ?? '';
  const sLib  = document.getElementById('s_lib')?.value.trim().toLowerCase()  ?? '';
  const sDesc = document.getElementById('s_desc')?.value.trim().toLowerCase() ?? '';
  const rows  = document.querySelectorAll('#tableTaches tbody tr[data-nro]');
  let visible = 0;
  rows.forEach(row => {
    const nro  = row.dataset.nro;
    const lib  = row.dataset.lib;
    const desc = row.dataset.desc;
    const ok   = nro.includes(sNro) && lib.includes(sLib) && desc.includes(sDesc);
    row.style.display = ok ? '' : 'none';
    if (ok) {
      visible++;
      const cells = row.querySelectorAll('td');
      cells[0].innerHTML = '<span>#' + highlight(nro, sNro) + '</span>';
      cells[1].innerHTML = highlight(row.cells[1].textContent, sLib);
      cells[2].innerHTML = highlight(row.cells[2].textContent, sDesc);
    }
  });
  const c = document.getElementById('compteur');
  if (c) c.textContent = '— ' + visible + ' tâche' + (visible !== 1 ? 's' : '');
}
filtrer();
</script>
</body>
</html>
