-- Ejecutar una sola vez en la base principal del hosting desde phpMyAdmin.
-- Para una ejecución repetible por CLI, usar: php bin/migrate.php
ALTER TABLE customers ADD COLUMN birth_date DATE NULL DEFAULT NULL AFTER mobile;
