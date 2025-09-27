<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'event_db';
    private $username = 'root';
    private $password = '';  // Empty password for XAMPP default
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                $this->username, 
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            // More detailed error message
            die("Connection error: " . $exception->getMessage() . 
                "<br>Check: XAMPP MySQL running? Password correct? Database exists?");
        }
        return $this->conn;
    }
}
?>


