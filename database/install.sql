-- SlotKita - skema pangkalan data (MySQL 5.7+/8 atau MariaDB 10.3+)
-- Import fail ini SEKALI pada pangkalan data kosong (phpMyAdmin > Import).
-- Selepas itu cipta akaun admin platform:  php tools/buat-admin.php emel@anda.com KataLaluan

SET NAMES utf8mb4;

CREATE TABLE shops (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(40)  NOT NULL,
  name          VARCHAR(80)  NOT NULL,
  category      VARCHAR(20)  NOT NULL DEFAULT 'barber',
  tagline       VARCHAR(140) NULL,
  about         TEXT         NULL,
  phone         VARCHAR(20)  NULL,
  address       VARCHAR(255) NULL,
  city          VARCHAR(60)  NULL,
  logo          VARCHAR(120) NULL,
  cover         VARCHAR(120) NULL,
  color         CHAR(7)      NOT NULL DEFAULT '#FF5A1F',
  slot_step     SMALLINT     NOT NULL DEFAULT 30,
  lead_minutes  SMALLINT     NOT NULL DEFAULT 60,
  max_days      SMALLINT     NOT NULL DEFAULT 21,
  cancel_hours  SMALLINT     NOT NULL DEFAULT 3,
  auto_confirm  TINYINT(1)   NOT NULL DEFAULT 1,
  plan          VARCHAR(10)  NOT NULL DEFAULT 'percuma',
  paid_until    DATE         NULL,
  trial_until   DATE         NULL,
  status        VARCHAR(12)  NOT NULL DEFAULT 'aktif',
  tg_chat_id    VARCHAR(32)  NULL,
  tg_token      CHAR(16)     NULL,
  referred_by   INT UNSIGNED NULL,
  ref_rewarded  TINYINT(1)   NOT NULL DEFAULT 0,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_slug (slug),
  UNIQUE KEY uq_tg_token (tg_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pemilik kedai (role=owner, shop_id diisi) dan admin platform (role=superadmin, shop_id NULL)
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shop_id       INT UNSIGNED NULL,
  name          VARCHAR(80)  NOT NULL,
  email         VARCHAR(120) NOT NULL,
  phone         VARCHAR(20)  NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          VARCHAR(12)  NOT NULL DEFAULT 'owner',
  must_change   TINYINT(1)   NOT NULL DEFAULT 0,
  last_login    DATETIME     NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_email (email),
  KEY k_shop (shop_id),
  CONSTRAINT fk_users_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE staff (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shop_id   INT UNSIGNED NOT NULL,
  name      VARCHAR(60)  NOT NULL,
  title     VARCHAR(60)  NULL,
  off_days  VARCHAR(20)  NOT NULL DEFAULT '',
  active    TINYINT(1)   NOT NULL DEFAULT 1,
  sort      SMALLINT     NOT NULL DEFAULT 0,
  KEY k_shop (shop_id),
  CONSTRAINT fk_staff_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE services (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shop_id      INT UNSIGNED NOT NULL,
  name         VARCHAR(80)  NOT NULL,
  description  VARCHAR(200) NULL,
  duration_min SMALLINT     NOT NULL DEFAULT 30,
  price_sen    INT UNSIGNED NOT NULL DEFAULT 0,
  active       TINYINT(1)   NOT NULL DEFAULT 1,
  sort         SMALLINT     NOT NULL DEFAULT 0,
  KEY k_shop (shop_id),
  CONSTRAINT fk_services_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Waktu operasi mingguan. weekday: 0=Ahad ... 6=Sabtu. open_time NULL = tutup.
CREATE TABLE hours (
  shop_id    INT UNSIGNED NOT NULL,
  weekday    TINYINT      NOT NULL,
  open_time  TIME         NULL,
  close_time TIME         NULL,
  PRIMARY KEY (shop_id, weekday),
  CONSTRAINT fk_hours_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cuti / hari tutup khas
CREATE TABLE closures (
  id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shop_id INT UNSIGNED NOT NULL,
  day     DATE         NOT NULL,
  note    VARCHAR(80)  NULL,
  UNIQUE KEY uq_shop_day (shop_id, day),
  CONSTRAINT fk_closures_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bookings (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shop_id        INT UNSIGNED NOT NULL,
  code           CHAR(8)      NOT NULL,
  staff_id       INT UNSIGNED NULL,
  customer_name  VARCHAR(60)  NOT NULL,
  customer_phone VARCHAR(20)  NOT NULL,
  note           VARCHAR(200) NULL,
  start_at       DATETIME     NOT NULL,
  end_at         DATETIME     NOT NULL,
  total_sen      INT UNSIGNED NOT NULL DEFAULT 0,
  status         VARCHAR(12)  NOT NULL DEFAULT 'menunggu',
  source         VARCHAR(10)  NOT NULL DEFAULT 'online',
  ip             VARCHAR(45)  NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_code (code),
  KEY k_shop_start (shop_id, start_at),
  KEY k_staff_start (staff_id, start_at),
  KEY k_shop_created (shop_id, created_at),
  CONSTRAINT fk_bookings_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE,
  CONSTRAINT fk_bookings_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Salinan servis pada masa tempahan (harga lama kekal walaupun harga servis ditukar)
CREATE TABLE booking_services (
  booking_id   INT UNSIGNED NOT NULL,
  service_id   INT UNSIGNED NULL,
  name         VARCHAR(80)  NOT NULL,
  price_sen    INT UNSIGNED NOT NULL,
  duration_min SMALLINT     NOT NULL,
  KEY k_booking (booking_id),
  CONSTRAINT fk_bs_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bayaran langganan kedai kepada platform (duit anda)
CREATE TABLE payments (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shop_id      INT UNSIGNED NOT NULL,
  order_id     VARCHAR(40)  NOT NULL,
  plan         VARCHAR(10)  NOT NULL,
  months       TINYINT      NOT NULL,
  amount_sen   INT UNSIGNED NOT NULL,
  method       VARCHAR(12)  NOT NULL,
  status       VARCHAR(10)  NOT NULL DEFAULT 'pending',
  bill_code    VARCHAR(40)  NULL,
  ref_no       VARCHAR(60)  NULL,
  receipt_file VARCHAR(120) NULL,
  note         VARCHAR(200) NULL,
  period_from  DATE         NULL,
  period_to    DATE         NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at      DATETIME     NULL,
  UNIQUE KEY uq_order (order_id),
  KEY k_bill (bill_code),
  KEY k_shop (shop_id),
  CONSTRAINT fk_payments_shop FOREIGN KEY (shop_id) REFERENCES shops(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Had cubaan log masuk
CREATE TABLE login_attempts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip         VARCHAR(45)  NOT NULL,
  email      VARCHAR(120) NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY k_ip (ip, created_at),
  KEY k_email (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
