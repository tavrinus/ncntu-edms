<?php
require_once '../config/config.php';
require_once '../app/controllers/document_controller.php';
require_once '../app/models/User.php';
require_once '../app/models/Document.php';
require_once '../app/models/Department.php';
require_once '../app/models/Letter.php';
if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
    exit;
}
$userModel = new User();
$user_id = $_SESSION['user_id'];
$user = $userModel->getUserById($user_id);
if ($userModel->isAdmin($user_id)) {
    redirect('admin/dashboard.php');
    exit;
}
$isDirector = $userModel->hasRole($user_id, 2);
$isDeputyDirector = $userModel->hasRole($user_id, 3);
$isDepartmentHead = $userModel->hasRole($user_id, 8);
$documentController = new DocumentController();
$departmentModel = new Department();
$letterModel = new Letter();
$action = isset($_GET['action']) ? $_GET['action'] : '';
$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$department_id = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = $page < 1 ? 1 : $page;
$limit = 10;
$offset = ($page - 1) * $limit;
$userDepartmentIds = [];
$userDepartments = $departmentModel->getUserDepartments($user_id);
foreach ($userDepartments as $dept) {
    $userDepartmentIds[] = $dept['id'];
}
$canCreateDocuments = $isDirector || $isDeputyDirector || !empty($userDepartments);
if ($action === 'create' && !$canCreateDocuments) {
    $_SESSION['document_error'] = 'У вас немає прав для створення документів. Ви повинні бути призначені до хоча б одного відділення.';
    redirect('dashboard.php?tab=documents');
    exit;
}
$success_message = '';
$error_message = '';
if (isset($_SESSION['document_success'])) {
    $success_message = $_SESSION['document_success'];
    unset($_SESSION['document_success']);
}
if (isset($_SESSION['document_error'])) {
    $error_message = $_SESSION['document_error'];
    unset($_SESSION['document_error']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Помилка безпеки. Спробуйте знову.';
    } else {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $current_dept_id = (int)$_POST['department_id'];
        $errors = [];
        if (empty($title)) {
            $errors[] = 'Назва документа обов\'язкова';
        }
        if (strlen($title) > 255) {
            $errors[] = 'Назва документа занадто довга (максимум 255 символів)';
        }
        if ($action === 'create' && empty($_FILES['document_file']['name'])) {
            $errors[] = 'Завантаження файлу обов\'язкове при створенні документа';
        }
        if ($action === 'create') {
            if (!$documentController->canCreateDocumentInDepartment($user_id, $current_dept_id)) {
                $errors[] = 'У вас немає прав для створення документів у цьому відділенні';
            }
        } elseif ($action === 'edit' && $document_id > 0) {
            if (!$documentController->canEditDocument($user_id, $document_id)) {
                $errors[] = 'У вас немає прав для редагування цього документа';
            }
        }
        $file_path = '';
        if (!empty($_FILES['document_file']['name'])) {
            $allowed_extensions = ['pdf', 'doc', 'docx', 'xlsx', 'xls', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png'];
            $file_name = $_FILES['document_file']['name'];
            $file_tmp = $_FILES['document_file']['tmp_name'];
            $file_size = $_FILES['document_file']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if ($file_size > MAX_UPLOAD_SIZE) {
                $errors[] = 'Розмір файлу не повинен перевищувати 100MB';
            }
            if (!in_array($file_ext, $allowed_extensions)) {
                $errors[] = 'Недозволений тип файлу. Дозволені типи: ' . implode(', ', $allowed_extensions);
            }
            if (empty($errors)) {
                $new_file_name = uniqid('doc_') . '.' . $file_ext;
                $upload_dir = '../public/uploads/documents/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $upload_path = $upload_dir . $new_file_name;
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $file_path = 'documents/' . $new_file_name;
                    if ($action === 'edit' && isset($document) && !empty($document['file_path'])) {
                        $old_file_path = $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/public/uploads/' . $document['file_path'];
                        if (file_exists($old_file_path)) {
                            @unlink($old_file_path);
                            error_log("Видалено старий файл: " . $old_file_path);
                        }
                    }
                } else {
                    $errors[] = 'Помилка при завантаженні файлу';
                }
            }
        }
        if (empty($errors)) {
            $data = [
                'title' => $title,
                'content' => $content,
                'department_id' => $current_dept_id,
                'author_id' => $user_id
            ];
            if (!empty($file_path)) {
                $data['file_path'] = $file_path;
            }
            if ($action === 'create') {
                $result = $documentController->createNormalDocument($data);
                if ($result) {
                    $_SESSION['document_success'] = 'Документ успішно створено';
                    redirect('dashboard.php?tab=documents&subtab=my_documents');
                    exit;
                } else {
                    $error_message = 'Помилка при створенні документа';
                }
            } elseif ($action === 'edit' && $document_id > 0) {
                $current_document = $documentController->documentModel->getDocumentById($document_id);
                $data['type_id'] = 1;
                if (empty($file_path) && !empty($current_document['file_path'])) {
                    $data['file_path'] = $current_document['file_path'];
                } 
                else if (!empty($file_path) && !empty($current_document['file_path'])) {
                    $old_file_path = $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/public/uploads/' . $current_document['file_path'];
                    if (file_exists($old_file_path)) {
                        @unlink($old_file_path);
                        error_log("Додатково видалено старий файл під час оновлення: " . $old_file_path);
                    }
                }
                error_log("Updating document #{$document_id}. Data: " . print_r($data, true));
                $result = $documentController->updateDocument($document_id, $data);
                if ($result) {
                    $_SESSION['document_success'] = 'Документ успішно оновлено';
                    redirect('dashboard.php?tab=documents&subtab=my_documents');
                    exit;
                } else {
                    $error_message = 'Помилка при оновленні документа';
                    error_log("Error updating document #{$document_id}");
                }
            }
        } else {
            $error_message = implode('<br>', $errors);
        }
    }
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
if ($action === 'delete' && $document_id > 0) {
    if ($documentController->canEditDocument($user_id, $document_id)) {
        $attachedLetters = $documentController->documentModel->isDocumentAttachedToLetters($document_id);
        if ($attachedLetters && !isset($_GET['confirmed'])) {
            $document = $documentController->documentModel->getDocumentById($document_id);
            $page_title = 'Підтвердження видалення документу';
            require_once '../app/views/includes/header.php';
            ?>
            <div class="container mt-5">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> Попередження</h4>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Документ прикріплений до листів</h5>
                        <p class="card-text">
                            Документ "<strong><?= htmlspecialchars($document['title']) ?></strong>" прикріплений до 
                            <?= count($attachedLetters) > 1 ? count($attachedLetters) . ' листів' : '1 листа' ?>.
                        </p>
                        <p class="card-text">
                            Якщо ви видалите цей документ, він буде видалений з усіх листів, де він прикріплений.
                        </p>
                        <div class="d-flex justify-content-between mt-4">
                            <a href="<?= BASE_URL ?>dashboard.php?tab=documents&subtab=my_documents" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i> Скасувати
                            </a>
                            <a href="<?= BASE_URL ?>documents.php?action=delete&id=<?= $document_id ?>&confirmed=1" 
                               class="btn btn-danger" 
                               onclick="return confirm('Ви впевнені, що хочете видалити цей документ? Документ буде видалений з усіх листів, де він прикріплений. Ця дія незворотна.')">
                                <i class="bi bi-trash me-2"></i> Видалити все одно
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            require_once '../app/views/includes/footer.php';
            exit;
        }
        $document = $documentController->documentModel->getDocumentById($document_id);
        if ($documentController->documentModel->deleteDocument($document_id)) {
            if (!empty($document['file_path'])) {
                $file_path = $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/public/uploads/' . $document['file_path'];
                if (file_exists($file_path)) {
                    @unlink($file_path);
                    error_log("Видалено файл документа #{$document_id}: " . $file_path);
                }
            }
            $_SESSION['document_success'] = 'Документ успішно видалено';
        } else {
            $_SESSION['document_error'] = 'Помилка при видаленні документа';
        }
    } else {
        $_SESSION['document_error'] = 'У вас немає прав для видалення цього документа';
    }
    redirect('dashboard.php?tab=documents&subtab=my_documents');
    exit;
} elseif ($action === 'archive' && $document_id > 0) {
    if ($documentController->canEditDocument($user_id, $document_id)) {
        if ($documentController->archiveDocument($document_id)) {
            $_SESSION['document_success'] = 'Документ успішно переміщено в архів';
        } else {
            $_SESSION['document_error'] = 'Помилка при архівації документа';
        }
    } else {
        $_SESSION['document_error'] = 'У вас немає прав для архівації цього документа';
    }
    redirect('dashboard.php?tab=documents&subtab=my_documents');
    exit;
} elseif ($action === 'unarchive' && $document_id > 0) {
    if ($documentController->canEditDocument($user_id, $document_id)) {
        if ($documentController->unarchiveDocument($document_id)) {
            $_SESSION['success_message'] = 'Документ успішно відновлено з архіву';
        } else {
            $_SESSION['error_message'] = 'Помилка при відновленні документа з архіву';
        }
    } else {
        $_SESSION['error_message'] = 'У вас немає прав для відновлення цього документа з архіву';
    }
    if (isset($_GET['from_archive']) && $_GET['from_archive'] == 1) {
        redirect('dashboard.php?tab=archive');
    } else {
        redirect('dashboard.php?tab=documents&subtab=my_documents');
    }
    exit;
}
$document = null;
if ($action === 'edit' && $document_id > 0) {
    $document = $documentController->documentModel->getDocumentById($document_id);
    if (!$document) {
        redirect('dashboard.php?tab=documents&subtab=my_documents');
        exit;
    }
    if ($document['author_id'] != $user_id && !$documentController->canEditDocument($user_id, $document_id)) {
        redirect('dashboard.php?tab=documents&subtab=my_documents');
        exit;
    }
}
if ($action === 'view' && $document_id > 0) {
    $document = $documentController->documentModel->getDocumentById($document_id);
    if (!$document) {
        redirect('dashboard.php?tab=documents&subtab=my_documents');
        exit;
    }
}
$department = null;
if ($department_id > 0) {
    $department = $departmentModel->getDepartmentById($department_id);
    if (!$department) {
        redirect('dashboard.php?tab=documents&subtab=department_documents');
        exit;
    }
}
if ($action === 'create') {
    $page_title = 'Створення документа';
} elseif ($action === 'edit') {
    $page_title = 'Редагування документа';
} elseif ($action === 'view') {
    $page_title = 'Перегляд документа';
} elseif ($department_id > 0) {
    $page_title = 'Документи відділення: ' . htmlspecialchars($department['name']);
} else {
    $page_title = 'Документи';
}
require_once '../app/views/includes/header.php';
?>
<!-- Підключення стилів для мобільної бічної панелі -->
<link rel="stylesheet" href="<?= BASE_URL ?>css/mobile-sidebar.css">
<?php
if ($action === 'create' && !$canCreateDocuments): 
?>
<div class="container mt-4">
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Увага!</strong> Ви не можете створювати документи, оскільки не прив'язані до жодного відділення.
    </div>
    <div class="text-center mt-3">
        <a href="<?= BASE_URL ?>dashboard.php?tab=documents" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-2"></i> Повернутися до списку документів
        </a>
    </div>
</div>
<?php
exit;
endif;
?>
<!-- Стилі для документів -->
<style>
.document-item {
    transition: transform 0.2s;
    border: 1px solid #dee2e6;
}
.document-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}
.document-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
}
.document-actions {
    min-width: 150px;
    text-align: right;
}
.departments-container {
    margin-top: 15px;
}
.document-list .card {
    border-left: 4px solid #198754;
}
.document-actions .btn-group {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}
.modal-header.bg-success {
    color: white;
}
@media (min-width: 992px) {
    .modal-lg.document-modal {
        max-width: 900px;
    }
}
.sidebar {
    height: calc(100vh - 70px);
    position: sticky;
    top: 70px;
    overflow-y: auto;
    padding-top: 20px;
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
    padding-top: 28px;
    border-top: none;
}
.list-group-item.active {
    background-color: #198754;
    border-color: #198754;
    color: white;
}
</style>
<!-- JavaScript для роботи з документами -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const createDocBtn = document.querySelector('.btn-create-document');
    if (createDocBtn) {
        createDocBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.getAttribute('href');
            window.location.href = `<?= BASE_URL ?>dashboard.php?tab=documents&subtab=my_documents&action=create`;
        });
    }
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.click();
            }
        }, 5000);
    });
});
</script>
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
                    <?php 
                    $roles = $userModel->getUserRoles($user_id);
                    foreach ($roles as $role): 
                    ?>
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
                <a href="<?= BASE_URL ?>dashboard.php?tab=documents" class="list-group-item list-group-item-action active">
                    <i class="bi bi-file-earmark-text me-2"></i> Документи
                </a>
                <a href="<?= BASE_URL ?>dashboard.php?tab=orders" class="list-group-item list-group-item-action">
                    <i class="bi bi-journal-text me-2"></i> Накази
                </a>
                <a href="<?= BASE_URL ?>dashboard.php?tab=directives" class="list-group-item list-group-item-action">
                    <i class="bi bi-journal-check me-2"></i> Розпорядження
                </a>
                <a href="<?= BASE_URL ?>dashboard.php?tab=letters" class="list-group-item list-group-item-action">
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
                        <?php if ($department_id > 0 && $action === 'view'): ?>
                            <a href="<?= BASE_URL ?>documents.php?dept_id=<?= $department_id ?>" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-arrow-left me-1"></i> Назад до документів відділення
                            </a>
                        <?php elseif ($department_id > 0): ?>
                            <a href="<?= BASE_URL ?>dashboard.php?tab=documents&subtab=department_documents" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-arrow-left me-1"></i> Назад
                            </a>
                        <?php elseif ($action === 'view' && isset($document) && $document['is_archived']): ?>
                            <a href="<?= BASE_URL ?>dashboard.php?tab=archive" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-arrow-left me-1"></i> Назад до архіву
                            </a>
                        <?php elseif ($action === 'view' && isset($_GET['from_letter'])): ?>
                            <a href="<?= BASE_URL ?>letters.php?action=view&id=<?= (int)$_GET['from_letter'] ?>" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-arrow-left me-1"></i> Назад до листа
                            </a>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>dashboard.php?tab=documents" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-arrow-left me-1"></i> Назад
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($action === 'create' || $action === 'edit'): ?>
                        <!-- Форма створення/редагування документа -->
                        <form action="<?= BASE_URL ?>documents.php?action=<?= $action ?><?= $document_id ? '&id=' . $document_id : '' ?>" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <div class="mb-3">
                                <label for="title" class="form-label">Назва документа <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" required 
                                       value="<?= isset($document) ? htmlspecialchars($document['title']) : '' ?>">
                            </div>
                            <div class="mb-3">
                                <label for="content" class="form-label">Опис документа</label>
                                <textarea class="form-control" id="content" name="content" rows="5"><?= isset($document) ? htmlspecialchars($document['content']) : '' ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="department_id" class="form-label">Відділення <span class="text-danger">*</span></label>
                                <select class="form-select" id="department_id" name="department_id" required>
                                    <option value="">Виберіть відділення</option>
                                    <?php
                                    $departments = [];
                                    if ($isDirector || $isDeputyDirector) {
                                        $departments = $departmentModel->getAllDepartments();
                                    } else {
                                        $departments = $userDepartments;
                                    }
                                    foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['id'] ?>" <?= (isset($document) && $document['department_id'] == $dept['id']) || (isset($department_id) && $department_id == $dept['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="document_file" class="form-label">Файл документа <?= $action === 'create' ? '<span class="text-danger">*</span>' : '' ?></label>
                                <?php if (isset($document) && !empty($document['file_path'])): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <div>
                                            <span class="badge bg-success me-2">Поточний файл:</span>
                                            <a href="<?= BASE_URL ?>uploads/<?= $document['file_path'] ?>" target="_blank">
                                                <?= htmlspecialchars($document['title']) ?>.<?= strtolower(pathinfo($document['file_path'], PATHINFO_EXTENSION)) ?>
                                            </a>
                                        </div>
                                        <a href="<?= BASE_URL ?>uploads/<?= $document['file_path'] ?>" download="<?= htmlspecialchars($document['title']) ?>.<?= strtolower(pathinfo($document['file_path'], PATHINFO_EXTENSION)) ?>" class="btn btn-sm btn-outline-success ms-2">
                                            <i class="bi bi-download"></i> Завантажити
                                        </a>
                                    </div>
                                    <div class="form-text mb-2">Завантажте новий файл, якщо хочете замінити поточний.</div>
                                    <input type="file" class="form-control" id="document_file" name="document_file">
                                <?php else: ?>
                                    <input type="file" class="form-control" id="document_file" name="document_file" <?= $action === 'create' ? 'required' : '' ?>>
                                <?php endif; ?>
                                <div class="form-text">
                                    Дозволені формати: PDF, DOC, DOCX, XLSX, XLS, PPT, PPTX, TXT, JPG, JPEG, PNG. Максимальний розмір: 100MB.
                                </div>
                            </div>
                            <div class="mt-4 d-flex justify-content-between">
                                <?php if ($department_id > 0): ?>
                                    <a href="<?= BASE_URL ?>documents.php?dept_id=<?= $department_id ?>" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left me-1"></i> Скасувати
                                    </a>
                                <?php elseif (isset($document) && $document['is_archived']): ?>
                                    <a href="<?= BASE_URL ?>dashboard.php?tab=archive" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left me-1"></i> Скасувати
                                    </a>
                                <?php else: ?>
                                    <a href="<?= BASE_URL ?>dashboard.php?tab=documents" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left me-1"></i> Скасувати
                                    </a>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-save me-1"></i> <?= $action === 'create' ? 'Створити документ' : 'Зберегти зміни' ?>
                                </button>
                            </div>
                        </form>
                    <?php elseif ($action === 'view' && $document): ?>
                        <!-- Перегляд документа -->
                        <div class="row">
                            <div class="col-md-8">
                                <h3><?= htmlspecialchars($document['title']) ?></h3>
                                <p class="text-muted">
                                    <small>
                                        <i class="bi bi-calendar me-1"></i> Створено: <?= (new DateTime($document['created_at']))->format('d.m.Y H:i') ?>
                                        <?php if ($document['created_at'] != $document['updated_at']): ?>
                                            | <i class="bi bi-pencil me-1"></i> Оновлено: <?= (new DateTime($document['updated_at']))->format('d.m.Y H:i') ?>
                                        <?php endif; ?>
                                    </small>
                                </p>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <strong>Опис документа</strong>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($document['content'])): ?>
                                            <em>Опис відсутній</em>
                                        <?php else: ?>
                                            <?= nl2br(htmlspecialchars($document['content'])) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <strong>Інформація</strong>
                                    </div>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item p-2">
                                            <strong>Автор:</strong> 
                                            <div class="d-flex align-items-center mt-1">
                                                <a href="<?= BASE_URL ?>user_profile.php?id=<?= $document['author_id'] ?>" class="text-decoration-none me-2">
                                                    <?php 
                                                    $author = $userModel->getUserById($document['author_id']);
                                                    $author_avatar = !empty($author['avatar']) ? $author['avatar'] : '';
                                                    ?>
                                                    <?php if (!empty($author_avatar)): ?>
                                                        <img src="<?= BASE_URL ?>uploads/<?= $author_avatar ?>" 
                                                             class="img-thumbnail rounded-circle" alt="Аватар" 
                                                             style="width: 30px; height: 30px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded-circle d-flex justify-content-center align-items-center" 
                                                             style="width: 30px; height: 30px;">
                                                            <i class="bi bi-person-fill text-secondary"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </a>
                                                <a href="<?= BASE_URL ?>user_profile.php?id=<?= $document['author_id'] ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($document['author_name']) ?>
                                                </a>
                                            </div>
                                        </li>
                                        <li class="list-group-item p-2">
                                            <strong>Відділення:</strong> <?= htmlspecialchars($document['department_name']) ?>
                                        </li>
                                        <?php if ($document['is_archived']): ?>
                                            <li class="list-group-item p-2 text-danger">
                                                <strong><i class="bi bi-archive me-1"></i> Документ в архіві</strong>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <?php if (!empty($document['file_path'])): ?>
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <strong>Файл документа</strong>
                                        </div>
                                        <div class="card-body">
                                            <?php 
                                            $file_ext = strtolower(pathinfo($document['file_path'], PATHINFO_EXTENSION));
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
                                            <p>
                                                <i class="bi <?= $file_icon ?> me-2 fs-4"></i>
                                                <?= htmlspecialchars($document['title']) ?>.<?= strtolower(pathinfo($document['file_path'], PATHINFO_EXTENSION)) ?>
                                            </p>
                                            <div class="d-grid gap-2">
                                                <button type="button" class="btn btn-success view-document" 
                                                   data-bs-toggle="modal" data-bs-target="#viewDocumentModal" 
                                                   data-document-path="<?= BASE_URL ?>uploads/<?= $document['file_path'] ?>"
                                                   data-document-title="<?= htmlspecialchars($document['title']) ?>"
                                                   data-document-type="<?= strtolower(pathinfo($document['file_path'], PATHINFO_EXTENSION)) ?>">
                                                    <i class="bi bi-eye me-1"></i> Переглянути
                                                </button>
                                                <a href="<?= BASE_URL ?>uploads/<?= $document['file_path'] ?>" class="btn btn-outline-success" download="<?= htmlspecialchars($document['title']) ?>.<?= strtolower(pathinfo($document['file_path'], PATHINFO_EXTENSION)) ?>">
                                                    <i class="bi bi-download me-1"></i> Завантажити
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($document['author_id'] == $user_id): ?>
                                    <div class="d-grid gap-2">
                                        <?php if (!$document['is_archived']): ?>
                                        <a href="<?= BASE_URL ?>documents.php?action=edit&id=<?= $document_id ?>&dept_id=<?= $department_id ?>" class="btn btn-primary">
                                            <i class="bi bi-pencil me-1"></i> Редагувати
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($document['is_archived']): ?>
                                            <a href="<?= BASE_URL ?>documents.php?action=unarchive&id=<?= $document_id ?>&from_archive=1&dept_id=<?= $department_id ?>" class="btn btn-success" 
                                               onclick="return confirm('Ви впевнені, що хочете відновити цей документ з архіву?')">
                                                <i class="bi bi-archive-fill me-1"></i> Відновити з архіву
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= BASE_URL ?>documents.php?action=archive&id=<?= $document_id ?>&dept_id=<?= $department_id ?>" class="btn btn-warning" 
                                               onclick="return confirm('Ви впевнені, що хочете перемістити цей документ в архів?')">
                                                <i class="bi bi-archive me-1"></i> В архів
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= BASE_URL ?>documents.php?action=delete&id=<?= $document_id ?>&dept_id=<?= $department_id ?>" class="btn btn-danger" 
                                           onclick="return confirm('Ви впевнені, що хочете видалити цей документ? Ця дія незворотна.')">
                                            <i class="bi bi-trash me-1"></i> Видалити
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif ($department_id > 0): ?>
                        <!-- Список документів відділення -->
                        <?php
                        $title_filter = isset($_GET['title']) ? $_GET['title'] : '';
                        $author_filter = isset($_GET['author_id']) ? (int)$_GET['author_id'] : 0;
                        $filters = [];
                        if (!empty($title_filter)) {
                            $filters['title'] = $title_filter;
                        }
                        if ($author_filter > 0) {
                            $filters['author_id'] = $author_filter;
                        }
                        $authors = $documentController->getDocumentAuthorsInDepartment($department_id);
                        $documents = $documentController->getDepartmentDocuments($department_id, $limit, $offset, $filters);
                        $total_documents = $documentController->countDepartmentDocuments($department_id, $filters);
                        $total_pages = ceil($total_documents / $limit);
                        ?>
                        <div class="mb-3">
                            <form action="<?= BASE_URL ?>documents.php" method="get" class="row g-3">
                                <input type="hidden" name="dept_id" value="<?= $department_id ?>">
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="Пошук за назвою" name="title" value="<?= htmlspecialchars($title_filter) ?>">
                                        <button class="btn btn-outline-secondary" type="submit">
                                            <i class="bi bi-search"></i>
                                        </button>
                                        <?php if (!empty($title_filter)): ?>
                                        <a href="<?= BASE_URL ?>documents.php?dept_id=<?= $department_id ?><?= isset($_GET['author_id']) ? '&author_id=' . $_GET['author_id'] : '' ?>" class="btn btn-outline-danger">
                                            <i class="bi bi-x-lg"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <select name="author_id" class="form-select" onchange="this.form.submit()">
                                        <option value="">Всі автори</option>
                                        <?php foreach ($authors as $author): ?>
                                            <option value="<?= $author['id'] ?>" <?= $author_filter == $author['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($author['full_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 text-end">
                                    <?php if ($documentController->canCreateDocumentInDepartment($user_id, $department_id)): ?>
                                        <a href="<?= BASE_URL ?>documents.php?action=create&dept_id=<?= $department_id ?>" class="btn btn-success">
                                            <i class="bi bi-plus-lg me-1"></i> Створити документ
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                        <?php if (empty($documents)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i> 
                                <?php if (!empty($title_filter)): ?>
                                    Документи за заданими критеріями не знайдені.
                                <?php else: ?>
                                    У цьому відділенні ще немає документів.
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="document-list">
                                <?php foreach ($documents as $doc): ?>
                                    <div class="document-item card mb-3 card-no-hover">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <?php 
                                                $file_ext = !empty($doc['file_path']) ? strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION)) : '';
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
                                                <div class="document-icon me-3">
                                                    <i class="bi <?= $file_icon ?> text-primary fs-1"></i>
                                                </div>
                                                <div class="document-info flex-grow-1">
                                                    <h5 class="mb-1">
                                                        <a href="<?= BASE_URL ?>documents.php?action=view&id=<?= $doc['id'] ?>&dept_id=<?= $department_id ?>" class="text-decoration-none">
                                                            <?= htmlspecialchars($doc['title']) ?>
                                                        </a>
                                                    </h5>
                                                    <div class="text-muted small mb-2">
                                                        <span class="me-2"><i class="bi bi-person me-1"></i> <a href="<?= BASE_URL ?>user_profile.php?id=<?= $doc['author_id'] ?>" class="text-decoration-none text-muted"><?= htmlspecialchars($doc['author_name']) ?></a></span>
                                                        <span><i class="bi bi-calendar me-1"></i> <?= (new DateTime($doc['created_at']))->format('d.m.Y') ?></span>
                                                    </div>
                                                    <div class="document-description">
                                                        <?php if (empty($doc['content'])): ?>
                                                            <em class="text-muted">Немає опису</em>
                                                        <?php else: ?>
                                                            <?= mb_substr(htmlspecialchars($doc['content']), 0, 100) . (mb_strlen($doc['content']) > 100 ? '...' : '') ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="document-actions">
                                                    <div class="btn-group">
                                                        <?php if (!empty($doc['file_path'])): ?>
                                                        <a href="<?= BASE_URL ?>uploads/<?= $doc['file_path'] ?>" download="<?= htmlspecialchars($doc['title']) ?>.<?= strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION)) ?>" class="btn btn-sm btn-outline-success" title="Завантажити">
                                                            <i class="bi bi-download"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                        <a href="<?= BASE_URL ?>documents.php?action=view&id=<?= $doc['id'] ?>&dept_id=<?= $department_id ?>" class="btn btn-sm btn-outline-primary" title="Переглянути">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <?php if ($doc['author_id'] == $user_id): ?>
                                                        <a href="<?= BASE_URL ?>documents.php?action=edit&id=<?= $doc['id'] ?>&dept_id=<?= $department_id ?>" class="btn btn-sm btn-outline-secondary" title="Редагувати">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="<?= BASE_URL ?>documents.php?action=archive&id=<?= $doc['id'] ?>&dept_id=<?= $department_id ?>" class="btn btn-sm btn-outline-warning" 
                                                           onclick="return confirm('Ви впевнені, що хочете перемістити цей документ в архів?')" title="В архів">
                                                            <i class="bi bi-archive"></i>
                                                        </a>
                                                        <a href="<?= BASE_URL ?>documents.php?action=delete&id=<?= $doc['id'] ?>&dept_id=<?= $department_id ?>" class="btn btn-sm btn-outline-danger" 
                                                           onclick="return confirm('Ви впевнені, що хочете видалити цей документ? Ця дія незворотна.')" title="Видалити">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                        <?php endif; ?>
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
                                            <a class="page-link" href="<?= BASE_URL ?>documents.php?dept_id=<?= $department_id ?>&page=<?= $page - 1 ?><?= !empty($title_filter) ? '&title=' . urlencode($title_filter) : '' ?><?= $author_filter > 0 ? '&author_id=' . $author_filter : '' ?>">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                <a class="page-link" href="<?= BASE_URL ?>documents.php?dept_id=<?= $department_id ?>&page=<?= $i ?><?= !empty($title_filter) ? '&title=' . urlencode($title_filter) : '' ?><?= $author_filter > 0 ? '&author_id=' . $author_filter : '' ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="<?= BASE_URL ?>documents.php?dept_id=<?= $department_id ?>&page=<?= $page + 1 ?><?= !empty($title_filter) ? '&title=' . urlencode($title_filter) : '' ?><?= $author_filter > 0 ? '&author_id=' . $author_filter : '' ?>">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Модальне вікно для перегляду документа -->
<div class="modal fade" id="viewDocumentModal" tabindex="-1" aria-labelledby="viewDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="viewDocumentModalLabel">
                    <i class="bi bi-file-earmark-text me-2"></i> Перегляд документа
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="documentViewerContainer" class="document-viewer-container">
                    <!-- Вміст документа буде завантажено динамічно -->
                    <div class="text-center p-5" id="documentLoading">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Завантаження...</span>
                        </div>
                        <p class="mt-3">Завантаження документа...</p>
                    </div>
                    <div id="documentViewerContent" style="display: none;">
                        <!-- PDF та інші документи будуть відображатися тут -->
                    </div>
                    <div id="documentUnsupportedMessage" class="alert alert-warning m-4" style="display: none;">
                        <i class="bi bi-exclamation-triangle me-2"></i> Формат документа не підтримується для перегляду в браузері. Будь ласка, завантажте документ для перегляду.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" class="btn btn-success" id="downloadDocumentBtn" download>
                    <i class="bi bi-download me-1"></i> Завантажити
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрити</button>
            </div>
        </div>
    </div>
</div>
<style>
.document-viewer-container {
    min-height: 500px;
    max-height: 70vh;
    overflow: auto;
}
.document-viewer-container iframe, 
.document-viewer-container embed,
.document-viewer-container object {
    width: 100%;
    height: 100%;
    min-height: 500px;
}
.document-viewer-container img {
    max-width: 100%;
    max-height: 70vh;
    display: block;
    margin: 0 auto;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const viewButtons = document.querySelectorAll('.view-document');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const documentPath = this.getAttribute('data-document-path');
            const documentTitle = this.getAttribute('data-document-title');
            const documentType = this.getAttribute('data-document-type');
            document.getElementById('viewDocumentModalLabel').innerHTML = 
                `<i class="bi bi-file-earmark-text me-2"></i> ${documentTitle}`;
            const downloadBtn = document.getElementById('downloadDocumentBtn');
            downloadBtn.href = documentPath;
            downloadBtn.setAttribute('download', `${documentTitle}.${documentType}`);
            document.getElementById('documentLoading').style.display = 'block';
            document.getElementById('documentViewerContent').style.display = 'none';
            document.getElementById('documentUnsupportedMessage').style.display = 'none';
            if (documentType === 'pdf') {
                const viewer = document.createElement('embed');
                viewer.setAttribute('src', `${documentPath}#toolbar=0&navpanes=0`);
                viewer.setAttribute('type', 'application/pdf');
                document.getElementById('documentViewerContent').innerHTML = '';
                document.getElementById('documentViewerContent').appendChild(viewer);
                document.getElementById('documentLoading').style.display = 'none';
                document.getElementById('documentViewerContent').style.display = 'block';
            } else if (['jpg', 'jpeg', 'png'].includes(documentType)) {
                const img = document.createElement('img');
                img.setAttribute('src', documentPath);
                img.setAttribute('alt', documentTitle);
                document.getElementById('documentViewerContent').innerHTML = '';
                document.getElementById('documentViewerContent').appendChild(img);
                document.getElementById('documentLoading').style.display = 'none';
                document.getElementById('documentViewerContent').style.display = 'block';
            } else if (documentType === 'txt') {
                fetch(documentPath)
                    .then(response => response.text())
                    .then(text => {
                        const pre = document.createElement('pre');
                        pre.style.padding = '20px';
                        pre.style.whiteSpace = 'pre-wrap';
                        pre.textContent = text;
                        document.getElementById('documentViewerContent').innerHTML = '';
                        document.getElementById('documentViewerContent').appendChild(pre);
                        document.getElementById('documentLoading').style.display = 'none';
                        document.getElementById('documentViewerContent').style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Error loading text file:', error);
                        document.getElementById('documentLoading').style.display = 'none';
                        document.getElementById('documentUnsupportedMessage').style.display = 'block';
                    });
            } else {
                document.getElementById('documentLoading').style.display = 'none';
                document.getElementById('documentUnsupportedMessage').style.display = 'block';
            }
        });
    });
});
</script>
<?php
require_once '../app/views/includes/footer.php';
?>
<!-- Підключення скрипту для мобільної бічної панелі -->
<script src="<?= BASE_URL ?>js/mobile-sidebar.js"></script>