<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__).'/src/bootstrap.php';
require dirname(__DIR__).'/src/migrations.php';
migrateCustomerBirthDate();
echo "Migración completada: fecha de nacimiento opcional en clientes.\n";
