<?php
class Department {
    private $db;
    public function __construct() {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/config/database.php';
        $this->db = getDBConnection();
    }
    public function getDepartmentById($id) {
        $stmt = $this->db->prepare("SELECT d.*, p.name as parent_name 
                                   FROM departments d
                                   LEFT JOIN departments p ON d.parent_id = p.id
                                   WHERE d.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            return null;
        }
        return $result->fetch_assoc();
    }
    public function getAllDepartments($includeParent = true) {
        $sql = "SELECT d.* ";
        if ($includeParent) {
            $sql .= ", p.name as parent_name ";
        }
        $sql .= "FROM departments d ";
        if ($includeParent) {
            $sql .= "LEFT JOIN departments p ON d.parent_id = p.id ";
        }
        $sql .= "ORDER BY d.parent_id IS NULL DESC, d.parent_id, d.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $departments = [];
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row;
        }
        return $departments;
    }
    public function getDepartmentsHierarchy() {
        $departments = $this->getAllDepartments();
        $hierarchy = [];
        $map = [];
        foreach ($departments as $dept) {
            $dept['children'] = [];
            $map[$dept['id']] = $dept;
        }
        foreach ($departments as $dept) {
            $dept_id = $dept['id'];
            if (!empty($dept['parent_id']) && isset($map[$dept['parent_id']])) {
                $map[$dept['parent_id']]['children'][] = &$map[$dept_id];
            } else {
                $hierarchy[] = &$map[$dept_id];
            }
        }
        return $hierarchy;
    }
    public function getUserDepartments($user_id) {
        $stmt = $this->db->prepare("
            SELECT d.* 
            FROM departments d
            JOIN user_departments ud ON d.id = ud.department_id
            WHERE ud.user_id = ?
            ORDER BY d.name
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $departments = [];
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row;
        }
        return $departments;
    }
    public function getUserDepartmentsForDocumentFilter($user_id) {
        $stmt = $this->db->prepare("
            SELECT DISTINCT d.* 
            FROM departments d
            JOIN documents doc ON d.id = doc.department_id
            WHERE doc.author_id = ? AND doc.type_id = 1
            ORDER BY d.name
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $departments = [];
        while ($row = $result->fetch_assoc()) {
            $departments[] = $row;
        }
        return $departments;
    }
    public function isUserInDepartment($user_id, $department_id) {
        $stmt = $this->db->prepare("
            SELECT 1 FROM user_departments 
            WHERE user_id = ? AND department_id = ?
        ");
        $stmt->bind_param("ii", $user_id, $department_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
    public function __destruct() {
        if ($this->db) {
            $this->db->close();
        }
    }
} 