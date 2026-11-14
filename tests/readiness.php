<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/autoload.php';
use Learmingo\Services\ReadinessWorkspace;
$dir=sys_get_temp_dir().'/learmingo-readiness-'.bin2hex(random_bytes(8));
$store=new ReadinessWorkspace($dir.'/items.json');
function check(bool $ok): void { if (!$ok) { throw new RuntimeException('Assertion failed'); } }
function rejected(callable $fn): void { try {$fn();} catch (InvalidArgumentException) { return; } throw new RuntimeException('Expected rejection'); }
try {
    check($store->run()===[]);
    $input=['title'=>'Quiz acceptance','course'=>'Leadership','assessment'=>'Final quiz','version'=>'v1','environment'=>'staging','rule'=>'Pass 80%','reviewer'=>'QA','evidence'=>'https://example.com/evidence','defects'=>'none','rollback'=>'Restore previous revision','decision'=>'hold','details'=>'Quiz rubric','view'=>'Assessment design','status'=>'planned'];
    rejected(fn()=>$store->run(array_merge($input,['status'=>'approved']),1));
    $items=$store->run($input,1);$item=reset($items);
    $input=array_merge($input,['id'=>$item['id'],'revision'=>1]);
    rejected(fn()=>$store->run(array_merge($input,['status'=>'verified']),1));
    rejected(fn()=>$store->run(array_merge($input,['evidence'=>'javascript:alert(1)']),1));
    foreach (['in progress','in review','verified','approved'] as $status) {
        if ($status==='verified') {
            rejected(fn()=>$store->run(array_merge($input,['status'=>$status,'environment'=>'production']),1));
            rejected(fn()=>$store->run(array_merge($input,['status'=>$status,'defects'=>'Broken grading']),1));
            rejected(fn()=>$store->run(array_merge($input,['status'=>$status,'evidence'=>'']),1));
            rejected(fn()=>$store->run(array_merge($input,['status'=>$status,'title'=>'Changed after review']),1));
        }
        if ($status==='approved') { rejected(fn()=>$store->run(array_merge($input,['status'=>$status]),1)); $input['decision']='acceptance complete'; }
        $items=$store->run(array_merge($input,['status'=>$status]),7);$item=reset($items);
        $input=array_merge($input,['status'=>$status,'revision'=>$item['revision']]);
    }
    check(count($item['history'])===5 && $item['status']==='approved');
    rejected(fn()=>$store->run(array_merge($input,['revision'=>1]),1));
    $items=$store->run(array_merge($input,['status'=>'planned','title'=>'Revised quiz']),7);
    check(reset($items)['status']==='planned');
    check($store->run()===$items);
    echo "Readiness workflow: PASS\n";
} finally { foreach (glob($dir.'/*') ?: [] as $file) {unlink($file);} if (is_dir($dir)) {rmdir($dir);} }
