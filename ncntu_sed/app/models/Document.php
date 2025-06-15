<?php
class Document {
    private $db;
    public function __construct() {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/config/database.php';
        $this->db = getDBConnection();
    }
    public function getDocumentById($id) {
        $stmt = $this->db->prepare("SELECT d.*, dt.name as type_name, u.full_name as author_name, dp.name as department_name 
                                   FROM documents d
                                   LEFT JOIN document_types dt ON d.type_id = dt.id
                                   LEFT JOIN users u ON d.author_id = u.id
                                   LEFT JOIN departments dp ON d.department_id = dp.id
                                   WHERE d.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            return null;
        }
        return $result->fetch_assoc();
    }
    public function getAllDocuments($limit = 10, $offset = 0, $filters = []) {
        $where_clauses = [];
        $params = [];
        $types = "";
        if (isset($filters['title']) && !empty($filters['title'])) {
            $where_clauses[] = "d.title LIKE ?";
            $params[] = "%" . $filters['title'] . "%";
            $types .= "s";
        }
        if (isset($filters['type_id']) && !empty($filters['type_id'])) {
            $where_clauses[] = "d.type_id = ?";
            $params[] = $filters['type_id'];
            $types .= "i";
        }
        if (isset($filters['author_id']) && !empty($filters['author_id'])) {
            $where_clauses[] = "d.author_id = ?";
            $params[] = $filters['author_id'];
            $types .= "i";
        }
        if (isset($filters['department_id']) && !empty($filters['department_id'])) {
            $where_clauses[] = "d.department_id = ?";
            $params[] = $filters['department_id'];
            $types .= "i";
        }
        if (isset($filters['is_archived'])) {
            $where_clauses[] = "d.is_archived = ?";
            $params[] = $filters['is_archived'];
            $types .= "i";
        }
        if (isset($filters['file_extension']) && !empty($filters['file_extension'])) {
            if ($filters['file_extension'] === 'jpg') {
                $where_clauses[] = "(LOWER(SUBSTRING_INDEX(d.file_path, '.', -1)) = 'jpg' OR LOWER(SUBSTRING_INDEX(d.file_path, '.', -1)) = 'jpeg')";
            } else {
                $where_clauses[] = "LOWER(SUBSTRING_INDEX(d.file_path, '.', -1)) = ?";
                $params[] = strtolower($filters['file_extension']);
                $types .= "s";
            }
        }
        $sql = "SELECT d.*, dt.name as type_name, u.full_name as author_name, dp.name as department_name,
                UPPER(SUBSTRING_INDEX(d.file_path, '.', -1)) as file_extension
                FROM documents d
                LEFT JOIN document_types dt ON d.type_id = dt.id
                LEFT JOIN users u ON d.author_id = u.id
                LEFT JOIN departments dp ON d.department_id = dp.id";
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(" AND ", $where_clauses);
        }
        $sql .= " ORDER BY d.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $documents[] = $row;
        }
        return $documents;
    }
    public function countDocuments($filters = []) {
        $where_clauses = [];
        $params = [];
        $types = "";
        if (isset($filters['title']) && !empty($filters['title'])) {
            $where_clauses[] = "d.title LIKE ?";
            $params[] = "%" . $filters['title'] . "%";
            $types .= "s";
        }
        if (isset($filters['type_id']) && !empty($filters['type_id'])) {
            $where_clauses[] = "d.type_id = ?";
            $params[] = $filters['type_id'];
            $types .= "i";
        }
        if (isset($filters['author_id']) && !empty($filters['author_id'])) {
            $where_clauses[] = "d.author_id = ?";
            $params[] = $filters['author_id'];
            $types .= "i";
        }
        if (isset($filters['department_id']) && !empty($filters['department_id'])) {
            $where_clauses[] = "d.department_id = ?";
            $params[] = $filters['department_id'];
            $types .= "i";
        }
        if (isset($filters['is_archived'])) {
            $where_clauses[] = "d.is_archived = ?";
            $params[] = $filters['is_archived'];
            $types .= "i";
        }
        if (isset($filters['file_extension']) && !empty($filters['file_extension'])) {
            if ($filters['file_extension'] === 'jpg') {
                $where_clauses[] = "(LOWER(SUBSTRING_INDEX(d.file_path, '.', -1)) = 'jpg' OR LOWER(SUBSTRING_INDEX(d.file_path, '.', -1)) = 'jpeg')";
            } else {
                $where_clauses[] = "LOWER(SUBSTRING_INDEX(d.file_path, '.', -1)) = ?";
                $params[] = strtolower($filters['file_extension']);
                $types .= "s";
            }
        }
        $sql = "SELECT COUNT(*) as total FROM documents d";
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(" AND ", $where_clauses);
        }
        $stmt = $this->db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['total'];
    }
    public function createDocument($data) {
        try {
            $this->db->begin_transaction();
            $stmt = $this->db->prepare("INSERT INTO documents (title, content, file_path, type_id, author_id, department_id, created_at) 
                                      VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssiis", $data['title'], $data['content'], $data['file_path'], $data['type_id'], $data['author_id'], $data['department_id']);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                throw new Exception("Помилка при створенні документу");
            }
            $document_id = $this->db->insert_id;
            $this->db->commit();
            return $document_id;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    public function updateDocument($id, $data) {
        try {
            $this->db->begin_transaction();
            $file_update = '';
            $types = "ssii";
            $params = [
                $data['title'],
                $data['content'],
                $data['type_id'],
                $data['department_id']
            ];
            if (!empty($data['file_path'])) {
                $file_update = ", file_path = ?";
                $types .= "s";
                $params[] = $data['file_path'];
            }
            $types .= "i";
            $params[] = $id;
            $stmt = $this->db->prepare("UPDATE documents SET title = ?, content = ?, type_id = ?, department_id = ?" . $file_update . " WHERE id = ?");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    public function toggleArchiveStatus($id, $archive = true) {
        $status = $archive ? 1 : 0;
        $stmt = $this->db->prepare("UPDATE documents SET is_archived = ? WHERE id = ?");
        $stmt->bind_param("ii", $status, $id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }
    public function isDocumentAttachedToLetters($document_id) {
        $stmt = $this->db->prepare("
            SELECT letter_id 
            FROM letter_documents 
            WHERE document_id = ?
        ");
        $stmt->bind_param("i", $document_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            return false;
        }
        $letters = [];
        while ($row = $result->fetch_assoc()) {
            $letters[] = $row['letter_id'];
        }
        return $letters;
    }
    public function deleteDocument($id) {
        $document = $this->getDocumentById($id);
        if (!$document) {
            return false;
        }
        try {
            $this->db->begin_transaction();
            $stmt = $this->db->prepare("DELETE FROM letter_documents WHERE document_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt = $this->db->prepare("DELETE FROM documents WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                throw new Exception("Помилка при видаленні документу");
            }
            if (!empty($document['file_path'])) {
                $file_path = $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/public/uploads/' . $document['file_path'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    public function getAllDocumentTypes() {
        $stmt = $this->db->prepare("SELECT * FROM document_types ORDER BY name");
        $stmt->execute();
        $result = $stmt->get_result();
        $types = [];
        while ($row = $result->fetch_assoc()) {
            $types[] = $row;
        }
        return $types;
    }
    public function getUserDocuments($user_id, $limit = 100, $offset = 0, $filters = []) {
        $where_clauses = ["d.author_id = ?"];
        $params = [$user_id];
        $types = "i";
        if (isset($filters['title']) && !empty($filters['title'])) {
            $where_clauses[] = "d.title LIKE ?";
            $params[] = "%" . $filters['title'] . "%";
            $types .= "s";
        }
        if (isset($filters['type_id']) && !empty($filters['type_id'])) {
            $where_clauses[] = "d.type_id = ?";
            $params[] = $filters['type_id'];
            $types .= "i";
        }
        if (isset($filters['department_id']) && !empty($filters['department_id'])) {
            $where_clauses[] = "d.department_id = ?";
            $params[] = $filters['department_id'];
            $types .= "i";
        }
        if (isset($filters['is_archived'])) {
            $where_clauses[] = "d.is_archived = ?";
            $params[] = $filters['is_archived'];
            $types .= "i";
        } else {
            $where_clauses[] = "d.is_archived = 0";
        }
        $sql = "SELECT d.*, dt.name as type_name, u.full_name as author_name, dp.name as department_name 
                FROM documents d
                LEFT JOIN document_types dt ON d.type_id = dt.id
                LEFT JOIN users u ON d.author_id = u.id
                LEFT JOIN departments dp ON d.department_id = dp.id
                WHERE " . implode(" AND ", $where_clauses) . "
                ORDER BY d.created_at DESC
                LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $documents[] = $row;
        }
        return $documents;
    }
    public function __destruct() {
        if ($this->db) {
            $this->db->close();
        }
    }
} 