<?php 
require_once $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/config/config.php';
$current_page = basename($_SERVER['PHP_SELF']);
$is_home_page = ($current_page === 'index.php' || $current_page === '');
$is_admin_page = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_TITLE ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body class="<?= $is_admin_page ? 'admin-page' : '' ?>">
    <header>
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
            <div class="container" style="background-color:rgb(255, 255, 255);">
                <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>">
                    <span class="fw-bold text-success">NCNTU-EDMS</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarContent">
                    <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <a class="nav-link <?= $is_home_page ? 'active' : '' ?>" href="<?= BASE_URL ?>">
                                Головна
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="http://ncntu.com.ua/" target="_blank">
                                Сайт коледжу
                            </a>
                        </li>
                    </ul>
                    <div class="d-flex">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-outline-success me-2">
                                <i class="bi bi-speedometer2"></i> Кабінет
                            </a>
                            <a href="<?= BASE_URL ?>logout.php" class="btn btn-outline-danger">
                                <i class="bi bi-box-arrow-right"></i> Вихід
                            </a>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>login.php" class="btn btn-outline-success me-2">
                                <i class="bi bi-box-arrow-in-right"></i> Вхід
                            </a>
                            <a href="<?= BASE_URL ?>register.php" class="btn btn-success">
                                <i class="bi bi-person-plus"></i> Реєстрація
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
    </header>
    <main class="<?= $is_admin_page ? 'py-2 mt-3' : 'py-5 mt-3' ?>"><?php