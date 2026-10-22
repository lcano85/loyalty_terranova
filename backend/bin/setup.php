<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
if (PHP_SAPI !== 'cli') exit;
$database=env('DB_DATABASE','loyalty_terranova');
if (!preg_match('/^[a-zA-Z0-9_]+$/',$database)) throw new RuntimeException('Nombre de base de datos inválido.');
$pdo=new PDO('mysql:host='.env('DB_HOST','127.0.0.1').';port='.env('DB_PORT','3306').';charset=utf8mb4',env('DB_USERNAME','root'),env('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
db()->exec(file_get_contents(dirname(__DIR__).'/database/schema.sql'));
require dirname(__DIR__).'/src/migrations.php';
migrateCustomerBirthDate();
migratePromotionsNotifications();
migrateCampaignBranding();
$password=env('SEED_OWNER_PASSWORD');
if (!one("SELECT id FROM users WHERE role='owner'")) {
    if (strlen($password)<10) throw new RuntimeException('Configura SEED_OWNER_PASSWORD con al menos 10 caracteres.');
    db()->beginTransaction();
    query('INSERT INTO business (id,name,email,slug) VALUES (1,?,?,?)',['Terranova Restobar',env('SEED_OWNER_EMAIL','admin@terranova.local'),'terranova']);
    query("INSERT INTO users (id,name,email,password_hash,role) VALUES (?,?,?,?,'owner')",[uid(),'Terranova Restobar',env('SEED_OWNER_EMAIL','admin@terranova.local'),password_hash($password,PASSWORD_DEFAULT)]);
    query('INSERT INTO campaigns (id,name,tagline,reward,total_stamps,color,background) VALUES (?,?,?,?,?,?,?)',[uid(),'Terranova','Por 6 compras gana un producto','Tus compras te premian',6,'#b52732','#fff2f3']);
    db()->commit();
}
echo "Base de datos inicializada. No se modificaron cuentas existentes.\n";
