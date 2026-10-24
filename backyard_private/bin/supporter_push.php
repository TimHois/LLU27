<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(403); exit; }
require_once dirname(__DIR__).'/app/push.php';
$lock=fopen(dirname(__DIR__).'/supporter-push.lock','c'); if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)) exit;
$p=db();
$p->exec('DELETE FROM supporter_limits WHERE expires_at<UTC_TIMESTAMP()-INTERVAL 1 DAY');
$p->exec('DELETE FROM supporter_tokens WHERE expires_at<UTC_TIMESTAMP()');
$p->exec('DELETE j FROM supporter_push_jobs j JOIN supporter_cheers c ON c.id=j.cheer_id WHERE c.created_at<UTC_TIMESTAMP()-INTERVAL 1 DAY');
if(cfg('VAPID_PUBLIC_KEY')===''||cfg('VAPID_PRIVATE_KEY')==='') exit("Push not configured; maintenance completed.\n");
require_once dirname(__DIR__).'/vendor/autoload.php';
$push=new Minishlink\WebPush\WebPush(['VAPID'=>['subject'=>cfg('VAPID_SUBJECT'),'publicKey'=>cfg('VAPID_PUBLIC_KEY'),'privateKey'=>cfg('VAPID_PRIVATE_KEY')]],['TTL'=>3600,'urgency'=>'normal']);
// Bounded batches; one device per delivery, retry temporary errors only.
$jobs=$p->query('SELECT j.*,c.runner_id,c.message,c.created_at FROM supporter_push_jobs j JOIN supporter_cheers c ON c.id=j.cheer_id WHERE j.available_at<=UTC_TIMESTAMP() ORDER BY j.id LIMIT 50')->fetchAll();
foreach($jobs as $job) {
    $failed=false;
    $q=$p->prepare('SELECT s.* FROM push_subscriptions s LEFT JOIN supporter_push_deliveries d ON d.subscription_id=s.id AND d.job_id=? WHERE s.runner_id=? AND d.job_id IS NULL'); $q->execute([$job['id'],$job['runner_id']]);
    foreach($q->fetchAll() as $sub) {
        // Do not deliver old cheers to a device which subscribed afterwards.
        if($sub['created_at']>$job['created_at']) continue;
        try {
            $data=pushValidate(json_decode(dec($sub['subscription']),true,16,JSON_THROW_ON_ERROR));
            $report=$push->sendOneNotification(Minishlink\WebPush\Subscription::create($data),json_encode(['body'=>'🔥 Ein Supporter feuert dich an: '.$job['message']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
            if($report->isSuccess()) $p->prepare('INSERT IGNORE INTO supporter_push_deliveries(job_id,subscription_id) VALUES(?,?)')->execute([$job['id'],$sub['id']]);
            elseif($report->isSubscriptionExpired()) $p->prepare('DELETE FROM push_subscriptions WHERE id=?')->execute([$sub['id']]);
            elseif(!$report->isSuccess()) { $failed=true; error_log('LLU27 push failed job='.(int)$job['id'].' subscription='.(int)$sub['id'].' status='.(int)($report->getResponse()?->getStatusCode()??0)); }
        } catch(Throwable $e) { $failed=true; error_log('LLU27 push error job='.(int)$job['id'].' subscription='.(int)$sub['id'].' type='.get_class($e)); }
    }
    if(!$failed || (int)$job['attempts']>=4) $p->prepare('DELETE FROM supporter_push_jobs WHERE id=?')->execute([$job['id']]);
    else $p->prepare('UPDATE supporter_push_jobs SET attempts=attempts+1,available_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE) WHERE id=?')->execute([$job['id']]);
}
