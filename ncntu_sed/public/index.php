<?php
require_once '../config/config.php';
require_once '../app/views/includes/header.php';
?>
<div class="container">
    <!-- Головний банер -->
    <section class="hero-section text-center">
            <h1 class="hero-title">СЕД NCNTU-EDMS</h1>
            <p class="hero-description" style="margin-left: 20px; margin-right: 20px;">
                Система електронного документообігу для ефективного управління документами в Надвірнянському фаховому коледжі національного транспортного університету
            </p>
                <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="d-flex justify-content-center gap-3">
                    <a href="<?= BASE_URL ?>login.php" class="btn btn-success btn-lg">
                        <i class="bi bi-box-arrow-in-right"></i> Увійти в систему
                    </a>
                    <a href="<?= BASE_URL ?>register.php" class="btn btn-outline-success btn-lg">
                        <i class="bi bi-person-plus"></i> Зареєструватися
                    </a>
                </div>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-success btn-lg">
                        <i class="bi bi-speedometer2"></i> Перейти до кабінету
                    </a>
                <?php endif; ?>
    </section>
    <!-- Основні можливості -->
    <section class="features-section mb-5">
        <div class="container">
            <h2 class="text-center mb-4 border-bottom border-dark pb-2">Основні можливості системи</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-dark">
                        <div class="card-body text-center">
                            <i class="bi bi-file-earmark-text text-success fs-1 mb-3"></i>
                            <h3 class="card-title h5">Електронні документи</h3>
                            <p class="card-text">Створення, редагування та зберігання документів різних типів</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-dark">
                        <div class="card-body text-center">
                            <i class="bi bi-envelope text-success fs-1 mb-3"></i>
                            <h3 class="card-title h5">Система листування</h3>
                            <p class="card-text">Зручний обмін листами між користувачами з можливістю прикріплення файлів та документів.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-dark">
                        <div class="card-body text-center">
                            <i class="bi bi-archive text-success fs-1 mb-3"></i>
                            <h3 class="card-title h5">Архів документів</h3>
                            <p class="card-text">Зберігання та доступ до архіву документів з функцією пошуку та фільтрації для швидкого знаходження потрібних файлів.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Про коледж -->
    <section class="about-section mb-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <h2 class="border-bottom border-dark pb-2">Про НФКНТУ</h2>
                    <p сlass="card-text">Надвірнянський фаховий коледж національного транспортного університету забезпечує підготовку фахівців у галузі транспорту, комп'ютерних технологій, будівництва та економіки, використовуючи сучасні методики навчання та інноваційні технології.</p>
                    <a href="http://ncntu.com.ua/" target="_blank" class="btn btn-outline-success">Дізнатися більше</a>
                </div>
                <div class="col-lg-6">
                    <div class="rounded overflow-hidden shadow border border-dark text-center bg-white p-3">
                        <img src="<?= BASE_URL ?>images/college.jpg" alt="НФКНТУ" class="img-fluid" style="max-height: 300px; object-fit: contain;">
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<?php
require_once '../app/views/includes/footer.php';
?> 