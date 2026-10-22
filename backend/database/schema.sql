CREATE TABLE IF NOT EXISTS business (
 id INT PRIMARY KEY, name VARCHAR(160) NOT NULL, email VARCHAR(190) NOT NULL, slug VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS users (
 id CHAR(32) PRIMARY KEY, name VARCHAR(160) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL, role ENUM('owner','staff') NOT NULL DEFAULT 'staff',
 active BOOLEAN NOT NULL DEFAULT TRUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS campaigns (
 id CHAR(32) PRIMARY KEY, name VARCHAR(160) NOT NULL, tagline VARCHAR(255) NOT NULL,
 reward VARCHAR(255) NOT NULL, total_stamps INT NOT NULL, color CHAR(7) NOT NULL DEFAULT '#b52732',
 background CHAR(7) NOT NULL DEFAULT '#fff2f3', logo MEDIUMTEXT NULL, stamp_icon VARCHAR(30) NOT NULL DEFAULT 'coffee', enabled BOOLEAN NOT NULL DEFAULT TRUE,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, CHECK (total_stamps BETWEEN 1 AND 40)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS customers (
 id CHAR(32) PRIMARY KEY, name VARCHAR(160) NOT NULL, email VARCHAR(190) NULL,
 mobile VARCHAR(30) NOT NULL DEFAULT '', birth_date DATE NULL DEFAULT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY customer_email (email)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cards (
 id CHAR(32) PRIMARY KEY, public_token CHAR(64) NOT NULL UNIQUE,
 campaign_id CHAR(32) NOT NULL, customer_id CHAR(32) NOT NULL,
 snapshot JSON NOT NULL, stamps INT NOT NULL DEFAULT 0,
 status ENUM('Active','Redeemed','Revoked') NOT NULL DEFAULT 'Active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (campaign_id) REFERENCES campaigns(id), FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS transactions (
 id CHAR(32) PRIMARY KEY, card_id CHAR(32) NOT NULL, actor_id CHAR(32) NULL,
 actor_name VARCHAR(160) NOT NULL, actor_role VARCHAR(10) NOT NULL,
 type ENUM('issued','stamp_add','stamp_remove','redeem','revoke') NOT NULL,
 amount INT NOT NULL DEFAULT 0, remarks VARCHAR(500) NOT NULL DEFAULT '',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (card_id) REFERENCES cards(id), INDEX tx_created (created_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS login_attempts (
 key_hash CHAR(64) PRIMARY KEY, attempts INT NOT NULL DEFAULT 0, window_start BIGINT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS promotions (
 id CHAR(32) PRIMARY KEY,
 title VARCHAR(160) NOT NULL, description TEXT NOT NULL, benefit VARCHAR(255) NOT NULL,
 terms TEXT NOT NULL, start_date DATE NOT NULL, start_time TIME NOT NULL DEFAULT '00:00:00', end_date DATE NOT NULL, end_time TIME NOT NULL DEFAULT '23:59:59',
 campaign_id CHAR(32) NULL, published BOOLEAN NOT NULL DEFAULT FALSE,
 created_by CHAR(32) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
 FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX promotion_window (published,start_date,end_date)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS customer_notifications (
 id CHAR(32) PRIMARY KEY, title VARCHAR(160) NOT NULL, message TEXT NOT NULL,
 promotion_id CHAR(32) NULL, campaign_id CHAR(32) NULL, customer_id CHAR(32) NULL,
 enabled BOOLEAN NOT NULL DEFAULT TRUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (promotion_id) REFERENCES promotions(id),
 FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
 FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 UNIQUE KEY promotion_announcement (promotion_id),
 INDEX notification_created (created_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS notification_reads (
 notification_id CHAR(32) NOT NULL, customer_id CHAR(32) NOT NULL,
 read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (notification_id,customer_id),
 FOREIGN KEY (notification_id) REFERENCES customer_notifications(id) ON DELETE CASCADE,
 FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS promotion_requests (
 id CHAR(32) PRIMARY KEY, promotion_id CHAR(32) NOT NULL,
 customer_id CHAR(32) NOT NULL, card_id CHAR(32) NOT NULL,
 code CHAR(12) NOT NULL UNIQUE, promotion_title VARCHAR(160) NOT NULL,
 benefit VARCHAR(255) NOT NULL,
 status ENUM('pending','approved','delivered','rejected') NOT NULL DEFAULT 'pending',
 customer_note VARCHAR(500) NOT NULL DEFAULT '', admin_note VARCHAR(500) NOT NULL DEFAULT '',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (promotion_id) REFERENCES promotions(id),
 FOREIGN KEY (customer_id) REFERENCES customers(id),
 FOREIGN KEY (card_id) REFERENCES cards(id),
 UNIQUE KEY one_request_per_customer (promotion_id,customer_id),
 INDEX request_status (status,created_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS promotion_request_events (
 id CHAR(32) PRIMARY KEY, request_id CHAR(32) NOT NULL, actor_id CHAR(32) NULL,
 actor_name VARCHAR(160) NOT NULL, from_status VARCHAR(15) NOT NULL,
 to_status VARCHAR(15) NOT NULL, note VARCHAR(500) NOT NULL DEFAULT '',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (request_id) REFERENCES promotion_requests(id) ON DELETE CASCADE,
 FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
