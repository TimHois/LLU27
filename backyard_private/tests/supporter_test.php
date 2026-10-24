<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/supporter.php';
require_once dirname(__DIR__).'/app/push.php';
$checks=0;
function check(bool $condition,string $name): void { global $checks; if(!$condition) throw new RuntimeException('FAIL: '.$name); $checks++; }
function rejects(callable $f,string $name): void { try { $f(); } catch(RuntimeException $e) { check(true,$name); return; } check(false,$name); }
check(supporterUsername(' Alice_27 ')==='alice_27','username normalization');
foreach(['ab','../admin','a b','äbc','admin<script>'] as $v) rejects(fn()=>supporterUsername($v),'invalid username');
rejects(fn()=>supporterPassword('short','short'),'short password');
rejects(fn()=>supporterPassword('a long password','a different password'),'confirmation');
rejects(fn()=>supporterPassword(str_repeat('ä',40),str_repeat('ä',40)),'bcrypt byte limit');
supporterPassword('a long password','a long password'); check(true,'valid password');
$hash=password_hash('a long password',PASSWORD_DEFAULT);
check(password_verify('a long password',$hash),'password login'); check(!password_verify('wrong password',$hash),'wrong password');
check(h('<script>"&')==='&lt;script&gt;&quot;&amp;','escaping');
rejects(fn()=>supporterDisplay(str_repeat('x',81),'user'),'display name limit');
$rows=[];
foreach(['tim'=>4,'jonas'=>4,'noah'=>2] as $name=>$loops) $rows[]=['username'=>$name,'iso_year'=>2026,'iso_week'=>40,'completed_loops'=>$loops,'run_count'=>2,'finalized'=>1];
$s=challengeScore($rows);
check($s['history'][0]['winners']===['tim','jonas'],'tie winners');
check($s['board']['tim']['points']===9 && $s['board']['jonas']['bonus']===5,'existing full tie bonus');
check($s['board']['noah']['points']===2,'non-winner points');
check(supporterTipStatus(['iso_year'=>2026,'iso_week'=>40,'username'=>'jonas'],$s['history'])==='Richtig','co-winner prediction');
check(supporterTipStatus(['iso_year'=>2026,'iso_week'=>40,'username'=>'noah'],$s['history'])==='Leider falsch','incorrect prediction');
$rows[0]['finalized']=0; $s=challengeScore($rows);
check(!$s['history'][0]['complete'] && $s['board']['tim']['bonus']===0,'no early bonus');
check(supporterTipStatus(['iso_year'=>2026,'iso_week'=>40,'username'=>'tim'],$s['history'])==='Wertung ausstehend','pending prediction');
foreach($rows as &$r) { $r['completed_loops']=0; $r['finalized']=1; } unset($r);
check(challengeScore($rows)['history'][0]['winners']===[],'zero loop week has no winner');
check(challengeScore(array_slice($rows,0,2))['history'][0]['complete']===false,'missing runner');
check(challengeScore([])['ranking'][0]['points']===0,'empty scoreboard');
$utc=new DateTimeZone('UTC');
check(supporterWeek(new DateTimeImmutable('2026-10-19 17:59:59',$utc))['open'],'before cutoff');
check(!supporterWeek(new DateTimeImmutable('2026-10-19 18:00:00',$utc))['open'],'exact cutoff');
check(supporterWeek(new DateTimeImmutable('2027-01-01',$utc))['year']===2026,'ISO year boundary');
$encode=static fn($v)=>rtrim(strtr(base64_encode($v),'+/','-_'),'=');
$sub=['endpoint'=>'https://fcm.googleapis.com/fcm/send/example','keys'=>['p256dh'=>$encode(chr(4).str_repeat('x',64)),'auth'=>$encode(str_repeat('x',16))]];
check(pushValidate($sub)['contentEncoding']==='aes128gcm','valid subscription');
foreach(['http://fcm.googleapis.com/test','https://127.0.0.1/test','https://fcm.googleapis.com.attacker.test/push','https://fcm.googleapis.com:443/push','https://user@fcm.googleapis.com/test'] as $endpoint) { $bad=$sub; $bad['endpoint']=$endpoint; rejects(fn()=>pushValidate($bad),'SSRF endpoint'); }
$sub['keys']['auth']='short'; rejects(fn()=>pushValidate($sub),'invalid subscription keys');
echo "OK: $checks checks\n";
