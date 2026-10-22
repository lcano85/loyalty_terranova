<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
$base=['name'=>'Branding QA','tagline'=>'QA','reward'=>'QA','total_stamps'=>6,'color'=>'#000000','background'=>'#ffffff'];
$logo='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aC1sAAAAASUVORK5CYII=';
$cases=[[$base,true],[$base+['logo'=>$logo,'stamp_icon'=>'pizza'],true],[$base+['stamp_icon'=>'unknown'],false],[$base+['logo'=>'data:image/svg+xml;base64,PHN2Zy8+'],false],[$base+['logo'=>'data:image/png;base64,aGVsbG8='],false],[$base+['logo'=>str_replace('image/png','image/jpeg',$logo)],false],[$base+['logo'=>'data:image/png;base64,'.base64_encode(str_repeat('x',204801))],false]];
foreach ($cases as [$data,$valid]) {
    $code='require '.var_export(dirname(__DIR__).'/src/bootstrap.php',true).'; echo json_encode(campaignData(json_decode(base64_decode("'.base64_encode(json_encode($data)).'"),true)));';
    $temp=tempnam(sys_get_temp_dir(),'branding-qa-');
    file_put_contents($temp,'<?php '.$code);
    $process=proc_open([PHP_BINARY,$temp],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    unlink($temp);
    $result=json_decode($output,true);
    if ($exit!==0 || $errors!=='' || !is_array($result) || ($valid ? count($result)!==8 || isset($result['error']) : !isset($result['error']))) throw new RuntimeException('Validation failed: '.$output.$errors);
}
db()->beginTransaction();
try {
    $id=uid();$fields=campaignData($base+['logo'=>$logo,'stamp_icon'=>'pizza']);
    query('INSERT INTO campaigns (name,tagline,reward,total_stamps,color,background,logo,stamp_icon,id) VALUES (?,?,?,?,?,?,?,?,?)',[...$fields,$id]);
    $campaign=one('SELECT * FROM campaigns WHERE id=?',[$id]);
    if ($campaign['logo']!==$logo || $campaign['stamp_icon']!=='pizza') throw new RuntimeException('Branding storage failed.');
    $snapshot=json_encode($campaign,JSON_THROW_ON_ERROR);
    query("UPDATE campaigns SET logo=NULL,stamp_icon='heart' WHERE id=?",[$id]);
    if (json_decode($snapshot,true)['stamp_icon']!=='pizza') throw new RuntimeException('Snapshot changed.');
} finally {db()->rollBack();}
echo "Campaign branding validation and storage passed.\n";
