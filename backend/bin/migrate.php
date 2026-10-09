<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__).'/src/bootstrap.php';
require dirname(__DIR__).'/src/migrations.php';
migrateCustomerBirthDate();
migratePromotionsNotifications();
echo "Migraciones completadas: fecha de nacimiento, promociones, notificaciones y solicitudes.\n";
