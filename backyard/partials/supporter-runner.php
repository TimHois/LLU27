<?php
// Optional module: a pending supporter migration must never break the runner dashboard.
require_once dirname(__DIR__,3).'/backyard_private/app/supporter.php';
require_once dirname(__DIR__,3).'/backyard_private/app/push.php';
$supporterError=''; $supporterMessages=[]; $supporterReady=false; $sharing=false;
$adminIds=array_filter(array_map('intval',explode(',',cfg('SUPPORTER_ADMIN_RUNNER_IDS'))));
$supporterAdmin=in_array((int)$u['id'],$adminIds,true);
try {
    $q=db()->prepare('SELECT runner_id FROM supporter_sharing WHERE runner_id=?'); $q->execute([$u['id']]); $sharing=(bool)$q->fetchColumn(); $supporterReady=true;
    if($_SERVER['REQUEST_METHOD']==='POST' && str_starts_with((string)($_POST['action']??''),'supporter_')) {
        verifyCsrf();
        if($_POST['action']==='supporter_share') {
            if(!empty($_POST['approved'])) db()->prepare('INSERT INTO supporter_sharing(runner_id,approved_at) VALUES(?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE approved_at=UTC_TIMESTAMP()')->execute([$u['id']]);
            else db()->prepare('DELETE FROM supporter_sharing WHERE runner_id=?')->execute([$u['id']]);
            $sharing=!empty($_POST['approved']);
        } elseif($_POST['action']==='supporter_delete' && $supporterAdmin) db()->prepare('DELETE FROM supporter_cheers WHERE id=?')->execute([(int)($_POST['cheer_id']??0)]);
    }
    $q=db()->prepare('SELECT c.*,COALESCE(s.display_name,\'Ehemaliger Supporter\') sender FROM supporter_cheers c LEFT JOIN supporters s ON s.id=c.supporter_id WHERE c.runner_id=? ORDER BY c.id DESC LIMIT 20'); $q->execute([$u['id']]); $supporterMessages=$q->fetchAll();
} catch(Throwable $e) { $supporterError='Supporter-Bereich derzeit nicht verfügbar. Trainingsfunktionen bleiben verfügbar.'; }
?>
<section class="panel" id="supporter-cheers"><h2>Deine Supporter feuern dich an</h2><?php if($supporterError): ?><p><?=h($supporterError)?></p><?php endif; ?><?php if($supporterReady): ?>
<?php if(!$supporterMessages): ?><p>Noch keine Anfeuerungen. Teile die <a href="supporter/register.php">Supporter-Registrierung</a>.</p><?php endif; ?>
<?php foreach($supporterMessages as $c): ?><article><strong><?=h($c['sender'])?></strong><p><?=h($c['message'])?></p><small><?=h($c['created_at'])?> UTC</small><?php if($supporterAdmin): ?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="supporter_delete"><input type="hidden" name="cheer_id" value="<?=h($c['id'])?>"><button type="submit">Nachricht löschen</button></form><?php endif; ?></article><?php endforeach; ?>
<h3>Freigabe für die Supporter Zone</h3><p>Freigegeben werden dein Anzeigename, aggregierte Kilometer, Laufanzahl, Loops, Wochenkilometer und Challenge-Ergebnisse. Keine Routen, Aktivitätsdetails oder Gesundheitswerte.</p><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="supporter_share"><label><input type="checkbox" name="approved" value="1" <?=$sharing?'checked':''?>> Ich gebe meine Trainingssummen und Challenge-Ergebnisse für registrierte Supporter frei.</label><button type="submit">Freigabe speichern</button></form>
<?php if($supporterAdmin): ?><p><a href="supporter/moderation.php">Alle Nachrichten moderieren</a></p><?php endif; ?>
<?php endif; ?></section><?php if($supporterReady) pushPanel('runner'); ?>
