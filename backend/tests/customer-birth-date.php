<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
$cases=[
    [[],null],
    [['birth_date'=>''],null],
    [['birth_date'=>null],null],
    [['birth_date'=>'1990-06-15'],'1990-06-15'],
    [['birth_date'=>'2024-02-29'],'2024-02-29'],
    [['birth_date'=>date('Y-m-d')],date('Y-m-d')],
    [['birth_date'=>'2023-02-29'],'error'],
    [['birth_date'=>'2000-02-30'],'error'],
    [['birth_date'=>'15/06/1990'],'error'],
    [['birth_date'=>'0999-01-01'],'error'],
    [['birth_date'=>date('Y-m-d',strtotime('+1 day'))],'error'],
    [['birth_date'=>['invalid']],'error'],
];
foreach ($cases as [$input,$expected]) {
    $code='require '.var_export(dirname(__DIR__).'/src/bootstrap.php',true).'; $data=json_decode(base64_decode("'.base64_encode(json_encode($input)).'"),true); echo json_encode(["value"=>birthDateField($data)]);';
    $process=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $stdout=stream_get_contents($pipes[1]); $stderr=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($process);
    $result=json_decode($stdout,true);
    if ($exit!==0 || $stderr!=='' || !is_array($result) || ($expected==='error' ? !isset($result['error']) : !array_key_exists('value',$result) || $result['value']!==$expected)) {
        throw new RuntimeException('Validación fallida: '.json_encode($input).' '.$stdout.' '.$stderr);
    }
}
// Check MySQL DATE storage in a transaction; this fixture is never committed.
db()->beginTransaction();
try {
    $id=uid();
    query('INSERT INTO customers (id,name,birth_date) VALUES (?,?,?)',[$id,'Validación temporal de fecha','1990-06-15']);
    if (one('SELECT birth_date FROM customers WHERE id=?',[$id])['birth_date']!=='1990-06-15') throw new RuntimeException('La fecha no se conservó.');
    query('UPDATE customers SET birth_date=NULL WHERE id=?',[$id]);
    if (one('SELECT birth_date FROM customers WHERE id=?',[$id])['birth_date']!==null) throw new RuntimeException('No se pudo limpiar la fecha.');
} finally {db()->rollBack();}
echo "PASS: fecha opcional, validación de calendario, rechazo de fechas futuras y almacenamiento/limpieza en MySQL. Sin datos de prueba persistidos.\n";
