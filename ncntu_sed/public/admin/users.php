<?php
require_once '../../config/config.php';
require_once '../../app/models/User.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}
$userModel = new User();
$user_id = $_SESSION['user_id'];
$user = $userModel->getUserById($user_id);
if (!$userModel->isAdmin($user_id)) {
    header("Location: " . BASE_URL . "dashboard.php");
    exit;
}
$conn = getDBConnection();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$user_id_to_edit = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($action == 'delete' && $user_id_to_edit > 0) {
    if ($user_id_to_edit == $user_id) {
        header("Location: " . BASE_URL . "admin/users.php?error=self_delete");
        exit;
    }
    if ($userModel->isAdmin($user_id_to_edit)) {
        header("Location: " . BASE_URL . "admin/users.php?error=admin_delete");
        exit;
    }
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE FROM user_roles WHERE user_id = ?");
        $stmt->bind_param("i", $user_id_to_edit);
        $stmt->execute();
        $stmt = $conn->prepare("DELETE FROM user_departments WHERE user_id = ?");
        $stmt->bind_param("i", $user_id_to_edit);
        $stmt->execute();
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id_to_edit);
        $stmt->execute();
        $conn->commit();
        header("Location: " . BASE_URL . "admin/users.php?success=deleted");
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: " . BASE_URL . "admin/users.php?error=delete_failed");
    }
    exit;
}
$stmt = $conn->prepare("SELECT * FROM roles ORDER BY id");
$stmt->execute();
$all_roles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt = $conn->prepare("SELECT * FROM departments ORDER BY name");
$stmt->execute();
$all_departments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $login = trim($_POST['login']);
    $email = trim($_POST['email']);
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $roles = isset($_POST['roles']) ? $_POST['roles'] : [];
    $departments = isset($_POST['departments']) ? $_POST['departments'] : [];
    $errors = [];
    if (empty($full_name)) {
        $errors[] = "ПІБ користувача обов'язкове для заповнення";
    }
    if (empty($login)) {
        $errors[] = "Логін користувача обов'язковий для заповнення";
    }
    if (empty($email)) {
        $errors[] = "Email користувача обов'язковий для заповнення";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Вказано невірний формат email";
    }
    if ($_POST['form_action'] === 'create' || ($_POST['form_action'] === 'edit' && $_POST['original_login'] !== $login)) {
        if (!$userModel->isLoginUnique($login)) {
            $errors[] = "Користувач з таким логіном вже існує";
        }
    }
    if ($_POST['form_action'] === 'create' || ($_POST['form_action'] === 'edit' && $_POST['original_email'] !== $email)) {
        if (!$userModel->isEmailUnique($email)) {
            $errors[] = "Користувач з таким email вже існує";
        }
    }
    if ($_POST['form_action'] === 'create' && empty($password)) {
        $errors[] = "Пароль обов'язковий для нового користувача";
    }
    if (!empty($password) && strlen($password) < 4) {
        $errors[] = "Пароль має бути не менше 4 символів";
    }
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            if ($_POST['form_action'] === 'create') {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (full_name, login, email, password, phone, address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("ssssss", $full_name, $login, $email, $password_hash, $phone, $address);
                $stmt->execute();
                $new_user_id = $conn->insert_id;
                if (!empty($roles)) {
                    $stmt = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    foreach ($roles as $role_id) {
                        $stmt->bind_param("ii", $new_user_id, $role_id);
                        $stmt->execute();
                    }
                }
                if (!empty($departments)) {
                    $stmt = $conn->prepare("INSERT INTO user_departments (user_id, department_id) VALUES (?, ?)");
                    foreach ($departments as $dept_id) {
                        $stmt->bind_param("ii", $new_user_id, $dept_id);
                        $stmt->execute();
                    }
                }
                $conn->commit();
                header("Location: " . BASE_URL . "admin/users.php?success=created");
            } else if ($_POST['form_action'] === 'edit') {
                $edit_id = (int)$_POST['edit_id'];
                if ($edit_id === $user_id) {
                    $has_admin_role = false;
                    foreach ($roles as $role_id) {
                        if ((int)$role_id === 1) {
                            $has_admin_role = true;
                            break;
                        }
                    }
                    if (!$has_admin_role) {
                        $roles[] = 1;
                    }
                }
                if (!empty($password)) {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET full_name = ?, login = ?, email = ?, password = ?, phone = ?, address = ? WHERE id = ?");
                    $stmt->bind_param("ssssssi", $full_name, $login, $email, $password_hash, $phone, $address, $edit_id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET full_name = ?, login = ?, email = ?, phone = ?, address = ? WHERE id = ?");
                    $stmt->bind_param("sssssi", $full_name, $login, $email, $phone, $address, $edit_id);
                }
                $stmt->execute();
                $stmt = $conn->prepare("DELETE FROM user_roles WHERE user_id = ?");
                $stmt->bind_param("i", $edit_id);
                $stmt->execute();
                if (!empty($roles)) {
                    $stmt = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    foreach ($roles as $role_id) {
                        $stmt->bind_param("ii", $edit_id, $role_id);
                        $stmt->execute();
                    }
                }
                $stmt = $conn->prepare("DELETE FROM user_departments WHERE user_id = ?");
                $stmt->bind_param("i", $edit_id);
                $stmt->execute();
                if (!empty($departments)) {
                    $stmt = $conn->prepare("INSERT INTO user_departments (user_id, department_id) VALUES (?, ?)");
                    foreach ($departments as $dept_id) {
                        $stmt->bind_param("ii", $edit_id, $dept_id);
                        $stmt->execute();
                    }
                }
                $conn->commit();
                header("Location: " . BASE_URL . "admin/users.php?success=updated");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Помилка збереження даних: " . $e->getMessage();
        }
    }
}
$user_to_edit = null;
$user_roles = [];
$user_departments = [];
if ($action === 'edit' && $user_id_to_edit > 0) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id_to_edit);
    $stmt->execute();
    $user_to_edit = $stmt->get_result()->fetch_assoc();
    if ($user_to_edit) {
        $stmt = $conn->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
        $stmt->bind_param("i", $user_id_to_edit);
        $stmt->execute();
        $roles_result = $stmt->get_result();
        while ($role = $roles_result->fetch_assoc()) {
            $user_roles[] = $role['role_id'];
        }
        $stmt = $conn->prepare("SELECT department_id FROM user_departments WHERE user_id = ?");
        $stmt->bind_param("i", $user_id_to_edit);
        $stmt->execute();
        $departments_result = $stmt->get_result();
        while ($dept = $departments_result->fetch_assoc()) {
            $user_departments[] = $dept['department_id'];
        }
    }
}
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? (int)$_GET['role'] : 0;
$department_filter = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset = ($current_page - 1) * $per_page;
$where_clause = '';
$params = [];
$types = '';
$where_conditions = [];
if (!empty($search_query)) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.login LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}
if ($role_filter > 0) {
    $where_conditions[] = "ur.role_id = ?";
    $params[] = $role_filter;
    $types .= 'i';
}
if ($department_filter > 0) {
    $where_conditions[] = "ud.department_id = ?";
    $params[] = $department_filter;
    $types .= 'i';
}
if (!empty($where_conditions)) {
    $where_clause = " WHERE " . implode(" AND ", $where_conditions);
}
$count_sql = "SELECT COUNT(DISTINCT u.id) as total FROM users u 
              LEFT JOIN user_roles ur ON u.id = ur.user_id 
              LEFT JOIN user_departments ud ON u.id = ud.user_id" . $where_clause;
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);
$sql = "SELECT u.*, 
        GROUP_CONCAT(DISTINCT r.name) as role_names, 
        GROUP_CONCAT(DISTINCT d.name) as department_names 
        FROM users u 
        LEFT JOIN user_roles ur ON u.id = ur.user_id 
        LEFT JOIN roles r ON ur.role_id = r.id 
        LEFT JOIN user_departments ud ON u.id = ud.user_id 
        LEFT JOIN departments d ON ud.department_id = d.id" . 
        $where_clause . 
        " GROUP BY u.id 
        ORDER BY u.id DESC 
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $params[] = $per_page;
    $params[] = $offset;
    $stmt->bind_param($types . 'ii', ...$params);
} else {
    $stmt->bind_param('ii', $per_page, $offset);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();
require_once '../../app/views/includes/header.php';
?>
<div class="container-fluid mt-3">
    <div class="row">
        <!-- Бічне меню -->
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
                <a href="<?= BASE_URL ?>admin/dashboard.php" class="list-group-item list-group-item-action admin-nav-link">
                    <i class="bi bi-speedometer2 me-2"></i> Головна панель
                </a>
                <a href="<?= BASE_URL ?>admin/users.php" class="list-group-item list-group-item-action active admin-nav-link">
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
        <!-- Основний контент -->
        <div class="col-md-9 col-lg-10 ms-sm-auto px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom border-dark">
                <h1 class="h2 text-success"><i class="bi bi-people me-2"></i> Управління користувачами</h1>
                <?php if ($action === 'list'): ?>
                <div>
                    <a href="<?= BASE_URL ?>admin/users.php?action=create" class="btn btn-success">
                        <i class="bi bi-person-plus me-1"></i> Додати користувача
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php if (isset($_GET['error']) && false): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                        $error = $_GET['error'];
                        if ($error === 'self_delete') {
                            echo 'Ви не можете видалити свій власний обліковий запис.';
                        } elseif ($error === 'delete_failed') {
                            echo 'Помилка при видаленні користувача.';
                        } elseif ($error === 'admin_delete') {
                            echo 'Ви не можете видалити обліковий запис адміністратора.';
                        } else {
                            echo 'Виникла помилка при обробці запиту.';
                        }
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['success']) && false): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php 
                        $success = $_GET['success'];
                        if ($success === 'created') {
                            echo 'Користувача успішно створено.';
                        } elseif ($success === 'updated') {
                            echo 'Дані користувача успішно оновлено.';
                        } elseif ($success === 'deleted') {
                            echo 'Користувача успішно видалено.';
                        }
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h5 class="alert-heading">Помилки при збереженні:</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= $error ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($action === 'list'): ?>
                <!-- Список користувачів -->
                <!-- Фільтри користувачів -->
                <div class="card border-dark shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 text-success">
                            <i class="bi bi-funnel me-2"></i> Фільтри
                        </h5>
                    </div>
                    <div class="card-body content-container">
                        <form action="<?= BASE_URL ?>admin/users.php" method="get" class="row g-3">
                            <div class="col-md-6">
                                <label for="search" class="form-label">Пошук за ім'ям, логіном або email</label>
                                <input type="text" name="search" id="search" class="form-control" value="<?= htmlspecialchars($search_query) ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="role" class="form-label">Роль</label>
                                <select name="role" id="role" class="form-select">
                                    <option value="0">Всі ролі</option>
                                    <?php foreach ($all_roles as $role): ?>
                                        <option value="<?= $role['id'] ?>" <?= $role_filter == $role['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($role['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="department" class="form-label">Відділення</label>
                                <select name="department" id="department" class="form-select">
                                    <option value="0">Всі відділення</option>
                                    <?php foreach ($all_departments as $dept): ?>
                                        <option value="<?= $dept['id'] ?>" <?= $department_filter == $dept['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 text-end">
                                <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-outline-secondary me-2">
                                    <i class="bi bi-x-circle me-1"></i> Скинути
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-search me-1"></i> Застосувати фільтри
                                </button>
                            </div>
                        </form>
                        <?php if (!empty($search_query) || $role_filter > 0 || $department_filter > 0): ?>
                            <div class="alert alert-info mt-3">
                                <i class="bi bi-info-circle me-2"></i> Знайдено користувачів: <strong><?= $total_records ?></strong>
                                <?php if (!empty($search_query)): ?>
                                    <span class="ms-2 badge bg-secondary">Пошук: <?= htmlspecialchars($search_query) ?></span>
                                <?php endif; ?>
                                <?php if ($role_filter > 0): 
                                    $selected_role_name = '';
                                    foreach ($all_roles as $role) {
                                        if ($role['id'] == $role_filter) {
                                            $selected_role_name = $role['name'];
                                            break;
                                        }
                                    }
                                ?>
                                    <span class="ms-2 badge bg-primary">Роль: <?= htmlspecialchars($selected_role_name) ?></span>
                                <?php endif; ?>
                                <?php if ($department_filter > 0): 
                                    $selected_dept_name = '';
                                    foreach ($all_departments as $dept) {
                                        if ($dept['id'] == $department_filter) {
                                            $selected_dept_name = $dept['name'];
                                            break;
                                        }
                                    }
                                ?>
                                    <span class="ms-2 badge bg-success">Відділення: <?= htmlspecialchars($selected_dept_name) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Список користувачів -->
                <div class="card border-dark shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 text-success">
                            <i class="bi bi-people me-2"></i> Список користувачів
                        </h5>
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
                                        <th scope="col">Email</th>
                                        <th scope="col">Ролі</th>
                                        <th scope="col">Відділення</th>
                                        <th scope="col">Дата реєстрації</th>
                                        <th scope="col">Дії</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center">
                                                <?= !empty($search_query) ? 'Користувачів за вашим запитом не знайдено.' : 'Немає користувачів у системі.' ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $u): ?>
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
                                                <td><?= htmlspecialchars($u['email']) ?></td>
                                                <td>
                                                    <?php 
                                                    if (!empty($u['role_names'])) {
                                                        $roles = explode(',', $u['role_names']);
                                                        foreach ($roles as $role) {
                                                            $role_name = trim($role);
                                                            $role_lower = mb_strtolower($role_name);
                                                            if ($role_lower == 'адміністратор') {
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
                                                    if (!empty($u['department_names'])) {
                                                        $departments = explode(',', $u['department_names']);
                                                        foreach ($departments as $dept) {
                                                            echo "<span class='badge bg-secondary me-1'>" . htmlspecialchars($dept) . "</span>";
                                                        }
                                                    } else {
                                                        echo "<span class='badge bg-light text-dark border'>Без відділення</span>";
                                                    }
                                                    ?>
                                                </td>
                                                <td><?= date('d.m.Y H:i', strtotime($u['created_at'])) ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="<?= BASE_URL ?>admin/users.php?action=edit&id=<?= $u['id'] ?>" class="btn btn-outline-dark">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <?php
                                                        $is_admin = false;
                                                        if (!empty($u['role_names'])) {
                                                            $roles = explode(',', $u['role_names']);
                                                            foreach ($roles as $role) {
                                                                $role_name = trim($role);
                                                                $role_lower = mb_strtolower($role_name);
                                                                if ($role_lower == 'адміністратор') {
                                                                    $is_admin = true;
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                        if (!$is_admin && $u['id'] != $user_id): ?>
                                                            <a href="<?= BASE_URL ?>admin/users.php?action=delete&id=<?= $u['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Ви впевнені, що хочете видалити цього користувача?');">
                                                                <i class="bi bi-trash"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= BASE_URL ?>admin/users.php?page=<?= $current_page - 1 ?>&search=<?= urlencode($search_query) ?>&role=<?= $role_filter ?>&department=<?= $department_filter ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                            <a class="page-link" href="<?= BASE_URL ?>admin/users.php?page=<?= $i ?>&search=<?= urlencode($search_query) ?>&role=<?= $role_filter ?>&department=<?= $department_filter ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= BASE_URL ?>admin/users.php?page=<?= $current_page + 1 ?>&search=<?= urlencode($search_query) ?>&role=<?= $role_filter ?>&department=<?= $department_filter ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($action === 'create' || $action === 'edit'): ?>
                <!-- Форма створення/редагування користувача -->
                <div class="card border-dark shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="mb-0 text-success">
                            <i class="bi bi-person-<?= $action === 'create' ? 'plus' : 'gear' ?> me-2"></i>
                            <?= $action === 'create' ? 'Створення нового користувача' : 'Редагування користувача' ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <form action="<?= BASE_URL ?>admin/users.php" method="post">
                            <input type="hidden" name="form_action" value="<?= $action ?>">
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="edit_id" value="<?= $user_to_edit['id'] ?>">
                                <input type="hidden" name="original_login" value="<?= $user_to_edit['login'] ?>">
                                <input type="hidden" name="original_email" value="<?= $user_to_edit['email'] ?>">
                            <?php endif; ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="full_name" class="form-label">ПІБ <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required 
                                           value="<?= $action === 'edit' ? htmlspecialchars($user_to_edit['full_name']) : '' ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="login" class="form-label">Логін <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="login" name="login" required 
                                           value="<?= $action === 'edit' ? htmlspecialchars($user_to_edit['login']) : '' ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required 
                                           value="<?= $action === 'edit' ? htmlspecialchars($user_to_edit['email']) : '' ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">
                                        Пароль <?= $action === 'create' ? '<span class="text-danger">*</span>' : '' ?>
                                    </label>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           <?= $action === 'create' ? 'required' : '' ?> minlength="4">
                                    <?php if ($action === 'edit'): ?>
                                        <div class="form-text">Залиште порожнім, якщо не хочете змінювати пароль</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Телефон</label>
                                    <input type="text" class="form-control" id="phone" name="phone" 
                                           value="<?= $action === 'edit' ? htmlspecialchars($user_to_edit['phone']) : '' ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="address" class="form-label">Адреса</label>
                                    <input type="text" class="form-control" id="address" name="address" 
                                           value="<?= $action === 'edit' ? htmlspecialchars($user_to_edit['address']) : '' ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Ролі</label>
                                    <div class="border rounded p-3">
                                        <?php foreach ($all_roles as $role): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="role_<?= $role['id'] ?>" name="roles[]" value="<?= $role['id'] ?>" 
                                                       <?= $action === 'edit' && in_array($role['id'], $user_roles) ? 'checked' : '' ?>
                                                       <?= ($action === 'edit' && $user_id_to_edit == $user_id) ? 'disabled' : '' ?>>
                                                <label class="form-check-label" for="role_<?= $role['id'] ?>">
                                                    <?= htmlspecialchars($role['name']) ?>
                                                    <?php if ($action === 'edit' && $user_id_to_edit == $user_id && $role['id'] == 1): ?>
                                                        <input type="hidden" name="roles[]" value="1">
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Відділення</label>
                                    <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                        <?php foreach ($all_departments as $dept): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="dept_<?= $dept['id'] ?>" name="departments[]" value="<?= $dept['id'] ?>" 
                                                       <?= $action === 'edit' && in_array($dept['id'], $user_departments) ? 'checked' : '' ?>
                                                       <?= ($action === 'edit' && $user_id_to_edit == $user_id) ? 'disabled' : '' ?>>
                                                <label class="form-check-label" for="dept_<?= $dept['id'] ?>">
                                                    <?= htmlspecialchars($dept['name']) ?>
                                                </label>
                                                <?php if ($action === 'edit' && $user_id_to_edit == $user_id && in_array($dept['id'], $user_departments)): ?>
                                                    <input type="hidden" name="departments[]" value="<?= $dept['id'] ?>">
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 d-flex justify-content-between">
                                <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Повернутися до списку
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-save me-1"></i> <?= $action === 'create' ? 'Створити користувача' : 'Зберегти зміни' ?>
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
require_once '../../app/views/includes/footer.php';
?>
<script>
function showMessage(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-5`;
    alertDiv.style.zIndex = '9999';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.body.appendChild(alertDiv);
    setTimeout(() => {
        const bootstrapAlert = new bootstrap.Alert(alertDiv);
        bootstrapAlert.close();
    }, 5000);
}
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        const success = urlParams.get('success');
        let message = '';
        if (success === 'created') {
            message = 'Користувача успішно створено.';
        } else if (success === 'updated') {
            message = 'Дані користувача успішно оновлено.';
        } else if (success === 'deleted') {
            message = 'Користувача успішно видалено.';
        }
        if (message) {
            showMessage('success', message);
            const newUrl = window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
        }
    }
    if (urlParams.has('error')) {
        const error = urlParams.get('error');
        let message = '';
        if (error === 'self_delete') {
            message = 'Ви не можете видалити свій власний обліковий запис.';
        } else if (error === 'delete_failed') {
            message = 'Помилка при видаленні користувача.';
        } else if (error === 'admin_delete') {
            message = 'Ви не можете видалити обліковий запис адміністратора.';
        } else {
            message = 'Виникла помилка при обробці запиту.';
        }
        if (message) {
            showMessage('danger', message);
            const newUrl = window.location.pathname;
            window.history.replaceState({}, document.title, newUrl);
        }
    }
});
</script> 