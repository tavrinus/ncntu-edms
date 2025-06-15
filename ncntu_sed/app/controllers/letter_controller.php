<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/app/models/User.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/app/models/Letter.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/app/models/Document.php';
if (isset($_POST['action']) && $_POST['action'] == 'send_letter') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Необхідно авторизуватися']);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $errors = [];
    if ($receiver_id <= 0) {
        $errors[] = 'Необхідно вибрати отримувача';
    }
    if (empty($subject)) {
        $errors[] = 'Тема листа обов\'язкова';
    }
    if (empty($message)) {
        $errors[] = 'Повідомлення обов\'язкове';
    }
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
    $attachments = [];
    if (isset($_FILES['attachments']) && $_FILES['attachments']['error'][0] != 4) {
        $allowed_extensions = explode(',', 'pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png');
        $file_count = count($_FILES['attachments']['name']);
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['attachments']['error'][$i] == 0) {
                $file_name = $_FILES['attachments']['name'][$i];
                $file_size = $_FILES['attachments']['size'][$i];
                $file_tmp = $_FILES['attachments']['tmp_name'][$i];
                $file_parts = explode('.', $file_name);
                $file_ext = strtolower(end($file_parts));
                if (!in_array($file_ext, $allowed_extensions)) {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Недопустимий формат файлу: ' . $file_name . '. Дозволені формати: ' . implode(', ', $allowed_extensions)
                    ]);
                    exit;
                }
                if ($file_size > MAX_UPLOAD_SIZE) {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Розмір файлу ' . $file_name . ' не повинен перевищувати 100MB'
                    ]);
                    exit;
                }
                $new_file_name = uniqid() . '.' . $file_ext;
                $upload_path = $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/public/uploads/letters/' . $new_file_name;
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $attachments[] = 'letters/' . $new_file_name;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Помилка при завантаженні файлу ' . $file_name]);
                    exit;
                }
            }
        }
    }
    $documents = [];
    if (isset($_POST['documents']) && is_array($_POST['documents'])) {
        foreach ($_POST['documents'] as $document_id) {
            $documents[] = (int)$document_id;
        }
    }
    $data = [
        'sender_id' => $user_id,
        'receiver_id' => $receiver_id,
        'subject' => $subject,
        'message' => $message,
        'attachments' => $attachments,
        'documents' => $documents
    ];
    $letterModel = new Letter();
    $letter_id = $letterModel->sendLetter($data);
    if ($letter_id) {
        logAction('send_letter', "Надіслано лист: {$subject}", $user_id);
        echo json_encode(['success' => true, 'message' => 'Лист успішно надіслано', 'letter_id' => $letter_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Помилка при надсиланні листа']);
    }
    exit;
}
if (isset($_GET['action']) && $_GET['action'] == 'get_incoming_letters') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Необхідно авторизуватися']);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $letterModel = new Letter();
    $letters = $letterModel->getIncomingLetters($user_id, $limit, $offset);
    $total = $letterModel->countIncomingLetters($user_id);
    echo json_encode([
        'success' => true, 
        'letters' => $letters, 
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
    exit;
}
if (isset($_GET['action']) && $_GET['action'] == 'get_outgoing_letters') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Необхідно авторизуватися']);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $letterModel = new Letter();
    $letters = $letterModel->getOutgoingLetters($user_id, $limit, $offset);
    $total = $letterModel->countOutgoingLetters($user_id);
    echo json_encode([
        'success' => true, 
        'letters' => $letters, 
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
    exit;
}
if (isset($_GET['action']) && $_GET['action'] == 'get_letter_details') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Необхідно авторизуватися']);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $letter_id = isset($_GET['letter_id']) ? (int)$_GET['letter_id'] : 0;
    if ($letter_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Неправильний ID листа']);
        exit;
    }
    $letterModel = new Letter();
    $letter = $letterModel->getLetterById($letter_id);
    if (!$letter) {
        echo json_encode(['success' => false, 'message' => 'Лист не знайдено']);
        exit;
    }
    if ($letter['sender_id'] != $user_id && $letter['receiver_id'] != $user_id) {
        $userModel = new User();
        $isAdmin = $userModel->isAdmin($user_id);
        if (!$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'У вас немає прав для перегляду цього листа']);
            exit;
        }
    }
    echo json_encode(['success' => true, 'letter' => $letter]);
    exit;
}
if (isset($_POST['action']) && $_POST['action'] == 'delete_letter') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Необхідно авторизуватися']);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $letter_id = isset($_POST['letter_id']) ? (int)$_POST['letter_id'] : 0;
    $is_sender = isset($_POST['is_sender']) ? (bool)$_POST['is_sender'] : true;
    if ($letter_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Неправильний ID листа']);
        exit;
    }
    $letterModel = new Letter();
    $result = $letterModel->markLetterAsDeleted($letter_id, $user_id, $is_sender);
    if ($result) {
        logAction('delete_letter', "Видалено лист ID: {$letter_id}", $user_id);
        echo json_encode(['success' => true, 'message' => 'Лист успішно видалено']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Помилка при видаленні листа']);
    }
    exit;
}
if (isset($_GET['action']) && $_GET['action'] == 'get_users_for_letter') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Необхідно авторизуватися']);
        exit;
    }
    $user_id = $_SESSION['user_id'];
    $userModel = new User();
    $users = $userModel->getAllUsers();
    foreach ($users as $key => $user) {
        if ($user['id'] == $user_id) {
            unset($users[$key]);
            break;
        }
    }
    $users = array_values($users);
    echo json_encode(['success' => true, 'users' => $users]);
    exit;
}
?> 