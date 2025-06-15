    </main>
    
    <footer class="<?= isset($is_admin_page) && $is_admin_page ? 'py-2 mt-0' : 'py-4 mt-4' ?>">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5 class="text-success border-bottom border-dark pb-2">NCNTU-EDMS</h5>
                    <p class="text-muted">Система електронного документообігу для Надвірнянського фахового коледжу національного транспортного університету.</p>
                </div>
                
                <div class="col-md-4 mb-3">
                    <h5 class="text-success border-bottom border-dark pb-2">Контакти</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="bi bi-geo-alt-fill text-success me-2"></i>
                            78405 Україна, Івано-Франківська обл., м. Надвірна, вул. Соборна, 177
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-telephone-fill text-success me-2"></i>
                            (03475) 2-03-22
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-envelope-fill text-success me-2"></i>
                            ncntu@ukr.net
                        </li>
                    </ul>
                </div>
                
                <div class="col-md-4 mb-3">
                    <h5 class="text-success border-bottom border-dark pb-2">Посилання</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="http://ncntu.com.ua/" target="_blank" class="text-decoration-none">
                                <i class="bi bi-globe text-success me-2"></i> Сайт коледжу
                            </a>
                        </li>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                        <li class="mb-2">
                            <a href="<?= BASE_URL ?>login.php" class="text-decoration-none">
                                <i class="bi bi-box-arrow-in-right text-success me-2"></i> Вхід
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= BASE_URL ?>register.php" class="text-decoration-none">
                                <i class="bi bi-person-plus text-success me-2"></i> Реєстрація
                            </a>
                        </li>
                        <?php else: ?>
                        <li class="mb-2">
                            <a href="<?= BASE_URL ?>dashboard.php" class="text-decoration-none">
                                <i class="bi bi-speedometer2 text-success me-2"></i> Кабінет
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?= BASE_URL ?>logout.php" class="text-decoration-none">
                                <i class="bi bi-box-arrow-right text-success me-2"></i> Вихід
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <hr class="border-dark">
            
            <p class="text-center text-muted">&copy; <?= date('Y') ?> NCNTU-EDMS. Всі права захищено.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>js/script.js"></script>
</body>
</html> 