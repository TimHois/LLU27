<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';

function pushValidate(array $data): array {
    $endpoint=(string)($data['endpoint']??''); $parts=parse_url($endpoint);
    // Only browser-vendor gateways; never let users make the server fetch arbitrary URLs.
    $host=strtolower((string)($parts['host']??''));
    $allowed=in_array($host,['fcm.googleapis.com','updates.push.services.mozilla.com','web.push.apple.com'],true) || (str_ends_with($host,'.notify.windows.com') && strlen($host)>19);
    if(strlen($endpoint)>2048 || !$allowed || ($parts['scheme']??'')!=='https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment'])) throw new RuntimeException('Nicht unterstützter Push-Dienst.');
    $keys=$data['keys']??[];
    foreach(['p256dh'=>65,'auth'=>16] as $name=>$length) {
        $value=(string)($keys[$name]??''); $raw=base64_decode(strtr($value,'-_','+/'),true);
        if(!preg_match('/^[A-Za-z0-9_-]+={0,2}$/D',$value) || $raw===false || strlen($raw)!==$length || ($name==='p256dh' && ord($raw[0])!==4)) throw new RuntimeException('Ungültige Push-Schlüssel.');
    }
    return ['endpoint'=>$endpoint,'keys'=>['p256dh'=>$keys['p256dh'],'auth'=>$keys['auth']],'contentEncoding'=>'aes128gcm'];
}
function pushPanel(string $role): void {
    ?><section class="panel"><h2>Push-Benachrichtigungen</h2><p><?= $role==='runner'?'Erhalte eine Nachricht, wenn Supporter dich anfeuern.':'Du kannst dieses Gerät für künftige Supporter-Benachrichtigungen anmelden. Aktuell werden Anfeuerungen nur an den jeweiligen Läufer gesendet.' ?></p><p>iPhone/iPad: In Safari „Teilen“ → „Zum Home-Bildschirm“, dann die App vom Home-Bildschirm öffnen (ab iOS 16.4).</p><div class="push-controls" data-push data-api="<?=h(url('supporter/push.php?role='.$role))?>" data-sw="<?=h(url('sw.js'))?>" data-key="<?=h(cfg('VAPID_PUBLIC_KEY'))?>" data-csrf="<?=h(csrf())?>"><button type="button" class="btn" data-enable>Push auf diesem Gerät aktivieren</button><button type="button" class="btn outline" data-disable>Auf diesem Gerät deaktivieren</button><button type="button" class="btn outline" data-disable-all>Auf allen Geräten deaktivieren</button><p role="status" data-status></p></div></section><script src="<?=h(url('assets/supporter-push.js'))?>" defer></script><?php
}
