CREATE DATABASE IF NOT EXISTS event_db;
USE event_db;

-- Drop tables if they exist
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS registrations;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS users;

-- Users table with additional fields
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  phone VARCHAR(20),
  department VARCHAR(100),
  role ENUM('admin','organizer','student') NOT NULL,
  approved TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Events table with cost field
CREATE TABLE events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  event_date DATE NOT NULL,
  cost DECIMAL(10,2) DEFAULT 0.00,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Registrations table
CREATE TABLE registrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  user_id INT NOT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  payment_status ENUM('pending', 'paid', 'free') DEFAULT 'pending',
  registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Payments table
CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  registration_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_method VARCHAR(50),
  transaction_id VARCHAR(100),
  status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
  paid_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE
);

-- Insert sample users (password = 123456)
INSERT INTO users (name, email, password, phone, department, role, approved) VALUES
('Admin User', 'admin@example.com', '$2y$10$RmnCj6QjGV6T8Jv7WuhQ2.Lt0hdZmAe8PYIpxm96cZVtM7gGcQfZ6', '123-456-7890', 'Administration', 'admin', 1),
('Organizer User', 'organizer@example.com', '$2y$10$RmnCj6QjGV6T8Jv7WuhQ2.Lt0hdZmAe8PYIpxm96cZVtM7gGcQfZ6', '123-456-7891', 'Events', 'organizer', 1),
('Student User', 'student@example.com', '$2y$10$RmnCj6QjGV6T8Jv7WuhQ2.Lt0hdZmAe8PYIpxm96cZVtM7gGcQfZ6', '123-456-7892', 'Computer Science', 'student', 1);

-- Insert sample events (one free, one paid)
INSERT INTO events (title, description, event_date, cost, created_by) VALUES
('Tech Seminar', 'A seminar on latest technology trends.', '2025-09-20', 0.00, 2),
('Premium Workshop', 'Advanced technical workshop with certification.', '2025-10-15', 50.00, 2);

-- Insert sample registration
INSERT INTO registrations (event_id, user_id, status, payment_status) VALUES
(1, 3, 'pending', 'free');