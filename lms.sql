-- ================================================
-- Library Management System - Database Schema
-- ================================================
-- Run this in phpMyAdmin or MySQL CLI:
--   mysql -u root -p < lms.sql
-- ================================================

CREATE DATABASE IF NOT EXISTS library_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE library_lms;

-- ------------------------------------------------
-- Table: users  (admin + students)
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  VARCHAR(20)  UNIQUE,               -- student ID number (NULL for admin)
    first_name  VARCHAR(100) NOT NULL,
    last_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,              -- bcrypt hash
    role        ENUM('admin','student') DEFAULT 'student',
    course      VARCHAR(100),                       -- e.g. BSCS, BSN
    year_level  TINYINT,                            -- 1-5
    contact     VARCHAR(20),
    address     TEXT,
    status      ENUM('active','inactive','pending') DEFAULT 'pending',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ------------------------------------------------
-- Table: categories
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------
-- Table: books
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS books (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    isbn            VARCHAR(20)  UNIQUE,
    title           VARCHAR(255) NOT NULL,
    author          VARCHAR(255) NOT NULL,
    publisher       VARCHAR(255),
    publication_year YEAR,
    edition         VARCHAR(50),
    category_id     INT,
    description     TEXT,
    cover_image     VARCHAR(255),                  -- filename in uploads/
    total_copies    INT DEFAULT 1,
    available_copies INT DEFAULT 1,
    location        VARCHAR(100),                  -- shelf / section
    status          ENUM('active','inactive') DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- ------------------------------------------------
-- Table: reservations
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS reservations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    book_id         INT NOT NULL,
    reserved_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date     DATE NOT NULL,                 -- reservation expires after X days
    status          ENUM('pending','approved','cancelled','expired','converted') DEFAULT 'pending',
    notes           TEXT,
    approved_by     INT,                           -- admin user id
    approved_at     TIMESTAMP NULL,
    FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id)    REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ------------------------------------------------
-- Table: borrows
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS borrows (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    book_id         INT NOT NULL,
    reservation_id  INT,                           -- linked reservation (nullable)
    borrow_date     DATE NOT NULL DEFAULT (CURRENT_DATE),
    due_date        DATE NOT NULL,
    issued_by       INT,                           -- admin user id
    status          ENUM('active','returned','overdue','lost') DEFAULT 'active',
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)         REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id)         REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (reservation_id)  REFERENCES reservations(id) ON DELETE SET NULL,
    FOREIGN KEY (issued_by)       REFERENCES users(id) ON DELETE SET NULL
);

-- ------------------------------------------------
-- Table: returns
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS returns (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    borrow_id       INT NOT NULL UNIQUE,
    return_date     DATE NOT NULL DEFAULT (CURRENT_DATE),
    condition_note  ENUM('good','damaged','lost') DEFAULT 'good',
    received_by     INT,                           -- admin user id
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (borrow_id)   REFERENCES borrows(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ------------------------------------------------
-- Table: penalties
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS penalties (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    borrow_id       INT NOT NULL,
    user_id         INT NOT NULL,
    overdue_days    INT DEFAULT 0,
    daily_rate      DECIMAL(8,2) DEFAULT 5.00,     -- PHP 5/day default
    damage_fee      DECIMAL(8,2) DEFAULT 0.00,
    total_amount    DECIMAL(10,2) DEFAULT 0.00,
    status          ENUM('unpaid','paid','waived') DEFAULT 'unpaid',
    paid_at         TIMESTAMP NULL,
    received_by     INT,
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (borrow_id)   REFERENCES borrows(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ------------------------------------------------
-- Table: settings (system-wide config)
-- ------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    setting_key     VARCHAR(100) NOT NULL UNIQUE,
    setting_value   TEXT,
    description     VARCHAR(255)
);

-- ------------------------------------------------
-- Indexes for performance
-- ------------------------------------------------
CREATE INDEX idx_books_title     ON books(title);
CREATE INDEX idx_books_author    ON books(author);
CREATE INDEX idx_books_publisher ON books(publisher);
CREATE INDEX idx_users_lastname  ON users(last_name);
CREATE INDEX idx_borrows_status  ON borrows(status);
CREATE INDEX idx_borrows_due     ON borrows(due_date);

-- ------------------------------------------------
-- Seed: Default settings
-- ------------------------------------------------
INSERT INTO settings (setting_key, setting_value, description) VALUES
('borrow_duration_days',  '7',  'Default number of days a student can borrow a book'),
('reservation_expiry_days','3', 'Days before a reservation expires'),
('daily_penalty_rate',    '5',  'Daily penalty rate in PHP for overdue books'),
('max_borrows_per_student','3', 'Maximum books a student can borrow at once'),
('library_name',          'School Library', 'Name of the library'),
('library_contact',       '',   'Library contact number'),
('school_name',           'My School',      'School name');

-- ------------------------------------------------
-- Seed: Categories
-- ------------------------------------------------
INSERT INTO categories (name, description) VALUES
('Fiction',           'Novels, short stories and other fiction works'),
('Non-Fiction',       'Factual books including essays and biographies'),
('Science & Technology', 'Books on science, engineering and IT'),
('Mathematics',       'Mathematics and statistics textbooks'),
('Social Studies',    'History, geography and social sciences'),
('Literature',        'Poetry, drama and classic literature'),
('Reference',         'Dictionaries, encyclopedias and atlases'),
('Health & Medicine', 'Medical and health-related books'),
('Arts & Music',      'Fine arts, music and performing arts'),
('Religion & Philosophy', 'Religion, ethics and philosophy');

-- ------------------------------------------------
-- Seed: Admin account  (password: Admin@1234)
-- ------------------------------------------------
INSERT INTO users (first_name, last_name, email, password, role, status) VALUES
('Library', 'Admin', 'admin@library.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin', 'active');
 -- NOTE: The hash above is for "password" (Laravel default).
 -- Use the lms/scripts/hash_password.php helper to generate your own.

-- ------------------------------------------------
-- Seed: Sample students  (password: Student@1234)
-- ------------------------------------------------
INSERT INTO users (student_id, first_name, last_name, email, password, role, course, year_level, contact, status) VALUES
('2024-0001', 'Juan',   'Dela Cruz', 'juan.delacruz@student.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'BSCS', 2, '09171234567', 'active'),
('2024-0002', 'Maria',  'Santos',    'maria.santos@student.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'BSN',  1, '09281234567', 'active'),
('2024-0003', 'Pedro',  'Reyes',     'pedro.reyes@student.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'BSBA', 3, '09391234567', 'active');

-- ------------------------------------------------
-- Seed: Sample books
-- ------------------------------------------------
INSERT INTO books (isbn, title, author, publisher, publication_year, edition, category_id, total_copies, available_copies, location) VALUES
('978-0-13-110362-7', 'The C Programming Language',        'Brian W. Kernighan, Dennis M. Ritchie', 'Prentice Hall',    1988, '2nd', 3, 3, 3, 'Section A - Shelf 1'),
('978-0-596-51774-8', 'Learning PHP, MySQL & JavaScript',  'Robin Nixon',                           'O\'Reilly Media',  2021, '6th', 3, 2, 2, 'Section A - Shelf 2'),
('978-0-13-468599-1', 'Clean Code',                        'Robert C. Martin',                      'Prentice Hall',    2008, '1st', 3, 3, 3, 'Section A - Shelf 1'),
('978-0-7432-7356-5', 'Harry Potter and the Sorcerer\'s Stone', 'J.K. Rowling',                    'Scholastic',       1998, '1st', 1, 5, 5, 'Section B - Shelf 3'),
('978-0-14-028329-7', 'The Alchemist',                     'Paulo Coelho',                          'HarperOne',        1988, 'Special', 1, 4, 4, 'Section B - Shelf 2'),
('978-0-547-92822-7', 'The Hobbit',                        'J.R.R. Tolkien',                        'Houghton Mifflin', 1937, 'Revised', 1, 3, 3, 'Section B - Shelf 1'),
('978-0-07-340789-8', 'Calculus: Early Transcendentals',   'James Stewart',                         'Cengage Learning', 2015, '8th', 4, 5, 5, 'Section C - Shelf 1'),
('978-0-13-235088-4', 'Introduction to Algorithms',        'Cormen, Leiserson, Rivest, Stein',      'MIT Press',        2009, '3rd', 3, 2, 2, 'Section A - Shelf 3'),
('978-971-91512-0-5', 'Noli Me Tangere',                   'Jose Rizal',                            'Bookmark Inc.',    2006, 'Annotated', 6, 10, 10, 'Section D - Shelf 1'),
('978-971-91512-1-2', 'El Filibusterismo',                  'Jose Rizal',                            'Bookmark Inc.',    2006, 'Annotated', 6, 10, 10, 'Section D - Shelf 1');
