<?php
require_once '../config/config.php';
require_once '../app/views/includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card border-dark shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-lock-fill text-success fs-1 mb-3"></i>
                        <h2 class="text-success border-bottom border-dark pb-2">Вхід у систему</h2>
                        <p class="text-muted">Введіть ваші облікові дані для входу</p>
                    </div>
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php 
                                $error = $_GET['error'];
                                if ($error === 'invalid') {
                                    echo 'Невірний логін або пароль';
                                } elseif ($error === 'empty') {
                                    echo 'Всі поля обов\'язкові для заповнення';
                                } else {
                                    echo 'Помилка входу. Спробуйте ще раз';
                                }
                            ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success" role="alert">
                            <?php 
                                $success = $_GET['success'];
                                if ($success === 'registered') {
                                    echo 'Ви успішно зареєструвалися. Тепер можете увійти у систему.';
                                } else {
                                    echo 'Операція виконана успішно.';
                                }
                            ?>
                        </div>
                    <?php endif; ?>
                    <form action="../app/controllers/auth_controller.php" method="post">
                        <input type="hidden" name="action" value="login">
                        <div class="mb-3">
                            <label for="login" class="form-label">Логін або Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="login" name="login" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label">Пароль</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-box-arrow-in-right me-2"></i> Увійти
                            </button>
                        </div>
                    </form>
                    <div class="text-center mt-4">
                        <p>Не маєте облікового запису? <a href="<?= BASE_URL ?>register.php" class="text-dark">Зареєструватися</a></p>
                    </div>
                </div>
            </div>
            <div class="text-center mt-3">
                <a href="<?= BASE_URL ?>" class="text-decoration-none text-dark">
                    <i class="bi bi-arrow-left me-1"></i> Повернутися на головну
                </a>
            </div>
        </div>
    </div>
</div>
<script>
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const icon = this.querySelector('i');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    });
</script>
<?php
require_once '../app/views/includes/footer.php';
?> 