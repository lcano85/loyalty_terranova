<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
require dirname(__DIR__).'/src/engagement.php';
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = array_filter(array_map('trim', explode(',', env('FRONTEND_ALLOWED_ORIGINS', env('FRONTEND_URL','http://localhost:6012')))));
if ($origin !== '' && !in_array($origin, $allowedOrigins, true)) fail('Origen no autorizado.',403);
if ($origin !== '') {header('Access-Control-Allow-Origin: '.$origin); header('Vary: Origin'); header('Access-Control-Allow-Credentials: true');}
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {http_response_code(204); exit;}
session_name('terranova_session');
$sessionDirectory=dirname(__DIR__).'/storage/sessions';
if (!is_dir($sessionDirectory)) mkdir($sessionDirectory,0700,true);
session_save_path($sessionDirectory);
session_set_cookie_params(['httponly'=>true,'secure'=>env('SESSION_SECURE')==='true','samesite'=>'Lax','path'=>'/']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$path = rtrim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/');
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method,['GET','HEAD'],true) && !hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) fail('Sesión inválida. Recarga la página.',403);
try {
    if ($path === '/api/health') respond(['ok'=>true,'database'=>(bool)one('SELECT 1 AS ok')]);
    if ($path === '/api/session' && $method === 'GET') {
        $u=isset($_SESSION['user_id']) ? one('SELECT id,name,email,role,active FROM users WHERE id=?',[$_SESSION['user_id']]) : null;
        respond(['user'=>$u && $u['active'] ? $u : null,'csrf'=>$_SESSION['csrf']]);
    }
    if ($path === '/api/login' && $method === 'POST') {
        $data=body(); $email=emailField($data); $password=textField($data,'password',200);
        $key=hash('sha256',($_SERVER['REMOTE_ADDR'] ?? '').'|'.$email);
        query('INSERT INTO login_attempts (key_hash,attempts,window_start) VALUES (?,0,?) ON DUPLICATE KEY UPDATE key_hash=VALUES(key_hash)',[$key,time()]);
        db()->beginTransaction();
        $attempt=one('SELECT * FROM login_attempts WHERE key_hash=? FOR UPDATE',[$key]);
        if ((int)$attempt['window_start'] < time()-900) {query('UPDATE login_attempts SET attempts=0,window_start=? WHERE key_hash=?',[time(),$key]); $attempt['attempts']=0;}
        if ((int)$attempt['attempts'] >= 10) {db()->commit(); fail('Demasiados intentos. Espera 15 minutos.',429);}
        query('UPDATE login_attempts SET attempts=attempts+1 WHERE key_hash=?',[$key]); db()->commit();
        $u=one('SELECT * FROM users WHERE email=?',[$email]);
        if (!$u || !password_verify($password,$u['password_hash']) || !$u['active']) fail('Correo o contraseña incorrectos.',401);
        query('DELETE FROM login_attempts WHERE key_hash=?',[$key]);
        session_regenerate_id(true); $_SESSION['user_id']=$u['id']; $_SESSION['csrf']=bin2hex(random_bytes(32));
        unset($u['password_hash']); respond(['user'=>$u,'csrf'=>$_SESSION['csrf']]);
    }
    if ($path === '/api/logout' && $method === 'POST') {$_SESSION=[]; session_destroy(); respond(['ok'=>true]);}
    handlePublicEngagement($path,$method);
    if (preg_match('#^/api/public/cards/([a-f0-9]{64})$#',$path,$m) && $method === 'GET') {
        $card=one("SELECT id,snapshot,stamps,status,created_at,updated_at FROM cards WHERE public_token=? AND status!='Revoked'",[$m[1]]);
        if (!$card) fail('Tarjeta no disponible.',404);
        respond(['card'=>cardView($card),'business'=>one('SELECT name,slug FROM business WHERE id=1')]);
    }
    if (preg_match('#^/api/public/campaigns/([a-f0-9]{32})$#',$path,$m) && $method === 'GET') {
        $c=one('SELECT * FROM campaigns WHERE id=? AND enabled=1',[$m[1]]); if (!$c) fail('Campaña no disponible.',404); respond($c);
    }
    if (preg_match('#^/api/public/campaigns/([a-f0-9]{32})/join$#',$path,$m) && $method === 'POST') {
        $data=body(); $email=emailField($data); $name=textField($data,'name'); $mobile=textField($data,'mobile',30,false);
        $key=hash('sha256','join|'.($_SERVER['REMOTE_ADDR'] ?? ''));
        db()->beginTransaction();
        query('INSERT INTO login_attempts (key_hash,attempts,window_start) VALUES (?,0,?) ON DUPLICATE KEY UPDATE key_hash=VALUES(key_hash)',[$key,time()]);
        $attempt=one('SELECT * FROM login_attempts WHERE key_hash=? FOR UPDATE',[$key]);
        if ((int)$attempt['window_start'] < time()-3600) {query('UPDATE login_attempts SET attempts=0,window_start=? WHERE key_hash=?',[time(),$key]); $attempt['attempts']=0;}
        if ((int)$attempt['attempts'] >= 20) {db()->commit(); fail('Se alcanzó el límite de registros. Solicita tu tarjeta en el local.',429);}
        query('UPDATE login_attempts SET attempts=attempts+1 WHERE key_hash=?',[$key]); db()->commit();
        if (one('SELECT id FROM customers WHERE email=?',[$email])) fail('No se pudo registrar este correo. Solicita tu tarjeta o actualización de datos en el local.');
        db()->beginTransaction(); $customerId=uid();
        query('INSERT INTO customers (id,name,email,mobile) VALUES (?,?,?,?)',[$customerId,$name,$email,$mobile]);
        $card=issueCard($m[1],$customerId,['name'=>'Registro del cliente','role'=>'customer']);
        db()->commit(); respond($card,201);
    }
    $actor=user();
    handleAdminEngagement($path,$method,$actor);
    if ($path === '/api/workspace' && $method === 'GET') {
        $customers=rows("SELECT cu.*, (SELECT COUNT(*) FROM cards c WHERE c.customer_id=cu.id AND c.status='Active') AS active_cards FROM customers cu ORDER BY created_at DESC");
        $cards=array_map('cardView',rows('SELECT c.*,cu.name AS customer_name,cu.email AS customer_email FROM cards c JOIN customers cu ON cu.id=c.customer_id ORDER BY c.created_at DESC'));
        respond(['business'=>one('SELECT * FROM business WHERE id=1'),'campaigns'=>rows('SELECT * FROM campaigns ORDER BY created_at DESC'),'customers'=>$customers,'cards'=>$cards,'transactions'=>rows('SELECT t.*,cu.name AS customer_name,cu.email AS customer_email,c.campaign_id,c.snapshot,c.public_token FROM transactions t JOIN cards c ON c.id=t.card_id JOIN customers cu ON cu.id=c.customer_id ORDER BY t.created_at DESC,t.id DESC'),'staff'=>$actor['role']==='owner' ? rows("SELECT id,name,email,active,created_at FROM users WHERE role='staff'") : []]);
    }
    if ($path === '/api/campaigns' && $method === 'POST') {owner(); $fields=campaignData(body()); $id=uid(); query('INSERT INTO campaigns (name,tagline,reward,total_stamps,color,background,logo,stamp_icon,id) VALUES (?,?,?,?,?,?,?,?,?)',[...$fields,$id]); respond(['id'=>$id],201);}
    if (preg_match('#^/api/campaigns/([a-f0-9]{32})$#',$path,$m)) {
        owner(); if (!one('SELECT id FROM campaigns WHERE id=?',[$m[1]])) fail('Campaña inexistente.',404);
        if ($method === 'PATCH') {$data=body(); if (array_key_exists('enabled',$data)) {if (!is_bool($data['enabled'])) fail('Estado inválido.'); query('UPDATE campaigns SET enabled=? WHERE id=?',[(int)$data['enabled'],$m[1]]);} else {$fields=campaignData($data); query('UPDATE campaigns SET name=?,tagline=?,reward=?,total_stamps=?,color=?,background=?,logo=?,stamp_icon=? WHERE id=?',[...$fields,$m[1]]);} respond(['ok'=>true]);}
        if ($method === 'DELETE') {if (one('SELECT id FROM cards WHERE campaign_id=? LIMIT 1',[$m[1]])) fail('Esta campaña tiene tarjetas emitidas. Desactívala para conservar el historial.'); query('DELETE FROM campaigns WHERE id=?',[$m[1]]); respond(['ok'=>true]);}
    }
    if ($path === '/api/customers' && $method === 'POST') {
        $data=body(); $id=uid();
        query('INSERT INTO customers (id,name,email,mobile,birth_date) VALUES (?,?,?,?,?)',[$id,textField($data,'name'),emailField($data,false),textField($data,'mobile',30,false),birthDateField($data)]);
        respond(['id'=>$id],201);
    }
    if (preg_match('#^/api/customers/([a-f0-9]{32})$#',$path,$m) && $method === 'PATCH') {
        $data=body(); $customer=one('SELECT id,birth_date FROM customers WHERE id=?',[$m[1]]);
        if (!$customer) fail('Cliente inexistente.',404);
        $birthDate=array_key_exists('birth_date',$data) ? birthDateField($data) : $customer['birth_date'];
        query('UPDATE customers SET name=?,email=?,mobile=?,birth_date=? WHERE id=?',[textField($data,'name'),emailField($data,false),textField($data,'mobile',30,false),$birthDate,$m[1]]);
        respond(['ok'=>true]);
    }
    if ($path === '/api/cards' && $method === 'POST') {$data=body(); db()->beginTransaction(); $card=issueCard(textField($data,'campaign_id',32),textField($data,'customer_id',32),$actor); db()->commit(); respond($card,201);}
    if (preg_match('#^/api/cards/([a-f0-9]{32})/(stamp|remove|redeem|revoke)$#',$path,$m) && $method === 'POST') {
        $data=body(); $remarks=textField($data,'remarks',500,false);
        db()->beginTransaction(); $card=one('SELECT * FROM cards WHERE id=? FOR UPDATE',[$m[1]]);
        if (!$card) fail('Tarjeta inexistente.',404);
        if ($card['status'] !== 'Active') fail('La tarjeta ya no está activa.');
        $snapshot=json_decode($card['snapshot'],true); $stamps=(int)$card['stamps']; $goal=(int)$snapshot['total_stamps'];
        $action=$m[2];
        if ($action === 'stamp') {if ($stamps >= $goal) fail('La tarjeta está completa. Canjea la recompensa.'); query('UPDATE cards SET stamps=stamps+1 WHERE id=?',[$card['id']]); logAction($card['id'],$actor,'stamp_add',1,$remarks);}
        if ($action === 'remove') {if ($stamps <= 0) fail('No hay sellos para retirar.'); query('UPDATE cards SET stamps=stamps-1 WHERE id=?',[$card['id']]); logAction($card['id'],$actor,'stamp_remove',-1,$remarks);}
        if ($action === 'redeem') {if ($stamps < $goal) fail('Aún no se alcanzó la meta de sellos.'); query("UPDATE cards SET status='Redeemed' WHERE id=?",[$card['id']]); logAction($card['id'],$actor,'redeem',0,$remarks);}
        if ($action === 'revoke') {owner(); query("UPDATE cards SET status='Revoked' WHERE id=?",[$card['id']]); logAction($card['id'],$actor,'revoke',0,$remarks);}
        db()->commit(); respond(['ok'=>true]);
    }
    if ($path === '/api/profile' && $method === 'PATCH') {owner(); $data=body(); $name=textField($data,'name'); $email=emailField($data); db()->beginTransaction(); query('UPDATE business SET name=?,email=? WHERE id=1',[$name,$email]); query('UPDATE users SET name=?,email=? WHERE id=?',[$name,$email,$actor['id']]); db()->commit(); respond(['ok'=>true]);}
    if ($path === '/api/password' && $method === 'POST') {$data=body(); $current=textField($data,'current_password',200); $new=textField($data,'password',200); if (strlen($new)<10) fail('Usa al menos 10 caracteres.'); $u=one('SELECT password_hash FROM users WHERE id=?',[$actor['id']]); if (!password_verify($current,$u['password_hash'])) fail('La contraseña actual es incorrecta.'); query('UPDATE users SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$actor['id']]); session_regenerate_id(true); respond(['ok'=>true]);}
    if ($path === '/api/staff' && $method === 'POST') {owner(); $data=body(); $password=textField($data,'password',200); if (strlen($password)<10) fail('Usa al menos 10 caracteres.'); $id=uid(); query("INSERT INTO users (id,name,email,password_hash,role) VALUES (?,?,?,?,'staff')",[$id,textField($data,'name'),emailField($data),password_hash($password,PASSWORD_DEFAULT)]); respond(['id'=>$id],201);}
    if (preg_match('#^/api/staff/([a-f0-9]{32})$#',$path,$m) && $method === 'PATCH') {owner(); $data=body(); if (!is_bool($data['active'] ?? null)) fail('Estado inválido.'); query("UPDATE users SET active=? WHERE id=? AND role='staff'",[(int)$data['active'],$m[1]]); respond(['ok'=>true]);}
    fail('Ruta no encontrada.',404);
} catch (PDOException $e) {
    try {if (db()->inTransaction()) db()->rollBack();} catch (Throwable) {}
    if ($e->getCode()==='23000') fail('Ya existe un registro con estos datos o está relacionado con otros registros.',409);
    error_log((string)$e); fail('No se pudo acceder a MySQL. Revisa la configuración del backend.',503);
} catch (Throwable $e) {error_log((string)$e); fail('No se pudo completar la operación.',500);}
