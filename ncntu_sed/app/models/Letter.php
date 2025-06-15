<?php
class Letter {
    private $db;
    public function __construct() {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/config/database.php';
        $this->db = getDBConnection();
    }
    public function getLetterById($id) {
        $stmt = $this->db->prepare("
            SELECT l.*, 
                   s.full_name as sender_name, 
                   r.full_name as receiver_name,
                   s.avatar as sender_avatar,
                   r.avatar as receiver_avatar
            FROM letters l
            JOIN users s ON l.sender_id = s.id
            JOIN users r ON l.receiver_id = r.id
            WHERE l.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            return null;
        }
        $letter = $result->fetch_assoc();
        $letter['attachments'] = $this->getLetterAttachments($id);
        $letter['documents'] = $this->getLetterDocuments($id);
        $letter['has_attachments'] = !empty($letter['attachments']);
        $letter['has_documents'] = !empty($letter['documents']);
        return $letter;
    }
    public function getLetterAttachments($letter_id) {
        $stmt = $this->db->prepare("SELECT * FROM letter_attachments WHERE letter_id = ?");
        $stmt->bind_param("i", $letter_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $attachments = [];
        while ($row = $result->fetch_assoc()) {
            $attachments[] = $row;
        }
        return $attachments;
    }
    public function getLetterDocuments($letter_id) {
        $stmt = $this->db->prepare("
            SELECT d.* 
            FROM documents d
            JOIN letter_documents ld ON d.id = ld.document_id
            WHERE ld.letter_id = ?
        ");
        $stmt->bind_param("i", $letter_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $documents[] = $row;
        }
        return $documents;
    }
    public function getIncomingLetters($user_id, $read_filter = 'all', $star_filter = 'all', $limit = 10, $offset = 0) {
        $query = "
            SELECT l.*, u.full_name as sender_name
            FROM letters l
            JOIN users u ON l.sender_id = u.id
            WHERE l.receiver_id = ? AND l.is_deleted_receiver = 0
        ";
        if ($read_filter === 'read') {
            $query .= " AND l.is_read = 1";
        } elseif ($read_filter === 'unread') {
            $query .= " AND l.is_read = 0";
        }
        if ($star_filter === 'starred') {
            $query .= " AND l.is_starred = 1";
        } elseif ($star_filter === 'unstarred') {
            $query .= " AND l.is_starred = 0";
        }
        $query .= " ORDER BY l.sent_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iii", $user_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $letters = [];
        while ($row = $result->fetch_assoc()) {
            $has_attachments = $this->hasAttachments($row['id']);
            $row['has_attachments'] = $has_attachments;
            $letters[] = $row;
        }
        return $letters;
    }
    public function getOutgoingLetters($user_id, $star_filter = 'all', $limit = 10, $offset = 0) {
        $query = "
            SELECT l.*, u.full_name as receiver_name
            FROM letters l
            JOIN users u ON l.receiver_id = u.id
            WHERE l.sender_id = ? AND l.is_deleted_sender = 0
        ";
        if ($star_filter === 'starred') {
            $query .= " AND l.is_starred = 1";
        } elseif ($star_filter === 'unstarred') {
            $query .= " AND l.is_starred = 0";
        }
        $query .= " ORDER BY l.sent_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("iii", $user_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $letters = [];
        while ($row = $result->fetch_assoc()) {
            $has_attachments = $this->hasAttachments($row['id']);
            $row['has_attachments'] = $has_attachments;
            $letters[] = $row;
        }
        return $letters;
    }
    public function hasAttachments($letter_id) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM letter_attachments 
            WHERE letter_id = ?
        ");
        $stmt->bind_param("i", $letter_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $has_docs = $this->hasDocuments($letter_id);
        return ($row['count'] > 0 || $has_docs);
    }
    public function hasDocuments($letter_id) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM letter_documents 
            WHERE letter_id = ?
        ");
        $stmt->bind_param("i", $letter_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'] > 0;
    }
    public function countIncomingLetters($user_id, $read_filter = 'all', $star_filter = 'all') {
        $query = "
            SELECT COUNT(*) as count 
            FROM letters 
            WHERE receiver_id = ? AND is_deleted_receiver = 0
        ";
        if ($read_filter === 'read') {
            $query .= " AND is_read = 1";
        } elseif ($read_filter === 'unread') {
            $query .= " AND is_read = 0";
        }
        if ($star_filter === 'starred') {
            $query .= " AND is_starred = 1";
        } elseif ($star_filter === 'unstarred') {
            $query .= " AND is_starred = 0";
        }
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    public function countOutgoingLetters($user_id, $star_filter = 'all') {
        $query = "
            SELECT COUNT(*) as count 
            FROM letters 
            WHERE sender_id = ? AND is_deleted_sender = 0
        ";
        if ($star_filter === 'starred') {
            $query .= " AND is_starred = 1";
        } elseif ($star_filter === 'unstarred') {
            $query .= " AND is_starred = 0";
        }
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    public function sendLetter($data) {
        try {
            $this->db->begin_transaction();
            $stmt = $this->db->prepare("
                INSERT INTO letters (sender_id, receiver_id, subject, message, sent_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("iiss", $data['sender_id'], $data['receiver_id'], $data['subject'], $data['message']);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                throw new Exception("Помилка при створенні листа");
            }
            $letter_id = $this->db->insert_id;
            if (!empty($data['attachments'])) {
                foreach ($data['attachments'] as $attachment) {
                    $stmt = $this->db->prepare("
                        INSERT INTO letter_attachments (letter_id, file_path) 
                        VALUES (?, ?)
                    ");
                    $stmt->bind_param("is", $letter_id, $attachment);
                    $stmt->execute();
                }
            }
            if (!empty($data['documents'])) {
                foreach ($data['documents'] as $document_id) {
                    $stmt = $this->db->prepare("
                        INSERT INTO letter_documents (letter_id, document_id) 
                        VALUES (?, ?)
                    ");
                    $stmt->bind_param("ii", $letter_id, $document_id);
                    $stmt->execute();
                }
            }
            $this->db->commit();
            return $letter_id;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    public function markLetterAsDeleted($letter_id, $user_id, $is_sender = true) {
        try {
            $this->db->begin_transaction();
            $field_to_check = $is_sender ? "sender_id" : "receiver_id";
            $stmt = $this->db->prepare("
                SELECT * FROM letters 
                WHERE id = ? AND {$field_to_check} = ?
            ");
            $stmt->bind_param("ii", $letter_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                $this->db->rollback();
                return false;
            }
            $letter = $result->fetch_assoc();
            $field_to_update = $is_sender ? 'is_deleted_sender' : 'is_deleted_receiver';
            $stmt = $this->db->prepare("UPDATE letters SET {$field_to_update} = 1 WHERE id = ?");
            $stmt->bind_param("i", $letter_id);
            $result = $stmt->execute();
            if (!$result) {
                $this->db->rollback();
                return false;
            }
            $stmt = $this->db->prepare("
                SELECT is_deleted_sender, is_deleted_receiver 
                FROM letters 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $letter_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $updated_letter = $result->fetch_assoc();
            if ($updated_letter['is_deleted_sender'] == 1 && $updated_letter['is_deleted_receiver'] == 1) {
                $attachments = $this->getLetterAttachments($letter_id);
                $stmt = $this->db->prepare("DELETE FROM letter_attachments WHERE letter_id = ?");
                $stmt->bind_param("i", $letter_id);
                $stmt->execute();
                $stmt = $this->db->prepare("DELETE FROM letter_documents WHERE letter_id = ?");
                $stmt->bind_param("i", $letter_id);
                $stmt->execute();
                $stmt = $this->db->prepare("DELETE FROM letters WHERE id = ?");
                $stmt->bind_param("i", $letter_id);
                $stmt->execute();
                foreach ($attachments as $attachment) {
                    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/public/uploads/' . $attachment['file_path'];
                    if (file_exists($file_path)) {
                        @unlink($file_path);
                    }
                }
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    public function __destruct() {
        if ($this->db) {
            $this->db->close();
        }
    }
    public function searchLettersByAuthor($user_id, $search_query, $incoming = true, $limit = 10, $offset = 0, $read_filter = 'all', $star_filter = 'all') {
        $search_terms = '%' . $this->db->real_escape_string($search_query) . '%';
        if ($incoming) {
            $query = "
                SELECT l.*, u.full_name as sender_name
                FROM letters l
                JOIN users u ON l.sender_id = u.id
                WHERE l.receiver_id = ? AND l.is_deleted_receiver = 0
                AND u.full_name LIKE ?
            ";
            if ($read_filter === 'read') {
                $query .= " AND l.is_read = 1";
            } elseif ($read_filter === 'unread') {
                $query .= " AND l.is_read = 0";
            }
            if ($star_filter === 'starred') {
                $query .= " AND l.is_starred = 1";
            } elseif ($star_filter === 'unstarred') {
                $query .= " AND l.is_starred = 0";
            }
            $query .= " ORDER BY l.sent_at DESC LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("isii", $user_id, $search_terms, $limit, $offset);
        } else {
            $query = "
                SELECT l.*, u.full_name as receiver_name
                FROM letters l
                JOIN users u ON l.receiver_id = u.id
                WHERE l.sender_id = ? AND l.is_deleted_sender = 0
                AND u.full_name LIKE ?
            ";
            if ($star_filter === 'starred') {
                $query .= " AND l.is_starred = 1";
            } elseif ($star_filter === 'unstarred') {
                $query .= " AND l.is_starred = 0";
            }
            $query .= " ORDER BY l.sent_at DESC LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("isii", $user_id, $search_terms, $limit, $offset);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $letters = [];
        while ($row = $result->fetch_assoc()) {
            $has_attachments = $this->hasAttachments($row['id']);
            $row['has_attachments'] = $has_attachments;
            $letters[] = $row;
        }
        return $letters;
    }
    public function searchLetters($user_id, $search_query, $incoming = true, $limit = 10, $offset = 0, $read_filter = 'all', $star_filter = 'all') {
        $where_condition = $incoming 
            ? "l.receiver_id = ? AND l.is_deleted_receiver = 0" 
            : "l.sender_id = ? AND l.is_deleted_sender = 0";
        if ($incoming && $read_filter !== 'all') {
            $where_condition .= $read_filter === 'read' ? " AND l.is_read = 1" : " AND l.is_read = 0";
        }
        if ($star_filter === 'starred') {
            $where_condition .= " AND l.is_starred = 1";
        } elseif ($star_filter === 'unstarred') {
            $where_condition .= " AND l.is_starred = 0";
        }
        if (!empty($search_query)) {
            $author_letters = $this->searchLettersByAuthor($user_id, $search_query, $incoming, $limit, $offset, $read_filter, $star_filter);
            if (!empty($author_letters)) {
                return $author_letters;
            }
            $search_terms = '%' . $this->db->real_escape_string($search_query) . '%';
            if ($incoming) {
                $query = "
                    SELECT l.*, u.full_name as sender_name
                    FROM letters l
                    JOIN users u ON l.sender_id = u.id
                    WHERE {$where_condition}
                    AND (l.subject LIKE ? OR l.message LIKE ?)
                    ORDER BY l.sent_at DESC
                    LIMIT ? OFFSET ?
                ";
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("issii", $user_id, $search_terms, $search_terms, $limit, $offset);
            } else {
                $query = "
                    SELECT l.*, u.full_name as receiver_name
                    FROM letters l
                    JOIN users u ON l.receiver_id = u.id
                    WHERE {$where_condition}
                    AND (l.subject LIKE ? OR l.message LIKE ?)
                    ORDER BY l.sent_at DESC
                    LIMIT ? OFFSET ?
                ";
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("issii", $user_id, $search_terms, $search_terms, $limit, $offset);
            }
        } else {
            if ($incoming) {
                $query = "
                    SELECT l.*, u.full_name as sender_name
                    FROM letters l
                    JOIN users u ON l.sender_id = u.id
                    WHERE {$where_condition}
                    ORDER BY l.sent_at DESC
                    LIMIT ? OFFSET ?
                ";
            } else {
                $query = "
                    SELECT l.*, u.full_name as receiver_name
                    FROM letters l
                    JOIN users u ON l.receiver_id = u.id
                    WHERE {$where_condition}
                    ORDER BY l.sent_at DESC
                    LIMIT ? OFFSET ?
                ";
            }
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("iii", $user_id, $limit, $offset);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $letters = [];
        while ($row = $result->fetch_assoc()) {
            $has_attachments = $this->hasAttachments($row['id']);
            $row['has_attachments'] = $has_attachments;
            $letters[] = $row;
        }
        return $letters;
    }
    public function countSearchByAuthor($user_id, $search_query, $incoming = true, $read_filter = 'all', $star_filter = 'all') {
        $search_terms = '%' . $this->db->real_escape_string($search_query) . '%';
        if ($incoming) {
            $query = "
                SELECT COUNT(*) as count
                FROM letters l
                JOIN users u ON l.sender_id = u.id
                WHERE l.receiver_id = ? AND l.is_deleted_receiver = 0
                AND u.full_name LIKE ?
            ";
            if ($read_filter === 'read') {
                $query .= " AND l.is_read = 1";
            } elseif ($read_filter === 'unread') {
                $query .= " AND l.is_read = 0";
            }
            if ($star_filter === 'starred') {
                $query .= " AND l.is_starred = 1";
            } elseif ($star_filter === 'unstarred') {
                $query .= " AND l.is_starred = 0";
            }
        } else {
            $query = "
                SELECT COUNT(*) as count
                FROM letters l
                JOIN users u ON l.receiver_id = u.id
                WHERE l.sender_id = ? AND l.is_deleted_sender = 0
                AND u.full_name LIKE ?
            ";
            if ($star_filter === 'starred') {
                $query .= " AND l.is_starred = 1";
            } elseif ($star_filter === 'unstarred') {
                $query .= " AND l.is_starred = 0";
            }
        }
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("is", $user_id, $search_terms);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    public function countSearchResults($user_id, $search_query, $incoming = true, $read_filter = 'all', $star_filter = 'all') {
        $author_count = $this->countSearchByAuthor($user_id, $search_query, $incoming, $read_filter, $star_filter);
        if ($author_count > 0) {
            return $author_count;
        }
        $where_condition = $incoming 
            ? "l.receiver_id = ? AND l.is_deleted_receiver = 0" 
            : "l.sender_id = ? AND l.is_deleted_sender = 0";
        if ($incoming && $read_filter !== 'all') {
            $where_condition .= $read_filter === 'read' ? " AND l.is_read = 1" : " AND l.is_read = 0";
        }
        if ($star_filter === 'starred') {
            $where_condition .= " AND l.is_starred = 1";
        } elseif ($star_filter === 'unstarred') {
            $where_condition .= " AND l.is_starred = 0";
        }
        if (!empty($search_query)) {
            $search_terms = '%' . $this->db->real_escape_string($search_query) . '%';
            if ($incoming) {
                $query = "
                    SELECT COUNT(*) as count
                    FROM letters l
                    JOIN users u ON l.sender_id = u.id
                    WHERE {$where_condition} AND (
                        l.subject LIKE ? OR 
                        l.message LIKE ?
                    )
                ";
            } else {
                $query = "
                    SELECT COUNT(*) as count
                    FROM letters l
                    JOIN users u ON l.receiver_id = u.id
                    WHERE {$where_condition} AND (
                        l.subject LIKE ? OR 
                        l.message LIKE ?
                    )
                ";
            }
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("iss", $user_id, $search_terms, $search_terms);
        } else {
            if ($incoming) {
                $query = "
                    SELECT COUNT(*) as count
                    FROM letters l
                    WHERE l.receiver_id = ? AND l.is_deleted_receiver = 0
                ";
                if ($read_filter === 'read') {
                    $query .= " AND l.is_read = 1";
                } elseif ($read_filter === 'unread') {
                    $query .= " AND l.is_read = 0";
                }
            } else {
                $query = "
                    SELECT COUNT(*) as count
                    FROM letters l
                    WHERE l.sender_id = ? AND l.is_deleted_sender = 0
                ";
            }
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $user_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    public function markAsRead($letter_id, $user_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM letters 
            WHERE id = ? AND receiver_id = ?
        ");
        $stmt->bind_param("ii", $letter_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            return false;
        }
        $stmt = $this->db->prepare("
            UPDATE letters 
            SET is_read = 1 
            WHERE id = ? AND receiver_id = ?
        ");
        $stmt->bind_param("ii", $letter_id, $user_id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }
    public function markAsUnread($letter_id, $user_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM letters 
            WHERE id = ? AND receiver_id = ?
        ");
        $stmt->bind_param("ii", $letter_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            return false;
        }
        $stmt = $this->db->prepare("
            UPDATE letters 
            SET is_read = 0 
            WHERE id = ? AND receiver_id = ?
        ");
        $stmt->bind_param("ii", $letter_id, $user_id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }
    public function markAllAsRead($user_id) {
        $stmt = $this->db->prepare("
            UPDATE letters 
            SET is_read = 1 
            WHERE receiver_id = ? AND is_read = 0 AND is_deleted_receiver = 0
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        return $stmt->affected_rows;
    }
    public function markAsStarred($letter_id, $user_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM letters 
            WHERE id = ? AND (receiver_id = ? OR sender_id = ?)
        ");
        $stmt->bind_param("iii", $letter_id, $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            return false;
        }
        $stmt = $this->db->prepare("
            UPDATE letters 
            SET is_starred = 1 
            WHERE id = ? AND (receiver_id = ? OR sender_id = ?)
        ");
        $stmt->bind_param("iii", $letter_id, $user_id, $user_id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }
    public function markAsUnstarred($letter_id, $user_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM letters 
            WHERE id = ? AND (receiver_id = ? OR sender_id = ?)
        ");
        $stmt->bind_param("iii", $letter_id, $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            return false;
        }
        $stmt = $this->db->prepare("
            UPDATE letters 
            SET is_starred = 0 
            WHERE id = ? AND (receiver_id = ? OR sender_id = ?)
        ");
        $stmt->bind_param("iii", $letter_id, $user_id, $user_id);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }
    public function countUnreadLetters($user_id) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM letters 
            WHERE receiver_id = ? AND is_deleted_receiver = 0 AND is_read = 0
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    public function countStarredLetters($user_id, $incoming = true) {
        if ($incoming) {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM letters 
                WHERE receiver_id = ? AND is_deleted_receiver = 0 AND is_starred = 1
            ");
        } else {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM letters 
                WHERE sender_id = ? AND is_deleted_sender = 0 AND is_starred = 1
            ");
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'];
    }
} 