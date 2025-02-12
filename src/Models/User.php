<?php

class User {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function create($username, $email, $password) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password_hash)
            VALUES (:username, :email, :password_hash)
        ");
        
        return $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $password_hash
        ]);
    }
    
    public function findByUsername($username) {
        $stmt = $this->db->prepare("
            SELECT * FROM users WHERE username = :username
        ");
        
        $stmt->execute([':username' => $username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function findByEmail($email) {
        $stmt = $this->db->prepare("
            SELECT * FROM users WHERE email = :email
        ");
        
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function findByRememberToken($token) {
        $stmt = $this->db->prepare("
            SELECT * FROM users WHERE remember_token = :token
        ");
        
        $stmt->execute([':token' => $token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public function updateRememberToken($userId, $token) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET remember_token = :token,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        
        return $stmt->execute([
            ':token' => $token,
            ':id' => $userId
        ]);
    }
} 