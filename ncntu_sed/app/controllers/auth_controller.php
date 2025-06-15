<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/config/config.php';
require_once APP_PATH . 'models/User.php';
$userModel = new User();
$action = isset($_POST['action']) ? $_POST['action'] : '';
switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'register':
        handleRegister();
        break;
    default:
        redirect('');
}
function handleLogin() {
    global $userModel;
    if (empty($_POST['login']) || empty($_POST['password'])) {
        redirect('login.php?error=empty');
        exit;
    }
    $login = $_POST['login'];
    $password = $_POST['password'];
    $user = $userModel->login($login, $password);
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_login'] = $user['login'];
        $_SESSION['user_email'] = $user['email'];
        $roles = $userModel->getUserRoles($user['id']);
        $_SESSION['user_roles'] = $roles;
        if ($userModel->isAdmin($user['id'])) {
            redirect('admin/dashboard.php');
        } else {
            redirect('dashboard.php');
        }
    } else {
        redirect('login.php?error=invalid');
    }
}
function handleRegister() {
    global $userModel;
    if (empty($_POST['full_name']) || empty($_POST['login']) || empty($_POST['email']) || 
        empty($_POST['password']) || empty($_POST['confirm_password'])) {
        redirect('register.php?error=empty');
        exit;
    }
    $full_name = $_POST['full_name'];
    $login = $_POST['login'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = isset($_POST['phone']) ? $_POST['phone'] : '';
    $address = isset($_POST['address']) ? $_POST['address'] : '';
    if ($password !== $confirm_password) {
        redirect('register.php?error=password');
        exit;
    }
    if (strlen($password) < 4) {
        redirect('register.php?error=password_length');
        exit;
    }
    if (!$userModel->isLoginUnique($login)) {
        redirect('register.php?error=login_exists');
        exit;
    }
    if (!$userModel->isEmailUnique($email)) {
        redirect('register.php?error=email_exists');
        exit;
    }
    $avatar = null;
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = UPLOAD_PATH . 'avatars/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array(strtolower($file_extension), $allowed_extensions)) {
            redirect('register.php?error=file_type');
            exit;
        }
        $avatar_name = uniqid('avatar_') . '.' . $file_extension;
        $avatar_path = $upload_dir . $avatar_name;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $avatar_path)) {
            $avatar = 'avatars/' . $avatar_name;
        }
    }
    $user_data = [
        'full_name' => $full_name,
        'login' => $login,
        'email' => $email,
        'password' => $password,
        'avatar' => $avatar,
        'phone' => $phone,
        'address' => $address
    ];
    $result = $userModel->register($user_data);
    if ($result) {
        redirect('login.php?success=registered');
    } else {
        redirect('register.php?error=failed');
    }
}
 