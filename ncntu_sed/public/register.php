<?php
require_once '../config/config.php';
require_once '../app/views/includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card border-dark shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-person-plus-fill text-success fs-1 mb-3"></i>
                        <h2 class="text-success border-bottom border-dark pb-2">Реєстрація</h2>
                        <p class="text-muted">Створіть новий обліковий запис у системі</p>
                    </div>
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php 
                                $error = $_GET['error'];
                                if ($error === 'exists' || $error === 'login_exists') {
                                    echo 'Користувач з таким логіном вже існує';
                                } elseif ($error === 'email_exists') {
                                    echo 'Користувач з такою електронною поштою вже існує';
                                } elseif ($error === 'password') {
                                    echo 'Паролі не співпадають';
                                } elseif ($error === 'password_length') {
                                    echo 'Пароль має бути не менше 4 символів';
                                } elseif ($error === 'empty') {
                                    echo 'Всі обов\'язкові поля повинні бути заповнені';
                                } elseif ($error === 'file_type') {
                                    echo 'Дозволені типи файлів для аватару: jpg, jpeg, png, gif';
                                } elseif ($error === 'failed') {
                                    echo 'Помилка реєстрації. Спробуйте ще раз';
                                } else {
                                    echo 'Помилка реєстрації. Спробуйте ще раз';
                                }
                            ?>
                        </div>
                    <?php endif; ?>
                    <form action="../app/controllers/auth_controller.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="register">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="full_name" class="form-label">ПІБ <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="login" class="form-label">Логін <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-person-badge"></i></span>
                                    <input type="text" class="form-control" id="login" name="login" required>
                                </div>
                                <div class="form-text">Логін повинен бути унікальним</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="form-text">Вкажіть активну електронну пошту</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Пароль <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required 
                                           minlength="4" oninput="validatePassword()">
                                </div>
                                <div class="form-text">Мінімум 4 символи</div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label for="confirm_password" class="form-label">Підтвердження паролю <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           required minlength="4" oninput="validatePassword()">
                                </div>
                                <div id="password-match-feedback" class="form-text"></div>
                            </div>
                            <div class="col-12 mb-4">
                                <label for="avatar" class="form-label">Аватар</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-image"></i></span>
                                    <input type="file" class="form-control" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif"
                                           onchange="previewAvatar(this);">
                                </div>
                                <div class="form-text">Дозволені формати: JPG, PNG, GIF. Максимальний розмір: 2MB</div>
                                <div class="text-center mt-3" id="avatar-preview-container" style="display: none;">
                                    <img id="avatar-preview" src="#" alt="Попередній перегляд" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Телефон</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-telephone"></i></span>
                                    <input type="tel" class="form-control" id="phone" name="phone">
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label for="address" class="form-label">Адреса</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-geo-alt"></i></span>
                                    <input type="text" class="form-control" id="address" name="address">
                                </div>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg" id="register-btn">
                                <i class="bi bi-person-plus me-2"></i> Зареєструватися
                            </button>
                        </div>
                    </form>
                    <div class="text-center mt-4">
                        <p>Вже маєте обліковий запис? <a href="<?= BASE_URL ?>login.php" class="text-dark">Увійти</a></p>
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
    function previewAvatar(input) {
        const previewContainer = document.getElementById('avatar-preview-container');
        const preview = document.getElementById('avatar-preview');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                previewContainer.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        } else {
            preview.src = '';
            previewContainer.style.display = 'none';
        }
    }
    function validatePassword() {
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const feedback = document.getElementById('password-match-feedback');
        const registerBtn = document.getElementById('register-btn');
        if (confirmPassword.value === '') {
            feedback.innerHTML = '';
            feedback.className = 'form-text';
            return;
        }
        if (password.value !== confirmPassword.value) {
            feedback.innerHTML = 'Паролі не співпадають';
            feedback.className = 'form-text text-danger';
            registerBtn.disabled = true;
        } else {
            feedback.innerHTML = 'Паролі співпадають';
            feedback.className = 'form-text text-success';
            registerBtn.disabled = false;
        }
        if (password.value.length < 4) {
            feedback.innerHTML = 'Пароль має бути не менше 4 символів';
            feedback.className = 'form-text text-danger';
            registerBtn.disabled = true;
        }
    }
</script>
<?php
require_once '../app/views/includes/footer.php';
?> 