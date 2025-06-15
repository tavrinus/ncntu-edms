<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/app/models/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/app/models/Document.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/app/models/Department.php';
if (isset($_POST['action']) && $_POST['action'] == 'create_document') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Необхідно авторизуватися']);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    $type_id = isset($_POST['type_id']) ? (int)$_POST['type_id'] : 0;
    $department_id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
    $errors = [];
    if (empty($title)) {
        $errors[] = 'Заголовок документу обов\'язковий';
    }
    if (empty($content)) {
        $errors[] = 'Вміст документу обов\'язковий';
    }
    if ($type_id <= 0) {
        $errors[] = 'Необхідно вибрати тип документу';
    }
    if ($department_id <= 0) {
        $errors[] = 'Необхідно вибрати відділення';
    }
    $departmentModel = new Department();
    if (!$departmentModel->isUserInDepartment($user_id, $department_id)) {
        $errors[] = 'У вас немає прав для створення документів у цьому відділенні';
    }
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
    $file_path = '';
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] == 0) {
        $allowed_extensions = explode(',', 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png');
        $file_name = $_FILES['document_file']['name'];
        $file_size = $_FILES['document_file']['size'];
        $file_tmp = $_FILES['document_file']['tmp_name'];
        $file_parts = explode('.', $file_name);
        $file_ext = strtolower(end($file_parts));
        if (!in_array($file_ext, $allowed_extensions)) {
            echo json_encode(['success' => false, 'message' => 'Недопустимий формат файлу. Дозволені формати: ' . implode(', ', $allowed_extensions)]);
            exit;
        }
        if ($file_size > MAX_UPLOAD_SIZE) {
            echo json_encode(['success' => false, 'message' => 'Розмір файлу не повинен перевищувати 100MB']);
            exit;
        }
        $new_file_name = uniqid() . '.' . $file_ext;
        $upload_path = $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/public/uploads/documents/' . $new_file_name;
        if (move_uploaded_file($file_tmp, $upload_path)) {
            $file_path = 'documents/' . $new_file_name;
        } else {
            echo json_encode(['success' => false, 'message' => 'Помилка при завантаженні файлу']);
            exit;
        }
    }
    $data = [
        'title' => $title,
        'content' => $content,
        'file_path' => $file_path,
        'type_id' => $type_id,
        'author_id' => $user_id,
        'department_id' => $department_id
    ];
    $documentModel = new Document();
    $document_id = $documentModel->createDocument($data);
    if ($document_id) {
        logAction('create_document', "Створено документ: {$title}", $user_id);
        echo json_encode(['success' => true, 'message' => 'Документ успішно створено', 'document_id' => $document_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Помилка при створенні документу']);
    }
    exit;
}
if (isset($_POST['action']) && $_POST['action'] == 'create_order') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Необхідно авторизуватися']);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    $department_id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
    $errors = [];
    if (empty($title)) {
        $errors[] = 'Заголовок наказу обов\'язковий';
    }
    if (empty($content)) {
        $errors[] = 'Вміст наказу обов\'язковий';
    }
    if ($department_id <= 0) {
        $errors[] = 'Необхідно вибрати відділення';
    }
    $userModel = new User();
    $isDirector = $userModel->hasRole($user_id, 2);
    $isDeputyDirector = $userModel->hasRole($user_id, 3);
    if (!$isDirector && !$isDeputyDirector) {
        $errors[] = 'У вас немає прав для створення наказів';
    }
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
    $file_path = '';
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] == 0) {
        $allowed_extensions = explode(',', 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png');
        $file_name = $_FILES['document_file']['name'];
        $file_size = $_FILES['document_file']['size'];
        $file_tmp = $_FILES['document_file']['tmp_name'];
        $file_parts = explode('.', $file_name);
        $file_ext = strtolower(end($file_parts));
        if (!in_array($file_ext, $allowed_extensions)) {
            echo json_encode(['success' => false, 'message' => 'Недопустимий формат файлу. Дозволені формати: ' . implode(', ', $allowed_extensions)]);
            exit;
        }
        if ($file_size > MAX_UPLOAD_SIZE) {
            echo json_encode(['success' => false, 'message' => 'Розмір файлу не повинен перевищувати 100MB']);
            exit;
        }
        $new_file_name = uniqid() . '.' . $file_ext;
        $upload_path = $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/public/uploads/documents/' . $new_file_name;
        if (move_uploaded_file($file_tmp, $upload_path)) {
            $file_path = 'documents/' . $new_file_name;
        } else {
            echo json_encode(['success' => false, 'message' => 'Помилка при завантаженні файлу']);
            exit;
        }
    }
    $data = [
        'title' => $title,
        'content' => $content,
        'file_path' => $file_path,
        'author_id' => $user_id,
        'department_id' => $department_id
    ];
    $documentController = new DocumentController();
    $document_id = $documentController->createOrderDocument($data);
    if ($document_id) {
        logAction('create_order', "Створено наказ: {$title}", $user_id);
        echo json_encode(['success' => true, 'message' => 'Наказ успішно створено', 'document_id' => $document_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Помилка при створенні наказу']);
    }
    exit;
}
if (isset($_GET['action']) && $_GET['action'] == 'get_user_documents') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Необхідно авторизуватися']);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $type_id = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;
    $filters = ['author_id' => $user_id];
    if ($type_id > 0) {
        $filters['type_id'] = $type_id;
    }
    $documentModel = new Document();
    $documents = $documentModel->getAllDocuments($limit, $offset, $filters);
    $total = $documentModel->countDocuments($filters);
    echo json_encode([
        'success' => true, 
        'documents' => $documents, 
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
    exit;
}
if (isset($_GET['action']) && $_GET['action'] == 'get_document_details') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Необхідно авторизуватися']);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $document_id = isset($_GET['document_id']) ? (int)$_GET['document_id'] : 0;
    if ($document_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Неправильний ID документу']);
        exit;
    }
    $documentModel = new Document();
    $document = $documentModel->getDocumentById($document_id);
    if (!$document) {
        echo json_encode(['success' => false, 'message' => 'Документ не знайдено']);
        exit;
    }
    $userModel = new User();
    $isAdmin = $userModel->isAdmin($user_id);
    $isDirector = $userModel->hasRole($user_id, 2);
    $isDeputyDirector = $userModel->hasRole($user_id, 3);
    if ($document['author_id'] != $user_id && !$isAdmin && !$isDirector && !$isDeputyDirector) {
        $departmentModel = new Department();
        if (!$departmentModel->isUserInDepartment($user_id, $document['department_id'])) {
            echo json_encode(['success' => false, 'message' => 'У вас немає прав для перегляду цього документу']);
            exit;
        }
    }
    echo json_encode(['success' => true, 'document' => $document]);
    exit;
}
if (isset($_POST['action']) && $_POST['action'] == 'update_document') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Необхідно авторизуватися']);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $document_id = isset($_POST['document_id']) ? (int)$_POST['document_id'] : 0;
    if ($document_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Неправильний ID документу']);
        exit;
    }
    $documentModel = new Document();
    $document = $documentModel->getDocumentById($document_id);
    if (!$document) {
        echo json_encode(['success' => false, 'message' => 'Документ не знайдено']);
        exit;
    }
    $userModel = new User();
    $isAdmin = $userModel->isAdmin($user_id);
    $isDirector = $userModel->hasRole($user_id, 2);
    $isDeputyDirector = $userModel->hasRole($user_id, 3);
    if ($document['author_id'] != $user_id && !$isAdmin && !$isDirector && !$isDeputyDirector) {
        echo json_encode(['success' => false, 'message' => 'У вас немає прав для редагування цього документу']);
        exit;
    }
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    $type_id = isset($_POST['type_id']) ? (int)$_POST['type_id'] : 0;
    $department_id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
    $errors = [];
    if (empty($title)) {
        $errors[] = 'Заголовок документу обов\'язковий';
    }
    if (empty($content)) {
        $errors[] = 'Вміст документу обов\'язковий';
    }
    if ($type_id <= 0) {
        $errors[] = 'Необхідно вибрати тип документу';
    }
    if ($department_id <= 0) {
        $errors[] = 'Необхідно вибрати відділення';
    }
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
    $file_path = '';
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] == 0) {
        $allowed_extensions = explode(',', 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png');
        $file_name = $_FILES['document_file']['name'];
        $file_size = $_FILES['document_file']['size'];
        $file_tmp = $_FILES['document_file']['tmp_name'];
        $file_parts = explode('.', $file_name);
        $file_ext = strtolower(end($file_parts));
        if (!in_array($file_ext, $allowed_extensions)) {
            echo json_encode(['success' => false, 'message' => 'Недопустимий формат файлу. Дозволені формати: ' . implode(', ', $allowed_extensions)]);
            exit;
        }
        if ($file_size > MAX_UPLOAD_SIZE) {
            echo json_encode(['success' => false, 'message' => 'Розмір файлу не повинен перевищувати 100MB']);
            exit;
        }
        $new_file_name = uniqid() . '.' . $file_ext;
        $upload_path = $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/public/uploads/documents/' . $new_file_name;
        if (move_uploaded_file($file_tmp, $upload_path)) {
            $file_path = 'documents/' . $new_file_name;
            if (!empty($document['file_path'])) {
                $old_file_path = $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/public/uploads/' . $document['file_path'];
                if (file_exists($old_file_path)) {
                    @unlink($old_file_path);
                }
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Помилка при завантаженні файлу']);
            exit;
        }
    }
    $data = [
        'title' => $title,
        'content' => $content,
        'type_id' => $type_id,
        'department_id' => $department_id
    ];
    if (!empty($file_path)) {
        $data['file_path'] = $file_path;
    }
    $result = $documentModel->updateDocument($document_id, $data);
    if ($result) {
        logAction('update_document', "Оновлено документ: {$title}", $user_id);
        echo json_encode(['success' => true, 'message' => 'Документ успішно оновлено']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Помилка при оновленні документу']);
    }
    exit;
}
if (isset($_POST['action']) && $_POST['action'] == 'toggle_archive') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Необхідно авторизуватися']);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $document_id = isset($_POST['document_id']) ? (int)$_POST['document_id'] : 0;
    $archive = isset($_POST['archive']) ? (bool)$_POST['archive'] : true;
    if ($document_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Неправильний ID документу']);
        exit;
    }
    $documentModel = new Document();
    $document = $documentModel->getDocumentById($document_id);
    if (!$document) {
        echo json_encode(['success' => false, 'message' => 'Документ не знайдено']);
        exit;
    }
    $userModel = new User();
    $isAdmin = $userModel->isAdmin($user_id);
    $isDirector = $userModel->hasRole($user_id, 2);
    $isDeputyDirector = $userModel->hasRole($user_id, 3);
    if ($document['author_id'] != $user_id && !$isAdmin && !$isDirector && !$isDeputyDirector) {
        echo json_encode(['success' => false, 'message' => 'У вас немає прав для архівації/розархівації цього документу']);
        exit;
    }
    $result = $documentModel->toggleArchiveStatus($document_id, $archive);
    if ($result) {
        $action_type = $archive ? 'archive_document' : 'unarchive_document';
        $action_desc = $archive ? "Архівовано документ: {$document['title']}" : "Розархівовано документ: {$document['title']}";
        logAction($action_type, $action_desc, $user_id);
        $message = $archive ? 'Документ успішно архівовано' : 'Документ успішно розархівовано';
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        $message = $archive ? 'Помилка при архівації документу' : 'Помилка при розархівації документу';
        echo json_encode(['success' => false, 'message' => $message]);
    }
    exit;
}
if (isset($_POST['action']) && $_POST['action'] == 'delete_document') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Необхідно авторизуватися']);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $document_id = isset($_POST['document_id']) ? (int)$_POST['document_id'] : 0;
    if ($document_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Неправильний ID документу']);
        exit;
    }
    $documentModel = new Document();
    $document = $documentModel->getDocumentById($document_id);
    if (!$document) {
        echo json_encode(['success' => false, 'message' => 'Документ не знайдено']);
        exit;
    }
    $userModel = new User();
    $isAdmin = $userModel->isAdmin($user_id);
    if ($document['author_id'] != $user_id && !$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'У вас немає прав для видалення цього документу']);
        exit;
    }
    $result = $documentModel->deleteDocument($document_id);
    if ($result) {
        logAction('delete_document', "Видалено документ: {$document['title']}", $user_id);
        echo json_encode(['success' => true, 'message' => 'Документ успішно видалено']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Помилка при видаленні документу']);
    }
    exit;
}
class DocumentController {
    public $documentModel;
    private $departmentModel;
    private $userModel;
    public function __construct() {
        $this->documentModel = new Document();
        $this->departmentModel = new Department();
        $this->userModel = new User();
    }
    public function getUserDocuments($user_id, $limit = 10, $offset = 0, $filters = []) {
        $filters['author_id'] = $user_id;
        if (!isset($filters['type_id'])) {
            $filters['type_id'] = 1;
        }
        return $this->documentModel->getAllDocuments($limit, $offset, $filters);
    }
    public function countUserDocuments($user_id, $filters = []) {
        $filters['author_id'] = $user_id;
        if (!isset($filters['type_id'])) {
            $filters['type_id'] = 1;
        }
        return $this->documentModel->countDocuments($filters);
    }
    public function getDepartmentDocuments($department_id, $limit = 10, $offset = 0, $filters = []) {
        $filters['department_id'] = $department_id;
        $filters['type_id'] = 1;
        if (!isset($filters['is_archived'])) {
            $filters['is_archived'] = 0;
        }
        return $this->documentModel->getAllDocuments($limit, $offset, $filters);
    }
    public function countDepartmentDocuments($department_id, $filters = []) {
        $filters['department_id'] = $department_id;
        $filters['type_id'] = 1;
        if (!isset($filters['is_archived'])) {
            $filters['is_archived'] = 0;
        }
        return $this->documentModel->countDocuments($filters);
    }
    public function getDocumentAuthorsInDepartment($department_id) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT DISTINCT u.id, u.full_name 
                               FROM users u 
                               JOIN documents d ON u.id = d.author_id 
                               WHERE d.department_id = ? AND d.type_id = 1 AND d.is_archived = 0
                               ORDER BY u.full_name");
        $stmt->bind_param("i", $department_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $authors = [];
        while ($row = $result->fetch_assoc()) {
            $authors[] = $row;
        }
        return $authors;
    }
    public function canCreateDocumentInDepartment($user_id, $department_id) {
        if ($this->userModel->hasRole($user_id, 2) || $this->userModel->hasRole($user_id, 3)) {
            return true;
        }
        return $this->departmentModel->isUserInDepartment($user_id, $department_id);
    }
    public function canEditDocument($user_id, $document_id) {
        $document = $this->documentModel->getDocumentById($document_id);
        if (!$document) {
            return false;
        }
        return $document['author_id'] == $user_id;
    }
    public function createNormalDocument($data) {
        $data['type_id'] = 1;
        return $this->documentModel->createDocument($data);
    }
    public function createOrderDocument($data) {
        $data['type_id'] = 2;
        return $this->documentModel->createDocument($data);
    }
    public function createDirectiveDocument($data) {
        $data['type_id'] = 3;
        return $this->documentModel->createDocument($data);
    }
    public function updateDocument($document_id, $data) {
        return $this->documentModel->updateDocument($document_id, $data);
    }
    public function archiveDocument($document_id) {
        return $this->documentModel->toggleArchiveStatus($document_id, true);
    }
    public function unarchiveDocument($document_id) {
        return $this->documentModel->toggleArchiveStatus($document_id, false);
    }
    public function deleteDocument($document_id) {
        return $this->documentModel->deleteDocument($document_id);
    }
    public function getDocumentTypes() {
        return $this->documentModel->getAllDocumentTypes();
    }
}
?> 