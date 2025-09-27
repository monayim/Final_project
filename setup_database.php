<?php
$allowed_ips = ['127.0.0.1', '::1'];
if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    die("Access denied. This script can only run on localhost.");
}

if ($_SERVER['SERVER_NAME'] != 'localhost' && $_SERVER['SERVER_NAME'] != '127.0.0.1') {
    die("Database setup can only be run on localhost");
}

$host = 'localhost';
$username = 'root';
$password = '';

try {
    // Connect to MySQL server
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS event_db");
    $pdo->exec("USE event_db");
    
    echo "<div style='padding: 20px; background: #d4edda; color: #155724; border-radius: 5px; margin: 20px;'>";
    echo "<h3>✅ Database 'event_db' created successfully!</h3>";
    
    // SQL to create tables
    $sql = "
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

    -- Events table WITH registration_limit and cost columns
    CREATE TABLE events (
      id INT AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(255) NOT NULL,
      description TEXT NOT NULL,
      event_date DATE NOT NULL,
      cost DECIMAL(10,2) DEFAULT 0.00,
      registration_limit INT DEFAULT 10,
      created_by INT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    );

    -- Registrations table WITH payment_status
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
    ";
    
    // Execute SQL to create tables
    $pdo->exec($sql);
    echo "<p>✅ Tables created successfully!</p>";

    $password = password_hash('123456', PASSWORD_BCRYPT);
    
    // Insert sample data
    $sampleData = "
    -- Insert sample users (password = 123456)
    INSERT INTO users (name, email, password, phone, department, role, approved) VALUES
    ('Admin User', 'admin@example.com', '$password', '123-456-7890', 'Administration', 'admin', 1),
    ('Organizer User', 'organizer@example.com', '$password', '123-456-7891', 'Events', 'organizer', 1),
    ('Student User', 'student@example.com', '$password', '123-456-7892', 'Computer Science', 'student', 1);

    -- Insert sample events WITH registration limit and cost
    INSERT INTO events (title, description, event_date, cost, registration_limit, created_by) VALUES
    ('Tech Seminar', 'A seminar on latest technology trends.', '2025-09-20', 0.00, 15, 2),
    ('Premium Workshop', 'Advanced technical workshop with certification.', '2025-10-15', 50.00, 10, 2);

    -- Insert sample registration
    INSERT INTO registrations (event_id, user_id, status, payment_status) VALUES
    (1, 3, 'pending', 'free');
    ";
    
    $pdo->exec($sampleData);
    echo "<p>✅ Sample data inserted successfully!</p>";
    
    echo "<p><strong>Login credentials:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> admin@example.com / 123456</li>";
    echo "<li><strong>Organizer:</strong> organizer@example.com / 123456</li>";
    echo "<li><strong>Student:</strong> student@example.com / 123456</li>";
    echo "</ul>";
    
    echo "<p><strong>Sample Events:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Tech Seminar:</strong> Free event</li>";
    echo "<li><strong>Premium Workshop:</strong> $50.00 paid event</li>";
    echo "</ul>";
    
    echo "<p><a href='login.php' style='background: #4361ee; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='padding: 20px; background: #f8d7da; color: #721c24; border-radius: 5px; margin: 20px;'>";
    echo "<h3>❌ Database Setup Failed</h3>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p>Please make sure:</p>";
    echo "<ol>";
    echo "<li>MySQL server is running in XAMPP</li>";
    echo "<li>MySQL username and password are correct in config/db.php</li>";
    echo "</ol>";
    echo "</div>";
}
?>
