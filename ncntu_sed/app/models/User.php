<?php
class User {
    private $db;
    public function __construct() {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/config/database.php';
        $this->db = getDBConnection();
    }
    public function getUserById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            return null;
        }
        return $result->fetch_assoc();
    }
    public function getUserByLoginOrEmail($login) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE login = ? OR email = ?");
        $stmt->bind_param("ss", $login, $login);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            return null;
        }
        return $result->fetch_assoc();
    }
    public function isLoginUnique($login) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE login = ?");
        $stmt->bind_param("s", $login);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows === 0;
    }
    public function isEmailUnique($email) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows === 0;
    }
    public function register($data) {
        $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $avatar = isset($data['avatar']) ? $data['avatar'] : null;
        try {
            $this->db->begin_transaction();
            $stmt = $this->db->prepare("INSERT INTO users (full_name, login, email, password, avatar, phone, address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssssss", $data['full_name'], $data['login'], $data['email'], $password_hash, $avatar, $data['phone'], $data['address']);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                throw new Exception("Помилка при створенні користувача");
            }
            $user_id = $this->db->insert_id;
            $this->db->commit();
            return $user_id;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    public function login($login, $password) {
        $user = $this->getUserByLoginOrEmail($login);
        if (!$user) {
            return false;
        }
        if (password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }
    public function getUserRoles($user_id) {
        $stmt = $this->db->prepare("
            SELECT r.id, r.name 
            FROM roles r
            JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $roles = [];
        while ($row = $result->fetch_assoc()) {
            $roles[] = $row;
        }
        return $roles;
    }
    public function hasRole($user_id, $role_id) {
        $stmt = $this->db->prepare("SELECT * FROM user_roles WHERE user_id = ? AND role_id = ?");
        $stmt->bind_param("ii", $user_id, $role_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
    public function isAdmin($user_id) {
        return $this->hasRole($user_id, 1);
    }
    public function updateResetToken($user_id, $token) {
        $stmt = $this->db->prepare("UPDATE users SET reset_token = ?, reset_token_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
        $stmt->bind_param("si", $token, $user_id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }
    public function getUserByResetToken($token) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            return null;
        }
        return $result->fetch_assoc();
    }
    public function updatePassword($user_id, $new_password) {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
        $stmt->bind_param("si", $password_hash, $user_id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }
    public function updateUser($user_id, $data) {
        try {
            $this->db->begin_transaction();
            $params = [];
            $types = "";
            $sql_parts = [];
            if (isset($data['full_name'])) {
                $sql_parts[] = "full_name = ?";
                $params[] = $data['full_name'];
                $types .= "s";
            }
            if (isset($data['login'])) {
                $sql_parts[] = "login = ?";
                $params[] = $data['login'];
                $types .= "s";
            }
            if (isset($data['email'])) {
                $sql_parts[] = "email = ?";
                $params[] = $data['email'];
                $types .= "s";
            }
            if (isset($data['phone'])) {
                $sql_parts[] = "phone = ?";
                $params[] = $data['phone'];
                $types .= "s";
            }
            if (isset($data['address'])) {
                $sql_parts[] = "address = ?";
                $params[] = $data['address'];
                $types .= "s";
            }
            if (isset($data['avatar'])) {
                $sql_parts[] = "avatar = ?";
                $params[] = $data['avatar'];
                $types .= "s";
            }
            if (isset($data['password']) && !empty($data['password'])) {
                $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
                $sql_parts[] = "password = ?";
                $params[] = $password_hash;
                $types .= "s";
            }
            $params[] = $user_id;
            $types .= "i";
            $sql = "UPDATE users SET " . implode(", ", $sql_parts) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    public function getAllUsers() {
        $stmt = $this->db->prepare("SELECT id, full_name, login, email, avatar, phone, address FROM users ORDER BY full_name");
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        return $users;
    }
    public function getAllUsersExceptAdmins() {
        $stmt = $this->db->prepare("
            SELECT u.id, u.full_name, u.login, u.email, u.avatar, u.phone, u.address 
            FROM users u
            WHERE u.id NOT IN (
                SELECT user_id FROM user_roles WHERE role_id = 1
            )
            ORDER BY u.full_name
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        return $users;
    }
    public function getUsersByDepartment($department_id) {
        $stmt = $this->db->prepare("
            SELECT u.id, u.full_name 
            FROM users u
            JOIN user_departments ud ON u.id = ud.user_id
            WHERE ud.department_id = ?
            ORDER BY u.full_name
        ");
        $stmt->bind_param("i", $department_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        return $users;
    }
    public function __destruct() {
        if ($this->db) {
            $this->db->close();
        }
    }
} 