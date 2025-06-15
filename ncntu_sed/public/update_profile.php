<?php
require_once '../config/config.php';
require_once '../app/models/User.php';
if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$userModel = new User();
$success_message = '';
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $login = isset($_POST['login']) ? trim($_POST['login']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $errors = [];
    if (empty($full_name)) {
        $errors[] = "Повне ім'я обов'язкове";
    }
    if (empty($login)) {
        $errors[] = "Логін обов'язковий";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
        $errors[] = "Логін може містити тільки літери, цифри та символ підкреслення";
    }
    if (empty($email)) {
        $errors[] = "Email обов'язковий";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Некоректний формат email";
    }
    $current_user = $userModel->getUserById($user_id);
    if ($login !== $current_user['login']) {
        if (!$userModel->isLoginUnique($login)) {
            $errors[] = "Користувач з таким логіном вже існує";
        }
    }
    if ($email !== $current_user['email']) {
        if (!$userModel->isEmailUnique($email)) {
            $errors[] = "Користувач з таким email вже існує";
        }
    }
    if (!empty($new_password) || !empty($confirm_password)) {
        if (empty($current_password)) {
            $errors[] = "Введіть поточний пароль для зміни паролю";
        } elseif (!password_verify($current_password, $current_user['password'])) {
            $errors[] = "Поточний пароль невірний";
        }
        if (empty($new_password)) {
            $errors[] = "Новий пароль не може бути порожнім";
        } elseif (strlen($new_password) < 4) {
            $errors[] = "Новий пароль повинен містити не менше 4 символів";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "Новий пароль та підтвердження не співпадають";
        }
    }
    $avatar_path = $current_user['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $file_name = $_FILES['avatar']['name'];
        $file_tmp = $_FILES['avatar']['tmp_name'];
        $file_size = $_FILES['avatar']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if ($file_size > MAX_UPLOAD_SIZE) {
            $errors[] = "Розмір аватарки не повинен перевищувати 100MB";
        }
        if (!in_array($file_ext, $allowed_extensions)) {
            $errors[] = "Недопустимий формат аватарки. Дозволені формати: " . implode(', ', $allowed_extensions);
        }
        if (empty($errors)) {
            $upload_dir = '../public/uploads/avatars/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $new_file_name = uniqid('avatar_') . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;
            if (move_uploaded_file($file_tmp, $upload_path)) {
                if (!empty($current_user['avatar'])) {
                    $old_avatar_path = $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/public/uploads/' . $current_user['avatar'];
                    if (file_exists($old_avatar_path)) {
                        unlink($old_avatar_path);
                    }
                }
                $avatar_path = 'avatars/' . $new_file_name;
            } else {
                $errors[] = "Помилка при завантаженні аватарки";
            }
        }
    }
    if (empty($errors)) {
        $user_data = [
            'full_name' => $full_name,
            'login' => $login,
            'email' => $email,
            'phone' => $phone,
            'address' => $address
        ];
        if ($avatar_path !== $current_user['avatar']) {
            $user_data['avatar'] = $avatar_path;
        }
        if (!empty($new_password)) {
            $user_data['password'] = $new_password;
        }
        if ($userModel->updateUser($user_id, $user_data)) {
            $success_message = "Профіль успішно оновлено";
            if ($full_name !== $current_user['full_name']) {
                $_SESSION['user_name'] = $full_name;
            }
        } else {
            $error_message = "Помилка при оновленні профілю";
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}
if (!empty($success_message)) {
    $_SESSION['profile_success'] = $success_message;
} elseif (!empty($error_message)) {
    $_SESSION['profile_error'] = $error_message;
}
redirect('dashboard.php?tab=settings');
exit; 