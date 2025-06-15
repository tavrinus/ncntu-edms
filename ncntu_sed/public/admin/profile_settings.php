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
$errors = [];
$success_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $login = trim($_POST['login']);
    $email = trim($_POST['email']);
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    if (empty($full_name)) {
        $errors[] = "ПІБ обов'язкове для заповнення";
    }
    if (empty($login)) {
        $errors[] = "Логін обов'язковий для заповнення";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE login = ? AND id != ?");
        $stmt->bind_param("si", $login, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Логін вже використовується іншим користувачем";
        }
    }
    if (empty($email)) {
        $errors[] = "Email обов'язковий для заповнення";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Введений email має неправильний формат";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Email вже використовується іншим користувачем";
        }
    }
    if (!empty($password) && $password !== $confirm_password) {
        $errors[] = "Паролі не співпадають";
    }
    if (!empty($password) && strlen($password) < 4) {
        $errors[] = "Пароль повинен містити мінімум 4 символи";
    }
    $avatar = $user['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/public/uploads/avatars/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array(strtolower($file_extension), $allowed_extensions)) {
            $errors[] = "Дозволені лише файли з розширеннями: jpg, jpeg, png, gif";
        } else {
            $max_size = MAX_UPLOAD_SIZE;
            if ($_FILES['avatar']['size'] > $max_size) {
                $errors[] = "Розмір файлу перевищує 100MB";
            } else {
                $avatar_name = uniqid('avatar_') . '.' . $file_extension;
                $avatar_path = $upload_dir . $avatar_name;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $avatar_path)) {
                    if (!empty($user['avatar']) && strpos($user['avatar'], 'avatars/') === 0) {
                        $old_avatar_path = $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/public/uploads/' . $user['avatar'];
                        if (file_exists($old_avatar_path)) {
                            unlink($old_avatar_path);
                        }
                    }
                    $avatar = 'avatars/' . $avatar_name;
                } else {
                    $errors[] = "Помилка при завантаженні файлу";
                }
            }
        }
    }
    if (empty($errors)) {
        $user_data = [
            'full_name' => $full_name,
            'login' => $login,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'avatar' => $avatar
        ];
        if (!empty($password)) {
            $user_data['password'] = $password;
        }
        if ($userModel->updateUser($user_id, $user_data)) {
            $success_message = "Дані профілю успішно оновлено";
            $user = $userModel->getUserById($user_id);
        } else {
            $errors[] = "Помилка при оновленні даних користувача";
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
                <a href="<?= BASE_URL ?>admin/departments.php" class="list-group-item list-group-item-action admin-nav-link">
                    <i class="bi bi-diagram-3 me-2"></i> Відділення
                </a>
                <a href="<?= BASE_URL ?>admin/profile_settings.php" class="list-group-item list-group-item-action active admin-nav-link">
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
                <h1 class="h2 text-success"><i class="bi bi-person-gear me-2"></i> Налаштування профілю</h1>
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
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i> <?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <!-- Форма налаштувань профілю -->
            <div class="row">
                <div class="col-12">
                    <div class="card border-dark shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0 text-success">
                                <i class="bi bi-person-gear me-2"></i> Дані профілю
                            </h5>
                        </div>
                        <div class="card-body content-container">
                            <form action="<?= BASE_URL ?>admin/profile_settings.php" method="post" enctype="multipart/form-data">
                                <div class="row">
                                    <!-- Аватар -->
                                    <div class="col-md-4 mb-4 text-center border-end">
                                        <div class="mb-3">
                                            <?php if (!empty($user['avatar'])): ?>
                                                <img src="<?= BASE_URL ?>uploads/<?= $user['avatar'] ?>" class="img-thumbnail rounded-circle mb-3" alt="Аватар" style="width: 150px; height: 150px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded-circle d-flex justify-content-center align-items-center mx-auto mb-3" style="width: 150px; height: 150px;">
                                                    <i class="bi bi-person-fill text-secondary" style="font-size: 5rem;"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div id="avatar-preview-container" style="display: none;">
                                                <img id="avatar-preview" src="#" alt="Попередній перегляд" class="img-thumbnail rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                                            </div>
                                            <label for="avatar" class="form-label">Завантажити новий аватар</label>
                                            <input type="file" class="form-control" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif" onchange="previewAvatar(this);">
                                            <div class="form-text">Дозволені формати: JPG, PNG, GIF. Максимальний розмір: 2MB</div>
                                        </div>
                                    </div>
                                    <!-- Особисті дані -->
                                    <div class="col-md-8 mb-4">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="full_name" class="form-label">ПІБ <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="full_name" name="full_name" required value="<?= htmlspecialchars($user['full_name']) ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="login" class="form-label">Логін <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="login" name="login" required value="<?= htmlspecialchars($user['login']) ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control" id="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="phone" class="form-label">Телефон</label>
                                                <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone']) ?>">
                                            </div>
                                            <div class="col-12 mb-3">
                                                <label for="address" class="form-label">Адреса</label>
                                                <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($user['address']) ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Зміна паролю -->
                                    <div class="col-12">
                                        <h5 class="mb-3 border-bottom pb-2">Зміна паролю</h5>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="password" class="form-label">Новий пароль</label>
                                                <input type="password" class="form-control" id="password" name="password" minlength="4">
                                                <div class="form-text">Залиште порожнім, якщо не хочете змінювати пароль</div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="confirm_password" class="form-label">Підтвердження паролю</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" oninput="validatePassword()">
                                                <div id="password-match-feedback" class="form-text"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end mt-4">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-save me-1"></i> Зберегти зміни
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function previewAvatar(input) {
    const previewContainer = document.getElementById('avatar-preview-container');
    const preview = document.getElementById('avatar-preview');
    const currentAvatar = document.querySelector('img[alt="Аватар"]');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            previewContainer.style.display = 'block';
            if (currentAvatar) {
                currentAvatar.style.display = 'none';
            }
        }
        reader.readAsDataURL(input.files[0]);
    } else {
        previewContainer.style.display = 'none';
        if (currentAvatar) {
            currentAvatar.style.display = 'block';
        }
    }
}
function validatePassword() {
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const feedback = document.getElementById('password-match-feedback');
    if (password.value === confirmPassword.value) {
        if (password.value) {
            feedback.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Паролі співпадають</span>';
        } else {
            feedback.innerHTML = '';
        }
    } else {
        if (confirmPassword.value) {
            feedback.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle"></i> Паролі не співпадають</span>';
        } else {
            feedback.innerHTML = '';
        }
    }
}
</script>
<?php
$conn->close();
require_once '../../app/views/includes/footer.php';
?> 