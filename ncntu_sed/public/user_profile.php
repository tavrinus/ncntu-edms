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
$current_user_id = $_SESSION['user_id'];
$current_user = $userModel->getUserById($current_user_id);
if ($userModel->isAdmin($current_user_id)) {
    redirect('admin/dashboard.php');
    exit;
}
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) {
    $_SESSION['error_message'] = 'Некоректний ID користувача';
    redirect('dashboard.php');
    exit;
}
if ($userModel->isAdmin($user_id)) {
    $_SESSION['error_message'] = 'Доступ заборонено';
    redirect('dashboard.php');
    exit;
}
$user = $userModel->getUserById($user_id);
if (!$user) {
    $_SESSION['error_message'] = 'Користувач не знайдений';
    redirect('dashboard.php');
    exit;
}
$roles = $userModel->getUserRoles($user_id);
$departmentModel = new Department();
$departments = $departmentModel->getUserDepartments($user_id);
$letterModel = new Letter();
$unread_count = $letterModel->countUnreadLetters($current_user_id);
$page_title = 'Профіль користувача: ' . htmlspecialchars($user['full_name']);
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
        padding-top: 20px;
        width: 20%;
    }
    .list-group-item:first-child {
    padding-top: 28px;
    border-top: none;
}
    .content-area {
        padding: 20px;
        background-color: rgba(255, 255, 255, 0.9);
        width: 80%;
    }
    .card-no-hover:hover {
        transform: none;
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
                    <?php if (!empty($current_user['avatar'])): ?>
                        <img src="<?= BASE_URL ?>uploads/<?= $current_user['avatar'] ?>" class="img-thumbnail rounded-circle" alt="Аватар" style="width: 90px; height: 90px; object-fit: cover;">
                    <?php else: ?>
                        <div class="bg-light rounded-circle d-flex justify-content-center align-items-center mx-auto" style="width: 90px; height: 90px;">
                            <i class="bi bi-person-fill text-secondary fs-2"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($current_user['full_name']) ?></h5>
                <p class="text-muted small mb-2">@<?= htmlspecialchars($current_user['login']) ?></p>
                <!-- Ролі поточного користувача -->
                <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
                    <?php 
                    $current_user_roles = $userModel->getUserRoles($current_user_id);
                    foreach ($current_user_roles as $role): 
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
                <a href="<?= BASE_URL ?>dashboard.php?tab=documents" class="list-group-item list-group-item-action">
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
        <div class="col-md-9 content-area">
            <div class="container-fluid">
                <div class="mb-3">
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i> Повернутися
                    </a>
                </div>
                <div class="card shadow-sm mb-4 card-no-hover">
                    <div class="card-header bg-white">
                        <h4 class="mb-0 text-success"><i class="bi bi-person-vcard me-2"></i> Профіль користувача</h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4 align-items-center">
                            <div class="col-md-3 text-center">
                                <?php if (!empty($user['avatar'])): ?>
                                    <img src="<?= BASE_URL ?>uploads/<?= $user['avatar'] ?>" class="img-thumbnail rounded-circle" alt="Аватар" style="width: 150px; height: 150px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-light rounded-circle d-flex justify-content-center align-items-center mx-auto" style="width: 150px; height: 150px;">
                                        <i class="bi bi-person-fill text-secondary fs-1"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-3">
                                    <?php foreach ($roles as $role): ?>
                                        <span class="badge bg-success rounded-pill px-3 py-2 mb-1 d-inline-block">
                                            <?= htmlspecialchars($role['name']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
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
                                            <?php if (!empty($user['phone'])): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-telephone-fill text-success me-3 fs-4"></i>
                                                    <div>
                                                        <div class="text-muted small">Телефон</div>
                                                        <div class="fw-bold"><?= htmlspecialchars($user['phone']) ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($user['address'])): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-geo-alt-fill text-success me-3 fs-4"></i>
                                                    <div>
                                                        <div class="text-muted small">Адреса</div>
                                                        <div class="fw-bold"><?= htmlspecialchars($user['address']) ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <a href="<?= BASE_URL ?>letters.php?action=compose&receiver_id=<?= $user_id ?>" class="btn btn-success">
                                        <i class="bi bi-envelope me-2"></i> Написати лист
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($departments)): ?>
                        <h5 class="border-bottom border-success pb-2 mb-3">Відділення користувача</h5>
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
            </div>
        </div>
    </div>
</div>
<?php
require_once '../app/views/includes/footer.php';
?>
<script src="<?= BASE_URL ?>js/mobile-sidebar.js"></script> 