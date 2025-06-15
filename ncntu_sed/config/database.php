<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'ncntu_sed');
define('DB_PORT', 3306);
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        die("Помилка підключення до бази даних: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}
function executeQuery($sql, $params = []) {
    $conn = getDBConnection();
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $types = '';
        $bindParams = [];
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 'b';
            }
            $bindParams[] = $param;
        }
        $stmt->bind_param($types, ...$bindParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    $conn->close();
    return $result;
}
function fetchOne($sql, $params = []) {
    $result = executeQuery($sql, $params);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}
function fetchAll($sql, $params = []) {
    $result = executeQuery($sql, $params);
    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}
function insert($table, $data) {
    $conn = getDBConnection();
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    $types = '';
    $values = [];
    foreach ($data as $value) {
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } elseif (is_string($value)) {
            $types .= 's';
        } else {
            $types .= 'b';
        }
        $values[] = $value;
    }
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $insertId = $stmt->insert_id;
    $stmt->close();
    $conn->close();
    return $insertId;
}
function update($table, $data, $where, $whereParams = []) {
    $conn = getDBConnection();
    $set = [];
    foreach (array_keys($data) as $column) {
        $set[] = "$column = ?";
    }
    $sql = "UPDATE $table SET " . implode(', ', $set) . " WHERE $where";
    $stmt = $conn->prepare($sql);
    $types = '';
    $values = [];
    foreach ($data as $value) {
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } elseif (is_string($value)) {
            $types .= 's';
        } else {
            $types .= 'b';
        }
        $values[] = $value;
    }
    foreach ($whereParams as $value) {
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } elseif (is_string($value)) {
            $types .= 's';
        } else {
            $types .= 'b';
        }
        $values[] = $value;
    }
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    $conn->close();
    return $affectedRows;
}
function delete($table, $where, $params = []) {
    $conn = getDBConnection();
    $sql = "DELETE FROM $table WHERE $where";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $types = '';
        foreach ($params as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } elseif (is_string($value)) {
                $types .= 's';
            } else {
                $types .= 'b';
            }
        }
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $deletedRows = $stmt->affected_rows;
    $stmt->close();
    $conn->close();
    return $deletedRows;
}
?> 