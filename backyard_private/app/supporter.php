<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/challenge_score.php';

function supporterSession(): void {
    if(session_status()===PHP_SESSION_ACTIVE) return;
    session_name('llu27_supporter_session');
    ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
    session_set_cookie_params(['path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']); session_start();
    header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: same-origin');
}
function supporterCookie(string $value='', int $expires=0): void {
    setcookie('llu27_supporter_remember',$value,['expires'=>$expires?:time()-3600,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
}
function supporterToken(int $id): void {
    $selector=bin2hex(random_bytes(12)); $secret=bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO supporter_tokens(selector,supporter_id,token_hash,expires_at) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 DAY))')->execute([$selector,$id,hash('sha256',$secret)]);
    supporterCookie($selector.'.'.$secret,time()+30*86400);
}
function supporterCurrent(): ?array {
    supporterSession();
    if(empty($_SESSION['supporter_id']) && preg_match('/^([a-f0-9]{24})\.([a-f0-9]{64})$/D',(string)($_COOKIE['llu27_supporter_remember']??''),$m)) {
        $p=db(); $p->beginTransaction();
        try {
            $q=$p->prepare('SELECT t.*,s.session_version FROM supporter_tokens t JOIN supporters s ON s.id=t.supporter_id WHERE selector=? AND expires_at>UTC_TIMESTAMP() FOR UPDATE'); $q->execute([$m[1]]); $t=$q->fetch();
            $p->prepare('DELETE FROM supporter_tokens WHERE selector=?')->execute([$m[1]]);
            if($t && hash_equals($t['token_hash'],hash('sha256',$m[2]))) {
                session_regenerate_id(true); $_SESSION['supporter_id']=(int)$t['supporter_id']; $_SESSION['supporter_version']=(int)$t['session_version']; supporterToken((int)$t['supporter_id']);
            } else supporterCookie();
            $p->commit();
        } catch(Throwable $e) { $p->rollBack(); throw $e; }
    }
    if(empty($_SESSION['supporter_id'])) return null;
    $q=db()->prepare('SELECT id,username,display_name,session_version,password_hash FROM supporters WHERE id=?'); $q->execute([$_SESSION['supporter_id']]); $u=$q->fetch();
    if(!$u || (int)$u['session_version']!==(int)($_SESSION['supporter_version']??0)) { $_SESSION=[]; supporterCookie(); return null; }
    return $u;
}
function supporterRequire(): array { $u=supporterCurrent(); if(!$u) redirect('supporter/login.php'); return $u; }
function supporterRevoke(int $id): void {
    db()->prepare('UPDATE supporters SET session_version=session_version+1 WHERE id=?')->execute([$id]);
    db()->prepare('DELETE FROM supporter_tokens WHERE supporter_id=?')->execute([$id]); supporterCookie(); $_SESSION=[]; session_regenerate_id(true);
}
/** Atomic fixed-window counters, keyed with a private salt; never trust proxy headers. */
function supporterLimit(string $scope,int $max,int $seconds): void {
    $bucket=hash_hmac('sha256',$scope,cfg('SUPPORTER_RATE_KEY',cfg('TOKEN_KEY')));
    $q=db()->prepare('INSERT INTO supporter_limits(bucket,hits,expires_at) VALUES(?,1,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND)) ON DUPLICATE KEY UPDATE hits=IF(expires_at<=UTC_TIMESTAMP(),1,hits+1),expires_at=IF(expires_at<=UTC_TIMESTAMP(),VALUES(expires_at),expires_at)'); $q->execute([$bucket,$seconds]);
    $q=db()->prepare('SELECT hits FROM supporter_limits WHERE bucket=?'); $q->execute([$bucket]);
    if((int)$q->fetchColumn()>$max) throw new RuntimeException('Zu viele Versuche. Bitte später erneut versuchen.');
}
function supporterUsername(string $value): string {
    $value=strtolower(trim($value));
    if(!preg_match('/^[a-z0-9][a-z0-9_\-]{2,39}$/D',$value)) throw new RuntimeException('Benutzername: 3–40 Zeichen, nur a–z, 0–9, _ und -, beginnend mit Buchstabe oder Zahl.');
    return $value;
}
function supporterPassword(string $password,string $confirm): void {
    if(mb_strlen($password)<12 || strlen($password)>72) throw new RuntimeException('Passwort: mindestens 12 Zeichen und höchstens 72 Bytes.');
    if($password!==$confirm) throw new RuntimeException('Die Passwörter stimmen nicht überein.');
}
function supporterDisplay(string $value,string $fallback): string {
    $v=trim($value)?:$fallback; if(mb_strlen($v)>80 || preg_match('/[\x00-\x1f\x7f]/u',$v)) throw new RuntimeException('Anzeigename: höchstens 80 Zeichen, keine Steuerzeichen.'); return $v;
}
function supporterTeam(): array {
    return db()->query("SELECT u.id,u.username,IF(s.runner_id IS NULL,CONCAT(UPPER(LEFT(u.username,1)),SUBSTRING(u.username,2)),u.display_name) display_name FROM users u LEFT JOIN supporter_sharing s ON s.runner_id=u.id WHERE u.username IN ('tim','jonas','noah') ORDER BY FIELD(u.username,'tim','jonas','noah')")->fetchAll();
}
function supporterScore(): array {
    return challengeScore(db()->query("SELECT u.username,w.* FROM challenge_weekly w JOIN users u ON u.id=w.user_id WHERE u.username IN ('tim','jonas','noah') ORDER BY iso_year DESC,iso_week DESC")->fetchAll());
}
function supporterWeek(?DateTimeImmutable $now=null): array {
    $now=$now??new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $start=$now->modify('monday this week')->setTime(0,0);
    // Early cutoff: Monday 18:00 UTC, before the majority of the week's training.
    return ['year'=>(int)$now->format('o'),'week'=>(int)$now->format('W'),'key'=>$now->format('o-\WW'),'deadline'=>$start->setTime(18,0),'open'=>$now<$start->setTime(18,0)];
}
function supporterCheer(int $supporter,int $runner,string $message): void {
    $message=trim($message);
    if($message==='' || mb_strlen($message)>240 || preg_match('/[\x00-\x08\x0b-\x1f\x7f]/u',$message)) throw new RuntimeException('Bitte 1–240 Zeichen ohne Steuerzeichen eingeben.');
    if(!in_array($runner,array_map('intval',array_column(supporterTeam(),'id')),true)) throw new RuntimeException('Ungültiger Empfänger.');
    supporterLimit('cheer:'.$supporter,1,30); supporterLimit('cheer-day:'.$supporter,100,86400);
    $p=db(); $p->beginTransaction();
    try {
        $p->prepare('INSERT INTO supporter_cheers(supporter_id,runner_id,message) VALUES(?,?,?)')->execute([$supporter,$runner,$message]);
        $p->prepare('INSERT INTO supporter_push_jobs(cheer_id) VALUES(?)')->execute([$p->lastInsertId()]); $p->commit();
    } catch(Throwable $e) { $p->rollBack(); throw $e; }
}
function supporterTip(int $supporter,int $runner): void {
    $w=supporterWeek();
    if(!$w['open']) throw new RuntimeException('Annahmeschluss war Montag um 18:00 UTC.');
    if(!in_array($runner,array_map('intval',array_column(supporterTeam(),'id')),true)) throw new RuntimeException('Ungültiger Tipp.');
    // Database clock checks the same deadline inside the write, including racing requests.
    $q=db()->prepare('INSERT INTO supporter_tips(supporter_id,iso_year,iso_week,runner_id) SELECT ?,?,?,? WHERE UTC_TIMESTAMP() < ? ON DUPLICATE KEY UPDATE runner_id=VALUES(runner_id),updated_at=UTC_TIMESTAMP()');
    $q->execute([$supporter,$w['year'],$w['week'],$runner,$w['deadline']->format('Y-m-d H:i:s')]);
}
function supporterTipStatus(array $tip,array $history): string {
    $key=sprintf('%04d-W%02d',$tip['iso_year'],$tip['iso_week']);
    foreach($history as $w) if($w['week']===$key && $w['complete']) return !$w['winners']?'Ohne Sieger':(in_array($tip['username'],$w['winners'],true)?'Richtig':'Leider falsch');
    return 'Wertung ausstehend';
}
function supporterHead(string $title,?array $u=null): void {
    ?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#101923"><title><?=h($title)?> · LLU 27</title><link rel="stylesheet" href="<?=h(url('assets/style.css'))?>"><link rel="stylesheet" href="<?=h(url('supporter/supporter.css'))?>"><link rel="manifest" href="<?=h(url('supporter/manifest.webmanifest'))?>"><link rel="apple-touch-icon" href="<?=h(url('assets/apple-touch-icon.png'))?>"><script src="<?=h(url('supporter/supporter.js'))?>" defer></script></head><body><button class="menu-toggle" aria-expanded="false" aria-controls="supporter-nav">☰ Menü</button><button class="nav-shade" aria-label="Menü schließen" hidden></button><nav id="supporter-nav" aria-label="Supporter-Navigation"><a class="support-brand" href="<?=h(url('supporter/index.php'))?>">LLU <b>27</b><small>SUPPORTER ZONE</small></a><?php foreach(['index.php'=>'Übersicht','index.php#scoreboard'=>'Scoreboard','index.php#cheers'=>'Anfeuern','index.php#tips'=>'Tipps','profile.php'=>'Profil','logout.php'=>'Abmelden'] as $p=>$label): if(!$u && $p!=='index.php') continue; ?><a href="<?=h(url('supporter/'.$p))?>"><?=h($label)?></a><?php endforeach; ?><button type="button" class="collapse-nav">Sidebar umschalten</button></nav><main class="support-shell"><header><p class="eyebrow">ONE MORE LOOP · LLU 27</p><h1><?=h($title)?></h1></header><?php
}
function supporterFoot(): void { echo '</main></body></html>'; }
function supporterField(): void { echo '<input type="hidden" name="csrf" value="'.h(csrf()).'">'; }
