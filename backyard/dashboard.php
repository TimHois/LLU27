<?php
declare(strict_types=1);require_once dirname(__DIR__, 2).'/backyard_private/app/bootstrap.php';$u=requireUser();db()->exec('DELETE FROM activities WHERE cache_updated_at < UTC_TIMESTAMP() - INTERVAL 7 DAY');$err='';
if($_SERVER['REQUEST_METHOD']==='POST')try{verifyCsrf();$action=(string)($_POST['action']??'');if($action==='date'){$date=(string)($_POST['start_date']??'');$dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$dt||$dt->format('Y-m-d')!==$date)throw new RuntimeException('Ungültiges Datum');$s=db()->prepare('UPDATE users SET start_date=? WHERE id=?');$s->execute([$date,$u['id']]);redirect('dashboard.php');}if($action==='disconnect'){$s=db()->prepare('DELETE FROM athletes WHERE user_id=?');$s->execute([$u['id']]);$s=db()->prepare('DELETE FROM activities WHERE user_id=?');$s->execute([$u['id']]);redirect('dashboard.php');}}catch(Throwable $e){$err=$e->getMessage();}
// Teamvergleich der drei LLU-27-Accounts.
// Fremde HF-Werte nur mit allgemeiner und persönlicher Freigabe ausgeben.
// Nur gültige Cache-Einträge zählen; damit ist dies kein dauerhaftes Kilometerarchiv.
$teamStmt = db()->prepare("
 SELECT u.username, u.display_name, u.start_date,
        COUNT(a.id) AS run_count,
        COALESCE(SUM(a.distance_m), 0) / 1000 AS km
 FROM users u
 LEFT JOIN activities a ON a.user_id = u.id
    AND a.activity_date >= CONCAT(u.start_date, ' 00:00:00')
    AND a.cache_updated_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY
 WHERE u.username IN ('tim', 'jonas', 'noah')
 GROUP BY u.id, u.username, u.display_name, u.start_date
 ORDER BY FIELD(u.username, 'tim', 'jonas', 'noah')
");
$teamStmt->execute();
$teamRows = $teamStmt->fetchAll();
$teamMax = max(1.0, ...array_map(static fn(array $r): float => (float)$r['km'], $teamRows));
$teamTotal = array_sum(array_map(static fn(array $r): float => (float)$r['km'], $teamRows));

// Teamvergleiche nach ISO-Kalenderwochen, jeweils ab individuellem Trainingsstart.
$teamHeartAllowed=cfg('TEAM_HR_SHARING_APPROVED','0')==='1';
$teamWeekStmt=db()->prepare("SELECT u.username, u.display_name, u.share_team_hr, a.activity_date, a.distance_m, a.moving_seconds, a.elevation_m, a.avg_hr
 FROM users u JOIN activities a ON a.user_id=u.id
 WHERE u.username IN ('tim','jonas','noah')
 AND a.activity_date>=CONCAT(u.start_date,' 00:00:00')
 AND a.cache_updated_at>=UTC_TIMESTAMP()-INTERVAL 7 DAY
 ORDER BY a.activity_date ASC");
$teamWeekStmt->execute();
$teamWeeks=[];$teamStats=[];$teamRuns=[];$teamScatter=[];
foreach($teamRows as $member){
 $name=strtolower((string)$member['username']);
 $teamStats[$name]=['name'=>$member['display_name'],'km'=>0.0,'seconds'=>0,'hrSum'=>0.0,'hrCount'=>0,'count'=>0];
 $teamScatter[$name]=[];
}
foreach($teamWeekStmt->fetchAll() as $activity){
 $name=strtolower((string)$activity['username']);
 $when=new DateTimeImmutable((string)$activity['activity_date'],new DateTimeZone('UTC'));
 $week=$when->format('o-\WW');
 $km=(float)$activity['distance_m']/1000;
 $secondsRun=(int)$activity['moving_seconds'];
 $hrVisible=($name===strtolower((string)$u['username']) || ($teamHeartAllowed && (int)$activity['share_team_hr']===1));
 $hr=$hrVisible && (float)($activity['avg_hr']??0)>0?(float)$activity['avg_hr']:null;
 $runPace=$km>0 && $secondsRun>0?$secondsRun/60/$km:null;
 $teamStats[$name]['km']+=$km;$teamStats[$name]['seconds']+=$secondsRun;$teamStats[$name]['count']++;
 if($hr!==null){$teamStats[$name]['hrSum']+=$hr;$teamStats[$name]['hrCount']++;}
 if($hr!==null && $runPace!==null)$teamScatter[$name][]=['x'=>$runPace,'y'=>$hr];
 $teamRuns[]=['athlete'=>$name,'runner'=>$activity['display_name'],'date'=>substr($activity['activity_date'],0,10),'km'=>round($km,2),'pace'=>$runPace,'hr'=>$hr,'elev'=>round((float)$activity['elevation_m'])];
 if(!isset($teamWeeks[$week]))$teamWeeks[$week]=[];
 if(!isset($teamWeeks[$week][$name]))$teamWeeks[$week][$name]=['km'=>0.0,'hrSum'=>0.0,'hrCount'=>0];
 $teamWeeks[$week][$name]['km']+=(float)$activity['distance_m']/1000;
 if($hr!==null){
  $teamWeeks[$week][$name]['hrSum']+=(float)$activity['avg_hr'];
  $teamWeeks[$week][$name]['hrCount']++;
 }
}
ksort($teamWeeks);
$teamWeeks=array_slice($teamWeeks,-12,12,true);
$weekKeys=array_keys($teamWeeks);
$weekLabels=array_map(static function(string $key):string {
 $parts=explode('-W',$key);
 return 'KW '.(int)($parts[1]??0).' / '.$parts[0];
},$weekKeys);
$teamChart=['labels'=>$weekLabels,'distance'=>[],'heart'=>[],'scatter'=>$teamScatter];
$teamRuns=array_reverse($teamRuns);
foreach(['tim','jonas','noah'] as $person){
 $teamChart['distance'][$person]=[];$teamChart['heart'][$person]=[];
 foreach($teamWeeks as $perWeek){
  $v=$perWeek[$person]??null;
  $teamChart['distance'][$person][]=round((float)($v['km']??0),2);
  $teamChart['heart'][$person]=$teamChart['heart'][$person]??[];
  $teamChart['heart'][$person][]=($v && $v['hrCount']>0)?round($v['hrSum']/$v['hrCount'],1):null;
 }
}

$a=activeAthlete((int)$u['id']);
$data=['team'=>$teamChart];
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LLU 27 · Backyard Running · <?=h($u['display_name'])?></title><link rel="icon" type="image/png" href="assets/llu27-icon.png"><link rel="stylesheet" href="assets/style.css?v=llu27-hf-consent-1"><link rel="apple-touch-icon" href="assets/apple-touch-icon.png"><link rel="manifest" href="manifest.webmanifest"><meta name="theme-color" content="#101923"><meta name="apple-mobile-web-app-title" content="LLU 27"><style>
/* LLU 27: kritische Komponentenstyles – funktionieren auch mit gecachtem externem CSS. */
.runner-tags{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-top:20px}
.runner-tags>span{display:inline-flex;align-items:center;justify-content:center;padding:7px 16px;background:#293a4d;color:#fff;border:1px solid #45566a;border-radius:999px;font-size:12px;font-weight:800;letter-spacing:1.3px}



.hero-footer{border-top:1px solid #354558;max-width:410px;padding-top:15px;margin:23px 0 0;color:#ff875d;font-size:11px;font-weight:800;letter-spacing:2px}
.team-board{margin:18px 0 22px;background:linear-gradient(140deg,#1b2a3b,#182433)}
.team-head{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;margin-bottom:22px;flex-wrap:wrap}
.team-head .eyebrow{margin:0 0 5px}.team-head h2{font-size:clamp(19px,2.8vw,27px);margin:0}
.team-total{border:1px solid #425267;background:#0f1b2a;border-radius:14px;padding:10px 20px;text-align:right}
.team-total span{display:block;font-size:10px;letter-spacing:1.5px;color:#9dadbd}
.team-total strong{display:block;font-size:26px;color:#fc4c02;white-space:nowrap}
.team-list{display:grid;gap:22px}
.team-entry{--person:#fc4c02;min-width:0}
.team-jonas{--person:#2dd4bf}.team-noah{--person:#a5a0ff}
.team-row,.team-meta{display:flex;justify-content:space-between;align-items:baseline;gap:10px}
.team-row{margin-bottom:9px}.team-name{font-size:15px;font-weight:800;text-transform:uppercase;letter-spacing:1px}
.team-row strong{font-size:20px;white-space:nowrap}
.team-bar-bg{display:block;width:100%;height:13px;border-radius:999px;background:#344457;overflow:hidden}
.team-bar{display:block;height:100%;min-width:0;border-radius:999px;background:var(--person,#fc4c02);transition:width .3s ease}
.team-meta{font-size:11px;color:#aebdcc;margin-top:8px;flex-wrap:wrap}
.team-disclaimer{font-size:11px;line-height:1.5;color:#9dadbd;border-top:1px solid #314154;padding-top:12px;margin:20px 0 0}
@media(max-width:520px){.team-total{text-align:left}.team-row strong{font-size:17px}}

/* LLU27 Logo */
.brand{display:flex;align-items:center;gap:10px}
.brand-icon{display:block;object-fit:cover;width:56px;height:56px;border-radius:14px;flex-shrink:0}
@media(max-width:550px){.brand-icon{width:42px;height:42px}.brand{font-size:15px}}

/* LLU 27: klickbare Athleten-Badges */
.runner-tags .runner-toggle{appearance:none;cursor:pointer;background:#293a4d;border:1px solid #405066;border-radius:999px;color:#edf4fa;padding:8px 16px;font:800 12px/1.2 system-ui,sans-serif;letter-spacing:1.2px;transition:background .18s,opacity .18s,box-shadow .18s;}
.runner-tags .runner-toggle[data-athlete="tim"]{--runner-color:#fc4c02}
.runner-tags .runner-toggle[data-athlete="jonas"]{--runner-color:#2dd4bf}
.runner-tags .runner-toggle[data-athlete="noah"]{--runner-color:#a5a0ff}
.runner-tags .runner-toggle[aria-pressed="true"]{border-color:var(--runner-color);box-shadow:inset 0 -2px 0 var(--runner-color);background:#304459;color:#fff}
.runner-tags .runner-toggle[aria-pressed="false"]{opacity:.46;text-decoration:line-through}
.runner-tags .runner-toggle:focus-visible{outline:3px solid #fff;outline-offset:3px}
.runner-help{color:#aebccd;font-size:12px;margin:10px 0 0}
.team-controls-note{grid-column:1/-1;color:#aebccd;font-size:12px;line-height:1.5}
.team-controls-note code{color:#ffaf88}
.team-entry.is-filtered{display:none}
</style><script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js" defer></script><script src="assets/charts.js?v=llu27-team-all-2" defer></script><link rel="stylesheet" href="assets/llu-navigation.css?v=3"><script src="assets/llu-navigation.js?v=3" defer></script></head><body class="llu-layout">
<?php require __DIR__ . "/partials/navigation.php"; ?>
<div class="shell"><header class="top"><div class="brand"><img class="brand-icon" src="assets/llu27-icon.png" alt="LLU 27 Logo" width="56" height="56"> LLU <em>27</em> <span class="brand-caption">/ BACKYARD RUNNING</span></div><nav style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><a class="btn outline" href="profile.php" style="padding:9px 13px">Mein Profil</a><form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><span class="username"><?=h($u['display_name'])?></span><button class="outline">Abmelden</button></form></nav></header><section class="hero llu-hero"><div class="hero-copy"><p class="eyebrow"><span class="eyebrow-dot"></span> LLU 27 · THE BACKYARD ULTRA PROJECT</p><h1>ONE MORE LOOP.<br><span>ONE MORE HOUR.</span></h1><p class="muted hero-subtitle">6,706 km. Jede Stunde. Bis nur noch einer läuft.</p><div class="runner-tags" role="group" aria-label="Athleten für Teamvergleiche auswählen"><button type="button" class="runner-toggle runner-tim" data-athlete="tim" aria-pressed="true">TIM</button><button type="button" class="runner-toggle runner-jonas" data-athlete="jonas" aria-pressed="true">JONAS</button><button type="button" class="runner-toggle runner-noah" data-athlete="noah" aria-pressed="true">NOAH</button></div><p class="runner-help">Tippe auf einen Namen, um ihn in allen Anzeigen ein- oder auszublenden.</p><p class="hero-footer">THE ROAD TO LLU 27</p></div><div class="pill"><?= $a ? '● Strava verbunden':'○ Strava nicht verbunden' ?></div></section>
<?php if($err):?><p class="error"><?=h($err)?></p><?php endif;?>
<?php if(!$a):?><section class="panel intro"><h2>Strava verbinden</h2><p class="muted">Verbinde dein persönliches Konto, damit deine Läufe hier erscheinen.</p><a class="btn" href="connect.php">Mit Strava verbinden ↗</a></section><?php else:?>
<?php if(isset($_GET['connected'])):?><p class="notice">Strava verbunden. Der nächste Cronjob importiert deine Läufe.</p><?php endif;?>
<?php if($a['sync_error']):?><p class="error">Letzter Sync: <?=h($a['sync_error'])?></p><?php endif;?>
<?php endif;?>
<section class="settings"><form method="post" class="dateform"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="date"><label>Trainingsstart <input type="date" name="start_date" value="<?=h($u['start_date'])?>" required></label><button class="outline">Übernehmen</button></form><p class="muted tiny">Letzter Sync: <?=h($a['last_sync']??'Noch nicht synchronisiert')?> UTC · Cache max. 7 Tage</p></section>
<section class="panel team-board" aria-labelledby="team-heading">
 <div class="team-head">
  <div><p class="eyebrow">LLU 27 · TEAMVERGLEICH</p><h2 id="team-heading">Unsere Kilometer im Vergleich</h2><p class="muted">Kilometer pro Läufer ab dem eigenen Trainingsstart · vorhandener Strava-Cache (max. 7 Tage)</p></div>
  <div class="team-total"><span>TEAM GESAMT</span><strong><?=h(percent((float)$teamTotal))?> km</strong></div>
 </div>
 <?php if(!$teamRows):?>
  <p class="muted">Noch keine Teamkonten gefunden. Prüfe, ob tim, jonas und noah angelegt sind.</p>
 <?php else:?>
  <div class="team-list">
  <?php foreach($teamRows as $member):
   $memberKm=(float)$member['km'];
   $teamColor=match(strtolower($member['username'])) {'tim'=>'tim','jonas'=>'jonas','noah'=>'noah',default=>'other'};
   $barWidth=max(0.0,min(100.0,$memberKm/$teamMax*100));
  ?>
   <div class="team-entry team-<?=h($teamColor)?>">
    <div class="team-row"><span class="team-name"><?=h($member['display_name'])?></span><strong><?=h(percent($memberKm))?> km</strong></div>
    <div class="team-bar-bg" role="img" aria-label="<?=h($member['display_name'])?>: <?=h(percent($memberKm))?> Kilometer"><div class="team-bar" style="width:<?=h(number_format($barWidth,2,'.',''))?>%"></div></div>
    <div class="team-meta"><span><?=h(percent($memberKm/6.706))?> Backyard-Rundenäquivalente</span><span><?=h((string)$member['run_count'])?> Läufe im Cache</span></div>
   </div>
  <?php endforeach;?>
  </div>
 <?php endif;?>
 <p class="team-disclaimer">Vergleich aus den verfügbaren Aktivitäten; fremde Herzfrequenzwerte nur mit allgemeiner und persönlicher Freigabe. Liegen Aktivitäten außerhalb des gültigen Caches, wird hier nicht der historische Gesamtstand angezeigt.</p>
</section>

<section class="charts team-charts" aria-label="Gemeinsamer Wochenvergleich">
 <div class="team-controls-note">Auswahl oben: <strong id="team-selection-count">3 von 3</strong> Athleten aktiv. <?php if(!$teamHeartAllowed):?>Der Team-HF-Vergleich benötigt zuerst die allgemeine Strava-Freigabe. Danach kann jeder Läufer die eigenen HF-Werte im Profil freigeben.<?php endif;?></div>
 <article class="panel"><h2>Wochenkilometer im Teamvergleich</h2><p class="muted">ISO-Kalenderwochen (KW) · letzter verfügbarer 12-Wochen-Zeitraum</p><div class="canvas"><canvas id="team-weekly"></canvas></div></article>
 <article class="panel"><h2>Herzfrequenz im Teamvergleich</h2><p class="muted">Mittelwert pro Lauf und KW; eigene HF und freigegebene HF-Werte der anderen Läufer</p><div class="canvas"><canvas id="team-heart"></canvas></div></article>
</section>
<section class="stats" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr))"><?php foreach($teamStats as $person=>$summary):
 $summaryHr=$summary['hrCount']>0?round($summary['hrSum']/$summary['hrCount']):null;
 $summaryPace=$summary['km']>0?(int)round($summary['seconds']/$summary['km']):0;
?><article class="stat team-entry team-<?=h($person)?>"><span><?=h($summary['name'])?></span><strong><?=h(percent($summary['km']))?> <small>km</small></strong><p><?=h(percent($summary['km']/6.706))?> Runden · <?=h((string)floor($summary['seconds']/3600))?> h · <?=h((string)$summary['count'])?> Läufe</p><p>Ø HF <?=h($summaryHr??'–')?> bpm · Ø Pace <?=h($summaryPace>0?sprintf('%d:%02d',intdiv($summaryPace,60),$summaryPace%60):'–')?> min/km</p></article><?php endforeach;?></section><section class="charts"><article class="panel"><h2>Wochenkilometer</h2><p class="muted">Letzte 12 erfasste Trainingswochen (KW)</p><div class="canvas"><canvas id="weekly"></canvas></div></article><article class="panel"><h2>Herzfrequenz-Trend</h2><p class="muted">Mittlere Ø-HF pro Lauf, je KW</p><div class="canvas"><canvas id="heart"></canvas></div></article><article class="panel wide"><h2>Pace × Herzfrequenz</h2><p class="muted">Jeder Punkt steht für einen Lauf · niedrigerer Puls bei gleicher Pace kann ein positives Signal sein</p><div class="canvas"><canvas id="scatter"></canvas></div></article></section>
<?php require __DIR__.'/partials/supporter-runner.php'; ?>
<section class="panel"><h2>Letzte Läufe im Team</h2><div class="tablewrap"><table><thead><tr><th>Datum</th><th>Läufer</th><th>Distanz</th><th>Pace</th><th>Ø HF</th><th>Höhenmeter</th></tr></thead><tbody><?php foreach(array_slice($teamRuns,0,90) as $r):?><tr class="team-entry team-<?=h($r['athlete'])?>"><td><?=h(date('d.m.Y',strtotime($r['date'])))?></td><td><?=h($r['runner'])?></td><td><?=h(percent($r['km']))?> km</td><td><?=h($r['pace']!==null?sprintf('%d:%02d',intdiv((int)round($r['pace']*60),60),(int)round($r['pace']*60)%60):'–')?></td><td><?=h($r['hr']??'–')?></td><td><?=h($r['elev'])?> m</td></tr><?php endforeach;?><?php if(!$teamRuns):?><tr><td colspan="6">Noch keine Läufe vorhanden. Bitte auf den ersten Sync warten.</td></tr><?php endif;?></tbody></table></div></section>
<?php if($a):?><section class="foot"><form method="post" onsubmit="return confirm('Strava-Verbindung und lokale Daten wirklich löschen?')"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="disconnect"><button class="outline danger">Strava trennen und Daten löschen</button></form></section><?php endif;?></div><script id="chart-data" type="application/json"><?=json_encode($data,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script></body></html>
