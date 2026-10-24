<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/backyard_private/app/supporter.php';
$u=supporterRequire();
if($_SERVER['REQUEST_METHOD']==='POST') { verifyCsrf(); supporterRevoke((int)$u['id']); redirect('supporter/login.php'); }
supporterHead('Abmelden',$u); ?><section class="panel"><p>Alle deine Supporter-Sitzungen und dauerhaften Anmeldungen werden widerrufen.</p><form method="post"><?php supporterField(); ?><button class="btn">Auf allen Geräten abmelden</button></form></section><?php supporterFoot(); ?>
