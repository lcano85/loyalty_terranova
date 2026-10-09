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
