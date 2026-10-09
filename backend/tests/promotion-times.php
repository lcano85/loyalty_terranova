<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
require dirname(__DIR__).'/src/engagement.php';
function windowAt(int $start,int $end): array {
    return ['published'=>1,'start_date'=>date('Y-m-d',$start),'start_time'=>date('H:i:s',$start),'end_date'=>date('Y-m-d',$end),'end_time'=>date('H:i:s',$end)];
}
$now=time();
foreach ([['scheduled',$now+60,$now+120],['active',$now-60,$now+60],['expired',$now-120,$now-60]] as [$expected,$start,$end]) {
    if (promotionAvailability(windowAt($start,$end))!==$expected) throw new RuntimeException('Estado incorrecto: '.$expected);
}
echo "PASS: vigencia antes, durante y después del horario de Lima.\n";
