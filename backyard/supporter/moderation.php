<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/backyard_private/app/bootstrap.php';
$u=requireUser();
if(!in_array((int)$u['id'],array_filter(array_map('intval',explode(',',cfg('SUPPORTER_ADMIN_RUNNER_IDS')))),true)) { http_response_code(403); exit('Kein Zugriff.'); }
if($_SERVER['REQUEST_METHOD']==='POST') { verifyCsrf(); db()->prepare('DELETE FROM supporter_cheers WHERE id=?')->execute([(int)($_POST['id']??0)]); redirect('supporter/moderation.php'); }
$messages=db()->query('SELECT c.id,c.message,c.created_at,COALESCE(s.display_name,\'Gelöschtes Konto\') sender,u.display_name runner FROM supporter_cheers c LEFT JOIN supporters s ON s.id=c.supporter_id JOIN users u ON u.id=c.runner_id ORDER BY c.id DESC LIMIT 200')->fetchAll();
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Supporter-Moderation</title><link rel="stylesheet" href="../assets/style.css"></head><body><main class="shell"><h1>Anfeuerungen moderieren</h1><a href="../dashboard.php">Zum Läufer-Dashboard</a><?php foreach($messages as $m): ?><section class="panel"><p><?=h($m['sender'])?> → <?=h($m['runner'])?> · <?=h($m['created_at'])?> UTC</p><p><?=h($m['message'])?></p><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="id" value="<?=h($m['id'])?>"><button>Löschen</button></form></section><?php endforeach; ?></main></body></html>
