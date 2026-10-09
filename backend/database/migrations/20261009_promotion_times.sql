-- Ejecutar una vez en instalaciones que ya tienen la tabla promotions.
ALTER TABLE promotions
 ADD COLUMN start_time TIME NOT NULL DEFAULT '00:00:00' AFTER start_date,
 ADD COLUMN end_time TIME NOT NULL DEFAULT '23:59:59' AFTER end_date;
