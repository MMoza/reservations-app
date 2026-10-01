-- ============================================================================
-- Reservations database — MisterPlan technical test
--
-- Import this file in phpMyAdmin (MAMP) or from the CLI:
--   mysql -u root -p < database/schema.sql
--
-- Compatible with MySQL 8.x / MariaDB 10.x
-- Naming convention: English, snake_case.
-- Every table carries created_at / updated_at for data integrity.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS reservation_event;
DROP TABLE IF EXISTS reservation;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- reservation: one row per accommodation booking
-- ----------------------------------------------------------------------------
CREATE TABLE reservation (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    guest_name         VARCHAR(120) NOT NULL,
    guest_email        VARCHAR(180) NOT NULL,
    accommodation_name VARCHAR(120) NOT NULL,
    check_in_date      DATE NOT NULL,
    check_out_date     DATE NOT NULL,
    status             ENUM('PENDING', 'CONFIRMED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    amount             DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    notes              TEXT NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reservation_status (status),
    KEY idx_reservation_check_in_date (check_in_date),
    KEY idx_reservation_check_out_date (check_out_date),
    KEY idx_reservation_guest_email (guest_email),
    CONSTRAINT chk_reservation_dates CHECK (check_out_date > check_in_date),
    CONSTRAINT chk_reservation_amount CHECK (amount >= 0)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- reservation_event: audit trail of creations and status changes
-- ----------------------------------------------------------------------------
CREATE TABLE reservation_event (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id INT UNSIGNED NOT NULL,
    event_type     VARCHAR(40) NOT NULL,
    description    VARCHAR(255) NOT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_event_reservation_id (reservation_id),
    CONSTRAINT fk_event_reservation FOREIGN KEY (reservation_id)
        REFERENCES reservation (id) ON DELETE CASCADE,
    CONSTRAINT chk_event_type CHECK (event_type IN ('CREATED', 'STATUS_CHANGED'))
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

-- ============================================================================
-- Seed data
-- Dates are relative to CURDATE() so the filters always show fresh data:
-- past stays (completed), upcoming stays and recent cancellations.
-- updated_at mirrors the timestamp of the last change for each row.
-- ============================================================================

INSERT INTO reservation
    (guest_name, guest_email, accommodation_name, check_in_date, check_out_date,
     status, amount, notes, created_at, updated_at)
VALUES
    ('Lucía Fernández',   'lucia.fernandez@example.com',  'Hotel Playa de Nerja',            CURDATE() + INTERVAL 3 DAY,  CURDATE() + INTERVAL 7 DAY,  'CONFIRMED',  480.00, NULL, NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 18 DAY),
    ('Carlos Martínez',   'carlos.martinez@example.com',  'Apartamentos Costa Málaga',       CURDATE() + INTERVAL 10 DAY, CURDATE() + INTERVAL 14 DAY, 'PENDING',    320.50, NULL, NOW() - INTERVAL 5 DAY,  NOW() - INTERVAL 5 DAY),
    ('Marta Gómez',       'marta.gomez@example.com',      'Rural Casa del Olivo',            CURDATE() + INTERVAL 15 DAY, CURDATE() + INTERVAL 17 DAY, 'CANCELLED',  210.00, 'Anulación por imprevistos familiares.', NOW() - INTERVAL 30 DAY, NOW() - INTERVAL 6 DAY),
    ('Jorge López',       'jorge.lopez@example.com',      'Hotel Vistamar',                  CURDATE() - INTERVAL 30 DAY, CURDATE() - INTERVAL 26 DAY, 'CONFIRMED',  640.00, NULL, NOW() - INTERVAL 45 DAY, NOW() - INTERVAL 44 DAY),
    ('Ana Ruiz',          'ana.ruiz@example.com',         'Hostal Puerta del Sol',           CURDATE() - INTERVAL 10 DAY, CURDATE() - INTERVAL 5 DAY,  'CONFIRMED',  275.90, NULL, NOW() - INTERVAL 25 DAY, NOW() - INTERVAL 24 DAY),
    ('Pablo Sánchez',     'pablo.sanchez@example.com',    'Apartamentos Sol y Playa',        CURDATE() + INTERVAL 21 DAY, CURDATE() + INTERVAL 28 DAY, 'PENDING',    890.00, 'Llegada tardía: vuelo a las 23:10.', NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY),
    ('Elena Torres',      'elena.torres@example.com',     'Hotel Boutique Sevilla Center',   CURDATE() + INTERVAL 7 DAY,  CURDATE() + INTERVAL 9 DAY,  'CONFIRMED',  356.00, NULL, NOW() - INTERVAL 12 DAY, NOW() - INTERVAL 11 DAY),
    ('Diego Ramírez',     'diego.ramirez@example.com',    'Chalet Lago de Banyoles',         CURDATE() - INTERVAL 45 DAY, CURDATE() - INTERVAL 43 DAY, 'CANCELLED',  180.00, 'Cancelada por el cliente con reembolso completo.', NOW() - INTERVAL 60 DAY, NOW() - INTERVAL 52 DAY),
    ('Sofía Navarro',     'sofia.navarro@example.com',    'Apartamentos Ribadeo Marina',     CURDATE() + INTERVAL 30 DAY, CURDATE() + INTERVAL 35 DAY, 'PENDING',    545.00, NULL, NOW() - INTERVAL 8 DAY, NOW() - INTERVAL 8 DAY),
    ('Antonio Vázquez',   'antonio.vazquez@example.com',  'Hotel Real Bilbao',               CURDATE() - INTERVAL 60 DAY, CURDATE() - INTERVAL 57 DAY, 'CONFIRMED',  720.00, NULL, NOW() - INTERVAL 75 DAY, NOW() - INTERVAL 74 DAY),
    ('Carmen Delgado',    'carmen.delgado@example.com',   'Casa Rural Sierra de Guadarrama', CURDATE() + INTERVAL 12 DAY, CURDATE() + INTERVAL 14 DAY, 'PENDING',    198.00, 'Cuna solicitada para bebé de 1 año.', NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 4 DAY),
    ('Iker Molina',       'iker.molina@example.com',      'Hotel Gandía Playa',              CURDATE() + INTERVAL 45 DAY, CURDATE() + INTERVAL 52 DAY, 'CONFIRMED', 1024.00, NULL, NOW() - INTERVAL 15 DAY, NOW() - INTERVAL 14 DAY),
    ('Nuria Peña',        'nuria.pena@example.com',       'Hostal Plaza Mayor',              CURDATE() - INTERVAL 3 DAY,  CURDATE() - INTERVAL 1 DAY,  'CONFIRMED',  165.00, NULL, NOW() - INTERVAL 18 DAY, NOW() - INTERVAL 17 DAY),
    ('Rubén Ortega',      'ruben.ortega@example.com',     'Apartamentos Ronda del Mar',      CURDATE() + INTERVAL 5 DAY,  CURDATE() + INTERVAL 9 DAY,  'CANCELLED',  400.00, 'Cancelada por restricciones de viaje.', NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 7 DAY);

INSERT INTO reservation_event
    (reservation_id, event_type, description, created_at, updated_at)
VALUES
    -- 1. Lucía Fernández (CONFIRMED)
    (1, 'CREATED',        'Reserva creada por el huésped',                NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 20 DAY),
    (1, 'STATUS_CHANGED', 'Estado cambiado de PENDING a CONFIRMED',    NOW() - INTERVAL 18 DAY, NOW() - INTERVAL 18 DAY),
    -- 2. Carlos Martínez (PENDING)
    (2, 'CREATED',        'Reserva creada por el huésped',                NOW() - INTERVAL 5 DAY,  NOW() - INTERVAL 5 DAY),
    -- 3. Marta Gómez (CANCELLED)
    (3, 'CREATED',        'Reserva creada por el huésped',                NOW() - INTERVAL 30 DAY, NOW() - INTERVAL 30 DAY),
    (3, 'STATUS_CHANGED', 'Estado cambiado de PENDING a CONFIRMED',    NOW() - INTERVAL 28 DAY, NOW() - INTERVAL 28 DAY),
    (3, 'STATUS_CHANGED', 'Estado cambiado de CONFIRMED a CANCELLED',  NOW() - INTERVAL 6 DAY,  NOW() - INTERVAL 6 DAY),
    -- 4. Jorge López (CONFIRMED, past stay)
    (4, 'CREATED',        'Reserva creada por el huésped',                NOW() - INTERVAL 45 DAY, NOW() - INTERVAL 45 DAY),
    (4, 'STATUS_CHANGED', 'Estado cambiado de PENDING a CONFIRMED',    NOW() - INTERVAL 44 DAY, NOW() - INTERVAL 44 DAY),
    -- 5. Ana Ruiz (CONFIRMED, past stay)
    (5, 'CREATED',        'Reserva creada por el huésped',                NOW() - INTERVAL 25 DAY, NOW() - INTERVAL 25 DAY),
    (5, 'STATUS_CHANGED', 'Estado cambiado de PENDING a CONFIRMED',    NOW() - INTERVAL 24 DAY, NOW() - INTERVAL 24 DAY),
    -- 6. Pablo Sánchez (PENDING)
    (6, 'CREATED',        'Reserva creada por el huésped',                NOW() - INTERVAL 2 DAY,  NOW() - INTERVAL 2 DAY),
    -- 7. Elena Torres (CONFIRMED)
    (7, 'CREATED',        'Reserva creada por el huésped',                NOW() - INTERVAL 12 DAY, NOW() - INTERVAL 12 DAY),
    (7, 'STATUS_CHANGED', 'Estado cambiado de PENDING a CONFIRMED',    NOW() - INTERVAL 11 DAY, NOW() - INTERVAL 11 DAY),
    -- 8. Diego Ramírez (CANCELLED)
    (8, 'CREATED',        'Reserva creada por el huésped',                NOW() - INTERVAL 60 DAY, NOW() - INTERVAL 60 DAY),
    (8, 'STATUS_CHANGED', 'Estado cambiado de PENDING a CONFIRMED',    NOW() - INTERVAL 59 DAY, NOW() - INTERVAL 59 DAY),
    (8, 'STATUS_CHANGED', 'Estado cambiado de CONFIRMED a CANCELLED',  NOW() - INTERVAL 52 DAY, NOW() - INTERVAL 52 DAY),
    -- 9. Sofía Navarro (PENDING)
    (9, 'CREATED',        'Reserva creada por el huésped',                NOW() - INTERVAL 8 DAY,  NOW() - INTERVAL 8 DAY),
    -- 10. Antonio Vázquez (CONFIRMED, past stay)
    (10, 'CREATED',        'Reserva creada por el huésped',               NOW() - INTERVAL 75 DAY, NOW() - INTERVAL 75 DAY),
    (10, 'STATUS_CHANGED', 'Estado cambiado de PENDING a CONFIRMED',   NOW() - INTERVAL 74 DAY, NOW() - INTERVAL 74 DAY),
    -- 11. Carmen Delgado (PENDING)
    (11, 'CREATED',        'Reserva creada por el huésped',               NOW() - INTERVAL 4 DAY,  NOW() - INTERVAL 4 DAY),
    -- 12. Iker Molina (CONFIRMED)
    (12, 'CREATED',        'Reserva creada por el huésped',               NOW() - INTERVAL 15 DAY, NOW() - INTERVAL 15 DAY),
    (12, 'STATUS_CHANGED', 'Estado cambiado de PENDING a CONFIRMED',   NOW() - INTERVAL 14 DAY, NOW() - INTERVAL 14 DAY),
    -- 13. Nuria Peña (CONFIRMED, past stay)
    (13, 'CREATED',        'Reserva creada por el huésped',               NOW() - INTERVAL 18 DAY, NOW() - INTERVAL 18 DAY),
    (13, 'STATUS_CHANGED', 'Estado cambiado de PENDING a CONFIRMED',   NOW() - INTERVAL 17 DAY, NOW() - INTERVAL 17 DAY),
    -- 14. Rubén Ortega (CANCELLED)
    (14, 'CREATED',        'Reserva creada por el huésped',               NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 10 DAY),
    (14, 'STATUS_CHANGED', 'Estado cambiado de CONFIRMED a CANCELLED', NOW() - INTERVAL 7 DAY,  NOW() - INTERVAL 7 DAY);
