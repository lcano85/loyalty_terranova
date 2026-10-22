-- Ejecutar una sola vez en instalaciones existentes si no se usa bin/migrate.php.
ALTER TABLE campaigns ADD COLUMN logo MEDIUMTEXT NULL;
ALTER TABLE campaigns ADD COLUMN stamp_icon VARCHAR(30) NOT NULL DEFAULT 'coffee';
