<?php

require_once '../config/config.php';
require_once '../app/models/User.php';
require_once '../app/models/Department.php';
require_once '../app/models/Document.php';


if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
    exit;
}


$userModel = new User();
$user_id = $_SESSION['user_id'];
$user = $userModel->getUserById($user_id);
$roles = $userModel->getUserRoles($user_id);


if ($userModel->isAdmin($user_id)) {
    redirect('admin/dashboard.php');
    exit;
}


$isDirector = $userModel->hasRole($user_id, 2);
$isDeputyDirector = $userModel->hasRole($user_id, 3);
$isDepartmentHead = $userModel->hasRole($user_id, 8);
$isSecretary = $userModel->hasRole($user_id, 5);
$isTeacher = $userModel->hasRole($user_id, 4);
$isAccountant = $userModel->hasRole($user_id, 6);


$firstLogin = !isset($_SESSION['has_logged_in']);
if ($firstLogin) {
    $_SESSION['has_logged_in'] = true;
}


$departments = [];
if (!$isDirector) {
    $departmentModel = new Department();
    $departments = $departmentModel->getUserDepartments($user_id);
}


if (isset($_GET['tab']) && $_GET['tab'] === 'letters' && isset($_GET['action'])) {
    $action = $_GET['action'];
    

    if ($action === 'delete' && isset($_GET['id'])) {
        $letter_id = (int)$_GET['id'];
        $is_sender = isset($_GET['is_sender']) ? (bool)$_GET['is_sender'] : true;
        

        if (!isset($letterModel)) {
            require_once '../app/models/Letter.php';
            $letterModel = new Letter();
        }
        

        $result = $letterModel->markLetterAsDeleted($letter_id, $user_id, $is_sender);
        

        $redirect_tab = $is_sender ? 'outgoing' : 'incoming';
        

        if ($result) {
            $_SESSION['success_message'] = 'Лист успішно видалено!';
        } else {
            $_SESSION['error_message'] = 'Помилка при видаленні листа. Спробуйте ще раз.';
        }
        
        redirect("dashboard.php?tab=letters&subtab={$redirect_tab}");
        exit;
    }
}


$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';
$active_subtab = isset($_GET['subtab']) ? $_GET['subtab'] : '';


$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}


if ($active_tab == 'documents' && isset($_GET['action']) && $_GET['action'] === 'create') {
    redirect("documents.php?action=create");
    exit;
}


if ($active_tab == 'documents' && $active_subtab == 'department_documents' && isset($_GET['dept_id'])) {
    $dept_id = (int)$_GET['dept_id'];
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    if ($dept_id > 0 || $action == 'create') {
        redirect("documents.php?dept_id={$dept_id}&action={$action}");
        exit;
    }
}

require_once '../app/views/includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>css/mobile-sidebar.css">

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
    
    .avatar-container {
        width: 150px;
        height: 150px;
        margin: 0 auto;
        cursor: pointer;
        overflow: hidden;
    }
    
    .avatar-overlay {
        background-color: rgba(0, 0, 0, 0.5);
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .avatar-overlay i {
        color: white;
        font-size: 2rem;
    }
    
    .avatar-container:hover .avatar-overlay {
        opacity: 1;
    }
</style>

<div class="container-fluid p-0">

    <button class="mobile-toggle-sidebar d-md-none" id="toggleSidebar">
        <i class="bi bi-list fs-4"></i>
    </button>
    

    <div class="mobile-overlay" id="sidebarOverlay"></div>
    
    <div class="row g-0">

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
                <a href="<?= BASE_URL ?>dashboard.php?tab=profile" class="list-group-item list-group-item-action <?= $active_tab == 'profile' ? 'active' : '' ?>">
                    <i class="bi bi-person-vcard me-2"></i> Мій кабінет
                </a>
                
                <a href="<?= BASE_URL ?>dashboard.php?tab=documents" class="list-group-item list-group-item-action <?= $active_tab == 'documents' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-text me-2"></i> Документи
                </a>
                
                <a href="<?= BASE_URL ?>dashboard.php?tab=orders" class="list-group-item list-group-item-action <?= $active_tab == 'orders' ? 'active' : '' ?>">
                    <i class="bi bi-journal-text me-2"></i> Накази
                </a>
                
                                <a href="<?= BASE_URL ?>dashboard.php?tab=directives" class="list-group-item list-group-item-action <?= $active_tab == 'directives' ? 'active' : '' ?>">
                        <i class="bi bi-journal-check me-2"></i> Розпорядження
                    </a>
                
                <a href="<?= BASE_URL ?>dashboard.php?tab=letters" class="list-group-item list-group-item-action <?= $active_tab == 'letters' ? 'active' : '' ?>">
                    <i class="bi bi-envelope me-2"></i> Листи
                    <?php 

                    require_once '../app/models/Letter.php';
                    if (isset($letterModel) || ($letterModel = new Letter())) {
                        $unread_count = $letterModel->countUnreadLetters($user_id);
                        if ($unread_count > 0): 
                    ?>
                    <span class="badge rounded-pill bg-danger float-end"><?= $unread_count ?></span>
                    <?php endif; } ?>
                </a>
                
                <a href="<?= BASE_URL ?>dashboard.php?tab=archive" class="list-group-item list-group-item-action <?= $active_tab == 'archive' ? 'active' : '' ?>">
                    <i class="bi bi-archive me-2"></i> Архів
                </a>
                
                <a href="<?= BASE_URL ?>dashboard.php?tab=settings" class="list-group-item list-group-item-action <?= $active_tab == 'settings' ? 'active' : '' ?>">
                    <i class="bi bi-person-gear me-2"></i> Налаштування профілю
                </a>
                
                <a href="<?= BASE_URL ?>logout.php" class="list-group-item list-group-item-action text-danger">
                    <i class="bi bi-box-arrow-right me-2"></i> Вихід
                </a>
            </div>
        </div>
        

        <div class="col-md-9 content-area">
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4">
                    <i class="bi bi-check-circle me-2"></i> <?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4">
                    <i class="bi bi-exclamation-triangle me-2"></i> <?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($active_tab == 'profile'): ?>
            <div class="card border-dark shadow-sm mb-4 card-no-hover">
                <div class="card-header bg-white">
                    <h4 class="mb-0 text-success"><i class="bi bi-person-vcard me-2"></i> Мій кабінет</h4>
                </div>
                <div class="card-body">
                    <?php if ($firstLogin): ?>
                    <div class="alert alert-success">
                        <h5 class="alert-heading"><i class="bi bi-check-circle me-2"></i> Ласкаво просимо, <?= htmlspecialchars($user['full_name']) ?>!</h5>
                        <p>Ви успішно увійшли в систему електронного документообігу NCNTU-EDMS.</p>
                    </div>
                    <?php endif; ?>
                    
                    <h5 class="border-bottom border-success pb-2 mb-4">Персональна інформація</h5>
                    <div class="row mb-4 align-items-center">
                        <div class="col-md-3 text-center">
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="<?= BASE_URL ?>uploads/<?= $user['avatar'] ?>" class="img-thumbnail rounded-circle" alt="Аватар" style="width: 150px; height: 150px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-light rounded-circle d-flex justify-content-center align-items-center mx-auto" style="width: 150px; height: 150px;">
                                    <i class="bi bi-person-fill text-secondary fs-1"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-9">
                            <div class="card card-no-hover mb-3">
                                <div class="card-body p-4">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-person-fill text-success me-3 fs-4"></i>
                                                <div>
                                                    <div class="text-muted small">Повне ім'я</div>
                                                    <div class="fw-bold"><?= htmlspecialchars($user['full_name']) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-person-badge text-success me-3 fs-4"></i>
                                                <div>
                                                    <div class="text-muted small">Логін</div>
                                                    <div class="fw-bold"><?= htmlspecialchars($user['login']) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-envelope-fill text-success me-3 fs-4"></i>
                                                <div>
                                                    <div class="text-muted small">Email</div>
                                                    <div class="fw-bold"><?= htmlspecialchars($user['email']) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-telephone-fill text-success me-3 fs-4"></i>
                                                <div>
                                                    <div class="text-muted small">Телефон</div>
                                                    <div class="fw-bold"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : '<em class="text-muted">Не вказано</em>' ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-geo-alt-fill text-success me-3 fs-4"></i>
                                                <div>
                                                    <div class="text-muted small">Адреса</div>
                                                    <div class="fw-bold"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : '<em class="text-muted">Не вказано</em>' ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-calendar-check text-success me-3 fs-4"></i>
                                                <div>
                                                    <div class="text-muted small">Дата реєстрації</div>
                                                    <div class="fw-bold"><?= (new DateTime($user['created_at']))->format('d.m.Y H:i') ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            

                        </div>
                    </div>
                    
                    <?php if (!$isDirector && !empty($departments)): ?>
                    <h5 class="border-bottom border-success pb-2 mb-3">Мої відділення</h5>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="d-flex flex-wrap gap-3">
                                <?php foreach ($departments as $dept): ?>
                                    <div class="card card-no-hover">
                                        <div class="card-body d-flex align-items-center p-3">
                                            <i class="bi bi-building text-success me-3 fs-4"></i>
                                            <div>
                                                <h6 class="mb-0"><?= htmlspecialchars($dept['name']) ?></h6>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($active_tab == 'documents'): ?>
            <div class="card border-dark shadow-sm mb-4 card-no-hover">
                <div class="card-header bg-white">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <a class="nav-link <?= empty($active_subtab) || $active_subtab == 'my_documents' ? 'active' : '' ?>" href="<?= BASE_URL ?>dashboard.php?tab=documents&subtab=my_documents">
                                Мої документи
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $active_subtab == 'department_documents' ? 'active' : '' ?>" href="<?= BASE_URL ?>dashboard.php?tab=documents&subtab=department_documents">
                                Документи відділень
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <?php
                    $departmentModel = new Department();
                    
                    $userAllDepartments = $departmentModel->getUserDepartments($user_id);
                    $canCreateDocuments = $isDirector || $isDeputyDirector || !empty($userAllDepartments);

                    if (!$canCreateDocuments && !$isDirector && !$isDeputyDirector): 
                    ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Увага!</strong> Ви не можете створювати документи, оскільки не прив'язані до жодного відділення.
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <div class="col-md-6">
                            <?php if (empty($active_subtab) || $active_subtab == 'my_documents'): ?>
                            <?php
                            $userDepartmentsForFilter = $departmentModel->getUserDepartmentsForDocumentFilter($user_id);
                            ?>
                            <form action="<?= BASE_URL ?>dashboard.php" method="get" class="row g-3">
                                <input type="hidden" name="tab" value="documents">
                                <input type="hidden" name="subtab" value="my_documents">
                                <div class="col-md-8">
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="Пошук за назвою" name="title" value="<?= isset($_GET['title']) ? htmlspecialchars($_GET['title']) : '' ?>">
                                        <button class="btn btn-outline-secondary" type="submit">
                                            <i class="bi bi-search"></i>
                                        </button>
                                        <?php if (isset($_GET['title']) && !empty($_GET['title'])): ?>
                                        <a href="<?= BASE_URL ?>dashboard.php?tab=documents&subtab=my_documents<?= isset($_GET['department_id']) ? '&department_id=' . $_GET['department_id'] : '' ?>" class="btn btn-outline-danger">
                                            <i class="bi bi-x-lg"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <select name="department_id" class="form-select" onchange="this.form.submit()">
                                        <option value="">Всі відділення</option>
                                        <?php foreach ($userDepartmentsForFilter as $dept): ?>
                                            <option value="<?= $dept['id'] ?>" <?= isset($_GET['department_id']) && $_GET['department_id'] == $dept['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($dept['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php if ($canCreateDocuments): ?>
                        <a href="<?= BASE_URL ?>documents.php?action=create" class="btn btn-success btn-create-document">
                            <i class="bi bi-plus-lg me-1"></i> Створити документ
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($active_subtab) || $active_subtab == 'my_documents'): ?>
                        <?php
                        require_once '../app/controllers/document_controller.php';
                        $documentController = new DocumentController();
                        
                        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                        $page = $page < 1 ? 1 : $page;
                        $limit = 10;
                        $offset = ($page - 1) * $limit;
                        
                        $filters = ['is_archived' => 0];
                        
                        if (isset($_GET['title']) && !empty($_GET['title'])) {
                            $filters['title'] = $_GET['title'];
                        }
                        
                        if (isset($_GET['department_id']) && !empty($_GET['department_id'])) {
                            $filters['department_id'] = (int)$_GET['department_id'];
                        }
                        
                        $documents = $documentController->getUserDocuments($user_id, $limit, $offset, $filters);
                        $total_documents = $documentController->countUserDocuments($user_id, $filters);
                        $total_pages = ceil($total_documents / $limit);
                        
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
                        ?>
                        
                        <?php if ($success_message): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i> <?= $success_message ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i> <?= $error_message ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (empty($documents)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i> Наразі у вас немає документів.
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
                                                        <a href="<?= BASE_URL ?>documents.php?action=view&id=<?= $doc['id'] ?>" class="text-decoration-none">
                                                            <?= htmlspecialchars($doc['title']) ?>
                                                        </a>
                                                    </h5>
                                                    <div class="text-muted small mb-2">
                                                        <span class="me-2"><i class="bi bi-building me-1"></i> <?= htmlspecialchars($doc['department_name']) ?></span>
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
                                                        <a href="<?= BASE_URL ?>documents.php?action=view&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-primary" title="Переглянути">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="<?= BASE_URL ?>documents.php?action=edit&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Редагувати">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="<?= BASE_URL ?>documents.php?action=archive&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-warning" 
                                                           onclick="return confirm('Ви впевнені, що хочете перемістити цей документ в архів?')" title="В архів">
                                                            <i class="bi bi-archive"></i>
                                                        </a>
                                                        <a href="<?= BASE_URL ?>documents.php?action=delete&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-danger" 
                                                           onclick="return confirm('Ви впевнені, що хочете видалити цей документ? Ця дія незворотна.')" title="Видалити">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Навігація по сторінках">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="<?= BASE_URL ?>dashboard.php?tab=documents&subtab=my_documents&page=<?= $page - 1 ?><?= isset($_GET['title']) && !empty($_GET['title']) ? '&title=' . urlencode($_GET['title']) : '' ?><?= isset($_GET['department_id']) && !empty($_GET['department_id']) ? '&department_id=' . $_GET['department_id'] : '' ?>">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                <a class="page-link" href="<?= BASE_URL ?>dashboard.php?tab=documents&subtab=my_documents&page=<?= $i ?><?= isset($_GET['title']) && !empty($_GET['title']) ? '&title=' . urlencode($_GET['title']) : '' ?><?= isset($_GET['department_id']) && !empty($_GET['department_id']) ? '&department_id=' . $_GET['department_id'] : '' ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="<?= BASE_URL ?>dashboard.php?tab=documents&subtab=my_documents&page=<?= $page + 1 ?><?= isset($_GET['title']) && !empty($_GET['title']) ? '&title=' . urlencode($_GET['title']) : '' ?><?= isset($_GET['department_id']) && !empty($_GET['department_id']) ? '&department_id=' . $_GET['department_id'] : '' ?>">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($active_subtab == 'department_documents'): ?>
                            <?php 
                            $departmentModel = new Department();
                            $departments_hierarchy = $departmentModel->getDepartmentsHierarchy();
                            
                            $userDepartments = $departmentModel->getUserDepartments($user_id);
                            $userDepartmentIds = array_column($userDepartments, 'id');
                            
                            require_once '../app/views/components/department_hierarchy.php';
                            ?>
                            
                            <?php if (empty($departments_hierarchy)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i> В системі ще немає відділень.
                                </div>
                            <?php else: ?>
                                <div class="departments-container">
                                    <?php
                                    foreach ($departments_hierarchy as $dept) {
                                        renderDepartmentTree($dept, $isDirector, $isDeputyDirector, $userDepartmentIds);
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($active_tab == 'orders'): ?>
            <div class="card border-dark shadow-sm mb-4 card-no-hover">
                <div class="card-header bg-white">
                    <h4 class="mb-0 text-success"><i class="bi bi-journal-text me-2"></i> Накази</h4>
                </div>
                <div class="card-body">
                    <?php
                    if (isset($_SESSION['dashboard_success'])) {
                        echo '<div class="alert alert-success alert-dismissible fade show mb-4">
                                <i class="bi bi-check-circle me-2"></i> ' . $_SESSION['dashboard_success'] . '
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
                        unset($_SESSION['dashboard_success']);
                    }
                    
                    if (isset($_SESSION['dashboard_error'])) {
                        echo '<div class="alert alert-danger alert-dismissible fade show mb-4">
                                <i class="bi bi-exclamation-triangle me-2"></i> ' . $_SESSION['dashboard_error'] . '
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
                        unset($_SESSION['dashboard_error']);
                    }

                    require_once '../app/controllers/document_controller.php';
                    $documentController = new DocumentController();
                    
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $page = $page < 1 ? 1 : $page;
                    $limit = 10;
                    $offset = ($page - 1) * $limit;
                    
                    $filters = [
                        'type_id' => 2,
                        'is_archived' => 0 
                    ];
                    
                    if (isset($_GET['title']) && !empty($_GET['title'])) {
                        $filters['title'] = $_GET['title'];
                    }
                    
                    if (isset($_GET['department_id']) && !empty($_GET['department_id'])) {
                        $filters['department_id'] = (int)$_GET['department_id'];
                    }
                    
                    $documents = $documentController->documentModel->getAllDocuments($limit, $offset, $filters);
                    $total_documents = $documentController->documentModel->countDocuments($filters);
                    $total_pages = ceil($total_documents / $limit);
                    
                    $departmentModel = new Department();
                    $departments = $departmentModel->getAllDepartments();
                    ?>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <div class="col-md-8">
                            <form action="<?= BASE_URL ?>dashboard.php" method="get" class="row g-3">
                                <input type="hidden" name="tab" value="orders">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="Пошук за назвою" name="title" value="<?= isset($_GET['title']) ? htmlspecialchars($_GET['title']) : '' ?>">
                                        <button class="btn btn-outline-secondary" type="submit">
                                            <i class="bi bi-search"></i>
                                        </button>
                                        <?php if (isset($_GET['title']) && !empty($_GET['title'])): ?>
                                        <a href="<?= BASE_URL ?>dashboard.php?tab=orders<?= isset($_GET['department_id']) ? '&department_id=' . $_GET['department_id'] : '' ?>" class="btn btn-outline-danger">
                                            <i class="bi bi-x-lg"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <select name="department_id" class="form-select" onchange="this.form.submit()">
                                        <option value="">Всі відділення</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= $dept['id'] ?>" <?= isset($_GET['department_id']) && $_GET['department_id'] == $dept['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($dept['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                        </div>
                        <?php if ($isDirector || $isDeputyDirector): ?>
                        <a href="<?= BASE_URL ?>orders.php?action=create" class="btn btn-success">
                            <i class="bi bi-plus-lg me-1"></i> Створити наказ
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($documents)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i> Наразі немає наказів у системі.
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
                                                <i class="bi <?= $file_icon ?> text-danger fs-1"></i>
                                            </div>
                                            <div class="document-info flex-grow-1">
                                                <h5 class="mb-1">
                                                    <a href="<?= BASE_URL ?>orders.php?action=view&id=<?= $doc['id'] ?>" class="text-decoration-none">
                                                        <?= htmlspecialchars($doc['title']) ?>
                                                    </a>
                                                </h5>
                                                <div class="text-muted small mb-2">
                                                    <span class="me-2"><i class="bi bi-person me-1"></i> <a href="<?= BASE_URL ?>user_profile.php?id=<?= $doc['author_id'] ?>" class="text-decoration-none text-muted"><?= htmlspecialchars($doc['author_name']) ?></a></span>
                                                    <span class="me-2"><i class="bi bi-building me-1"></i> <?= htmlspecialchars($doc['department_name']) ?></span>
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
                                                    <a href="<?= BASE_URL ?>orders.php?action=view&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-primary" title="Переглянути">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if (($doc['author_id'] == $user_id) && ($isDirector || $isDeputyDirector)): ?>
                                                    <a href="<?= BASE_URL ?>orders.php?action=edit&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Редагувати">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="<?= BASE_URL ?>orders.php?action=archive&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-warning" 
                                                       onclick="return confirm('Ви впевнені, що хочете перемістити цей наказ в архів?')" title="В архів">
                                                        <i class="bi bi-archive"></i>
                                                    </a>
                                                    <a href="<?= BASE_URL ?>orders.php?action=delete&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-danger" 
                                                       onclick="return confirm('Ви впевнені, що хочете видалити цей наказ? Ця дія незворотна.')" title="Видалити">
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
                        
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Навігація по сторінках">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= BASE_URL ?>dashboard.php?tab=orders&page=<?= $page - 1 ?><?= isset($_GET['title']) && !empty($_GET['title']) ? '&title=' . urlencode($_GET['title']) : '' ?><?= isset($_GET['department_id']) && !empty($_GET['department_id']) ? '&department_id=' . $_GET['department_id'] : '' ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="<?= BASE_URL ?>dashboard.php?tab=orders&page=<?= $i ?><?= isset($_GET['title']) && !empty($_GET['title']) ? '&title=' . urlencode($_GET['title']) : '' ?><?= isset($_GET['department_id']) && !empty($_GET['department_id']) ? '&department_id=' . $_GET['department_id'] : '' ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= BASE_URL ?>dashboard.php?tab=orders&page=<?= $page + 1 ?><?= isset($_GET['title']) && !empty($_GET['title']) ? '&title=' . urlencode($_GET['title']) : '' ?><?= isset($_GET['department_id']) && !empty($_GET['department_id']) ? '&department_id=' . $_GET['department_id'] : '' ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($active_tab == 'directives'): ?>
            <div class="card border-dark shadow-sm mb-4 card-no-hover">
                <div class="card-header bg-white">
                    <h4 class="mb-0 text-success"><i class="bi bi-journal-check me-2"></i> Розпорядження</h4>
                </div>
                <div class="card-body">
                    <?php
                    if (isset($_SESSION['dashboard_success'])) {
                        echo '<div class="alert alert-success alert-dismissible fade show mb-4">
                                <i class="bi bi-check-circle me-2"></i> ' . $_SESSION['dashboard_success'] . '
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
                        unset($_SESSION['dashboard_success']);
                    }
                    
                    if (isset($_SESSION['dashboard_error'])) {
                        echo '<div class="alert alert-danger alert-dismissible fade show mb-4">
                                <i class="bi bi-exclamation-triangle me-2"></i> ' . $_SESSION['dashboard_error'] . '
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
                        unset($_SESSION['dashboard_error']);
                    }
                    
                    require_once '../app/controllers/document_controller.php';
                    $documentController = new DocumentController();
                    
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $page = $page < 1 ? 1 : $page;
                    $limit = 10;
                    $offset = ($page - 1) * $limit;
                    
                    $filters = [
                        'type_id' => 3,
                        'is_archived' => 0 
                    ];
                    
                    if (isset($_GET['title']) && !empty($_GET['title'])) {
                        $filters['title'] = $_GET['title'];
                    }
                    
                    if (isset($_GET['department_id']) && !empty($_GET['department_id'])) {
                        $filters['department_id'] = (int)$_GET['department_id'];
                    }
                    
                    $documents = $documentController->documentModel->getAllDocuments($limit, $offset, $filters);
                    $total_documents = $documentController->documentModel->countDocuments($filters);
                    $total_pages = ceil($total_documents / $limit);
                    
                    $departmentModel = new Department();
                    $departments = $departmentModel->getAllDepartments();
                    ?>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <div class="col-md-8">
                            <form action="<?= BASE_URL ?>dashboard.php" method="get" class="row g-3">
                                <input type="hidden" name="tab" value="directives">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="Пошук за назвою" name="title" value="<?= isset($_GET['title']) ? htmlspecialchars($_GET['title']) : '' ?>">
                                        <button class="btn btn-outline-secondary" type="submit">
                                            <i class="bi bi-search"></i>
                                        </button>
                                        <?php if (isset($_GET['title']) && !empty($_GET['title'])): ?>
                                        <a href="<?= BASE_URL ?>dashboard.php?tab=directives<?= isset($_GET['department_id']) ? '&department_id=' . $_GET['department_id'] : '' ?>" class="btn btn-outline-danger">
                                            <i class="bi bi-x-lg"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <select name="department_id" class="form-select" onchange="this.form.submit()">
                                        <option value="">Всі відділення</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= $dept['id'] ?>" <?= isset($_GET['department_id']) && $_GET['department_id'] == $dept['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($dept['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                        </div>
                        <?php if ($isDirector || $isDeputyDirector): ?>
                        <a href="<?= BASE_URL ?>directives.php?action=create" class="btn btn-success">
                            <i class="bi bi-plus-lg me-1"></i> Створити розпорядження
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($documents)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i> Наразі немає розпоряджень у системі.
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
                                                <?php if ($doc['type_id'] == 3): ?>
                                                <i class="bi <?= $file_icon ?> fs-1" style="color: #fd7e14;"></i>
                                                <?php else: ?>
                                                <i class="bi <?= $file_icon ?> <?= $icon_color ?> fs-1"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="document-info flex-grow-1">
                                                <h5 class="mb-1">
                                                    <?php if ($doc['type_id'] == 3): ?>
                                                    <a href="<?= BASE_URL ?>directives.php?action=view&id=<?= $doc['id'] ?>" class="text-decoration-none">
                                                        <?= htmlspecialchars($doc['title']) ?>
                                                    </a>
                                                    <?php elseif ($doc['type_id'] == 2): // Якщо це наказ ?>
                                                    <a href="<?= BASE_URL ?>orders.php?action=view&id=<?= $doc['id'] ?>" class="text-decoration-none">
                                                        <?= htmlspecialchars($doc['title']) ?>
                                                    </a>
                                                    <?php else: // Для інших типів документів ?>
                                                    <a href="<?= BASE_URL ?>documents.php?action=view&id=<?= $doc['id'] ?>" class="text-decoration-none">
                                                        <?= htmlspecialchars($doc['title']) ?>
                                                    </a>
                                                    <?php endif; ?>
                                                </h5>
                                                <div class="text-muted small mb-2">
                                                    <span class="me-2"><i class="bi bi-person me-1"></i> <a href="<?= BASE_URL ?>user_profile.php?id=<?= $doc['author_id'] ?>" class="text-decoration-none text-muted"><?= htmlspecialchars($doc['author_name']) ?></a></span>
                                                    <span class="me-2"><i class="bi bi-building me-1"></i> <?= htmlspecialchars($doc['department_name']) ?></span>
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
                                                    <?php if ($doc['type_id'] == 3): // Якщо це розпорядження ?>
                                                    <a href="<?= BASE_URL ?>directives.php?action=view&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-primary" title="Переглянути">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php elseif ($doc['type_id'] == 2): // Якщо це наказ ?>
                                                    <a href="<?= BASE_URL ?>orders.php?action=view&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-primary" title="Переглянути">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php else: // Для інших типів документів ?>
                                                    <a href="<?= BASE_URL ?>documents.php?action=view&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-primary" title="Переглянути">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($doc['author_id'] == $user_id): ?>
                                                    <a href="<?= BASE_URL ?>directives.php?action=edit&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Редагувати">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="<?= BASE_URL ?>directives.php?action=archive&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-warning" 
                                                       onclick="return confirm('Ви впевнені, що хочете архівувати це розпорядження?')" title="Архівувати">
                                                        <i class="bi bi-archive"></i>
                                                    </a>
                                                    
                                                    <a href="<?= BASE_URL ?>directives.php?action=delete&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-danger" 
                                                       onclick="return confirm('Ви впевнені, що хочете видалити це розпорядження? Ця дія незворотна.')" title="Видалити">
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
                        
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Навігація по сторінках">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= BASE_URL ?>dashboard.php?tab=directives&page=<?= $page - 1 ?><?= isset($_GET['title']) ? '&title=' . urlencode($_GET['title']) : '' ?><?= isset($_GET['department_id']) && !empty($_GET['department_id']) ? '&department_id=' . $_GET['department_id'] : '' ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="<?= BASE_URL ?>dashboard.php?tab=directives&page=<?= $i ?><?= isset($_GET['title']) ? '&title=' . urlencode($_GET['title']) : '' ?><?= isset($_GET['department_id']) && !empty($_GET['department_id']) ? '&department_id=' . $_GET['department_id'] : '' ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= BASE_URL ?>dashboard.php?tab=directives&page=<?= $page + 1 ?><?= isset($_GET['title']) ? '&title=' . urlencode($_GET['title']) : '' ?><?= isset($_GET['department_id']) && !empty($_GET['department_id']) ? '&department_id=' . $_GET['department_id'] : '' ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($active_tab == 'letters'): ?>
            <div class="card border-dark shadow-sm mb-4 card-no-hover">
                <div class="card-header bg-white">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <a class="nav-link <?= empty($active_subtab) || $active_subtab == 'incoming' ? 'active' : '' ?>" href="<?= BASE_URL ?>dashboard.php?tab=letters&subtab=incoming">
                                Вхідні
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $active_subtab == 'outgoing' ? 'active' : '' ?>" href="<?= BASE_URL ?>dashboard.php?tab=letters&subtab=outgoing">
                                Вихідні
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <?php
                    
                    require_once '../app/models/Letter.php';
                    $letterModel = new Letter();
                    
                    
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $page = $page < 1 ? 1 : $page;
                    $limit = 10;
                    $offset = ($page - 1) * $limit;
                    
                    
                    $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
                    
                    
                    $read_filter = 'all';
                    if ((empty($active_subtab) || $active_subtab == 'incoming') && isset($_GET['read_filter']) && in_array($_GET['read_filter'], ['all', 'read', 'unread'])) {
                        $read_filter = $_GET['read_filter'];
                    }
                    
                    
                    $star_filter = 'all';
                    if (isset($_GET['star_filter']) && in_array($_GET['star_filter'], ['all', 'starred', 'unstarred'])) {
                        $star_filter = $_GET['star_filter'];
                    }
                    
                    
                    if (!empty($search_query)) {
                        if (empty($active_subtab) || $active_subtab == 'incoming') {
                            $letters = $letterModel->searchLetters($user_id, $search_query, true, $limit, $offset, $read_filter, $star_filter);
                            $total_letters = $letterModel->countSearchResults($user_id, $search_query, true, $read_filter, $star_filter);
                        } else {
                            $letters = $letterModel->searchLetters($user_id, $search_query, false, $limit, $offset, 'all', $star_filter);
                            $total_letters = $letterModel->countSearchResults($user_id, $search_query, false, 'all', $star_filter);
                        }
                    } else {
                        if (empty($active_subtab) || $active_subtab == 'incoming') {
                            $letters = $letterModel->getIncomingLetters($user_id, $read_filter, $star_filter, $limit, $offset);
                            $total_letters = $letterModel->countIncomingLetters($user_id, $read_filter, $star_filter);
                        } else {
                            $letters = $letterModel->getOutgoingLetters($user_id, $star_filter, $limit, $offset);
                            $total_letters = $letterModel->countOutgoingLetters($user_id, $star_filter);
                        }
                    }
                    
                    $total_pages = ceil($total_letters / $limit);
                    ?>
                    

                    <div class="card mb-4">
                        <div class="card-body">
                            <form action="<?= BASE_URL ?>dashboard.php" method="get" class="row g-3">
                                <input type="hidden" name="tab" value="letters">
                                <input type="hidden" name="subtab" value="<?= $active_subtab ?>">
                                
                                <div class="col-md-9">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="search" placeholder="Пошук за темою, вмістом або автором..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                                        <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                                        <a href="<?= BASE_URL ?>dashboard.php?tab=letters&subtab=<?= $active_subtab ?><?= isset($_GET['read_filter']) ? '&read_filter=' . $_GET['read_filter'] : '' ?>" class="btn btn-outline-danger">
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
                                        <?php if (empty($active_subtab) || $active_subtab == 'incoming'): ?>
                                        <div class="col-md-5 mb-3">
                                            <a href="<?= BASE_URL ?>letters.php?action=markAllRead&from=dashboard&subtab=<?= $active_subtab ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?><?= isset($_GET['page']) ? '&page=' . (int)$_GET['page'] : '' ?><?= isset($_GET['read_filter']) ? '&read_filter=' . $_GET['read_filter'] : '' ?><?= isset($_GET['star_filter']) ? '&star_filter=' . $_GET['star_filter'] : '' ?>" class="btn btn-outline-success w-100" onclick="return confirm('Ви впевнені, що хочете позначити всі вхідні листи як прочитані?')">
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
                                                        Непрочитані (<?= $letterModel->countUnreadLetters($user_id) ?>)
                                                    </option>
                                                    <option value="read" <?= $read_filter === 'read' ? 'selected' : '' ?>>
                                                        Прочитані (<?= $letterModel->countIncomingLetters($user_id, 'read') ?>)
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="col-md-<?= (empty($active_subtab) || $active_subtab == 'incoming') ? '4' : '12' ?> mb-3">
                                            <div class="d-flex align-items-center">
                                                <label for="star_filter" class="me-2">Зірочка:</label>
                                                <select class="form-select" name="star_filter" id="star_filter" onchange="this.form.submit()">
                                                    <option value="all" <?= $star_filter === 'all' ? 'selected' : '' ?>>
                                                        Усі листи
                                                    </option>
                                                    <option value="starred" <?= $star_filter === 'starred' ? 'selected' : '' ?>>
                                                        Із зірочкою (<?= $letterModel->countStarredLetters($user_id, empty($active_subtab) || $active_subtab == 'incoming') ?>)
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
                                <?php if (empty($active_subtab) || $active_subtab == 'incoming'): ?>
                                    За вашим запитом не знайдено вхідних листів.
                                <?php else: ?>
                                    За вашим запитом не знайдено вихідних листів.
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (empty($active_subtab) || $active_subtab == 'incoming'): ?>
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
                                                <?php if (empty($active_subtab) || $active_subtab == 'incoming'): ?>
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
                                                    <a href="<?= BASE_URL ?>letters.php?action=view&id=<?= $letter['id'] ?>" class="text-decoration-none <?= (!$letter['is_read'] && (empty($active_subtab) || $active_subtab == 'incoming')) ? 'fw-bold' : '' ?>">
                                                        <?= htmlspecialchars($letter['subject']) ?>
                                                        <?php if (!$letter['is_read'] && (empty($active_subtab) || $active_subtab == 'incoming')): ?>
                                                            <span class="badge bg-danger">Нове</span>
                                                        <?php endif; ?>
                                                    </a>
                                                </h5>
                                                <div class="text-muted small mb-2">
                                                                                                            <?php if (empty($active_subtab) || $active_subtab == 'incoming'): ?>
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
                                                    
                                                    <?php if (empty($active_subtab) || $active_subtab == 'incoming'): ?>
                                                    <a href="<?= BASE_URL ?>letters.php?action=compose&reply_to=<?= $letter['id'] ?>" class="btn btn-sm btn-outline-success" title="Відповісти">
                                                        <i class="bi bi-reply"></i>
                                                    </a>
                                                    
                                                    <?php if ($letter['is_read']): ?>
                                                    <a href="<?= BASE_URL ?>letters.php?action=mark&letter_id=<?= $letter['id'] ?>&mark_type=unread&search=<?= urlencode($search_query) ?>&page=<?= $page ?>&read_filter=<?= $read_filter ?>&star_filter=<?= $star_filter ?>&subtab=<?= $active_subtab ?>&from=dashboard" class="btn btn-sm btn-outline-info" title="Позначити як непрочитаний">
                                                        <i class="bi bi-envelope"></i>
                                                    </a>
                                                    <?php else: ?>
                                                    <a href="<?= BASE_URL ?>letters.php?action=mark&letter_id=<?= $letter['id'] ?>&mark_type=read&search=<?= urlencode($search_query) ?>&page=<?= $page ?>&read_filter=<?= $read_filter ?>&star_filter=<?= $star_filter ?>&subtab=<?= $active_subtab ?>&from=dashboard" class="btn btn-sm btn-outline-secondary" title="Позначити як прочитаний">
                                                        <i class="bi bi-envelope-open"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($letter['is_starred']): ?>
                                                    <a href="<?= BASE_URL ?>letters.php?action=mark&letter_id=<?= $letter['id'] ?>&mark_type=unstarred&search=<?= urlencode($search_query) ?>&page=<?= $page ?>&read_filter=<?= $read_filter ?>&star_filter=<?= $star_filter ?>&subtab=<?= $active_subtab ?>&from=dashboard" class="btn btn-sm btn-outline-warning" title="Прибрати зірочку">
                                                        <i class="bi bi-star"></i>
                                                    </a>
                                                    <?php else: ?>
                                                    <a href="<?= BASE_URL ?>letters.php?action=mark&letter_id=<?= $letter['id'] ?>&mark_type=starred&search=<?= urlencode($search_query) ?>&page=<?= $page ?>&read_filter=<?= $read_filter ?>&star_filter=<?= $star_filter ?>&subtab=<?= $active_subtab ?>&from=dashboard" class="btn btn-sm btn-outline-warning" title="Додати зірочку">
                                                        <i class="bi bi-star-fill"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="<?= BASE_URL ?>letters.php?action=delete&id=<?= $letter['id'] ?>&is_sender=<?= ($active_subtab == 'outgoing' ? '1' : '0') ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= $page > 1 ? '&page=' . $page : '' ?><?= $read_filter !== 'all' ? '&read_filter=' . $read_filter : '' ?><?= $star_filter !== 'all' ? '&star_filter=' . $star_filter : '' ?>" class="btn btn-sm btn-outline-danger" 
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
                        
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Навігація по сторінках">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= BASE_URL ?>dashboard.php?tab=letters&subtab=<?= $active_subtab ?>&page=<?= $page - 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= $read_filter !== 'all' ? '&read_filter=' . $read_filter : '' ?><?= $star_filter !== 'all' ? '&star_filter=' . $star_filter : '' ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="<?= BASE_URL ?>dashboard.php?tab=letters&subtab=<?= $active_subtab ?>&page=<?= $i ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= $read_filter !== 'all' ? '&read_filter=' . $read_filter : '' ?><?= $star_filter !== 'all' ? '&star_filter=' . $star_filter : '' ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= BASE_URL ?>dashboard.php?tab=letters&subtab=<?= $active_subtab ?>&page=<?= $page + 1 ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?><?= $read_filter !== 'all' ? '&read_filter=' . $read_filter : '' ?><?= $star_filter !== 'all' ? '&star_filter=' . $star_filter : '' ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-end">
                    <a href="<?= BASE_URL ?>letters.php?action=compose" class="btn btn-success">
                        <i class="bi bi-plus-lg me-1"></i> Написати лист
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($active_tab == 'archive'): ?>
            <div class="card border-dark shadow-sm mb-4 card-no-hover">
                <div class="card-header bg-white">
                    <h4 class="mb-0 text-success"><i class="bi bi-archive me-2"></i> Архів</h4>
                </div>
                <div class="card-body">
                    <?php
                    require_once '../app/controllers/document_controller.php';
                    $documentController = new DocumentController();
                    
                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $page = $page < 1 ? 1 : $page;
                    $limit = 10;
                    $offset = ($page - 1) * $limit;
                    
                    $filters = ['is_archived' => 1];
                    
                    if (isset($_GET['title']) && !empty($_GET['title'])) {
                        $filters['title'] = $_GET['title'];
                    }
                    
                    if (isset($_GET['type_id']) && !empty($_GET['type_id'])) {
                        $filters['type_id'] = (int)$_GET['type_id'];
                    }
                    
                    if (isset($_GET['author_id']) && !empty($_GET['author_id'])) {
                        $filters['author_id'] = (int)$_GET['author_id'];
                    }
                    
                    if (isset($_GET['department_id']) && !empty($_GET['department_id'])) {
                        $filters['department_id'] = (int)$_GET['department_id'];
                    }
                    
                    $documents = $documentController->documentModel->getAllDocuments($limit, $offset, $filters);
                    $total_documents = $documentController->documentModel->countDocuments($filters);
                    $total_pages = ceil($total_documents / $limit);
                    
                    $documentTypes = $documentController->getDocumentTypes();
                    
                    $userModel = new User();
                    $users = $userModel->getAllUsersExceptAdmins();
                    
                    $departmentModel = new Department();
                    $departments = $departmentModel->getAllDepartments();
                    ?>
                    
                    <div class="card mb-4">
                        <div class="card-body">
                            <form action="<?= BASE_URL ?>dashboard.php" method="get" class="row g-3">
                                <input type="hidden" name="tab" value="archive">
                                
                                <div class="col-md-3">
                                    <label for="title" class="form-label">Назва документа</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="title" name="title" placeholder="Пошук за назвою" value="<?= isset($_GET['title']) ? htmlspecialchars($_GET['title']) : '' ?>">
                                        <?php if (isset($_GET['title']) && !empty($_GET['title'])): ?>
                                        <a href="<?= BASE_URL ?>dashboard.php?tab=archive<?= isset($_GET['type_id']) ? '&type_id=' . $_GET['type_id'] : '' ?><?= isset($_GET['author_id']) ? '&author_id=' . $_GET['author_id'] : '' ?><?= isset($_GET['department_id']) ? '&department_id=' . $_GET['department_id'] : '' ?>" class="btn btn-outline-danger">
                                            <i class="bi bi-x-lg"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="type_id" class="form-label">Тип документа</label>
                                    <select name="type_id" id="type_id" class="form-select" onchange="this.form.submit()">
                                        <option value="">Всі типи</option>
                                        <?php foreach ($documentTypes as $type): ?>
                                            <?php if ($type['id'] != 4): // Виключаємо тип "Лист" ?>
                                            <option value="<?= $type['id'] ?>" <?= isset($_GET['type_id']) && $_GET['type_id'] == $type['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($type['name']) ?>
                                            </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="author_id" class="form-label">Автор</label>
                                    <select name="author_id" id="author_id" class="form-select" onchange="this.form.submit()">
                                        <option value="">Всі автори</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?= $user['id'] ?>" <?= isset($_GET['author_id']) && $_GET['author_id'] == $user['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($user['full_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="department_id" class="form-label">Відділення</label>
                                    <select name="department_id" id="department_id" class="form-select" onchange="this.form.submit()">
                                        <option value="">Всі відділення</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= $dept['id'] ?>" <?= isset($_GET['department_id']) && $_GET['department_id'] == $dept['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($dept['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-12 text-end">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-search me-1"></i> Пошук
                                    </button>
                                    <?php if (isset($_GET['title']) || isset($_GET['type_id']) || isset($_GET['author_id']) || isset($_GET['department_id'])): ?>
                                    <a href="<?= BASE_URL ?>dashboard.php?tab=archive" class="btn btn-outline-secondary ms-2">
                                        <i class="bi bi-x-circle me-1"></i> Скинути фільтри
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php if (empty($documents)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i> 
                            <?php if (isset($_GET['title']) || isset($_GET['type_id']) || isset($_GET['author_id']) || isset($_GET['department_id'])): ?>
                                Архівні документи за заданими критеріями не знайдені.
                            <?php else: ?>
                                Наразі немає архівних документів у системі.
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
                                            
                                            $icon_color = 'text-secondary';
                                            if ($doc['type_id'] == 1) { 
                                                $icon_color = 'text-primary';
                                            } elseif ($doc['type_id'] == 2) { 
                                                $icon_color = 'text-danger';
                                            } elseif ($doc['type_id'] == 3) { 
                                                $icon_color = ''; 
                                            }
                                            ?>
                                            <div class="document-icon me-3">
                                                <?php if ($doc['type_id'] == 3): ?>
                                                <i class="bi <?= $file_icon ?> fs-1" style="color: #fd7e14;"></i>
                                                <?php else: ?>
                                                <i class="bi <?= $file_icon ?> <?= $icon_color ?> fs-1"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="document-info flex-grow-1">
                                                <h5 class="mb-1">
                                                    <?php if ($doc['type_id'] == 3): // Якщо це розпорядження ?>
                                                    <a href="<?= BASE_URL ?>directives.php?action=view&id=<?= $doc['id'] ?>" class="text-decoration-none">
                                                        <?= htmlspecialchars($doc['title']) ?>
                                                    </a>
                                                    <?php elseif ($doc['type_id'] == 2): // Якщо це наказ ?>
                                                    <a href="<?= BASE_URL ?>orders.php?action=view&id=<?= $doc['id'] ?>" class="text-decoration-none">
                                                        <?= htmlspecialchars($doc['title']) ?>
                                                    </a>
                                                    <?php else: ?>
                                                    <a href="<?= BASE_URL ?>documents.php?action=view&id=<?= $doc['id'] ?>" class="text-decoration-none">
                                                        <?= htmlspecialchars($doc['title']) ?>
                                                    </a>
                                                    <?php endif; ?>
                                                </h5>
                                                <div class="text-muted small mb-2">
                                                    <span class="me-2"><i class="bi bi-person me-1"></i> <a href="<?= BASE_URL ?>user_profile.php?id=<?= $doc['author_id'] ?>" class="text-decoration-none text-muted"><?= htmlspecialchars($doc['author_name']) ?></a></span>
                                                    <span class="me-2"><i class="bi bi-building me-1"></i> <?= htmlspecialchars($doc['department_name']) ?></span>
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
                                                    <?php if ($doc['type_id'] == 3): // Якщо це розпорядження ?>
                                                    <a href="<?= BASE_URL ?>directives.php?action=view&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-primary" title="Переглянути">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php elseif ($doc['type_id'] == 2): // Якщо це наказ ?>
                                                    <a href="<?= BASE_URL ?>orders.php?action=view&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-primary" title="Переглянути">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php else: // Для інших типів документів ?>
                                                    <a href="<?= BASE_URL ?>documents.php?action=view&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-primary" title="Переглянути">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($doc['author_id'] == $user_id): ?>
                                                    <?php if ($doc['type_id'] == 3): // Якщо це розпорядження ?>
                                                    <a href="<?= BASE_URL ?>directives.php?action=unarchive&id=<?= $doc['id'] ?>&from_archive=1" class="btn btn-sm btn-outline-success" 
                                                       onclick="return confirm('Ви впевнені, що хочете відновити це розпорядження з архіву?')" title="Відновити">
                                                        <i class="bi bi-arrow-up-circle"></i>
                                                    </a>
                                                    
                                                    <a href="<?= BASE_URL ?>directives.php?action=delete&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-danger" 
                                                       onclick="return confirm('Ви впевнені, що хочете видалити це розпорядження? Ця дія незворотна.')" title="Видалити">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                    <?php elseif ($doc['type_id'] == 2): // Якщо це наказ ?>
                                                    <a href="<?= BASE_URL ?>orders.php?action=unarchive&id=<?= $doc['id'] ?>&from_archive=1" class="btn btn-sm btn-outline-success" 
                                                       onclick="return confirm('Ви впевнені, що хочете відновити цей наказ з архіву?')" title="Відновити">
                                                        <i class="bi bi-arrow-up-circle"></i>
                                                    </a>
                                                    
                                                    <a href="<?= BASE_URL ?>orders.php?action=delete&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-danger" 
                                                       onclick="return confirm('Ви впевнені, що хочете видалити цей наказ? Ця дія незворотна.')" title="Видалити">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                    <?php else: // Для інших типів документів ?>
                                                    <a href="<?= BASE_URL ?>documents.php?action=unarchive&id=<?= $doc['id'] ?>&from_archive=1" class="btn btn-sm btn-outline-success" 
                                                       onclick="return confirm('Ви впевнені, що хочете відновити цей документ з архіву?')" title="Відновити">
                                                        <i class="bi bi-arrow-up-circle"></i>
                                                    </a>
                                                    
                                                    <a href="<?= BASE_URL ?>documents.php?action=delete&id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-danger" 
                                                       onclick="return confirm('Ви впевнені, що хочете видалити цей документ? Ця дія незворотна.')" title="Видалити">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Навігація по сторінках">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= BASE_URL ?>dashboard.php?tab=archive&page=<?= $page - 1 ?><?= isset($_GET['title']) ? '&title=' . urlencode($_GET['title']) : '' ?><?= isset($_GET['type_id']) && !empty($_GET['type_id']) ? '&type_id=' . $_GET['type_id'] : '' ?><?= isset($_GET['author_id']) && !empty($_GET['author_id']) ? '&author_id=' . $_GET['author_id'] : '' ?><?= isset($_GET['department_id']) && !empty($_GET['department_id']) ? '&department_id=' . $_GET['department_id'] : '' ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="<?= BASE_URL ?>dashboard.php?tab=archive&page=<?= $i ?><?= isset($_GET['title']) ? '&title=' . urlencode($_GET['title']) : '' ?><?= isset($_GET['type_id']) && !empty($_GET['type_id']) ? '&type_id=' . $_GET['type_id'] : '' ?><?= isset($_GET['author_id']) && !empty($_GET['author_id']) ? '&author_id=' . $_GET['author_id'] : '' ?><?= isset($_GET['department_id']) && !empty($_GET['department_id']) ? '&department_id=' . $_GET['department_id'] : '' ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= BASE_URL ?>dashboard.php?tab=archive&page=<?= $page + 1 ?><?= isset($_GET['title']) ? '&title=' . urlencode($_GET['title']) : '' ?><?= isset($_GET['type_id']) && !empty($_GET['type_id']) ? '&type_id=' . $_GET['type_id'] : '' ?><?= isset($_GET['author_id']) && !empty($_GET['author_id']) ? '&author_id=' . $_GET['author_id'] : '' ?><?= isset($_GET['department_id']) && !empty($_GET['department_id']) ? '&department_id=' . $_GET['department_id'] : '' ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($active_tab == 'settings'): ?>
            <div class="card border-dark shadow-sm mb-4 card-no-hover">
                <div class="card-header bg-white">
                    <h4 class="mb-0 text-success"><i class="bi bi-person-gear me-2"></i> Налаштування профілю</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['profile_success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="bi bi-check-circle me-2"></i> <?= $_SESSION['profile_success'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['profile_success']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['profile_error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="bi bi-exclamation-triangle me-2"></i> <?= $_SESSION['profile_error'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['profile_error']); ?>
                    <?php endif; ?>
                    
                    <form action="<?= BASE_URL ?>update_profile.php" method="post" enctype="multipart/form-data">
                        <div class="row mb-4">
                            <div class="col-md-4 text-center">
                                <div class="avatar-container mb-3 position-relative">
                                    <?php if (!empty($user['avatar'])): ?>
                                        <img src="<?= BASE_URL ?>uploads/<?= $user['avatar'] ?>" class="img-thumbnail rounded-circle" alt="Аватар" style="width: 150px; height: 150px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light rounded-circle d-flex justify-content-center align-items-center mx-auto" style="width: 150px; height: 150px;">
                                            <i class="bi bi-person-fill text-secondary fs-1"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="avatar-overlay position-absolute top-0 start-0 end-0 bottom-0 d-flex justify-content-center align-items-center rounded-circle">
                                        <i class="bi bi-camera"></i>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="avatar" class="form-label">Змінити аватар</label>
                                    <input class="form-control form-control-sm" type="file" id="avatar" name="avatar" accept=".jpg,.jpeg,.png,.gif">
                                    <div class="form-text">Рекомендований розмір: 300x300 пікселів. Максимальний розмір: 2MB.</div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Повне ім'я <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="login" class="form-label">Логін <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="login" name="login" value="<?= htmlspecialchars($user['login']) ?>" required>
                                    <div class="form-text">Може містити тільки літери, цифри та символ підкреслення.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Телефон</label>
                                    <input type="text" class="form-control" id="phone" name="phone" value="<?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : '' ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="address" class="form-label">Адреса</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : '' ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <h5 class="border-bottom border-dark pb-2 mb-3">Зміна паролю</h5>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Поточний пароль</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                    <div class="form-text">Введіть поточний пароль, якщо хочете змінити пароль.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Новий пароль</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                    <div class="form-text">Мінімум 4 символи.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Підтвердження нового паролю</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-save me-1"></i> Зберегти зміни
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                const closeBtn = alert.querySelector('.btn-close');
                if (closeBtn) {
                    closeBtn.click();
                }
            }, 5000);
        });
        
        const avatarContainer = document.querySelector('.avatar-container');
        const avatarInput = document.getElementById('avatar');
        
        if (avatarContainer && avatarInput) {
            avatarContainer.addEventListener('click', function() {
                avatarInput.click();
            });
            
            avatarInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const avatarImg = avatarContainer.querySelector('img');
                        const avatarPlaceholder = avatarContainer.querySelector('div.bg-light');
                        
                        if (avatarImg) {
                            avatarImg.src = e.target.result;
                        } else if (avatarPlaceholder) {
                            avatarPlaceholder.style.display = 'none';
                            const newImg = document.createElement('img');
                            newImg.src = e.target.result;
                            newImg.className = 'img-thumbnail rounded-circle';
                            newImg.alt = 'Аватар';
                            newImg.style.width = '150px';
                            newImg.style.height = '150px';
                            newImg.style.objectFit = 'cover';
                            avatarContainer.insertBefore(newImg, avatarContainer.firstChild);
                        }
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleSidebarButton = document.getElementById('toggleSidebar');
        const userSidebar = document.getElementById('userSidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        function toggleSidebar() {
            userSidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            
            const iconElement = toggleSidebarButton.querySelector('i');
            if (userSidebar.classList.contains('active')) {
                iconElement.classList.remove('bi-list');
                iconElement.classList.add('bi-x-lg');
            } else {
                iconElement.classList.remove('bi-x-lg');
                iconElement.classList.add('bi-list');
            }
        }
        
        if (toggleSidebarButton) {
            toggleSidebarButton.addEventListener('click', toggleSidebar);
        }
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', toggleSidebar);
        }
        
        if (window.innerWidth <= 767) {
            const sidebarLinks = userSidebar.querySelectorAll('a.list-group-item');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    toggleSidebar();
                });
            });
        }
        
        window.addEventListener('resize', function() {
            if (window.innerWidth > 767 && userSidebar.classList.contains('active')) {
                userSidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                
                const iconElement = toggleSidebarButton.querySelector('i');
                iconElement.classList.remove('bi-x-lg');
                iconElement.classList.add('bi-list');
            }
        });
    });
</script>
<?php
require_once '../app/views/includes/footer.php';
?> 