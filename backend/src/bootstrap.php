<?php
declare(strict_types=1);
date_default_timezone_set('America/Lima');
$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        if (getenv(trim($key)) === false) putenv(trim($key) . '=' . trim($value, " \t\n\r\0\x0B\"'"));
    }
}
function env(string $key, string $default = ''): string { return getenv($key) === false ? $default : getenv($key); }
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('mysql:host='.env('DB_HOST','127.0.0.1').';port='.env('DB_PORT','3306').';dbname='.env('DB_DATABASE','loyalty_terranova').';charset=utf8mb4', env('DB_USERNAME','root'), env('DB_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
        $pdo->exec("SET time_zone = '-05:00'");
    }
    return $pdo;
}
function query(string $sql, array $params = []): PDOStatement { $s = db()->prepare($sql); $s->execute($params); return $s; }
function rows(string $sql, array $params = []): array {return query($sql,$params)->fetchAll();}
function one(string $sql, array $params = []): ?array {return query($sql,$params)->fetch() ?: null;}
function uid(): string {return bin2hex(random_bytes(16));}
function respond(mixed $data, int $status = 200): never {http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); exit;}
function fail(string $message, int $status = 422): never {respond(['error'=>$message],$status);}
function body(): array {try {$data = json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);} catch (JsonException) {fail('JSON inválido.');} if (!is_array($data)) fail('Solicitud inválida.'); return $data;}
function textField(array $data, string $key, int $max = 160, bool $required = true): string {
    if (isset($data[$key]) && !is_scalar($data[$key])) fail('Campo inválido: '.$key);
    $v = trim((string)($data[$key] ?? ''));
    if (($required && $v === '') || mb_strlen($v) > $max) fail('Revisa el campo '.$key.'.');
    return $v;
}
function emailField(array $data, bool $required = true): ?string {$email = strtolower(textField($data,'email',190,$required)); if ($email === '' && !$required) return null; if (!filter_var($email,FILTER_VALIDATE_EMAIL)) fail('Correo electrónico inválido.'); return $email;}
function birthDateField(array $data): ?string {
    $value=textField($data,'birth_date',10,false);
    if ($value === '') return null;
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
    $errors=DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d') !== $value || (int)$date->format('Y') < 1000) {
        fail('Fecha de nacimiento inválida. Usa una fecha real en formato YYYY-MM-DD.');
    }
    if ($value > date('Y-m-d')) fail('La fecha de nacimiento no puede ser futura.');
    return $value;
}
function user(): array {
    $u = isset($_SESSION['user_id']) ? one('SELECT id,name,email,role,active FROM users WHERE id=?',[$_SESSION['user_id']]) : null;
    if (!$u || !$u['active']) fail('Inicia sesión para continuar.',401);
    return $u;
}
function owner(): array {$u=user(); if ($u['role'] !== 'owner') fail('Solo el propietario puede realizar esta acción.',403); return $u;}
function campaignData(array $data): array {
    $total = filter_var($data['total_stamps'] ?? null,FILTER_VALIDATE_INT);
    if ($total === false || $total < 1 || $total > 40) fail('La meta debe tener entre 1 y 40 sellos.');
    $color = textField($data,'color',7); $bg = textField($data,'background',7);
    if (!preg_match('/^#[a-f0-9]{6}$/i',$color) || !preg_match('/^#[a-f0-9]{6}$/i',$bg)) fail('Color inválido.');
    $icon=textField($data,'stamp_icon',30,false) ?: 'coffee';
    if (!in_array($icon,['coffee','beer','wine','pizza','utensils','star','heart','gift','flame','ice-cream'],true)) fail('Icono no válido.');
    $logo=textField($data,'logo',280000,false);
    if ($logo !== '') {
        if (!preg_match('#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$#D',$logo,$parts)) fail('Usa un logo PNG, JPG o WebP.');
        $bytes=base64_decode($parts[2],true);
        $info=$bytes === false ? false : @getimagesizefromstring($bytes);
        if (!$info || strlen($bytes)>204800 || $info[0]>2048 || $info[1]>2048 || $info['mime']!=='image/'.$parts[1]) fail('El logo debe ser válido, de hasta 200 KB y 2048 × 2048 píxeles.');
    }
    return [textField($data,'name'),textField($data,'tagline',255),textField($data,'reward',255),$total,$color,$bg,$logo ?: null,$icon];
}
function cardView(array $card): array {$card['snapshot']=json_decode($card['snapshot'],true); $card['stamps']=(int)$card['stamps']; return $card;}
function logAction(string $card, array $actor, string $type, int $amount=0, string $remarks=''): void {query('INSERT INTO transactions (id,card_id,actor_id,actor_name,actor_role,type,amount,remarks) VALUES (?,?,?,?,?,?,?,?)',[uid(),$card,$actor['id'] ?? null,$actor['name'],$actor['role'],$type,$amount,$remarks]);}
function issueCard(string $campaignId, string $customerId, array $actor): array {
    $campaign=one('SELECT * FROM campaigns WHERE id=? FOR UPDATE',[$campaignId]);
    if (!$campaign || !$campaign['enabled']) fail('La campaña no está disponible.');
    if (!one('SELECT id FROM customers WHERE id=?',[$customerId])) fail('Cliente inexistente.');
    $id=uid(); $token=bin2hex(random_bytes(32));
    query('INSERT INTO cards (id,public_token,campaign_id,customer_id,snapshot) VALUES (?,?,?,?,?)',[$id,$token,$campaignId,$customerId,json_encode($campaign,JSON_THROW_ON_ERROR)]);
    logAction($id,$actor,'issued'); return ['id'=>$id,'public_token'=>$token];
}
