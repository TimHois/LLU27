<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/backyard_private/app/bootstrap.php';
safeSession();
if(currentUser())redirect('dashboard.php');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  verifyCsrf();
  $name=strtolower(trim((string)($_POST['username']??'')));
  $pass=(string)($_POST['password']??'');
  $q=db()->prepare('SELECT id,password_hash FROM users WHERE username=?');$q->execute([$name]);$account=$q->fetch();
  if(!$account||!password_verify($pass,$account['password_hash']))throw new RuntimeException('Benutzername oder Passwort falsch.');
  session_regenerate_id(true);$_SESSION['user_id']=(int)$account['id'];
  if(isset($_POST['remember'])&&$_POST['remember']==='1')issueRememberToken((int)$account['id']);
  redirect('dashboard.php');
 }catch(Throwable $e){$error='Anmeldung fehlgeschlagen. Bitte Zugangsdaten und Datenbankkonfiguration prüfen.';}
}
?><!doctype html><html lang="de"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>LLU 27 · Login</title><link rel="stylesheet" href="assets/style.css?v=profile-1">
<link rel="icon" href="/assets/favicon-32-v4.png"><link rel="apple-touch-icon" href="/assets/apple-touch-icon-v4.png">
<link rel="manifest" href="/manifest-v4.webmanifest"><meta name="theme-color" content="#101923">
</head><body><main class="login"><div class="brand">LLU <em>27</em> / BACKYARD RUNNING</div>
<section class="panel"><p class="eyebrow">ONE MORE LOOP. ONE MORE HOUR.</p><h1>Anmelden</h1><p class="muted">Tim · Jonas · Noah</p>
<?php if($error):?><p class="error"><?=h($error)?></p><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>">
<label>Benutzername<input name="username" autocomplete="username" required></label>
<label>Passwort<input name="password" type="password" autocomplete="current-password" required></label>
<label style="display:flex;align-items:center;gap:10px;font-size:13px;color:#bdcbd8"><input type="checkbox" name="remember" value="1" style="width:18px;height:18px"> 30 Tage eingeloggt bleiben</label>
<button class="btn">Einloggen →</button></form></section><p class="note">LLU 27 · BACKYARD ULTRA PROJECT</p></main><p style="text-align:center"><a href="supporter/login.php">Supporter Zone · Anmelden</a> · <a href="supporter/register.php">Kostenlos registrieren</a></p></body></html>
