<?php
declare(strict_types=1);
function migrateCustomerBirthDate(): void {
    if (!one("SHOW COLUMNS FROM customers LIKE 'birth_date'")) {
        db()->exec('ALTER TABLE customers ADD COLUMN birth_date DATE NULL DEFAULT NULL AFTER mobile');
    }
}
