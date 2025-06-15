<?php
require_once '../../config/config.php';
require_once '../../app/models/User.php';
if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
    exit;
}
$userModel = new User();
$user_id = $_SESSION['user_id'];
$user = $userModel->getUserById($user_id);
$roles = $userModel->getUserRoles($user_id);
if (!$userModel->isAdmin($user_id)) {
    redirect('../dashboard.php');
    exit;
}
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
$stmt->execute();
$result = $stmt->get_result();
$total_users = $result->fetch_assoc()['total'];
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM documents WHERE is_archived = 0");
$stmt->execute();
$result = $stmt->get_result();
$total_docs = $result->fetch_assoc()['total'];
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM departments");
$stmt->execute();
$result = $stmt->get_result();
$total_departments = $result->fetch_assoc()['total'];
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM documents WHERE is_archived = 1");
$stmt->execute();
$result = $stmt->get_result();
$archived_docs = $result->fetch_assoc()['total'];
$stmt = $conn->prepare("SELECT u.*, GROUP_CONCAT(r.name) as role_names, GROUP_CONCAT(d.name) as department_names 
                        FROM users u 
                        LEFT JOIN user_roles ur ON u.id = ur.user_id 
                        LEFT JOIN roles r ON ur.role_id = r.id 
                        LEFT JOIN user_departments ud ON u.id = ud.user_id 
                        LEFT JOIN departments d ON ud.department_id = d.id 
                        GROUP BY u.id 
                        ORDER BY u.id DESC LIMIT 5");
$stmt->execute();
$latest_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt = $conn->prepare("SELECT d.*, u.full_name as user_name, dt.name as type_name, dep.name as department_name,
                        SUBSTRING_INDEX(d.file_path, '.', -1) as file_extension
                        FROM documents d 
                        LEFT JOIN users u ON d.author_id = u.id 
                        LEFT JOIN document_types dt ON d.type_id = dt.id
                        LEFT JOIN departments dep ON d.department_id = dep.id
                        ORDER BY d.created_at DESC LIMIT 5");
$stmt->execute();
$latest_docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($latest_docs as &$doc) {
    if (!empty($doc['file_path'])) {
        $file_path = $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/public/uploads/' . $doc['file_path'];
        if (file_exists($file_path)) {
            $doc['file_size'] = round(filesize($file_path) / 1048576, 2);
        } else {
            $doc['file_size'] = 0;
        }
    } else {
        $doc['file_size'] = 0;
    }
}
unset($doc);
$conn->close();
require_once '../../app/views/includes/header.php';
?>
<div class="container-fluid mt-3 dashboard-container">
    <div class="row">
        <div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse" style="min-height: calc(100vh - 100px);">
            <div class="card border-dark shadow-sm mb-4">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?= BASE_URL ?>uploads/<?= $user['avatar'] ?>" class="img-thumbnail rounded-circle" alt="Аватар" style="width: 100px; height: 100px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-white rounded-circle d-flex justify-content-center align-items-center mx-auto" style="width: 100px; height: 100px;">
                                <i class="bi bi-person-fill text-secondary fs-1"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <h5 class="card-title"><?= htmlspecialchars($user['full_name']) ?></h5>
                    <p class="text-muted small">
                        <span class="badge bg-danger">Адміністратор</span>
                    </p>
                </div>
            </div>
            <div class="list-group mb-4 shadow-sm rounded">
                <a href="<?= BASE_URL ?>admin/dashboard.php" class="list-group-item list-group-item-action active admin-nav-link">
                    <i class="bi bi-speedometer2 me-2"></i> Головна панель
                </a>
                <a href="<?= BASE_URL ?>admin/users.php" class="list-group-item list-group-item-action admin-nav-link">
                    <i class="bi bi-people me-2"></i> Користувачі
                </a>
                <a href="<?= BASE_URL ?>admin/roles.php" class="list-group-item list-group-item-action admin-nav-link">
                    <i class="bi bi-person-badge me-2"></i> Ролі користувачів
                </a>
                <a href="<?= BASE_URL ?>admin/documents.php" class="list-group-item list-group-item-action admin-nav-link">
                    <i class="bi bi-file-earmark-text me-2"></i> Документи
                </a>
                <a href="<?= BASE_URL ?>admin/archived_documents.php" class="list-group-item list-group-item-action admin-nav-link">
                    <i class="bi bi-archive me-2"></i> Архів документів
                </a>
                <a href="<?= BASE_URL ?>admin/departments.php" class="list-group-item list-group-item-action admin-nav-link">
                    <i class="bi bi-diagram-3 me-2"></i> Відділення
                </a>
                <a href="<?= BASE_URL ?>admin/profile_settings.php" class="list-group-item list-group-item-action admin-nav-link">
                    <i class="bi bi-person-gear me-2"></i> Налаштування профілю
                </a>
                <a href="<?= BASE_URL ?>logout.php" class="list-group-item list-group-item-action text-danger admin-nav-link">
                    <i class="bi bi-box-arrow-right me-2"></i> Вихід
                </a>
            </div>
        </div>
        <div class="col-md-9 col-lg-10 ms-sm-auto px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom border-dark">
                <h1 class="h2 text-success"><i class="bi bi-shield-lock me-2"></i> Панель адміністратора</h1>
            </div>
            <div class="row mb-4">
                <div class="col-md-3 mb-4">
                    <div class="card border-success h-100 shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-people-fill text-success fs-1 mb-2"></i>
                            <h5 class="card-title border-bottom border-dark pb-2">Користувачі</h5>
                            <div class="display-4 fw-bold"><?= $total_users ?></div>
                        </div>
                        <div class="card-footer bg-white text-center">
                            <a href="<?= BASE_URL ?>admin/users.php" class="text-decoration-none text-dark">
                                <i class="bi bi-arrow-right-circle me-1"></i> Перейти до користувачів
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card border-success h-100 shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-file-earmark-text text-success fs-1 mb-2"></i>
                            <h5 class="card-title border-bottom border-dark pb-2">Документи</h5>
                            <div class="display-4 fw-bold"><?= $total_docs ?></div>
                        </div>
                        <div class="card-footer bg-white text-center">
                            <a href="<?= BASE_URL ?>admin/documents.php" class="text-decoration-none text-dark">
                                <i class="bi bi-arrow-right-circle me-1"></i> Перейти до документів
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card border-success h-100 shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-diagram-3-fill text-success fs-1 mb-2"></i>
                            <h5 class="card-title border-bottom border-dark pb-2">Відділення</h5>
                            <div class="display-4 fw-bold"><?= $total_departments ?></div>
                        </div>
                        <div class="card-footer bg-white text-center">
                            <a href="<?= BASE_URL ?>admin/departments.php" class="text-decoration-none text-dark">
                                <i class="bi bi-arrow-right-circle me-1"></i> Перейти до відділень
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card border-success h-100 shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-archive-fill text-success fs-1 mb-2"></i>
                            <h5 class="card-title border-bottom border-dark pb-2">Архів</h5>
                            <div class="display-4 fw-bold"><?= $archived_docs ?></div>
                        </div>
                        <div class="card-footer bg-white text-center">
                            <a href="<?= BASE_URL ?>admin/archived_documents.php" class="text-decoration-none text-dark">
                                <i class="bi bi-arrow-right-circle me-1"></i> Перейти до архіву
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-12 mb-4">
                    <div class="card border-dark shadow-sm">
                        <div class="card-header bg-white">
                            <h4 class="mb-0 text-success"><i class="bi bi-people me-2"></i> Останні користувачі</h4>
                        </div>
                        <div class="card-body content-container">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th scope="col">ID</th>
                                            <th scope="col">Аватар</th>
                                            <th scope="col">ПІБ</th>
                                            <th scope="col">Логін</th>
                                            <th scope="col">Роль</th>
                                            <th scope="col">Відділення</th>
                                            <th scope="col">Дата реєстрації</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($latest_users)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center">Немає користувачів у системі</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach($latest_users as $u): ?>
                                                <tr>
                                                    <th scope="row"><?= $u['id'] ?></th>
                                                    <td>
                                                        <?php if (!empty($u['avatar'])): ?>
                                                            <img src="<?= BASE_URL ?>uploads/<?= $u['avatar'] ?>" class="img-thumbnail rounded-circle" alt="Аватар" style="width: 40px; height: 40px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-light rounded-circle d-flex justify-content-center align-items-center" style="width: 40px; height: 40px;">
                                                                <i class="bi bi-person-fill text-secondary"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($u['full_name']) ?></td>
                                                    <td><?= htmlspecialchars($u['login']) ?></td>
                                                    <td>
                                                        <?php 
                                                        if(!empty($u['role_names'])) {
                                                            $roles = explode(',', $u['role_names']);
                                                            $unique_roles = array();
                                                            foreach($roles as $role) {
                                                                $role_name = trim($role);
                                                                $unique_roles[$role_name] = $role_name;
                                                            }
                                                            foreach($unique_roles as $role_name) {
                                                                $role_lower = mb_strtolower($role_name);
                                                                if($role_lower == 'адміністратор') {
                                                                    $badge_class = 'bg-danger';
                                                                } else {
                                                                    $badge_class = 'bg-success';
                                                                }
                                                                echo "<span class='badge $badge_class me-1'>" . htmlspecialchars($role_name) . "</span>";
                                                            }
                                                        } else {
                                                            echo "<span class='badge bg-warning text-dark'>Без ролі</span>";
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        if(!empty($u['department_names'])) {
                                                            $departments = explode(',', $u['department_names']);
                                                            $unique_departments = array();
                                                            foreach($departments as $dept) {
                                                                $dept_name = trim($dept);
                                                                $unique_departments[$dept_name] = $dept_name;
                                                            }
                                                            foreach($unique_departments as $dept_name) {
                                                                echo "<span class='badge bg-secondary me-1'>" . htmlspecialchars($dept_name) . "</span>";
                                                            }
                                                        } else {
                                                            echo "<span class='badge bg-light text-dark border'>Без відділення</span>";
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?= date('d.m.Y H:i', strtotime($u['created_at'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-white">
                            <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-sm btn-success">
                                <i class="bi bi-arrow-right-circle me-1"></i> Перейти до управління користувачами
                            </a>
                        </div>
                    </div>
                    <div class="card border-dark shadow-sm mt-4">
                        <div class="card-header bg-white">
                            <h4 class="mb-0 text-success"><i class="bi bi-file-earmark-text me-2"></i> Останні документи</h4>
                        </div>
                        <div class="card-body content-container">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th scope="col">ID</th>
                                            <th scope="col">Назва</th>
                                            <th scope="col">Тип</th>
                                            <th scope="col">Розширення</th>
                                            <th scope="col">Розмір</th>
                                            <th scope="col">Відділення</th>
                                            <th scope="col">Автор</th>
                                            <th scope="col">Статус</th>
                                            <th scope="col">Дата</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($latest_docs)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center">Немає документів у системі</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach($latest_docs as $doc): ?>
                                                <tr>
                                                    <th scope="row"><?= $doc['id'] ?></th>
                                                    <td><?= htmlspecialchars($doc['title']) ?></td>
                                                    <td><span class="badge bg-orange text-white"><?= htmlspecialchars(ucfirst($doc['type_name'] ?? 'Не вказано')) ?></span></td>
                                                    <td><span class="badge bg-danger"><?= strtoupper(htmlspecialchars($doc['file_extension'] ?? '-')) ?></span></td>
                                                    <td><?= number_format((float)$doc['file_size'], 2, '.', '') ?> МБ</td>
                                                    <td>
                                                        <?php if (!empty($doc['department_name'])): ?>
                                                            <span class="badge bg-secondary"><?= htmlspecialchars($doc['department_name']) ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-light text-dark border">Не вказано</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($doc['user_name']) ?></td>
                                                    <td>
                                                        <?php 
                                                        $status_class = 'bg-success';
                                                        $status_text = 'Активний';
                                                        if($doc['is_archived'] == 1) {
                                                            $status_class = 'bg-secondary';
                                                            $status_text = 'Архівований';
                                                        }
                                                        echo "<span class='badge $status_class'>" . $status_text . "</span>";
                                                        ?>
                                                    </td>
                                                    <td><?= date('d.m.Y H:i', strtotime($doc['created_at'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bg-white">
                            <a href="<?= BASE_URL ?>admin/documents.php" class="btn btn-sm btn-success">
                                <i class="bi bi-arrow-right-circle me-1"></i> Перейти до управління документами
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" style="display: none;">
                    <!-- Блок швидких налаштувань видалено -->
                </div>
            </div>
        </div>
    </div>
</div>
<style>
.bg-orange {
    background-color: #fd7e14 !important;
}
</style>
<?php
function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $time_diff = time() - $time;
    if ($time_diff < 60) {
        return 'щойно';
    } elseif ($time_diff < 3600) {
        $minutes = floor($time_diff / 60);
        return $minutes . ' ' . ($minutes == 1 ? 'хвилину' : ($minutes < 5 ? 'хвилини' : 'хвилин')) . ' тому';
    } elseif ($time_diff < 86400) {
        $hours = floor($time_diff / 3600);
        return $hours . ' ' . ($hours == 1 ? 'годину' : ($hours < 5 ? 'години' : 'годин')) . ' тому';
    } else {
        $days = floor($time_diff / 86400);
        return $days . ' ' . ($days == 1 ? 'день' : ($days < 5 ? 'дні' : 'днів')) . ' тому';
    }
}
require_once '../../app/views/includes/footer.php';
?>