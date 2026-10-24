<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/backyard_private/app/supporter.php';
$u=supporterRequire(); $error=''; $notice='';
if($_SERVER['REQUEST_METHOD']==='POST') try {
    verifyCsrf(); $action=(string)($_POST['action']??'');
    supporterLimit('profile:'.$u['id'],20,900);
    if($action==='name') {
        $name=supporterDisplay((string)($_POST['display_name']??''),$u['username']);
        db()->prepare('UPDATE supporters SET display_name=? WHERE id=?')->execute([$name,$u['id']]); $u['display_name']=$name; $notice='Anzeigename gespeichert.';
    } elseif(in_array($action,['password','delete'],true)) {
        if(!password_verify((string)($_POST['current_password']??''),$u['password_hash'])) throw new RuntimeException('Aktuelles Passwort ist falsch.');
        if($action==='password') { supporterPassword((string)($_POST['password']??''),(string)($_POST['confirm']??'')); db()->prepare('UPDATE supporters SET password_hash=? WHERE id=?')->execute([password_hash((string)$_POST['password'],PASSWORD_DEFAULT),$u['id']]); }
        supporterRevoke((int)$u['id']);
        if($action==='delete') {
            db()->prepare('DELETE FROM supporter_cheers WHERE supporter_id=?')->execute([$u['id']]);
            db()->prepare('DELETE FROM supporters WHERE id=?')->execute([$u['id']]);
        }
        redirect('supporter/login.php');
    } elseif($action==='push-off') { db()->prepare('DELETE FROM push_subscriptions WHERE supporter_id=?')->execute([$u['id']]); $notice='Push auf allen Geräten deaktiviert.'; }
} catch(Throwable $e) { $error=$e instanceof RuntimeException&&!($e instanceof PDOException)?$e->getMessage():'Änderung derzeit nicht möglich.'; }
supporterHead('Dein Profil',$u); ?>
<?php if($error): ?><p role="alert"><?=h($error)?></p><?php endif; ?><?php if($notice): ?><p role="status"><?=h($notice)?></p><?php endif; ?>
<section class="panel"><h2><?=h($u['username'])?></h2><form method="post"><?php supporterField(); ?><input type="hidden" name="action" value="name"><label>Anzeigename<input name="display_name" maxlength="80" value="<?=h($u['display_name'])?>"></label><button class="btn">Speichern</button></form></section>
<section class="panel"><h2>Passwort ändern</h2><form method="post"><?php supporterField(); ?><input type="hidden" name="action" value="password"><label>Aktuelles Passwort<input type="password" name="current_password" required autocomplete="current-password"></label><label>Neues Passwort<input type="password" name="password" required minlength="12" autocomplete="new-password"></label><label>Bestätigen<input type="password" name="confirm" required autocomplete="new-password"></label><button class="btn">Passwort ändern und überall abmelden</button></form></section>
<?php require dirname(__DIR__).'/partials/supporter-push.php'; ?>
<section class="panel"><h2>Konto löschen</h2><p>Dein Konto, deine Tipps, Anfeuerungen und Push-Abonnements werden dauerhaft gelöscht.</p><form method="post"><?php supporterField(); ?><input type="hidden" name="action" value="delete"><label>Aktuelles Passwort<input type="password" name="current_password" required autocomplete="current-password"></label><button class="btn">Konto endgültig löschen</button></form></section><?php supporterFoot(); ?>
