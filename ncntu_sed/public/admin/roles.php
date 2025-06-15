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
$role_id_to_edit = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$fixed_roles = [2, 3];
if ($action == 'delete' && $role_id_to_edit > 0) {
    if (in_array($role_id_to_edit, $fixed_roles)) {
        header("Location: " . BASE_URL . "admin/roles.php?error=fixed_role_delete");
        exit;
    }
    if ($role_id_to_edit == 1) {
        header("Location: " . BASE_URL . "admin/roles.php?error=admin_role_delete");
        exit;
    }
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE FROM user_roles WHERE role_id = ?");
        $stmt->bind_param("i", $role_id_to_edit);
        $stmt->execute();
        $stmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->bind_param("i", $role_id_to_edit);
        $stmt->execute();
        $conn->commit();
        header("Location: " . BASE_URL . "admin/roles.php?success=deleted");
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: " . BASE_URL . "admin/roles.php?error=delete_failed");
    }
    exit;
}
$stmt = $conn->prepare("SELECT * FROM roles ORDER BY name");
$stmt->execute();
$all_roles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role_name = trim($_POST['name']);
    if (empty($role_name)) {
        $errors[] = "Назва ролі обов'язкова для заповнення";
    }
    $is_unique = true;
    if ($_POST['form_action'] === 'create' || ($_POST['form_action'] === 'edit' && $_POST['original_name'] !== $role_name)) {
        $stmt = $conn->prepare("SELECT id FROM roles WHERE name = ?");
        $stmt->bind_param("s", $role_name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Роль з такою назвою вже існує";
            $is_unique = false;
        }
    }
    if ($_POST['form_action'] === 'edit') {
        $edit_id = (int)$_POST['edit_id'];
        if (in_array($edit_id, $fixed_roles)) {
            $errors[] = "Ця роль є фіксованою і не може бути змінена";
        }
        if ($edit_id == 1) {
            $errors[] = "Роль адміністратора не може бути змінена";
        }
    }
    if (empty($errors)) {
        try {
            if ($_POST['form_action'] === 'create') {
                $stmt = $conn->prepare("INSERT INTO roles (name) VALUES (?)");
                $stmt->bind_param("s", $role_name);
                $stmt->execute();
                header("Location: " . BASE_URL . "admin/roles.php?success=created");
            } else if ($_POST['form_action'] === 'edit') {
                $edit_id = (int)$_POST['edit_id'];
                $stmt = $conn->prepare("UPDATE roles SET name = ? WHERE id = ?");
                $stmt->bind_param("si", $role_name, $edit_id);
                $stmt->execute();
                header("Location: " . BASE_URL . "admin/roles.php?success=updated");
            }
            exit;
        } catch (Exception $e) {
            $errors[] = "Помилка збереження даних: " . $e->getMessage();
        }
    }
}
$role_to_edit = null;
if ($action === 'edit' && $role_id_to_edit > 0) {
    $stmt = $conn->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->bind_param("i", $role_id_to_edit);
    $stmt->execute();
    $role_to_edit = $stmt->get_result()->fetch_assoc();
    if ($role_to_edit && (in_array($role_to_edit['id'], $fixed_roles) || $role_to_edit['id'] == 1)) {
        header("Location: " . BASE_URL . "admin/roles.php?error=fixed_role_edit");
        exit;
    }
}
$role_counts = [];
foreach ($all_roles as $role) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_roles WHERE role_id = ?");
    $stmt->bind_param("i", $role['id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $role_counts[$role['id']] = $result['count'];
}
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
                <a href="<?= BASE_URL ?>admin/users.php" class="list-group-item list-group-item-action admin-nav-link">
                    <i class="bi bi-people me-2"></i> Користувачі
                </a>
                <a href="<?= BASE_URL ?>admin/roles.php" class="list-group-item list-group-item-action active admin-nav-link">
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
                <h1 class="h2 text-success"><i class="bi bi-person-badge me-2"></i> Управління ролями користувачів</h1>
                <?php if ($action === 'list'): ?>
                <div>
                    <a href="<?= BASE_URL ?>admin/roles.php?action=create" class="btn btn-success">
                        <i class="bi bi-plus-circle me-1"></i> Додати роль
                    </a>
                </div>
                <?php endif; ?>
            </div>
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
                <!-- Список ролей -->
                <div class="card border-dark shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 text-success">
                            <i class="bi bi-list-ul me-2"></i> Список ролей користувачів
                        </h5>
                    </div>
                    <div class="card-body content-container">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th scope="col">ID</th>
                                        <th scope="col">Назва ролі</th>
                                        <th scope="col">Кількість користувачів</th>
                                        <th scope="col">Статус</th>
                                        <th scope="col">Дії</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($all_roles)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">
                                                Немає жодної ролі у системі.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($all_roles as $role): ?>
                                            <tr>
                                                <th scope="row"><?= $role['id'] ?></th>
                                                <td><?= htmlspecialchars($role['name']) ?></td>
                                                <td><?= $role_counts[$role['id']] ?? 0 ?></td>
                                                <td>
                                                    <?php if ($role['id'] == 1): ?>
                                                        <span class="badge bg-danger">Адміністратор</span>
                                                    <?php elseif (in_array($role['id'], $fixed_roles)): ?>
                                                        <span class="badge bg-warning text-dark">Фіксована роль</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Звичайна роль</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <?php if ($role['id'] != 1 && !in_array($role['id'], $fixed_roles)): ?>
                                                            <a href="<?= BASE_URL ?>admin/roles.php?action=edit&id=<?= $role['id'] ?>" class="btn btn-outline-dark">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <?php if ($role_counts[$role['id']] == 0): ?>
                                                                <a href="<?= BASE_URL ?>admin/roles.php?action=delete&id=<?= $role['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Ви впевнені, що хочете видалити цю роль?');">
                                                                    <i class="bi bi-trash"></i>
                                                                </a>
                                                            <?php else: ?>
                                                                <button type="button" class="btn btn-outline-secondary" disabled title="Не можна видалити роль, яка призначена користувачам">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-outline-secondary" disabled>
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-secondary" disabled>
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($action === 'create' || $action === 'edit'): ?>
                <!-- Форма створення/редагування ролі -->
                <div class="card border-dark shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="mb-0 text-success">
                            <i class="bi bi-<?= $action === 'create' ? 'plus-circle' : 'pencil-square' ?> me-2"></i>
                            <?= $action === 'create' ? 'Створення нової ролі' : 'Редагування ролі' ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <form action="<?= BASE_URL ?>admin/roles.php" method="post">
                            <input type="hidden" name="form_action" value="<?= $action ?>">
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="edit_id" value="<?= $role_to_edit['id'] ?>">
                                <input type="hidden" name="original_name" value="<?= $role_to_edit['name'] ?>">
                            <?php endif; ?>
                            <div class="mb-3">
                                <label for="name" class="form-label">Назва ролі <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required 
                                       value="<?= $action === 'edit' ? htmlspecialchars($role_to_edit['name']) : '' ?>">
                                <div class="form-text">
                                    Введіть назву ролі для користувачів (наприклад, викладач, методист, завідувач тощо).
                                </div>
                            </div>
                            <div class="mt-4 d-flex justify-content-between">
                                <a href="<?= BASE_URL ?>admin/roles.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Повернутися до списку
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-save me-1"></i> <?= $action === 'create' ? 'Створити роль' : 'Зберегти зміни' ?>
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
            message = 'Роль успішно створено.';
        } else if (success === 'updated') {
            message = 'Роль успішно оновлено.';
        } else if (success === 'deleted') {
            message = 'Роль успішно видалено.';
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
        if (error === 'fixed_role_delete') {
            message = 'Неможливо видалити фіксовану роль.';
        } else if (error === 'admin_role_delete') {
            message = 'Неможливо видалити роль адміністратора.';
        } else if (error === 'delete_failed') {
            message = 'Помилка при видаленні ролі.';
        } else if (error === 'fixed_role_edit') {
            message = 'Неможливо редагувати фіксовану роль.';
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