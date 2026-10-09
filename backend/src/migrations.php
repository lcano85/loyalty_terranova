<?php
declare(strict_types=1);
function migrateCustomerBirthDate(): void {
    if (!one("SHOW COLUMNS FROM customers LIKE 'birth_date'")) {
        db()->exec('ALTER TABLE customers ADD COLUMN birth_date DATE NULL DEFAULT NULL AFTER mobile');
    }
}
function migratePromotionsNotifications(): void {
    db()->exec(file_get_contents(dirname(__DIR__).'/database/migrations/20261008_promotions_notifications.sql'));
    if (!one("SHOW COLUMNS FROM promotions LIKE 'start_time'")) db()->exec("ALTER TABLE promotions ADD COLUMN start_time TIME NOT NULL DEFAULT '00:00:00' AFTER start_date");
    if (!one("SHOW COLUMNS FROM promotions LIKE 'end_time'")) db()->exec("ALTER TABLE promotions ADD COLUMN end_time TIME NOT NULL DEFAULT '23:59:59' AFTER end_date");
}
