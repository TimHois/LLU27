<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/backyard_private/app/bootstrap.php';
$viewer = requireUser();
$pdo = db();
require_once dirname(__DIR__, 2) . '/backyard_private/app/challenge_score.php';
$score = challengeScore($pdo->query("SELECT u.username,w.* FROM challenge_weekly w JOIN users u ON u.id=w.user_id WHERE u.username IN ('tim','jonas','noah') ORDER BY w.iso_year DESC,w.iso_week DESC")->fetchAll());
extract($score, EXTR_SKIP);
$grand = array_sum(array_column($board, 'points'));
$allLoops = array_sum(array_column($board, 'loops'));
$finished = count(array_filter($history, static fn($h) => $h['complete']));
$cur = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$currentWeek = $cur->format('o-\WW');
?>
<!doctype html>
<html lang="de"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>LLU 27 · Challenge</title>
<link rel="icon" href="assets/llu27-icon.png"><link rel="stylesheet" href="assets/style.css?v=challenge-1">
<style>
.challenge-head{border:1px solid #354355;background:radial-gradient(circle at 95% 5%,#582b24 0,transparent 42%),linear-gradient(120deg,#1c2b3c,#111b29);border-radius:22px;padding:clamp(24px,4vw,42px);margin:30px 0 20px}
.challenge-head h1{font-size:clamp(36px,6vw,65px);line-height:1.04;letter-spacing:-.04em;margin:10px 0}.challenge-head h1 span{color:#fc4c02}
.challenge-head p{color:#b4c3d2}.challenge-meta{display:flex;gap:10px;flex-wrap:wrap;margin-top:23px}.challenge-tag{border-radius:30px;background:#27394d;border:1px solid #405267;padding:7px 13px;font-size:12px}
.challenge-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:18px 0 26px}.challenge-stat{border:1px solid #314154;background:#1a2635;border-radius:15px;padding:20px}.challenge-stat small{color:#9dadbd;display:block;letter-spacing:1px;font-size:11px}.challenge-stat strong{font-size:clamp(24px,4vw,34px);display:block;margin:4px 0}
.challenge-title{font-size:clamp(22px,3vw,28px);margin:0 0 16px}.challenge-list{display:grid;gap:12px}.rank-row{display:grid;grid-template-columns:54px 1fr auto;align-items:center;gap:16px;background:#142131;border:1px solid #354355;border-radius:14px;padding:17px}.rank-number{font-size:25px;font-weight:900;color:#9dadbd}.rank-row:first-child .rank-number{color:#ffc76b}.rank-name{font-size:17px;font-weight:800}.rank-meta{font-size:12px;color:#9dadbd;margin-top:3px}.rank-points{text-align:right;font-size:28px;font-weight:900;color:#fff}.rank-points small{display:block;font-size:10px;color:#9dadbd;font-weight:600}.challenge-bar-bg{margin-top:10px;background:#304254;height:8px;border-radius:30px;overflow:hidden}.challenge-bar{height:100%;border-radius:30px}
.challenge-week{border:1px solid #334255;background:#172537;border-radius:15px;padding:19px;margin-bottom:13px}.challenge-week h3{margin:0;font-size:17px}.challenge-week-header{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:14px}.week-status{color:#b8c8d7;font-size:11px}.week-status.final{color:#8be4bc}.week-table{width:100%;border-collapse:collapse}.week-table td,.week-table th{padding:9px 8px;border-bottom:1px solid #2b3d50;text-align:left}.week-table td:last-child,.week-table th:last-child{text-align:right}.week-name{font-weight:700}.week-winner{color:#ffc76b;font-size:11px;margin-left:7px}.challenge-note{color:#9dadbd;font-size:12px;line-height:1.6;margin:16px 0 28px}.challenge-nav{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.challenge-nav a{text-decoration:none}
@media(max-width:650px){.challenge-grid{grid-template-columns:1fr}.rank-row{grid-template-columns:36px 1fr auto;gap:8px;padding:13px}.rank-points{font-size:22px}.challenge-week{padding:13px}.week-table{font-size:12px}}
</style><link rel="stylesheet" href="assets/llu-navigation.css?v=2"><script src="assets/llu-navigation.js?v=2" defer></script></head><body class="llu-layout">
<?php require __DIR__ . "/partials/navigation.php"; ?>
<div class="shell">
<header class="top"><div class="brand"><img src="assets/llu27-icon.png" alt="LLU 27" width="46" height="46" style="border-radius:11px;object-fit:cover;vertical-align:middle"> LLU <em>27</em><span class="brand-caption"> / BACKYARD RUNNING</span></div><div class="challenge-nav"><a href="dashboard.php" class="btn outline">Dashboard</a><a href="profile.php" class="btn outline">Profil</a></div></header>
<section class="challenge-head"><p class="eyebrow">LLU 27 · BACKYARD ULTRA</p><h1>ONE MORE LOOP.<br><span>ONE MORE POINT.</span></h1><p>Jede vollständige 6,706-km-Runde zählt. Die meisten Runden einer abgeschlossenen Woche bringen zusätzlich fünf Punkte.</p><div class="challenge-meta"><span class="challenge-tag">+1 Punkt / Loop</span><span class="challenge-tag">+5 Wochenbonus</span><span class="challenge-tag">KW <?=h($cur->format('W / o'))?></span></div></section>
<section class="challenge-grid" aria-label="Teamstatistik"><div class="challenge-stat"><small>GESAMTPUNKTE</small><strong><?=h((string)$grand)?></strong>Teamwertung</div><div class="challenge-stat"><small>VOLLSTÄNDIGE LOOPS</small><strong><?=h((string)$allLoops)?></strong>6,706 km je Loop</div><div class="challenge-stat"><small>ABGESCHLOSSENE WOCHEN</small><strong><?=h((string)$finished)?></strong>Archivierte Wochen</div></section>
<section class="panel" style="margin-bottom:22px"><p class="eyebrow">LLU 27 · SCOREBOARD</p><h2 class="challenge-title">Die Rangliste</h2>
<?php $high = max(1, ...array_column($ranking,'points')); foreach ($ranking as $i => $person): ?>
<div class="rank-row"><div class="rank-number">#<?=h((string)($i+1))?></div><div><div class="rank-name" style="color:<?=h($person['color'])?>"><?=h($person['name'])?></div><div class="rank-meta"><?=h((string)$person['loops'])?> Loop-Punkte · <?=h((string)$person['bonus'])?> Bonuspunkte · <?=h((string)$person['wins'])?> Wochensiege</div><div class="challenge-bar-bg"><div class="challenge-bar" style="width:<?=h((string)round($person['points']/$high*100,2))?>%;background:<?=h($person['color'])?>"></div></div></div><div class="rank-points"><?=h((string)$person['points'])?><small>PUNKTE</small></div></div>
<?php endforeach; ?></section>
<section class="panel"><p class="eyebrow">LLU 27 · HISTORY</p><h2 class="challenge-title">Wochenhistorie</h2>
<?php if (!$history): ?><p class="muted">Noch keine Challenge-Daten vorhanden. Führe zuerst den Challenge-Cronjob aus.</p><?php endif; ?>
<?php foreach ($history as $w): $parts = explode('-W', $w['week']); $caption = 'KW '.(int)($parts[1]??0).' / '.$parts[0]; ?>
<div class="challenge-week"><div class="challenge-week-header"><h3><?=h($caption)?></h3><span class="week-status <?=$w['complete']?'final':''?>"><?=$w['complete']?'● Abgeschlossen':'○ Vorläufig'?></span></div>
<table class="week-table"><thead><tr><th>Läufer</th><th>Loops</th><th>Bonus</th><th>Punkte</th></tr></thead><tbody>
<?php foreach ($people as $id => $person): $loops=$w['members'][$id]['loops']??0; $win=in_array($id,$w['winners'],true); ?>
<tr><td class="week-name" style="color:<?=h($person['color'])?>"><?=h($person['name'])?><?=$win?'<span class="week-winner">★ Sieger</span>':''?></td><td><?=h((string)$loops)?></td><td><?=$win?'+5':'–'?></td><td><strong><?=h((string)($loops+($win?5:0)))?></strong></td></tr>
<?php endforeach; ?></tbody></table>
<?php if (!$w['complete']): ?><p class="week-status">Bonus noch nicht vergeben. Finalisierung frühestens Mittwoch nach erfolgreicher Synchronisierung aller Läufer.</p><?php endif; ?>
</div><?php endforeach; ?></section>
<p class="challenge-note">Regeln: Eine Aktivität mit 13,4 km ergibt 1 vollständigen Loop; zwei getrennte 4-km-Läufe ergeben 0. Gezählt werden nur komplette 6,706-km-Einheiten innerhalb einzelner Laufaktivitäten und ab dem jeweiligen persönlichen Trainingsstart. Bei Gleichstand an der Spitze erhalten alle Erstplatzierten jeweils +5 Punkte. Eine Woche ohne Loop bekommt keinen Bonus. Vergangene, bereits finalisierte Ergebnisse bleiben gespeichert. Die Challenge-Historie ist keine vollständige historische Strava-Archivierung; sie beginnt mit den Wochen, die beim Einrichten noch auswertbar sind.</p>
</div></body></html>
