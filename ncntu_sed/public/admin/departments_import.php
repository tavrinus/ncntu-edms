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
function addDepartment($name, $conn, $parent_id = null) {
    $stmt = $conn->prepare("SELECT id FROM departments WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $existing_id = $result->fetch_assoc()['id'];
        if ($parent_id !== null) {
            $update_stmt = $conn->prepare("UPDATE departments SET parent_id = ? WHERE id = ?");
            $update_stmt->bind_param("ii", $parent_id, $existing_id);
            $update_stmt->execute();
        }
        return $existing_id;
    } else {
        if ($parent_id !== null) {
            $stmt = $conn->prepare("INSERT INTO departments (name, parent_id) VALUES (?, ?)");
            $stmt->bind_param("si", $name, $parent_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
            $stmt->bind_param("s", $name);
        }
        $stmt->execute();
        return $conn->insert_id;
    }
}
function parseTextFile($file_content) {
    $lines = explode("\n", $file_content);
    $departments = [];
    $stack = [&$departments];
    $prev_level = 0;
    $last_node_at_level = [];
    foreach ($lines as $line_num => $line) {
        $line = rtrim($line);
        if (empty(trim($line))) continue;
        $level = 0;
        $temp_line = $line;
        while (mb_strlen($temp_line) > 0 && (mb_substr($temp_line, 0, 1) === ' ' || mb_substr($temp_line, 0, 1) === "\t")) {
            if (mb_substr($temp_line, 0, 1) === "\t") {
                $level += 4;
            } else {
                $level++;
            }
            $temp_line = mb_substr($temp_line, 1);
        }
        $level = floor($level / 4);
        $name = trim($line);
        if (empty($name)) continue;
        $node = ['name' => $name, 'children' => [], 'level' => $level];
        if ($level > $prev_level) {
            if ($level - $prev_level > 1) {
                $error_line = $line_num + 1;
                throw new Exception("Помилка в структурі на рядку $error_line: '$name'. Неможливо перейти з рівня $prev_level на рівень $level без проміжних рівнів");
            }
            $parent = &$stack[count($stack) - 1];
            if (!empty($parent)) {
                $last_index = count($parent) - 1;
                if ($last_index >= 0) {
                    $stack[] = &$parent[$last_index]['children'];
                }
            }
        } elseif ($level < $prev_level) {
            $steps_back = $prev_level - $level;
            for ($i = 0; $i < $steps_back; $i++) {
                if (count($stack) > 1) {
                    array_pop($stack);
                }
            }
        }
        $current = &$stack[count($stack) - 1];
        $current[] = $node;
        $last_node_at_level[$level] = &$current[count($current) - 1];
        $prev_level = $level;
    }
    return $departments;
}
function getExampleText($example_id = 1) {
    $examples = [
        1 => "Коледж
    Приймальна комісія
    Навчальна частина
    Циклові комісії
        Циклова комісія автомеханічних дисциплін
        Циклова комісія програмування та інформатики
    Відділ кадрів",
        2 => "Чернігівський відокремлений підрозділ
    Приймальна комісія
    Навчальна частина
Київський відокремлений підрозділ
    Приймальна комісія
    Відділ інформаційних технологій
        Серверна підтримка
        Техпідтримка користувачів
Одеський відокремлений підрозділ
    Відділ по роботі зі студентами",
        3 => "Головний корпус
    Адміністрація
        Ректорат
        Бухгалтерія
        Юридичний відділ
    Відділ ІТ
        Підтримка студентів
        Адміністрування мереж
    Приймальна комісія
Корпус №2
    Кафедра математики
    Кафедра інформатики
        Відділ програмування
        Відділ комп'ютерних мереж
    Бібліотека
Гуртожиток
    Адміністрація гуртожитку
    Господарська частина",
    ];
    return isset($examples[$example_id]) ? $examples[$example_id] : $examples[1];
}
$imported_departments = null;
$file_uploaded = false;
$messages = [];
$import_success = false;
$show_example = isset($_GET['example']) ? (int)$_GET['example'] : 0;
$example_text = '';
if ($show_example > 0 && $show_example <= 3) {
    $example_text = getExampleText($show_example);
    $imported_departments = parseTextFile($example_text);
    $file_uploaded = true;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['structure_file']) && $_FILES['structure_file']['error'] === UPLOAD_ERR_OK && 
        (!isset($_POST['use_example']) || $_POST['use_example'] != '1')) {
        try {
            $file_content = file_get_contents($_FILES['structure_file']['tmp_name']);
            $imported_departments = parseTextFile($file_content);
            $file_uploaded = true;
        } catch (Exception $e) {
            $messages[] = "Помилка при обробці файлу: " . $e->getMessage();
        }
    } 
    elseif (isset($_POST['use_example']) && $_POST['use_example'] == '1' && isset($_POST['example_text'])) {
        try {
            $example_text = $_POST['example_text'];
            $imported_departments = parseTextFile($example_text);
            $file_uploaded = true;
        } catch (Exception $e) {
            $messages[] = "Помилка при обробці прикладу: " . $e->getMessage();
        }
    }
    elseif (!isset($_POST['confirm_import'])) {
        $messages[] = "Необхідно завантажити файл або вибрати приклад для імпорту";
    }
    if (isset($_POST['confirm_import']) && $_POST['confirm_import'] === 'yes' && isset($_POST['structure_data'])) {
        try {
            $imported_departments = json_decode($_POST['structure_data'], true);
            if (!$imported_departments) {
                throw new Exception("Помилка декодування даних структури");
            }
            $conn->begin_transaction();
            function processImport($departments, $conn, $parent_id = null) {
                global $messages;
                foreach ($departments as $dept) {
                    $name = $dept['name'];
                    $children = $dept['children'];
                    $level = isset($dept['level']) ? $dept['level'] : 0;
                    $dept_id = addDepartment($name, $conn, $parent_id);
                    if ($parent_id) {
                        $parent_name = '';
                        $parent_query = $conn->prepare("SELECT name FROM departments WHERE id = ?");
                        $parent_query->bind_param("i", $parent_id);
                        $parent_query->execute();
                        $parent_result = $parent_query->get_result();
                        if ($parent_result->num_rows > 0) {
                            $parent_name = $parent_result->fetch_assoc()['name'];
                        }
                        $messages[] = "Додано відділення: $name (рівень: $level, батьківське відділення: $parent_name, ID: $parent_id)";
                    } else {
                        $messages[] = "Додано відділення: $name (рівень: $level, кореневе)";
                    }
                    if (!empty($children)) {
                        processImport($children, $conn, $dept_id);
                    }
                }
            }
            processImport($imported_departments, $conn, null);
            $conn->commit();
            $import_success = true;
            $_SESSION['department_success'] = 'imported';
        } catch (Exception $e) {
            $conn->rollback();
            $messages[] = "Помилка: " . $e->getMessage();
            $import_success = false;
        }
    }
}
require_once '../../app/views/includes/header.php';
?>
<div class="container-fluid mt-3">
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
                <a href="<?= BASE_URL ?>admin/dashboard.php" class="list-group-item list-group-item-action admin-nav-link">
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
                <a href="<?= BASE_URL ?>admin/departments.php" class="list-group-item list-group-item-action active admin-nav-link">
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
                <h1 class="h2 text-success"><i class="bi bi-cloud-upload me-2"></i> Імпорт структури відділень</h1>
            </div>
            <div class="card border-dark shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0 text-success"><i class="bi bi-cloud-upload me-2"></i> Імпорт структури відділень</h5>
                </div>
                <div class="card-body content-container">
                    <?php if ($import_success): ?>
                        <div class="alert alert-success">
                            <h5 class="alert-heading">Імпорт успішно завершено!</h5>
                            <?php if (!empty($messages)): ?>
                                <ul class="mb-0">
                                    <?php foreach ($messages as $message): ?>
                                        <li><?= $message ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                        <div class="text-center mt-4">
                            <a href="<?= BASE_URL ?>admin/departments.php" class="btn btn-success">
                                <i class="bi bi-arrow-left me-1"></i> Повернутися до відділень
                            </a>
                        </div>
                    <?php elseif ($file_uploaded && $imported_departments): ?>
                        <p class="lead">Перевірте структуру, яка буде імпортована:</p>
                        <div class="alert alert-warning">
                            <h5 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i> Увага!</h5>
                            <p>Цей процес додасть до бази даних нові відділення згідно із завантаженою структурою. Існуючі відділення не будуть змінені.</p>
                        </div>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Структура для імпорту:</h5>
                            </div>
                            <div class="card-body content-container">
                                <div class="department-tree">
                                    <?php 
                                    function renderDeptTree($departments, $level = 0) {
                                        echo '<ul class="list-group mb-0">';
                                        foreach ($departments as $dept) {
                                            if ($level === 0) {
                                                $item_class = 'list-group-item list-group-item-action list-group-item-primary fw-bold';
                                                $icon = '<i class="bi bi-diagram-3 me-2"></i>';
                                            } elseif ($level === 1) {
                                                $item_class = 'list-group-item list-group-item-action list-group-item-secondary ms-3';
                                                $icon = '<i class="bi bi-arrow-return-right me-2"></i>';
                                            } elseif ($level === 2) {
                                                $item_class = 'list-group-item list-group-item-action list-group-item-light ms-5';
                                                $icon = '<i class="bi bi-dot me-2"></i>';
                                            } else {
                                                $item_class = 'list-group-item list-group-item-action ms-' . ($level * 2 + 3);
                                                $icon = '<i class="bi bi-three-dots-vertical me-2"></i>';
                                            }
                                            echo "<li class='{$item_class}'>{$icon}" . htmlspecialchars($dept['name']) . "</li>";
                                            if (!empty($dept['children'])) {
                                                renderDeptTree($dept['children'], $level + 1);
                                            }
                                        }
                                        echo '</ul>';
                                    }
                                    renderDeptTree($imported_departments);
                                    ?>
                                </div>
                            </div>
                        </div>
                        <form action="<?= BASE_URL ?>admin/departments_import.php" method="post" class="mt-4">
                            <input type="hidden" name="confirm_import" value="yes">
                            <input type="hidden" name="structure_data" value="<?= htmlspecialchars(json_encode($imported_departments)) ?>">
                            <div class="d-flex justify-content-between">
                                <a href="<?= BASE_URL ?>admin/departments_import.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Повернутися до завантаження
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-cloud-upload me-1"></i> Підтвердити імпорт
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="lead">Завантажте текстовий файл зі структурою відділень.</p>
                        <div class="alert alert-info">
                            <h5 class="alert-heading"><i class="bi bi-info-circle me-2"></i> Формат файлу</h5>
                            <p>Кожен рядок файлу повинен містити назву відділення. Рівень ієрархії визначається кількістю відступів (пробілів або табуляцій) на початку рядка. Рекомендується використовувати 4 пробіли або 1 табуляцію для кожного рівня ієрархії.</p>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">Приклади структур для імпорту:</span>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= BASE_URL ?>admin/departments_import.php?example=1" class="btn btn-outline-primary">
                                            Приклад 1
                                        </a>
                                        <a href="<?= BASE_URL ?>admin/departments_import.php?example=2" class="btn btn-outline-primary">
                                            Приклад 2 (декілька кореневих)
                                        </a>
                                        <a href="<?= BASE_URL ?>admin/departments_import.php?example=3" class="btn btn-outline-primary">
                                            Приклад 3 (складна структура)
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <pre class="mb-0 bg-white p-2 rounded"><code>Коледж
    Приймальна комісія
    Навчальна частина
    Циклові комісії
        Циклова комісія автомеханічних дисциплін
        Циклова комісія програмування та інформатики
    Відділ кадрів</code></pre>
                        </div>
                        <form action="<?= BASE_URL ?>admin/departments_import.php" method="post" enctype="multipart/form-data" class="mt-4">
                            <div class="mb-3">
                                <label for="structure_file" class="form-label">Файл структури відділень</label>
                                <input type="file" class="form-control" id="structure_file" name="structure_file" accept=".txt" required>
                                <div class="form-text">Виберіть текстовий файл з ієрархічною структурою відділень</div>
                            </div>
                            <?php if ($show_example > 0): ?>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="use_example" name="use_example" checked>
                                    <label class="form-check-label" for="use_example">
                                        Використати обраний приклад замість файлу
                                    </label>
                                </div>
                                <textarea class="form-control mt-2" id="example_preview" rows="10" disabled><?= htmlspecialchars($example_text) ?></textarea>
                                <div class="form-text">Цей приклад буде використано для імпорту, якщо прапорець вище увімкнено</div>
                                <input type="hidden" name="example_text" value="<?= htmlspecialchars($example_text) ?>">
                                <input type="hidden" name="example_id" value="<?= $show_example ?>">
                            </div>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between">
                                <a href="<?= BASE_URL ?>admin/departments.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Повернутися до відділень
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-upload me-1"></i> Завантажити і проаналізувати
                                </button>
                            </div>
                        </form>
                        <?php if (!empty($messages)): ?>
                            <div class="alert alert-danger mt-3">
                                <h5 class="alert-heading">Помилки при обробці файлу:</h5>
                                <ul class="mb-0">
                                    <?php foreach ($messages as $message): ?>
                                        <li><?= $message ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
.department-tree .list-group-item {
    border-left: 3px solid #dee2e6;
    transition: all 0.2s;
    word-wrap: break-word;
    word-break: break-word;
    overflow-wrap: break-word;
    hyphens: auto;
    max-width: 100%;
}
.department-tree .list-group-item:hover {
    border-left-color: #198754;
}
.department-tree ul {
    padding-left: 0;
    max-width: 100%;
    width: 100%;
}
.department-tree .list-group-item.ms-3 {
    margin-left: 1rem !important;
    width: calc(100% - 1rem);
}
.department-tree .list-group-item.ms-5 {
    margin-left: 2rem !important;
    width: calc(100% - 2rem);
}
.department-tree .list-group-item.ms-7 {
    margin-left: 3rem !important;
    width: calc(100% - 3rem);
}
.department-tree .list-group-item.ms-9 {
    margin-left: 4rem !important;
    width: calc(100% - 4rem);
}
.department-tree .list-group-item.ms-11 {
    margin-left: 5rem !important;
    width: calc(100% - 5rem);
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var fileInput = document.getElementById('structure_file');
    var useExampleCheckbox = document.getElementById('use_example');
    var examplePreview = document.getElementById('example_preview');
    var submitButton = document.querySelector('button[type="submit"]');
    function updateFileInputState() {
        if (useExampleCheckbox && useExampleCheckbox.checked) {
            fileInput.disabled = true;
            fileInput.required = false;
            if (examplePreview) {
                examplePreview.classList.add('bg-light');
            }
        } else {
            fileInput.disabled = false;
            fileInput.required = true;
            if (examplePreview) {
                examplePreview.classList.remove('bg-light');
            }
        }
    }
    if (useExampleCheckbox) {
        updateFileInputState();
        useExampleCheckbox.addEventListener('change', function() {
            updateFileInputState();
        });
    }
});
</script>
<?php
$conn->close();
require_once '../../app/views/includes/footer.php';
?> 