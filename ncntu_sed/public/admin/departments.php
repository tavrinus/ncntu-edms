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
$department_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
function deleteRecursive($department_id, $conn) {
    $stmt = $conn->prepare("SELECT id FROM departments WHERE parent_id = ?");
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        deleteRecursive($row['id'], $conn);
    }
    $stmt = $conn->prepare("DELETE FROM user_departments WHERE department_id = ?");
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    return true;
}
if ($action == 'delete' && $department_id > 0) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM departments WHERE parent_id = ?");
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $has_children = $result->fetch_assoc()['count'] > 0;
    if ($has_children) {
        if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
            $conn->begin_transaction();
            try {
                deleteRecursive($department_id, $conn);
                $conn->commit();
                $_SESSION['department_success'] = 'deleted_with_children';
                header("Location: " . BASE_URL . "admin/departments.php");
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['department_error'] = 'delete_failed';
                header("Location: " . BASE_URL . "admin/departments.php");
            }
        } else {
            $_SESSION['department_warning'] = [
                'message' => 'Це відділення має підлеглі відділення. При видаленні будуть видалені всі підлеглі відділення!',
                'id' => $department_id,
                'has_children' => true
            ];
            header("Location: " . BASE_URL . "admin/departments.php");
        }
        exit;
    }
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_departments WHERE department_id = ?");
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $has_users = $result->fetch_assoc()['count'] > 0;
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM documents WHERE department_id = ?");
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $has_documents = $result->fetch_assoc()['count'] > 0;
    if ($has_users || $has_documents) {
        if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
            $conn->begin_transaction();
            try {
                if ($has_users) {
                    $stmt = $conn->prepare("DELETE FROM user_departments WHERE department_id = ?");
                    $stmt->bind_param("i", $department_id);
                    $stmt->execute();
                }
                if ($has_documents) {
                    $stmt = $conn->prepare("UPDATE documents SET department_id = NULL WHERE department_id = ?");
                    $stmt->bind_param("i", $department_id);
                    $stmt->execute();
                }
                $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
                $stmt->bind_param("i", $department_id);
                $stmt->execute();
                $conn->commit();
                $_SESSION['department_success'] = 'deleted_with_relations';
                header("Location: " . BASE_URL . "admin/departments.php");
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['department_error'] = 'delete_failed';
                header("Location: " . BASE_URL . "admin/departments.php");
            }
            exit;
        } else {
            $warning_message = 'Це відділення має ';
            if ($has_users && $has_documents) {
                $warning_message .= 'прив\'язаних користувачів та документи. ';
            } elseif ($has_users) {
                $warning_message .= 'прив\'язаних користувачів. ';
            } else {
                $warning_message .= 'прив\'язані документи. ';
            }
            $warning_message .= 'При видаленні відділення ці зв\'язки будуть видалені, але самі користувачі та документи залишаться в системі.';
            $_SESSION['department_warning'] = [
                'message' => $warning_message,
                'id' => $department_id,
                'has_relations' => true
            ];
            header("Location: " . BASE_URL . "admin/departments.php");
            exit;
        }
    }
    $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $_SESSION['department_success'] = 'deleted';
        header("Location: " . BASE_URL . "admin/departments.php");
    } else {
        $_SESSION['department_error'] = 'delete_failed';
        header("Location: " . BASE_URL . "admin/departments.php");
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $errors = [];
    if (empty($name)) {
        $errors[] = "Назва відділення обов'язкова для заповнення";
    }
    if ($_POST['form_action'] === 'create') {
        if ($parent_id) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM departments WHERE name = ? AND parent_id = ?");
            $stmt->bind_param("si", $name, $parent_id);
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM departments WHERE name = ? AND parent_id IS NULL");
            $stmt->bind_param("s", $name);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->fetch_assoc()['count'] > 0) {
            $errors[] = "Відділення з такою назвою вже існує на цьому рівні ієрархії";
        }
    } else if ($_POST['form_action'] === 'edit') {
        $edit_id = (int)$_POST['edit_id'];
        if ($parent_id) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM departments WHERE name = ? AND parent_id = ? AND id != ?");
            $stmt->bind_param("sii", $name, $parent_id, $edit_id);
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM departments WHERE name = ? AND parent_id IS NULL AND id != ?");
            $stmt->bind_param("si", $name, $edit_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->fetch_assoc()['count'] > 0) {
            $errors[] = "Відділення з такою назвою вже існує на цьому рівні ієрархії";
        }
    }
    if ($_POST['form_action'] === 'edit' && !empty($parent_id) && isset($_POST['edit_id'])) {
        $edit_id = (int)$_POST['edit_id'];
        if ($parent_id == $edit_id) {
            $errors[] = "Відділення не може бути батьківським для себе";
        }
    }
    if (empty($errors)) {
        if ($_POST['form_action'] === 'create') {
            if ($parent_id) {
                $stmt = $conn->prepare("INSERT INTO departments (name, parent_id) VALUES (?, ?)");
                $stmt->bind_param("si", $name, $parent_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
                $stmt->bind_param("s", $name);
            }
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $_SESSION['department_success'] = 'created';
                header("Location: " . BASE_URL . "admin/departments.php");
                exit;
            } else {
                $errors[] = "Помилка при створенні відділення";
            }
        } else if ($_POST['form_action'] === 'edit') {
            $edit_id = (int)$_POST['edit_id'];
            if ($parent_id == $edit_id) {
                $errors[] = "Відділення не може бути батьківським для себе";
            } else {
                if ($parent_id) {
                    $stmt = $conn->prepare("UPDATE departments SET name = ?, parent_id = ? WHERE id = ?");
                    $stmt->bind_param("sii", $name, $parent_id, $edit_id);
                } else {
                    $stmt = $conn->prepare("UPDATE departments SET name = ?, parent_id = NULL WHERE id = ?");
                    $stmt->bind_param("si", $name, $edit_id);
                }
                $stmt->execute();
                if ($stmt->affected_rows >= 0) {
                    $_SESSION['department_success'] = 'updated';
                    header("Location: " . BASE_URL . "admin/departments.php");
                    exit;
                } else {
                    $errors[] = "Помилка при оновленні відділення";
                }
            }
        }
    }
}
$department = null;
if ($action === 'edit' && $department_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $department = $stmt->get_result()->fetch_assoc();
}
$stmt = $conn->prepare("SELECT d.*, p.name as parent_name 
                       FROM departments d 
                       LEFT JOIN departments p ON d.parent_id = p.id 
                       ORDER BY d.parent_id IS NULL DESC, d.parent_id, d.name");
$stmt->execute();
$departments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$departments_hierarchy = [];
$departments_map = [];
foreach ($departments as $dept) {
    $dept['children'] = [];
    $departments_map[$dept['id']] = $dept;
}
foreach ($departments as $dept) {
    $dept_id = $dept['id'];
    if (!empty($dept['parent_id']) && isset($departments_map[$dept['parent_id']])) {
        $departments_map[$dept['parent_id']]['children'][] = &$departments_map[$dept_id];
    } else {
        $departments_hierarchy[] = &$departments_map[$dept_id];
    }
}
$success_message = '';
$error_message = '';
$warning_message = null;
if (isset($_SESSION['department_success'])) {
    $success = $_SESSION['department_success'];
    if ($success === 'created') {
        $success_message = 'Відділення успішно створено.';
    } elseif ($success === 'updated') {
        $success_message = 'Відділення успішно оновлено.';
    } elseif ($success === 'deleted') {
        $success_message = 'Відділення успішно видалено.';
    } elseif ($success === 'deleted_with_children') {
        $success_message = 'Відділення та всі його підрозділи успішно видалено.';
    } elseif ($success === 'deleted_with_relations') {
        $success_message = 'Відділення успішно видалено. Зв\'язки з користувачами та документами видалено.';
    } elseif ($success === 'imported') {
        $success_message = 'Структуру відділень успішно імпортовано.';
    }
    unset($_SESSION['department_success']);
}
if (isset($_SESSION['department_error'])) {
    $error = $_SESSION['department_error'];
    if ($error === 'has_children') {
        $error_message = 'Неможливо видалити відділення, яке має підлеглі відділення.';
    } elseif ($error === 'has_users') {
        $error_message = 'Неможливо видалити відділення, до якого прикріплені користувачі.';
    } elseif ($error === 'has_documents') {
        $error_message = 'Неможливо видалити відділення, оскільки до нього прив\'язані документи. Спочатку змініть відділення у документах або видаліть їх.';
    } elseif ($error === 'delete_failed') {
        $error_message = 'Помилка при видаленні відділення.';
    } else {
        $error_message = 'Виникла помилка при обробці запиту.';
    }
    unset($_SESSION['department_error']);
}
if (isset($_SESSION['department_warning'])) {
    $warning_message = $_SESSION['department_warning'];
    unset($_SESSION['department_warning']);
}
$conn->close();
function renderDepartmentTree($department, $level = 0) {
    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
    if ($level === 0) {
        $row_class = 'table-primary';
        $name_class = 'fw-bold';
        $icon = '<i class="bi bi-diagram-3 me-2"></i>';
    } elseif ($level === 1) {
        $row_class = 'table-secondary';
        $name_class = '';
        $icon = '<i class="bi bi-arrow-return-right me-2"></i>';
    } elseif ($level === 2) {
        $row_class = 'table-light';
        $name_class = '';
        $icon = '<i class="bi bi-dot me-2"></i>';
    } else {
        $row_class = '';
        $name_class = 'text-muted';
        $icon = '<i class="bi bi-three-dots-vertical me-2"></i>';
    }
    $collapse_class = '';
    $collapse_icon = '';
    $has_children = !empty($department['children']);
    if ($has_children) {
        $collapse_class = 'dept-parent';
        $collapse_icon = '<i class="bi bi-caret-right-fill toggle-icon me-1"></i>';
    }
    ?>
    <tr class="<?= $row_class ?> <?= $collapse_class ?>" data-department-id="<?= $department['id'] ?>">
        <td class="<?= $name_class ?>">
            <?= $indent ?>
            <?= $collapse_icon ?>
            <?= $icon ?><?= htmlspecialchars($department['name']) ?>
        </td>
        <td class="parent-department">
            <?= !empty($department['parent_name']) ? htmlspecialchars($department['parent_name']) : '<span class="text-muted">—</span>' ?>
        </td>
        <td class="text-end">
            <div class="btn-group btn-group-sm">
                <a href="<?= BASE_URL ?>admin/departments.php?action=edit&id=<?= $department['id'] ?>" class="btn btn-outline-dark" title="Редагувати">
                    <i class="bi bi-pencil"></i>
                </a>
                <a href="<?= BASE_URL ?>admin/departments.php?action=delete&id=<?= $department['id'] ?>" class="btn btn-outline-danger" 
                   onclick="return confirm('Ви впевнені, що хочете видалити це відділення?');" title="Видалити">
                    <i class="bi bi-trash"></i>
                </a>
            </div>
        </td>
    </tr>
    <?php
    if ($has_children) {
        foreach ($department['children'] as $child) {
            echo '<tr class="dept-child child-of-'.$department['id'].'" style="display: none;">';
            echo '<td colspan="3" style="padding: 0;">';
            echo '<table class="table mb-0 department-table" style="width:100%;">';
            echo '<thead style="display:none;"><tr><th></th><th></th><th></th></tr></thead>';
            echo '<tbody>';
            renderDepartmentTree($child, $level + 1);
            echo '</tbody>';
            echo '</table>';
            echo '</td>';
            echo '</tr>';
        }
    }
}
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
                <a href="<?= BASE_URL ?>admin/dashboard.php" class="list-group-item list-group-item-action admin-nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2 me-2"></i> Головна панель
                </a>
                <a href="<?= BASE_URL ?>admin/users.php" class="list-group-item list-group-item-action admin-nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
                    <i class="bi bi-people me-2"></i> Користувачі
                </a>
                <a href="<?= BASE_URL ?>admin/roles.php" class="list-group-item list-group-item-action admin-nav-link <?= basename($_SERVER['PHP_SELF']) == 'roles.php' ? 'active' : '' ?>">
                    <i class="bi bi-person-badge me-2"></i> Ролі користувачів
                </a>
                <a href="<?= BASE_URL ?>admin/documents.php" class="list-group-item list-group-item-action admin-nav-link <?= basename($_SERVER['PHP_SELF']) == 'documents.php' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-text me-2"></i> Документи
                </a>
                <a href="<?= BASE_URL ?>admin/archived_documents.php" class="list-group-item list-group-item-action admin-nav-link <?= basename($_SERVER['PHP_SELF']) == 'archived_documents.php' ? 'active' : '' ?>">
                    <i class="bi bi-archive me-2"></i> Архів документів
                </a>
                <a href="<?= BASE_URL ?>admin/departments.php" class="list-group-item list-group-item-action admin-nav-link <?= basename($_SERVER['PHP_SELF']) == 'departments.php' ? 'active' : '' ?>">
                    <i class="bi bi-diagram-3 me-2"></i> Відділення
                </a>
                <a href="<?= BASE_URL ?>admin/profile_settings.php" class="list-group-item list-group-item-action admin-nav-link <?= basename($_SERVER['PHP_SELF']) == 'profile_settings.php' ? 'active' : '' ?>">
                    <i class="bi bi-person-gear me-2"></i> Налаштування профілю
                </a>
                <a href="<?= BASE_URL ?>logout.php" class="list-group-item list-group-item-action text-danger admin-nav-link">
                    <i class="bi bi-box-arrow-right me-2"></i> Вихід
                </a>
            </div>
        </div>
        <div class="col-md-9 col-lg-10 ms-sm-auto px-md-4">
            <!-- Breadcrumb removed -->
            <!-- Продовжуємо основний контент -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom border-dark">
                <h1 class="h2 text-success"><i class="bi bi-diagram-3 me-2"></i> Управління відділеннями</h1>
            </div>
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($warning_message): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Увага!</h5>
                    <p><?= $warning_message['message'] ?></p>
                    <hr>
                    <div class="d-flex justify-content-end">
                        <a href="<?= BASE_URL ?>admin/departments.php" class="btn btn-secondary me-2">Скасувати</a>
                        <a href="<?= BASE_URL ?>admin/departments.php?action=delete&id=<?= $warning_message['id'] ?>&confirm=yes" class="btn btn-danger">
                            <?php if (isset($warning_message['has_children']) && $warning_message['has_children']): ?>
                                Видалити з усіма підрозділами
                            <?php elseif (isset($warning_message['has_relations']) && $warning_message['has_relations']): ?>
                                Видалити відділення та зв'язки
                            <?php else: ?>
                                Видалити
                            <?php endif; ?>
                        </a>
                    </div>
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
                <div class="card border-dark shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0 text-success"><i class="bi bi-diagram-3 me-2"></i> Структура відділень</h4>
                            <div>
                                <a href="<?= BASE_URL ?>admin/departments_import.php" class="btn btn-outline-primary me-2">
                                    <i class="bi bi-cloud-upload me-1"></i> Імпорт структури
                                </a>
                                <a href="<?= BASE_URL ?>admin/departments.php?action=create" class="btn btn-success">
                                    <i class="bi bi-plus-lg me-1"></i> Додати відділення
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($departments)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i> В системі ще немає відділень. Додайте перше відділення.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover department-table">
                                    <thead>
                                        <tr>
                                            <th scope="col" style="width: 60%;">Назва відділення</th>
                                            <th scope="col" style="width: 25%; text-align: center;">Батьківське відділення</th>
                                            <th scope="col" class="text-end" style="width: 15%;">Дії</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        foreach ($departments_hierarchy as $dept) {
                                            renderDepartmentTree($dept);
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($action === 'create' || $action === 'edit'): ?>
                <div class="card border-dark shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="mb-0 text-success">
                            <i class="bi bi-diagram-3 me-2"></i>
                            <?= $action === 'create' ? 'Додавання нового відділення' : 'Редагування відділення' ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <form action="<?= BASE_URL ?>admin/departments.php" method="post">
                            <input type="hidden" name="form_action" value="<?= $action ?>">
                            <?php if ($action === 'edit'): ?>
                                <input type="hidden" name="edit_id" value="<?= $department['id'] ?>">
                            <?php endif; ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Назва відділення <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required 
                                           value="<?= $action === 'edit' ? htmlspecialchars($department['name']) : '' ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="parent_id" class="form-label">Батьківське відділення</label>
                                    <select class="form-select" id="parent_id" name="parent_id">
                                        <option value="">-- Без батьківського відділення --</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <?php
                                            if ($action === 'edit' && $dept['id'] == $department_id) continue;
                                            ?>
                                            <option value="<?= $dept['id'] ?>" <?= ($action === 'edit' && isset($department['parent_id']) && $department['parent_id'] == $dept['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($dept['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Виберіть батьківське відділення, якщо це підрозділ. Або залиште порожнім для створення відділення верхнього рівня.</div>
                                </div>
                            </div>
                            <div class="mt-4 d-flex justify-content-between">
                                <a href="<?= BASE_URL ?>admin/departments.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Повернутися до списку
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-save me-1"></i> <?= $action === 'create' ? 'Створити відділення' : 'Зберегти зміни' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Стилі для відділень -->
<style>
.dept-parent {
    cursor: pointer;
}
.dept-parent:hover {
    background-color: rgba(0, 0, 0, 0.05);
}
.toggle-icon {
    transition: transform 0.2s;
}
.department-table {
    table-layout: fixed;
}
.department-table th:nth-child(2) {
    text-align: center;
}
.parent-department {
    text-align: center; 
    white-space: normal;
    overflow: visible;
    padding-left: 0; 
}
.dept-child .department-table th {
    display: none; 
}
.department-table td:nth-child(1) {
    width: 60%;
}
.department-table td:nth-child(2) {
    width: 25%;
}
.department-table td:nth-child(3) {
    width: 15%;
}
.department-table td:nth-child(1) {
    width: 60%;
}
.department-table td:nth-child(2) {
    width: 25%;
}
.department-table td:nth-child(3) {
    width: 15%;
}
</style>
<!-- JavaScript для роботи зі структурою відділень -->
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
function saveHierarchyState() {
    const expandedDepartments = [];
    document.querySelectorAll('.dept-parent').forEach(parent => {
        const deptId = parent.getAttribute('data-department-id');
        const isExpanded = parent.querySelector('.toggle-icon').classList.contains('bi-caret-down-fill');
        if (isExpanded) {
            expandedDepartments.push(deptId);
        }
    });
    localStorage.setItem('expandedDepartments', JSON.stringify(expandedDepartments));
}
document.addEventListener('DOMContentLoaded', function() {
    const deptParents = document.querySelectorAll('.dept-parent');
    let expandedDepartments = [];
    try {
        const savedState = localStorage.getItem('expandedDepartments');
        if (savedState) {
            expandedDepartments = JSON.parse(savedState);
        }
    } catch (e) {
        console.error('Помилка при відновленні стану ієрархії:', e);
    }
    deptParents.forEach(parent => {
        const deptId = parent.getAttribute('data-department-id');
        const toggleIcon = parent.querySelector('.toggle-icon');
        const children = document.querySelectorAll(`.child-of-${deptId}`);
        if (expandedDepartments.includes(deptId)) {
            toggleIcon.classList.remove('bi-caret-right-fill');
            toggleIcon.classList.add('bi-caret-down-fill');
            children.forEach(child => {
                child.style.display = '';
            });
        }
        parent.addEventListener('click', function(e) {
            if (e.target.closest('.btn-group')) {
                return;
            }
            if (toggleIcon) {
                if (toggleIcon.classList.contains('bi-caret-down-fill')) {
                    toggleIcon.classList.remove('bi-caret-down-fill');
                    toggleIcon.classList.add('bi-caret-right-fill');
                } else {
                    toggleIcon.classList.remove('bi-caret-right-fill');
                    toggleIcon.classList.add('bi-caret-down-fill');
                }
            }
            children.forEach(child => {
                if (child.style.display === 'none') {
                    child.style.display = '';
                } else {
                    child.style.display = 'none';
                }
            });
            saveHierarchyState();
        });
    });
    <?php if (empty($success_message) && empty($error_message)): ?>
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            const success = urlParams.get('success');
            let message = '';
            if (success === 'created') {
                message = 'Відділення успішно створено.';
            } else if (success === 'updated') {
                message = 'Відділення успішно оновлено.';
            } else if (success === 'deleted') {
                message = 'Відділення успішно видалено.';
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
            if (error === 'has_children') {
                message = 'Неможливо видалити відділення, яке має підлеглі відділення.';
            } else if (error === 'has_users') {
                message = 'Неможливо видалити відділення, до якого прикріплені користувачі.';
            } else if (error === 'has_documents') {
                message = 'Неможливо видалити відділення, оскільки до нього прив\'язані документи. Спочатку змініть відділення у документах або видаліть їх.';
            } else if (error === 'delete_failed') {
                message = 'Помилка при видаленні відділення.';
            } else {
                message = 'Виникла помилка при обробці запиту.';
            }
            if (message) {
                showMessage('danger', message);
                const newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            }
        }
    <?php endif; ?>
});
</script>
<?php
require_once '../../app/views/includes/footer.php';
?> 