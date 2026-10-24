<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/backyard_private/app/supporter.php';
supporterSession(); $error='';
if($_SERVER['REQUEST_METHOD']==='POST') try {
    verifyCsrf(); $name=strtolower(trim((string)($_POST['username']??'')));
    supporterLimit('login-ip:'.($_SERVER['REMOTE_ADDR']??''),30,900); supporterLimit('login-user:'.$name,10,900);
    $q=db()->prepare('SELECT * FROM supporters WHERE username=?'); $q->execute([$name]); $u=$q->fetch();
    $valid=password_verify((string)($_POST['password']??''),$u['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
    if(!$u||!$valid) throw new RuntimeException('Benutzername oder Passwort falsch.');
    session_regenerate_id(true); $_SESSION=['supporter_id'=>(int)$u['id'],'supporter_version'=>(int)$u['session_version']];
    if(!empty($_POST['remember'])) supporterToken((int)$u['id']); else supporterCookie();
    redirect('supporter/index.php');
} catch(Throwable $e) { $error=$e instanceof RuntimeException&&!($e instanceof PDOException)?$e->getMessage():'Anmeldung derzeit nicht möglich.'; }
supporterHead('Willkommen zurück'); ?>
<section class="panel auth"><?php if(isset($_GET['registered'])): ?><p>Konto erstellt. Du kannst dich jetzt anmelden.</p><?php endif; ?><?php if($error): ?><p role="alert"><?=h($error)?></p><?php endif; ?><form method="post"><?php supporterField(); ?><label>Benutzername<input name="username" required maxlength="40" autocomplete="username"></label><label>Passwort<input type="password" name="password" required autocomplete="current-password"></label><label><input type="checkbox" name="remember" value="1"> 30 Tage angemeldet bleiben</label><button class="btn">Anmelden</button></form><p><a href="register.php">Kostenlos registrieren</a></p><a href="../index.php">Zum Läufer-Login</a></section><?php supporterFoot(); ?>
