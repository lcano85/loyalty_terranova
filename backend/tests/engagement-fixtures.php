<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
$fixturePath=dirname(__DIR__,2).'/.local/engagement-fixture.json';
$mode=$argv[1] ?? '';
if ($mode==='create') {
    if (is_file($fixturePath)) throw new RuntimeException('Limpia primero los fixtures anteriores.');
    $password=bin2hex(random_bytes(16)); $suffix=uid();
    $data=['owner_id'=>uid(),'staff_id'=>uid(),'campaign_a'=>uid(),'campaign_b'=>uid(),'customer_a'=>uid(),'customer_b'=>uid(),'password'=>$password,'owner_email'=>'qa-owner-'.$suffix.'@example.invalid','staff_email'=>'qa-staff-'.$suffix.'@example.invalid'];
    db()->beginTransaction();
    try {
        foreach (['owner','staff'] as $role) query('INSERT INTO users (id,name,email,password_hash,role) VALUES (?,?,?,?,?)',[$data[$role.'_id'],'Verificación temporal '.$role,$data[$role.'_email'],password_hash($password,PASSWORD_DEFAULT),$role]);
        foreach (['a','b'] as $key) {
            query('INSERT INTO campaigns (id,name,tagline,reward,total_stamps) VALUES (?,?,?,?,?)',[$data['campaign_'.$key],'Verificación temporal '.$key,'QA temporal','QA',6]);
            query('INSERT INTO customers (id,name) VALUES (?,?)',[$data['customer_'.$key],'Verificación temporal '.$key]);
        }
        foreach (['a1'=>'a','a2'=>'a','b'=>'b'] as $key=>$group) {
            $card=issueCard($data['campaign_'.$group],$data['customer_'.$group],['id'=>$data['owner_id'],'name'=>'Verificación temporal','role'=>'owner']);
            $data['card_'.$key]=$card['id']; $data['token_'.$key]=$card['public_token'];
        }
        file_put_contents($fixturePath,json_encode($data,JSON_THROW_ON_ERROR));db()->commit();
    } catch (Throwable $error) {db()->rollBack();throw $error;}
    echo "Fixtures locales preparados en campañas exclusivas de verificación.\n";
} elseif ($mode==='cleanup') {
    if (!is_file($fixturePath)) {echo "Sin fixtures para limpiar.\n";exit;}
    $data=json_decode(file_get_contents($fixturePath),true,32,JSON_THROW_ON_ERROR);
    foreach (['campaign_a','campaign_b','customer_a','customer_b','owner_id','staff_id','card_a1','card_a2','card_b'] as $key) if (!preg_match('/^[a-f0-9]{32}$/',$data[$key])) throw new RuntimeException('Fixture inválido.');
    db()->beginTransaction();
    try {
        query('DELETE FROM customer_notifications WHERE campaign_id IN (?,?) OR customer_id IN (?,?) OR promotion_id IN (SELECT id FROM promotions WHERE created_by=?)',[$data['campaign_a'],$data['campaign_b'],$data['customer_a'],$data['customer_b'],$data['owner_id']]);
        query('DELETE FROM promotion_requests WHERE customer_id IN (?,?)',[$data['customer_a'],$data['customer_b']]);
        query('DELETE FROM promotions WHERE created_by=?',[$data['owner_id']]);
        query('DELETE FROM transactions WHERE card_id IN (?,?,?)',[$data['card_a1'],$data['card_a2'],$data['card_b']]);
        query('DELETE FROM cards WHERE id IN (?,?,?)',[$data['card_a1'],$data['card_a2'],$data['card_b']]);
        query('DELETE FROM customers WHERE id IN (?,?)',[$data['customer_a'],$data['customer_b']]);
        query('DELETE FROM campaigns WHERE id IN (?,?)',[$data['campaign_a'],$data['campaign_b']]);
        query('DELETE FROM users WHERE id IN (?,?)',[$data['owner_id'],$data['staff_id']]);
        query('DELETE FROM login_attempts WHERE key_hash IN (?,?,?,?)',[hash('sha256','::1|'.$data['owner_email']),hash('sha256','::1|'.$data['staff_email']),hash('sha256','127.0.0.1|'.$data['owner_email']),hash('sha256','127.0.0.1|'.$data['staff_email'])]);
        db()->commit();unlink($fixturePath);
    } catch (Throwable $error) {db()->rollBack();throw $error;}
    echo "Fixtures eliminados. Sin base de pruebas ni registros QA persistentes.\n";
} else throw new RuntimeException('Usa create o cleanup. Solo para pruebas locales.');
