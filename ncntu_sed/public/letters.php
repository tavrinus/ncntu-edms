<?php
require_once '../config/config.php';
require_once '../app/models/User.php';
require_once '../app/models/Department.php';
require_once '../app/models/Letter.php';
if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
    exit;
}
$userModel = new User();
$user_id = $_SESSION['user_id'];
$user = $userModel->getUserById($user_id);
$roles = $userModel->getUserRoles($user_id);
$isDirector = $userModel->hasRole($user_id, 2);
$isDeputyDirector = $userModel->hasRole($user_id, 3);
$isDepartmentHead = $userModel->hasRole($user_id, 8);
$isSecretary = $userModel->hasRole($user_id, 5);
$isTeacher = $userModel->hasRole($user_id, 4);
$isAccountant = $userModel->hasRole($user_id, 6);
$letterModel = new Letter();
$unread_count = $letterModel->countUnreadLetters($user_id);
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$letter_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$subtab = isset($_GET['subtab']) ? $_GET['subtab'] : 'incoming';
$success_message = '';
$error_message = '';
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$reply_to = isset($_GET['reply_to']) ? (int)$_GET['reply_to'] : 0;
$reply_letter = null;
$reply_receiver_id = 0;
if ($reply_to > 0) {
    $reply_letter = $letterModel->getLetterById($reply_to);
    if ($reply_letter && $reply_letter['receiver_id'] == $user_id) {
        $reply_receiver_id = $reply_letter['sender_id'];
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Помилка безпеки. Спробуйте ще раз.';
    } else {
        if ($action === 'send') {
            $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
            $message = isset($_POST['message']) ? trim($_POST['message']) : '';
            $receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
            $errors = [];
            if (empty($subject)) {
                $errors[] = 'Тема листа обов\'язкова';
            }
            if (empty($message)) {
                $errors[] = 'Повідомлення обов\'язкове';
            }
            if ($receiver_id <= 0) {
                $errors[] = 'Виберіть отримувача';
            }
            if (empty($errors)) {
                $attachments = [];
                if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                    $uploads_dir = '../public/uploads/letters/';
                    if (!file_exists($uploads_dir)) {
                        mkdir($uploads_dir, 0777, true);
                    }
                    $files = $_FILES['attachments'];
                    $file_count = count($files['name']);
                    for ($i = 0; $i < $file_count; $i++) {
                        if ($files['error'][$i] === 0) {
                            $tmp_name = $files['tmp_name'][$i];
                            $name = basename($files['name'][$i]);
                            $extension = pathinfo($name, PATHINFO_EXTENSION);
                            $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png'];
                            if (!in_array(strtolower($extension), $allowed_extensions)) {
                                $errors[] = "Файл {$name} має недопустиме розширення";
                                continue;
                            }
                            if ($files['size'][$i] > MAX_UPLOAD_SIZE) {
                                $errors[] = "Файл {$name} перевищує максимальний розмір 100MB";
                                continue;
                            }
                            $unique_name = uniqid() . '_' . $name;
                            $file_path = $uploads_dir . $unique_name;
                            if (move_uploaded_file($tmp_name, $file_path)) {
                                $attachments[] = 'letters/' . $unique_name;
                            } else {
                                $errors[] = "Помилка при завантаженні файлу {$name}";
                            }
                        }
                    }
                }
                $documents = [];
                if (isset($_POST['documents']) && !empty($_POST['documents'])) {
                    $documents = array_filter($_POST['documents'], function($doc_id) {
                        return !empty($doc_id);
                    });
                }
                if (empty($errors)) {
                    $letter_data = [
                        'sender_id' => $user_id,
                        'receiver_id' => $receiver_id,
                        'subject' => $subject,
                        'message' => $message,
                        'attachments' => $attachments,
                        'documents' => $documents
                    ];
                    $result = $letterModel->sendLetter($letter_data);
                    if ($result) {
                        $success_message = 'Лист успішно надіслано!';
                        redirect("dashboard.php?tab=letters&subtab=outgoing");
                        exit;
                    } else {
                        $error_message = 'Помилка при надсиланні листа. Спробуйте ще раз.';
                    }
                } else {
                    $error_message = implode('<br>', $errors);
                }
            } else {
                $error_message = implode('<br>', $errors);
            }
        }
        if ($action === 'mark') {
            $letter_id = isset($_POST['letter_id']) ? (int)$_POST['letter_id'] : 0;
            $mark_type = isset($_POST['mark_type']) ? $_POST['mark_type'] : '';
            if ($letter_id > 0) {
                if ($mark_type === 'read') {
                    $result = $letterModel->markAsRead($letter_id, $user_id);
                } elseif ($mark_type === 'unread') {
                    $result = $letterModel->markAsUnread($letter_id, $user_id);
                } elseif ($mark_type === 'starred') {
                    $result = $letterModel->markAsStarred($letter_id, $user_id);
                } elseif ($mark_type === 'unstarred') {
                    $result = $letterModel->markAsUnstarred($letter_id, $user_id);
                }
                if ($result) {
                    if ($mark_type === 'read') {
                        $success_message = 'Лист позначено як прочитаний';
                    } elseif ($mark_type === 'unread') {
                        $success_message = 'Лист позначено як непрочитаний';
                    } elseif ($mark_type === 'starred') {
                        $success_message = 'Лист позначено зірочкою';
                    } elseif ($mark_type === 'unstarred') {
                        $success_message = 'Зірочку видалено з листа';
                    }
                } else {
                    $error_message = 'Помилка при зміні статусу листа';
                }
                $redirect_url = "letters.php?subtab=incoming";
                if (isset($_POST['search'])) {
                    $redirect_url .= "&search=" . urlencode($_POST['search']);
                }
                if (isset($_POST['page'])) {
                    $redirect_url .= "&page=" . (int)$_POST['page'];
                }
                if (isset($_POST['read_filter']) && in_array($_POST['read_filter'], ['all', 'read', 'unread'])) {
                    $redirect_url .= "&read_filter=" . $_POST['read_filter'];
                }
                if (isset($_POST['star_filter']) && in_array($_POST['star_filter'], ['all', 'starred', 'unstarred'])) {
                    $redirect_url .= "&star_filter=" . $_POST['star_filter'];
                }
                if (isset($_POST['subtab']) && in_array($_POST['subtab'], ['incoming', 'outgoing'])) {
                    $redirect_url = "letters.php?subtab=" . $_POST['subtab'];
                    if (isset($_POST['search'])) {
                        $redirect_url .= "&search=" . urlencode($_POST['search']);
                    }
                    if (isset($_POST['page'])) {
                        $redirect_url .= "&page=" . (int)$_POST['page'];
                    }
                    if (isset($_POST['read_filter']) && in_array($_POST['read_filter'], ['all', 'read', 'unread'])) {
                        $redirect_url .= "&read_filter=" . $_POST['read_filter'];
                    }
                    if (isset($_POST['star_filter']) && in_array($_POST['star_filter'], ['all', 'starred', 'unstarred'])) {
                        $redirect_url .= "&star_filter=" . $_POST['star_filter'];
                    }
                }
                redirect($redirect_url);
                exit;
            }
        }
    }
}
else {
    if ($action === 'mark') {
        $letter_id = isset($_GET['letter_id']) ? (int)$_GET['letter_id'] : 0;
        $mark_type = isset($_GET['mark_type']) ? $_GET['mark_type'] : '';
        if ($letter_id > 0) {
            $result = false;
            if ($mark_type === 'read') {
                $result = $letterModel->markAsRead($letter_id, $user_id);
            } elseif ($mark_type === 'unread') {
                $result = $letterModel->markAsUnread($letter_id, $user_id);
            } elseif ($mark_type === 'starred') {
                $result = $letterModel->markAsStarred($letter_id, $user_id);
            } elseif ($mark_type === 'unstarred') {
                $result = $letterModel->markAsUnstarred($letter_id, $user_id);
            }
            if ($result) {
                if ($mark_type === 'read') {
                    $success_message = 'Лист позначено як прочитаний';
                } elseif ($mark_type === 'unread') {
                    $success_message = 'Лист позначено як непрочитаний';
                } elseif ($mark_type === 'starred') {
                    $success_message = 'Лист позначено зірочкою';
                } elseif ($mark_type === 'unstarred') {
                    $success_message = 'Зірочку видалено з листа';
                }
            } else {
                $error_message = 'Помилка при зміні статусу листа';
            }
            $subtab = isset($_GET['subtab']) ? $_GET['subtab'] : 'incoming';
            if (!in_array($subtab, ['incoming', 'outgoing'])) {
                $subtab = 'incoming';
            }
            $redirect_url = "letters.php?subtab={$subtab}";
            if (isset($_GET['search'])) {
                $redirect_url .= "&search=" . urlencode($_GET['search']);
            }
            if (isset($_GET['page']) && (int)$_GET['page'] > 0) {
                $redirect_url .= "&page=" . (int)$_GET['page'];
            }
            if (isset($_GET['read_filter']) && in_array($_GET['read_filter'], ['all', 'read', 'unread'])) {
                $redirect_url .= "&read_filter=" . $_GET['read_filter'];
            }
            if (isset($_GET['star_filter']) && in_array($_GET['star_filter'], ['all', 'starred', 'unstarred'])) {
                $redirect_url .= "&star_filter=" . $_GET['star_filter'];
            }
            if (isset($_GET['from']) && $_GET['from'] === 'dashboard') {
                $redirect_url = "dashboard.php?tab=letters";
                if (isset($_GET['subtab'])) {
                    $redirect_url .= "&subtab=" . $_GET['subtab'];
                }
                if (isset($_GET['search'])) {
                    $redirect_url .= "&search=" . urlencode($_GET['search']);
                }
                if (isset($_GET['page']) && (int)$_GET['page'] > 0) {
                    $redirect_url .= "&page=" . (int)$_GET['page'];
                }
                if (isset($_GET['read_filter']) && in_array($_GET['read_filter'], ['all', 'read', 'unread'])) {
                    $redirect_url .= "&read_filter=" . $_GET['read_filter'];
                }
                if (isset($_GET['star_filter']) && in_array($_GET['star_filter'], ['all', 'starred', 'unstarred'])) {
                    $redirect_url .= "&star_filter=" . $_GET['star_filter'];
                }
            }
            redirect($redirect_url);
            exit;
        }
    }
    if ($action === 'markAllRead') {
        $result = $letterModel->markAllAsRead($user_id);
        if ($result > 0) {
            $success_message = "Усі листи позначено як прочитані ($result)";
        } else {
            $success_message = "Усі листи вже прочитані";
        }
        $subtab = isset($_GET['subtab']) ? $_GET['subtab'] : 'incoming';
        if (!in_array($subtab, ['incoming', 'outgoing'])) {
            $subtab = 'incoming';
        }
        $redirect_url = "letters.php?subtab={$subtab}";
        if (isset($_GET['search'])) {
            $redirect_url .= "&search=" . urlencode($_GET['search']);
        }
        if (isset($_GET['page']) && (int)$_GET['page'] > 0) {
            $redirect_url .= "&page=" . (int)$_GET['page'];
        }
        if (isset($_GET['read_filter']) && in_array($_GET['read_filter'], ['all', 'read', 'unread'])) {
            $redirect_url .= "&read_filter=" . $_GET['read_filter'];
        }
        if (isset($_GET['star_filter']) && in_array($_GET['star_filter'], ['all', 'starred', 'unstarred'])) {
            $redirect_url .= "&star_filter=" . $_GET['star_filter'];
        }
        if (isset($_GET['from']) && $_GET['from'] === 'dashboard') {
            $redirect_url = "dashboard.php?tab=letters";
            if (isset($_GET['subtab'])) {
                $redirect_url .= "&subtab=" . $_GET['subtab'];
            }
            if (isset($_GET['search'])) {
                $redirect_url .= "&search=" . urlencode($_GET['search']);
            }
            if (isset($_GET['page']) && (int)$_GET['page'] > 0) {
                $redirect_url .= "&page=" . (int)$_GET['page'];
            }
            if (isset($_GET['read_filter']) && in_array($_GET['read_filter'], ['all', 'read', 'unread'])) {
                $redirect_url .= "&read_filter=" . $_GET['read_filter'];
            }
            if (isset($_GET['star_filter']) && in_array($_GET['star_filter'], ['all', 'starred', 'unstarred'])) {
                $redirect_url .= "&star_filter=" . $_GET['star_filter'];
            }
        }
        redirect($redirect_url);
        exit;
    }
    if ($action === 'delete') {
        $is_sender = isset($_GET['is_sender']) ? (bool)$_GET['is_sender'] : true;
        $result = $letterModel->markLetterAsDeleted($letter_id, $user_id, $is_sender);
        if ($result) {
            $success_message = 'Лист успішно видалено!';
            $redirect_tab = $is_sender ? 'outgoing' : 'incoming';
            $search_query = isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '';
            $page_param = isset($_GET['page']) && (int)$_GET['page'] > 0 ? '&page=' . (int)$_GET['page'] : '';
            $read_filter = isset($_GET['read_filter']) && in_array($_GET['read_filter'], ['all', 'read', 'unread']) ? '&read_filter=' . $_GET['read_filter'] : '';
            $star_filter = isset($_GET['star_filter']) && in_array($_GET['star_filter'], ['all', 'starred', 'unstarred']) ? '&star_filter=' . $_GET['star_filter'] : '';
            redirect("dashboard.php?tab=letters&subtab={$redirect_tab}{$search_query}{$page_param}{$read_filter}{$star_filter}");
            exit;
        } else {
            $error_message = 'Помилка при видаленні листа. Спробуйте ще раз.';
        }
    }
}
if ($action === 'view' && $letter_id > 0) {
    $letter = $letterModel->getLetterById($letter_id);
    if (!$letter || (
        $letter['sender_id'] != $user_id && 
        $letter['receiver_id'] != $user_id
    )) {
        redirect("dashboard.php?tab=letters");
        exit;
    }
    if ($letter['receiver_id'] == $user_id && !$letter['is_read']) {
        $letterModel->markAsRead($letter_id, $user_id);
        $letter['is_read'] = 1;
    }
    $page_title = "Перегляд листа: " . htmlspecialchars($letter['subject']);
} elseif ($action === 'compose') {
    $page_title = "Написати новий лист";
} else {
    $page_title = $subtab === 'incoming' ? "Вхідні листи" : "Вихідні листи";
}
require_once '../app/views/includes/header.php';
?>
<!-- Підключення стилів для мобільної бічної панелі -->
<link rel="stylesheet" href="<?= BASE_URL ?>css/mobile-sidebar.css">
<!-- Підключення Select2 стилів -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<!-- Стилі для сторінки листів -->
<style>
    .sidebar {
        height: calc(100vh - 70px);
        position: sticky;
        top: 70px;
        overflow-y: auto;
        padding-top: 0px;
        padding-bottom: 20px;
    }
    .user-sidebar {
        border-right: 1px solid #dee2e6;
        background-color: rgba(255, 255, 255, 0.95);
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.3);
        width: 20%;
    }
    .content-area {
        padding: 20px;
        background-color: rgba(255, 255, 255, 0.9);
        width: 80%;
    }
    .list-group-item {
        border-left: none;
        border-right: none;
        border-radius: 0 !important;
    }
    .list-group-item:first-child {
        border-top: none;
    }
    .list-group-item.active {
        background-color: #198754;
        border-color: #198754;
        color: white;
    }
    .card-no-hover:hover {
        transform: none;
    }
    .sidebar-profile {
        padding: 20px 0;
    }
    .select2-container--bootstrap-5 .select2-selection {
        min-height: 38px;
    }
    .select2-container--bootstrap-5 .select2-selection--single {
        padding-top: 5px;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        padding-left: 5px;
    }
    .select2-results__option {
        padding: 8px 12px;
    }
    .user-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        margin-right: 10px;
        object-fit: cover;
    }
    .user-avatar-icon {
        width: 30px;
        height: 30px;
        background-color: #f8f9fa;
        border-radius: 50%;
        margin-right: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .select2-container .select2-selection--single .select2-selection__rendered {
        display: flex;
        align-items: center;
    }
    @media (max-width: 767px) {
        .sidebar {
            height: auto;
            position: relative;
            top: 0;
        }
        .user-sidebar {
            border-right: none;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
    }
    .document-list {
        max-height: 300px;
        overflow-y: auto;
    }
    .document-item {
        transition: all 0.2s ease;
    }
    .document-item:hover {
        background-color: rgba(25, 135, 84, 0.1);
    }
    .document-item .form-check-label {
        cursor: pointer;
        width: 100%;
        padding: 8px;
        border-radius: 4px;
    }
    .document-item .form-check-input:checked + .form-check-label {
        background-color: rgba(25, 135, 84, 0.1);
    }
</style>
<div class="container-fluid p-0">
    <!-- Кнопка для мобільного меню -->
    <button class="mobile-toggle-sidebar d-md-none" id="toggleSidebar">
        <i class="bi bi-list fs-4"></i>
    </button>
    <!-- Затемнення фону при відкритому меню на мобільних -->
    <div class="mobile-overlay" id="sidebarOverlay"></div>
    <div class="row g-0">
        <!-- Ліва бокова панель -->
        <div class="col-md-3 user-sidebar sidebar" id="userSidebar">
            <div class="sidebar-profile text-center px-3">
                <div class="mb-3">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?= BASE_URL ?>uploads/<?= $user['avatar'] ?>" class="img-thumbnail rounded-circle" alt="Аватар" style="width: 90px; height: 90px; object-fit: cover;">
                    <?php else: ?>
                        <div class="bg-light rounded-circle d-flex justify-content-center align-items-center mx-auto" style="width: 90px; height: 90px;">
                            <i class="bi bi-person-fill text-secondary fs-2"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($user['full_name']) ?></h5>
                <p class="text-muted small mb-2">@<?= htmlspecialchars($user['login']) ?></p>
                <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
                    <?php foreach ($roles as $role): ?>
                        <span class="badge bg-success rounded-pill px-3 py-2">
                            <?= htmlspecialchars($role['name']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="list-group rounded-0">
                <a href="<?= BASE_URL ?>dashboard.php?tab=profile" class="list-group-item list-group-item-action">
                    <i class="bi bi-person-vcard me-2"></i> Мій кабінет
                </a>
                <a href="<?= BASE_URL ?>dashboard.php?tab=documents" class="list-group-item list-group-item-action">
                    <i class="bi bi-file-earmark-text me-2"></i> Документи
                </a>
                <a href="<?= BASE_URL ?>dashboard.php?tab=orders" class="list-group-item list-group-item-action">
                    <i class="bi bi-journal-text me-2"></i> Накази
                </a>
                <a href="<?= BASE_URL ?>dashboard.php?tab=directives" class="list-group-item list-group-item-action">
                    <i class="bi bi-journal-check me-2"></i> Розпорядження
                </a>
                <a href="<?= BASE_URL ?>dashboard.php?tab=letters" class="list-group-item list-group-item-action active">
                    <i class="bi bi-envelope me-2"></i> Листи
                    <?php
                    $unread_count = $letterModel->countUnreadLetters($user_id);
                    if ($unread_count > 0): 
                    ?>
                    <span class="badge rounded-pill bg-danger float-end"><?= $unread_count ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= BASE_URL ?>dashboard.php?tab=archive" class="list-group-item list-group-item-action">
                    <i class="bi bi-archive me-2"></i> Архів
                </a>
                <a href="<?= BASE_URL ?>dashboard.php?tab=settings" class="list-group-item list-group-item-action">
                    <i class="bi bi-person-gear me-2"></i> Налаштування профілю
                </a>
                <a href="<?= BASE_URL ?>logout.php" class="list-group-item list-group-item-action text-danger">
                    <i class="bi bi-box-arrow-right me-2"></i> Вихід
                </a>
            </div>
        </div>
        <!-- Основний контент справа -->
        <div class="col-md-9 content-area">
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-2"></i> <?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle me-2"></i> <?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <div class="card border-dark shadow-sm mb-4">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between mb-3">
                        <h5 class="card-title mb-0 pt-2">
                            <?= $page_title ?>
                        </h5>
                        <?php if ($action !== 'list' && $action !== 'view'): ?>
                            <a href="<?= BASE_URL ?>dashboard.php?tab=letters&subtab=<?= $subtab ?>" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-arrow-left me-1"></i> Назад
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php if ($action === 'list'): ?>
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <a class="nav-link <?= $subtab === 'incoming' ? 'active' : '' ?>" href="<?= BASE_URL ?>dashboard.php?tab=letters&subtab=incoming">
                                Вхідні
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $subtab === 'outgoing' ? 'active' : '' ?>" href="<?= BASE_URL ?>dashboard.php?tab=letters&subtab=outgoing">
                                Вихідні
                            </a>
                        </li>
                    </ul>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($action === 'compose'): ?>
                        <!-- Форма створення нового листа -->
                        <form action="<?= BASE_URL ?>letters.php?action=send" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <div class="mb-3">
                                <label for="receiver_id" class="form-label">Отримувач <span class="text-danger">*</span></label>
                                <div class="receiver-selector">
                                    <select class="form-select" id="receiver_id" name="receiver_id" required>
                                        <option value="">Виберіть отримувача</option>
                                        <?php
                                        $users = $userModel->getAllUsers();
                                        foreach ($users as $u):
                                            if ($u['id'] != $user_id && !$userModel->isAdmin($u['id'])): 
                                                $avatar_path = !empty($u['avatar']) ? BASE_URL . 'uploads/' . $u['avatar'] : '';
                                        ?>
                                            <option value="<?= $u['id'] ?>" <?= ($reply_receiver_id == $u['id']) ? 'selected' : '' ?> data-avatar="<?= $avatar_path ?>">
                                                <?= htmlspecialchars($u['full_name']) ?>
                                                <?php 
                                                $u_roles = $userModel->getUserRoles($u['id']);
                                                if (!empty($u_roles)) {
                                                    echo ' (';
                                                    $role_names = array_map(function($r) {
                                                        return $r['name'];
                                                    }, $u_roles);
                                                    echo htmlspecialchars(implode(', ', $role_names));
                                                    echo ')';
                                                }
                                                ?>
                                            </option>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </select>
                                </div>
                                <!-- Альтернативний інтерфейс вибору користувача -->
                                <div class="user-search-container mt-3">
                                    <div class="input-group mb-2">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" id="userSearchInput" placeholder="Пошук користувачів...">
                                    </div>
                                    <div class="user-search-results border rounded" style="max-height: 250px; overflow-y: auto; display: none;">
                                        <ul class="list-group list-group-flush" id="userSearchResults">
                                            <!-- Результати пошуку будуть відображатися тут -->
                                        </ul>
                                    </div>
                                    <div class="selected-user mt-2" id="selectedUserContainer" style="display: none;">
                                        <div class="card">
                                            <div class="card-body p-2 d-flex align-items-center">
                                                <div class="selected-user-avatar me-2" id="selectedUserAvatar">
                                                    <!-- Аватар буде тут -->
                                                </div>
                                                <div class="selected-user-info">
                                                    <strong id="selectedUserName"></strong>
                                                    <div class="small text-muted" id="selectedUserRole"></div>
                                                </div>
                                                <button type="button" class="btn-close ms-auto" id="clearSelectedUser"></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="subject" class="form-label">Тема <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="subject" name="subject" required>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Повідомлення <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="message" name="message" rows="6" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="attachments" class="form-label">Прикріпити файли</label>
                                <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                                <div class="form-text">
                                    Дозволені формати: PDF, DOC, DOCX, XLSX, XLS, PPT, PPTX, TXT, JPG, JPEG, PNG. Максимальний розмір: 100MB.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="documents" class="form-label">Прикріпити документи з системи</label>
                                <div class="card">
                                    <div class="card-body p-3">
                                        <div class="document-selector mb-2">
                                            <?php
                                            require_once '../app/models/Document.php';
                                            $documentModel = new Document();
                                            $documents = $documentModel->getUserDocuments($user_id);
                                            require_once '../app/models/Department.php';
                                            $departmentModel = new Department();
                                            if (empty($documents)): 
                                            ?>
                                                <div class="alert alert-info mb-0">
                                                    <i class="bi bi-info-circle me-2"></i> У вас немає документів для прикріплення
                                                </div>
                                            <?php else: ?>
                                                <!-- Кнопки для швидкого вибору всіх документів або очищення вибору -->
                                                <div class="d-flex justify-content-end mb-2">
                                                    <button type="button" class="btn btn-sm btn-outline-success me-2" id="selectAllDocs">
                                                        <i class="bi bi-check-all me-1"></i> Вибрати всі
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllDocs">
                                                        <i class="bi bi-x-lg me-1"></i> Очистити
                                                    </button>
                                                </div>
                                                <div class="document-list">
                                                    <?php foreach ($documents as $doc): 
                                                        $iconClass = 'text-primary';
                                                        $iconType = 'bi-file-earmark-text';
                                                        if ($doc['type_id'] == 2) {
                                                            $iconClass = 'text-danger';
                                                            $iconType = 'bi-file-earmark-text';
                                                        } elseif ($doc['type_id'] == 3) {
                                                            $iconClass = 'text-warning';
                                                            $iconType = 'bi-file-earmark-text';
                                                        }
                                                        $department = $departmentModel->getDepartmentById($doc['department_id']);
                                                        $departmentName = $department ? $department['name'] : 'Невідоме відділення';
                                                    ?>
                                                        <div class="form-check document-item mb-2">
                                                            <input class="form-check-input" type="checkbox" name="documents[]" value="<?= $doc['id'] ?>" id="doc_<?= $doc['id'] ?>">
                                                            <label class="form-check-label d-flex align-items-center" for="doc_<?= $doc['id'] ?>">
                                                                <i class="bi <?= $iconType ?> <?= $iconClass ?> me-2 fs-5"></i>
                                                                <div>
                                                                    <div><?= htmlspecialchars($doc['title']) ?></div>
                                                                    <small class="text-muted">
                                                                        <i class="bi bi-building me-1"></i> <?= htmlspecialchars($departmentName) ?>
                                                                    </small>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-text">
                                    Оберіть документи, які бажаєте прикріпити до листа.
                                </div>
                            </div>
                            <div class="mt-4 d-flex justify-content-between">
                                <a href="<?= BASE_URL ?>dashboard.php?tab=letters" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Скасувати
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-send me-1"></i> Надіслати лист
                                </button>
                            </div>
                        </form>
                    <?php elseif ($action === 'view' && isset($letter)): ?>
                        <!-- Перегляд листа -->
                        <div class="mb-4">
                            <h3><?= htmlspecialchars($letter['subject']) ?></h3>
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <div class="d-flex align-items-center">
                                        <?php if ($letter['sender_id'] == $user_id): ?>
                                            <!-- Якщо користувач - відправник (показуємо отримувача) -->
                                            <div class="me-3">
                                                <a href="<?= BASE_URL ?>user_profile.php?id=<?= $letter['receiver_id'] ?>" class="text-decoration-none">
                                                    <?php if (!empty($letter['receiver_avatar'])): ?>
                                                        <img src="<?= BASE_URL ?>uploads/<?= $letter['receiver_avatar'] ?>" 
                                                             class="img-thumbnail rounded-circle" alt="Аватар" 
                                                             style="width: 50px; height: 50px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded-circle d-flex justify-content-center align-items-center" 
                                                             style="width: 50px; height: 50px;">
                                                            <i class="bi bi-person-fill text-secondary fs-3"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </a>
                                            </div>
                                            <div>
                                                <span class="badge bg-success me-2">Надіслано</span>
                                                <div>До: <strong><a href="<?= BASE_URL ?>user_profile.php?id=<?= $letter['receiver_id'] ?>" class="text-decoration-none text-dark"><?= htmlspecialchars($letter['receiver_name']) ?></a></strong></div>
                                                <small class="text-muted">
                                                    <?= (new DateTime($letter['sent_at']))->format('d.m.Y H:i') ?>
                                                </small>
                                            </div>
                                        <?php else: ?>
                                            <!-- Якщо користувач - отримувач (показуємо відправника) -->
                                            <div class="me-3">
                                                <a href="<?= BASE_URL ?>user_profile.php?id=<?= $letter['sender_id'] ?>" class="text-decoration-none">
                                                    <?php if (!empty($letter['sender_avatar'])): ?>
                                                        <img src="<?= BASE_URL ?>uploads/<?= $letter['sender_avatar'] ?>" 
                                                             class="img-thumbnail rounded-circle" alt="Аватар" 
                                                             style="width: 50px; height: 50px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded-circle d-flex justify-content-center align-items-center" 
                                                             style="width: 50px; height: 50px;">
                                                            <i class="bi bi-person-fill text-secondary fs-3"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </a>
                                            </div>
                                            <div>
                                                <span class="badge bg-primary me-2">Отримано</span>
                                                <div>Від: <strong><a href="<?= BASE_URL ?>user_profile.php?id=<?= $letter['sender_id'] ?>" class="text-decoration-none text-dark"><?= htmlspecialchars($letter['sender_name']) ?></a></strong></div>
                                                <small class="text-muted">
                                                    <?= (new DateTime($letter['sent_at']))->format('d.m.Y H:i') ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?= nl2br(htmlspecialchars($letter['message'])) ?>
                                </div>
                            </div>
                            <?php if (!empty($letter['attachments'])): ?>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <strong>Прикріплені файли</strong>
                                    </div>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($letter['attachments'] as $attachment): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php 
                                                $file_name = basename($attachment['file_path']);
                                                $original_name = preg_replace('/^[a-f0-9]{13}_/', '', $file_name);
                                                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                                $file_icon = 'bi-file-earmark';
                                                if (in_array($file_ext, ['pdf'])) {
                                                    $file_icon = 'bi-file-earmark-pdf';
                                                } elseif (in_array($file_ext, ['doc', 'docx'])) {
                                                    $file_icon = 'bi-file-earmark-word';
                                                } elseif (in_array($file_ext, ['xls', 'xlsx'])) {
                                                    $file_icon = 'bi-file-earmark-excel';
                                                } elseif (in_array($file_ext, ['ppt', 'pptx'])) {
                                                    $file_icon = 'bi-file-earmark-ppt';
                                                } elseif (in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
                                                    $file_icon = 'bi-file-earmark-image';
                                                } elseif (in_array($file_ext, ['txt'])) {
                                                    $file_icon = 'bi-file-earmark-text';
                                                }
                                                ?>
                                                <div>
                                                    <i class="bi <?= $file_icon ?> me-2"></i>
                                                    <?= htmlspecialchars($original_name) ?>
                                                </div>
                                                <div>
                                                    <a href="<?= BASE_URL ?>uploads/<?= $attachment['file_path'] ?>" download="<?= htmlspecialchars($original_name) ?>" class="btn btn-sm btn-outline-success">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($letter['documents'])): ?>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <strong>Прикріплені документи</strong>
                                    </div>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($letter['documents'] as $document): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php 
                                                $doc_title = $document['title'];
                                                $file_ext = !empty($document['file_path']) ? strtolower(pathinfo($document['file_path'], PATHINFO_EXTENSION)) : '';
                                                $file_icon = 'bi-file-earmark';
                                                if (in_array($file_ext, ['pdf'])) {
                                                    $file_icon = 'bi-file-earmark-pdf';
                                                } elseif (in_array($file_ext, ['doc', 'docx'])) {
                                                    $file_icon = 'bi-file-earmark-word';
                                                } elseif (in_array($file_ext, ['xls', 'xlsx'])) {
                                                    $file_icon = 'bi-file-earmark-excel';
                                                } elseif (in_array($file_ext, ['ppt', 'pptx'])) {
                                                    $file_icon = 'bi-file-earmark-ppt';
                                                } elseif (in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
                                                    $file_icon = 'bi-file-earmark-image';
                                                } elseif (in_array($file_ext, ['txt'])) {
                                                    $file_icon = 'bi-file-earmark-text';
                                                }
                                                ?>
                                                <div>
                                                    <i class="bi <?= $file_icon ?> me-2"></i>
                                                    <?= htmlspecialchars($doc_title) ?>
                                                </div>
                                                <div>
                                                    <a href="<?= BASE_URL ?>documents.php?action=view&id=<?= $document['id'] ?>&from_letter=<?= $letter['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if (!empty($document['file_path'])): ?>
                                                    <a href="<?= BASE_URL ?>uploads/<?= $document['file_path'] ?>" download="<?= htmlspecialchars($doc_title) . '.' . pathinfo($document['file_path'], PATHINFO_EXTENSION) ?>" class="btn btn-sm btn-outline-success">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <div class="mt-4 d-flex justify-content-between">
                                <div>
                                    <a href="<?= BASE_URL ?>dashboard.php?tab=letters&subtab=<?= $letter['sender_id'] == $user_id ? 'outgoing' : 'incoming' ?>" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left me-1"></i> Назад
                                    </a>
                                    <?php if ($letter['sender_id'] == $user_id): ?>
                                    <a href="<?= BASE_URL ?>dashboard.php?tab=letters&action=delete&id=<?= $letter['id'] ?>&is_sender=1&from=view&subtab=outgoing" class="btn btn-outline-danger ms-2" 
                                       onclick="return confirm('Ви впевнені, що хочете видалити цей лист?')">
                                        <i class="bi bi-trash me-1"></i> Видалити
                                    </a>
                                    <?php else: ?>
                                    <a href="<?= BASE_URL ?>dashboard.php?tab=letters&action=delete&id=<?= $letter['id'] ?>&is_sender=0&from=view&subtab=incoming" class="btn btn-outline-danger ms-2" 
                                       onclick="return confirm('Ви впевнені, що хочете видалити цей лист?')">
                                        <i class="bi bi-trash me-1"></i> Видалити
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Список листів -->
                        <?php
                        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                        $page = $page < 1 ? 1 : $page;
                        $limit = 10;
                        $offset = ($page - 1) * $limit;
                        $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
                        $read_filter = 'all';
                        if ($subtab === 'incoming' && isset($_GET['read_filter']) && in_array($_GET['read_filter'], ['all', 'read', 'unread'])) {
                            $read_filter = $_GET['read_filter'];
                        }
                        $star_filter = 'all';
                        if (isset($_GET['star_filter']) && in_array($_GET['star_filter'], ['all', 'starred', 'unstarred'])) {
                            $star_filter = $_GET['star_filter'];
                        }
                        if (!empty($search_query)) {
                            if ($subtab === 'incoming') {
                                $letters = $letterModel->searchLetters($user_id, $search_query, true, $limit, $offset, $read_filter, $star_filter);
                                $total_letters = $letterModel->countSearchResults($user_id, $search_query, true, $read_filter, $star_filter);
                            } else {
                                $letters = $letterModel->searchLetters($user_id, $search_query, false, $limit, $offset, 'all', $star_filter);
                                $total_letters = $letterModel->countSearchResults($user_id, $search_query, false, 'all', $star_filter);
                            }
                        } else {
                            if ($subtab === 'incoming') {
                                $letters = $letterModel->getIncomingLetters($user_id, $read_filter, $star_filter, $limit, $offset);
                                $total_letters = $letterModel->countIncomingLetters($user_id, $read_filter, $star_filter);
                            } else {
                                $letters = $letterModel->getOutgoingLetters($user_id, $star_filter, $limit, $offset);
                                $total_letters = $letterModel->countOutgoingLetters($user_id, $star_filter);
                            }
                        }
                        $total_pages = ceil($total_letters / $limit);
                        ?>
                        <!-- Форма пошуку листів -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <form action="<?= BASE_URL ?>letters.php" method="get" class="row g-3">
                                    <input type="hidden" name="subtab" value="<?= $subtab ?>">
                                    <div class="col-md-9">
                                        <div class="input-group">
                                            <input type="text" class="form-control" name="search" placeholder="Пошук за темою, вмістом або автором..." value="<?= htmlspecialchars($search_query) ?>">
                                            <?php if (!empty($search_query)): ?>
                                            <a href="<?= BASE_URL ?>letters.php?subtab=<?= $subtab ?><?= isset($_GET['read_filter']) ? '&read_filter=' . $_GET['read_filter'] : '' ?>" class="btn btn-outline-danger">
                                                <i class="bi bi-x-lg"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <button type="submit" class="btn btn-success">
                                            <i class="bi bi-search me-1"></i> Знайти
                                        </button>
                                    </div>
                                    <div class="col-12">
                                        <div class="row align-items-center mt-3">
                                            <?php if ($subtab === 'incoming'): ?>
                                            <div class="col-md-5 mb-3">
                                                <a href="<?= BASE_URL ?>letters.php?action=markAllRead&subtab=<?= $subtab ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= isset($_GET['page']) ? '&page=' . (int)$_GET['page'] : '' ?><?= isset($_GET['read_filter']) ? '&read_filter=' . $_GET['read_filter'] : '' ?><?= isset($_GET['star_filter']) ? '&star_filter=' . $_GET['star_filter'] : '' ?>" class="btn btn-outline-success w-100" onclick="return confirm('Ви впевнені, що хочете позначити всі вхідні листи як прочитані?')">
                                                    <i class="bi bi-envelope-open me-1"></i> Позначити всі як прочитані
                                                </a>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <div class="d-flex align-items-center">
                                                    <label for="read_filter" class="me-2">Статус:</label>
                                                    <select class="form-select" name="read_filter" id="read_filter" onchange="this.form.submit()">
                                                        <option value="all" <?= $read_filter === 'all' ? 'selected' : '' ?>>
                                                            Усі листи (<?= $letterModel->countIncomingLetters($user_id) ?>)
                                                        </option>
                                                        <option value="unread" <?= $read_filter === 'unread' ? 'selected' : '' ?>>
                                                            Непрочитані (<?= $unread_count ?>)
                                                        </option>
                                                        <option value="read" <?= $read_filter === 'read' ? 'selected' : '' ?>>
                                                            Прочитані (<?= $letterModel->countIncomingLetters($user_id, 'read') ?>)
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <div class="col-md-<?= $subtab === 'incoming' ? '4' : '12' ?> mb-3">
                                                <div class="d-flex align-items-center">
                                                    <label for="star_filter" class="me-2">Зірочка:</label>
                                                    <select class="form-select" name="star_filter" id="star_filter" onchange="this.form.submit()">
                                                        <option value="all" <?= $star_filter === 'all' ? 'selected' : '' ?>>
                                                            Усі листи
                                                        </option>
                                                        <option value="starred" <?= $star_filter === 'starred' ? 'selected' : '' ?>>
                                                            Із зірочкою (<?= $letterModel->countStarredLetters($user_id, $subtab === 'incoming') ?>)
                                                        </option>
                                                        <option value="unstarred" <?= $star_filter === 'unstarred' ? 'selected' : '' ?>>
                                                            Без зірочки
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php if (empty($letters)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i> 
                                <?php if (!empty($search_query)): ?>
                                    <?php if ($subtab === 'incoming'): ?>
                                        За вашим запитом не знайдено вхідних листів.
                                    <?php else: ?>
                                        За вашим запитом не знайдено вихідних листів.
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if ($subtab === 'incoming'): ?>
                                        Наразі у вас немає вхідних листів.
                                    <?php else: ?>
                                        Наразі у вас немає надісланих листів.
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="letter-list">
                                <?php foreach ($letters as $letter): ?>
                                    <div class="letter-item card mb-3 card-no-hover">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <div class="letter-icon me-3 position-relative">
                                                    <?php if ($subtab === 'incoming'): ?>
                                                        <?php if ($letter['is_read']): ?>
                                                            <i class="bi bi-envelope-open text-secondary fs-2"></i>
                                                        <?php else: ?>
                                                            <i class="bi bi-envelope-fill text-primary fs-2"></i>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <i class="bi bi-envelope-arrow-up text-success fs-2"></i>
                                                    <?php endif; ?>
                                                    <?php if ($letter['is_starred']): ?>
                                                        <i class="bi bi-star-fill text-warning position-absolute" style="top: -7px; right: -7px; font-size: 1rem;"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="letter-info flex-grow-1">
                                                    <h5 class="mb-1">
                                                        <a href="<?= BASE_URL ?>letters.php?action=view&id=<?= $letter['id'] ?>" class="text-decoration-none <?= (!$letter['is_read'] && $subtab === 'incoming') ? 'fw-bold' : '' ?>">
                                                            <?= htmlspecialchars($letter['subject']) ?>
                                                            <?php if (!$letter['is_read'] && $subtab === 'incoming'): ?>
                                                                <span class="badge bg-danger">Нове</span>
                                                            <?php endif; ?>
                                                        </a>
                                                    </h5>
                                                    <div class="text-muted small mb-2">
                                                        <?php if ($subtab === 'incoming'): ?>
                                                            <span class="me-2"><i class="bi bi-person me-1"></i> Від: <a href="<?= BASE_URL ?>user_profile.php?id=<?= $letter['sender_id'] ?>" class="text-decoration-none text-muted"><?= htmlspecialchars($letter['sender_name']) ?></a></span>
                                                        <?php else: ?>
                                                            <span class="me-2"><i class="bi bi-person me-1"></i> До: <a href="<?= BASE_URL ?>user_profile.php?id=<?= $letter['receiver_id'] ?>" class="text-decoration-none text-muted"><?= htmlspecialchars($letter['receiver_name']) ?></a></span>
                                                        <?php endif; ?>
                                                        <span><i class="bi bi-calendar me-1"></i> <?= (new DateTime($letter['sent_at']))->format('d.m.Y H:i') ?></span>
                                                        <?php if ($letter['has_attachments']): ?>
                                                            <span class="ms-2"><i class="bi bi-paperclip me-1"></i> Прикріплені файли</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="letter-preview">
                                                        <?= mb_substr(htmlspecialchars($letter['message']), 0, 100) . (mb_strlen($letter['message']) > 100 ? '...' : '') ?>
                                                    </div>
                                                </div>
                                                <div class="letter-actions">
                                                    <div class="btn-group">
                                                        <a href="<?= BASE_URL ?>letters.php?action=view&id=<?= $letter['id'] ?>" class="btn btn-sm btn-outline-primary" title="Переглянути">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($letter['sender_id'] != $user_id): ?>
                                                        <a href="<?= BASE_URL ?>letters.php?action=compose&reply_to=<?= $letter['id'] ?>" class="btn btn-sm btn-outline-success" title="Відповісти">
                                                            <i class="bi bi-reply"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                        <?php if ($subtab === 'incoming'): ?>
                                                            <?php if ($letter['is_read']): ?>
                                                            <a href="<?= BASE_URL ?>letters.php?action=mark&letter_id=<?= $letter['id'] ?>&mark_type=unread&search=<?= urlencode($search_query) ?>&page=<?= $page ?>&read_filter=<?= $read_filter ?>&star_filter=<?= $star_filter ?>&subtab=<?= $subtab ?>" class="btn btn-sm btn-outline-info" title="Позначити як непрочитаний">
                                                                <i class="bi bi-envelope"></i>
                                                            </a>
                                                            <?php else: ?>
                                                            <a href="<?= BASE_URL ?>letters.php?action=mark&letter_id=<?= $letter['id'] ?>&mark_type=read&search=<?= urlencode($search_query) ?>&page=<?= $page ?>&read_filter=<?= $read_filter ?>&star_filter=<?= $star_filter ?>&subtab=<?= $subtab ?>" class="btn btn-sm btn-outline-secondary" title="Позначити як прочитаний">
                                                                <i class="bi bi-envelope-open"></i>
                                                            </a>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                        <?php if ($letter['is_starred']): ?>
                                                        <a href="<?= BASE_URL ?>letters.php?action=mark&letter_id=<?= $letter['id'] ?>&mark_type=unstarred&search=<?= urlencode($search_query) ?>&page=<?= $page ?>&read_filter=<?= $read_filter ?>&star_filter=<?= $star_filter ?>&subtab=<?= $subtab ?>" class="btn btn-sm btn-outline-warning" title="Прибрати зірочку">
                                                            <i class="bi bi-star"></i>
                                                        </a>
                                                        <?php else: ?>
                                                        <a href="<?= BASE_URL ?>letters.php?action=mark&letter_id=<?= $letter['id'] ?>&mark_type=starred&search=<?= urlencode($search_query) ?>&page=<?= $page ?>&read_filter=<?= $read_filter ?>&star_filter=<?= $star_filter ?>&subtab=<?= $subtab ?>" class="btn btn-sm btn-outline-warning" title="Додати зірочку">
                                                            <i class="bi bi-star-fill"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                        <a href="<?= BASE_URL ?>letters.php?action=delete&id=<?= $letter['id'] ?>&is_sender=<?= $subtab === 'outgoing' ? '1' : '0' ?>&from=dashboard&search=<?= urlencode($search_query) ?>&page=<?= $page ?>&read_filter=<?= $read_filter ?>&star_filter=<?= $star_filter ?>" class="btn btn-sm btn-outline-danger" 
                                                            onclick="return confirm('Ви впевнені, що хочете видалити цей лист?')" title="Видалити">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <!-- Пагінація -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Навігація по сторінках">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="<?= BASE_URL ?>letters.php?subtab=<?= $subtab ?>&page=<?= $page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= $read_filter !== 'all' ? '&read_filter=' . $read_filter : '' ?><?= $star_filter !== 'all' ? '&star_filter=' . $star_filter : '' ?>">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                <a class="page-link" href="<?= BASE_URL ?>letters.php?subtab=<?= $subtab ?>&page=<?= $i ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= $read_filter !== 'all' ? '&read_filter=' . $read_filter : '' ?><?= $star_filter !== 'all' ? '&star_filter=' . $star_filter : '' ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="<?= BASE_URL ?>letters.php?subtab=<?= $subtab ?>&page=<?= $page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= $read_filter !== 'all' ? '&read_filter=' . $read_filter : '' ?><?= $star_filter !== 'all' ? '&star_filter=' . $star_filter : '' ?>">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                        <!-- Кнопка "Написати лист" -->
                        <div class="text-end mt-3">
                            <a href="<?= BASE_URL ?>letters.php?action=compose" class="btn btn-success">
                                <i class="bi bi-plus-lg me-1"></i> Написати лист
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
require_once '../app/views/includes/footer.php';
?>
<!-- Підключення скрипту для мобільної бічної панелі -->
<script src="<?= BASE_URL ?>js/mobile-sidebar.js"></script>
<!-- Підключення Select2 JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('userSearchInput')) {
        const userSearchInput = document.getElementById('userSearchInput');
        const userSearchResults = document.getElementById('userSearchResults');
        const resultsContainer = document.querySelector('.user-search-results');
        const selectedUserContainer = document.getElementById('selectedUserContainer');
        const selectedUserAvatar = document.getElementById('selectedUserAvatar');
        const selectedUserName = document.getElementById('selectedUserName');
        const selectedUserRole = document.getElementById('selectedUserRole');
        const clearSelectedUserBtn = document.getElementById('clearSelectedUser');
        const receiverSelect = document.getElementById('receiver_id');
        const users = [];
        const options = receiverSelect.querySelectorAll('option');
        options.forEach(option => {
            if (option.value) {
                const fullText = option.textContent.trim();
                const nameMatch = fullText.match(/(.*?)(?:\s*\(|$)/);
                const name = nameMatch ? nameMatch[1].trim() : fullText;
                const roleMatch = fullText.match(/\((.*?)\)/);
                const role = roleMatch ? roleMatch[1].trim() : '';
                users.push({
                    id: option.value,
                    name: name,
                    role: role,
                    fullText: fullText,
                    avatar: option.getAttribute('data-avatar') || ''
                });
            }
        });
        function filterUsers(query) {
            if (!query) return [];
            query = query.toLowerCase();
            return users.filter(user => 
                user.name.toLowerCase().includes(query) || 
                user.role.toLowerCase().includes(query)
            );
        }
        function renderSearchResults(filteredUsers) {
            userSearchResults.innerHTML = '';
            if (filteredUsers.length === 0) {
                const li = document.createElement('li');
                li.className = 'list-group-item text-muted';
                li.textContent = 'Користувачів не знайдено';
                userSearchResults.appendChild(li);
                return;
            }
            filteredUsers.forEach(user => {
                const li = document.createElement('li');
                li.className = 'list-group-item user-search-item';
                li.style.cursor = 'pointer';
                const container = document.createElement('div');
                container.className = 'd-flex align-items-center';
                const avatarContainer = document.createElement('div');
                avatarContainer.className = 'me-2';
                if (user.avatar) {
                    const avatar = document.createElement('img');
                    avatar.src = user.avatar;
                    avatar.className = 'rounded-circle';
                    avatar.style.width = '32px';
                    avatar.style.height = '32px';
                    avatar.style.objectFit = 'cover';
                    avatarContainer.appendChild(avatar);
                } else {
                    const avatarIcon = document.createElement('div');
                    avatarIcon.className = 'bg-light rounded-circle d-flex justify-content-center align-items-center';
                    avatarIcon.style.width = '32px';
                    avatarIcon.style.height = '32px';
                    const icon = document.createElement('i');
                    icon.className = 'bi bi-person-fill';
                    avatarIcon.appendChild(icon);
                    avatarContainer.appendChild(avatarIcon);
                }
                container.appendChild(avatarContainer);
                const info = document.createElement('div');
                const name = document.createElement('div');
                name.textContent = user.name;
                info.appendChild(name);
                if (user.role) {
                    const role = document.createElement('small');
                    role.className = 'text-muted';
                    role.textContent = user.role;
                    info.appendChild(role);
                }
                container.appendChild(info);
                li.appendChild(container);
                li.addEventListener('click', () => {
                    selectUser(user);
                });
                userSearchResults.appendChild(li);
            });
        }
        function selectUser(user) {
            receiverSelect.value = user.id;
            selectedUserName.textContent = user.name;
            selectedUserRole.textContent = user.role;
            selectedUserAvatar.innerHTML = '';
            if (user.avatar) {
                const avatar = document.createElement('img');
                avatar.src = user.avatar;
                avatar.className = 'rounded-circle';
                avatar.style.width = '40px';
                avatar.style.height = '40px';
                avatar.style.objectFit = 'cover';
                selectedUserAvatar.appendChild(avatar);
            } else {
                const avatarIcon = document.createElement('div');
                avatarIcon.className = 'bg-light rounded-circle d-flex justify-content-center align-items-center';
                avatarIcon.style.width = '40px';
                avatarIcon.style.height = '40px';
                const icon = document.createElement('i');
                icon.className = 'bi bi-person-fill';
                avatarIcon.appendChild(icon);
                selectedUserAvatar.appendChild(avatarIcon);
            }
            selectedUserContainer.style.display = 'block';
            userSearchInput.value = '';
            resultsContainer.style.display = 'none';
        }
        userSearchInput.addEventListener('input', function() {
            const query = this.value.trim();
            const filteredUsers = filterUsers(query);
            if (query && filteredUsers.length > 0) {
                renderSearchResults(filteredUsers);
                resultsContainer.style.display = 'block';
            } else if (query) {
                renderSearchResults([]);
                resultsContainer.style.display = 'block';
            } else {
                resultsContainer.style.display = 'none';
            }
        });
        clearSelectedUserBtn.addEventListener('click', function() {
            receiverSelect.value = '';
            selectedUserContainer.style.display = 'none';
        });
        document.addEventListener('click', function(e) {
            if (!resultsContainer.contains(e.target) && e.target !== userSearchInput) {
                resultsContainer.style.display = 'none';
            }
        });
        if (receiverSelect.value) {
            const selectedOption = receiverSelect.options[receiverSelect.selectedIndex];
            const fullText = selectedOption.textContent.trim();
            const nameMatch = fullText.match(/(.*?)(?:\s*\(|$)/);
            const name = nameMatch ? nameMatch[1].trim() : fullText;
            const roleMatch = fullText.match(/\((.*?)\)/);
            const role = roleMatch ? roleMatch[1].trim() : '';
            selectUser({
                id: receiverSelect.value,
                name: name,
                role: role,
                avatar: selectedOption.getAttribute('data-avatar') || ''
            });
        }
        document.querySelector('.receiver-selector').style.display = 'none';
    }
    const selectAllDocsBtn = document.getElementById('selectAllDocs');
    const clearAllDocsBtn = document.getElementById('clearAllDocs');
    if (selectAllDocsBtn && clearAllDocsBtn) {
        selectAllDocsBtn.addEventListener('click', function() {
            const docCheckboxes = document.querySelectorAll('input[name="documents[]"]');
            docCheckboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
        });
        clearAllDocsBtn.addEventListener('click', function() {
            const docCheckboxes = document.querySelectorAll('input[name="documents[]"]');
            docCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
        });
    }
});
</script> 