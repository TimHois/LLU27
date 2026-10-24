<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/backyard_private/app/supporter.php';
require_once dirname(__DIR__,3).'/backyard_private/app/push.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
try {
    if($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); header('Allow: POST'); exit; }
    $role=(string)($_GET['role']??'');
    if($role==='runner') { safeSession(); $owner=currentUser(); $column='runner_id'; }
    elseif($role==='supporter') { $owner=supporterCurrent(); $column='supporter_id'; }
    else throw new RuntimeException('Ungültige Rolle.');
    if(!$owner) { http_response_code(401); echo json_encode(['error'=>'Bitte erneut anmelden.']); exit; }
    if((int)($_SERVER['CONTENT_LENGTH']??0)>8192) throw new RuntimeException('Anfrage zu groß.');
    $raw=file_get_contents('php://input',false,null,0,8193); if(strlen($raw)>8192) throw new RuntimeException('Anfrage zu groß.');
    $data=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
    if(!hash_equals(csrf(),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??''))) throw new RuntimeException('Ungültige Formularanfrage.');
    supporterLimit('push:'.$role.':'.$owner['id'],60,3600);
    if(($data['action']??'')==='disable-all') {
        db()->prepare("DELETE FROM push_subscriptions WHERE $column=?")->execute([$owner['id']]);
    } else {
        $sub=pushValidate((array)($data['subscription']??[])); $hash=hash('sha256',$sub['endpoint']);
        if(($data['action']??'')==='disable') db()->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash=? AND $column=?")->execute([$hash,$owner['id']]);
        elseif(($data['action']??'')==='enable') {
            if(cfg('VAPID_PUBLIC_KEY')===''||cfg('VAPID_PRIVATE_KEY')==='') throw new RuntimeException('Push ist serverseitig noch nicht eingerichtet.');
            $count=db()->prepare("SELECT COUNT(*) FROM push_subscriptions WHERE $column=? AND endpoint_hash<>?"); $count->execute([$owner['id'],$hash]);
            if((int)$count->fetchColumn()>=20) throw new RuntimeException('Maximal 20 Geräte. Bitte zuerst alte Abonnements deaktivieren.');
            // A browser subscription can only belong to one account/role at a time.
            $q=db()->prepare('INSERT INTO push_subscriptions(runner_id,supporter_id,endpoint_hash,subscription) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE runner_id=VALUES(runner_id),supporter_id=VALUES(supporter_id),subscription=VALUES(subscription),created_at=UTC_TIMESTAMP()');
            $q->execute([$role==='runner'?$owner['id']:null,$role==='supporter'?$owner['id']:null,$hash,enc(json_encode($sub,JSON_THROW_ON_ERROR))]);
        } else throw new RuntimeException('Ungültige Aktion.');
    }
    echo json_encode(['ok'=>true]);
} catch(Throwable $e) { http_response_code(400); echo json_encode(['error'=>$e instanceof RuntimeException&&!($e instanceof PDOException)?$e->getMessage():'Push-Anfrage fehlgeschlagen.']); }
