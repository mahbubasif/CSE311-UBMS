<?php
class DBConnection {
    private $host = 'testcc.me';
    private $db_name = 'tetcc_ubms';
    private $username = 'tetcc_asif';
    private $password = "54sA[pI%x=~^";
    private $conn;

    public function __construct() {
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->db_name);

        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    public function getConnection() {
        return $this->conn;
    }
}
?>