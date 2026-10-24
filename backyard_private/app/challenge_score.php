<?php
declare(strict_types=1);

/** Shared rules for the runner challenge, supporter ranking and predictions. */
function challengeScore(array $rows): array
{
    $people = ['tim'=>['name'=>'Tim','color'=>'#fc4c02'], 'jonas'=>['name'=>'Jonas','color'=>'#2dd4bf'], 'noah'=>['name'=>'Noah','color'=>'#a5a0ff']];
    $weeks = [];
    foreach ($rows as $r) {
        if (!isset($people[$r['username']])) continue;
        $key = sprintf('%04d-W%02d', $r['iso_year'], $r['iso_week']);
        $weeks[$key][$r['username']] = ['loops'=>(int)$r['completed_loops'], 'runs'=>(int)$r['run_count'], 'final'=>(bool)$r['finalized']];
    }
    krsort($weeks);
    $board=[]; $history=[];
    foreach ($people as $id=>$p) $board[$id]=$p+['username'=>$id,'loops'=>0,'bonus'=>0,'wins'=>0,'points'=>0];
    foreach ($weeks as $key=>$members) {
        $complete=count($members)===3 && !in_array(false,array_column($members,'final'),true);
        $max=max(array_column($members,'loops')); $winners=[];
        if ($complete && $max>0) foreach ($members as $id=>$m) if ($m['loops']===$max) $winners[]=$id;
        foreach ($people as $id=>$_) {
            $loops=$members[$id]['loops']??0; $bonus=in_array($id,$winners,true)?5:0;
            $board[$id]['loops']+=$loops; $board[$id]['bonus']+=$bonus;
            $board[$id]['wins']+=(int)($bonus>0); $board[$id]['points']+=$loops+$bonus;
        }
        $history[]=['week'=>$key,'members'=>$members,'complete'=>$complete,'winners'=>$winners];
    }
    $ranking=array_values($board);
    usort($ranking,static fn($a,$b)=>($b['points']<=>$a['points'])?:($b['loops']<=>$a['loops'])?:strcmp($a['name'],$b['name']));
    return compact('people','weeks','board','history','ranking');
}
