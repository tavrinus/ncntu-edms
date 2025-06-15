<?php
require_once '../../config/config.php';
require_once '../../app/models/User.php';
require_once '../../app/models/Document.php';
require_once '../../app/models/Department.php';
if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
    exit;
}
$userModel = new User();
$documentModel = new Document();
$departmentModel = new Department();
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
$warning_message = '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$filters = [];
if (isset($_GET['title']) && !empty($_GET['title'])) {
    $filters['title'] = $_GET['title'];
}
if (isset($_GET['type_id']) && !empty($_GET['type_id']) && $_GET['type_id'] != 'all') {
    $filters['type_id'] = intval($_GET['type_id']);
}
if (isset($_GET['author_id']) && !empty($_GET['author_id']) && $_GET['author_id'] != 'all') {
    $filters['author_id'] = intval($_GET['author_id']);
}
if (isset($_GET['department_id']) && !empty($_GET['department_id']) && $_GET['department_id'] != 'all') {
    $filters['department_id'] = intval($_GET['department_id']);
}
if (isset($_GET['file_extension']) && !empty($_GET['file_extension']) && $_GET['file_extension'] != 'all') {
    $filters['file_extension'] = $_GET['file_extension'];
}
$filters['is_archived'] = 1;
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$document_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($action === 'unarchive' && $document_id > 0) {
    if ($documentModel->toggleArchiveStatus($document_id, false)) {
        $_SESSION['document_success'] = 'unarchived';
        redirect('admin/archived_documents.php');
        exit;
    } else {
        $_SESSION['document_error'] = 'archive_failed';
        redirect('admin/archived_documents.php');
        exit;
    }
}
if ($action === 'delete' && $document_id > 0) {
    if ($documentModel->deleteDocument($document_id)) {
        $_SESSION['document_success'] = 'deleted';
        redirect('admin/archived_documents.php');
        exit;
    } else {
        $_SESSION['document_error'] = 'delete_failed';
        redirect('admin/archived_documents.php');
        exit;
    }
}
$documents = $documentModel->getAllDocuments($limit, $offset, $filters);
$total_documents = $documentModel->countDocuments($filters);
$total_pages = ceil($total_documents / $limit);
foreach ($documents as &$doc) {
    if (!empty($doc['file_path'])) {
        $file_path_full = $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/public/uploads/' . $doc['file_path'];
        if (file_exists($file_path_full)) {
            $doc['file_size'] = round(filesize($file_path_full) / 1048576, 2);
        } else {
            $doc['file_size'] = 0;
        }
        $doc['file_extension'] = strtoupper(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
    } else {
        $doc['file_size'] = 0;
        $doc['file_extension'] = '-';
    }
}
unset($doc);
$document_types = $documentModel->getAllDocumentTypes();
$departments = $departmentModel->getAllDepartments();
$stmt = $conn->prepare("SELECT DISTINCT u.id, u.full_name 
                       FROM users u 
                       JOIN documents d ON u.id = d.author_id 
                       WHERE d.is_archived = 1
                       ORDER BY u.full_name");
$stmt->execute();
$authors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
if (isset($_SESSION['document_success'])) {
    $success = $_SESSION['document_success'];
    if ($success === 'deleted') {
        $success_message = "Документ успішно видалено";
    } elseif ($success === 'unarchived') {
        $success_message = "Документ відновлено з архіву";
    }
    unset($_SESSION['document_success']);
}
if (isset($_SESSION['document_error'])) {
    $error = $_SESSION['document_error'];
    if ($error === 'delete_failed') {
        $errors[] = "Помилка при видаленні документа";
    } elseif ($error === 'not_found') {
        $errors[] = "Документ не знайдено";
    } elseif ($error === 'archive_failed') {
        $errors[] = "Помилка при розархівації документа";
    }
    unset($_SESSION['document_error']);
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
                <a href="<?= BASE_URL ?>admin/archived_documents.php" class="list-group-item list-group-item-action active admin-nav-link">
                    <i class="bi bi-archive me-2"></i> Архів документів
                </a>
                <a href="<?= BASE_URL ?>admin/departments.php" class="list-group-item list-group-item-action admin-nav-link">
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
        <!-- Основний контент -->
        <div class="col-md-9 col-lg-10 ms-sm-auto px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom border-dark">
                <h1 class="h2 text-success"><i class="bi bi-archive me-2"></i> Архів документів</h1>
            </div>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h5 class="alert-heading">Помилки:</h5>
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
            <!-- Фільтри документів -->
            <div class="card border-dark shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0 text-success">
                        <i class="bi bi-funnel me-2"></i> Фільтри
                    </h5>
                </div>
                <div class="card-body">
                    <form action="<?= BASE_URL ?>admin/archived_documents.php" method="get" class="row g-3">
                        <div class="col-md-6">
                            <label for="title" class="form-label">Заголовок документа</label>
                            <input type="text" class="form-control" id="title" name="title" value="<?= isset($filters['title']) ? htmlspecialchars($filters['title']) : '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="type_id" class="form-label">Тип документа</label>
                            <select class="form-select" id="type_id" name="type_id">
                                <option value="all">Всі типи</option>
                                <?php foreach ($document_types as $type): ?>
                                    <option value="<?= $type['id'] ?>" <?= isset($filters['type_id']) && $filters['type_id'] == $type['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(ucfirst($type['name'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="author_id" class="form-label">Автор</label>
                            <select class="form-select" id="author_id" name="author_id">
                                <option value="all">Всі автори</option>
                                <?php foreach ($authors as $author): ?>
                                    <option value="<?= $author['id'] ?>" <?= isset($filters['author_id']) && $filters['author_id'] == $author['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($author['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="department_id" class="form-label">Відділення</label>
                            <select class="form-select" id="department_id" name="department_id">
                                <option value="all">Всі відділення</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>" <?= isset($filters['department_id']) && $filters['department_id'] == $dept['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="file_extension" class="form-label">Розширення файлу</label>
                            <select class="form-select" id="file_extension" name="file_extension">
                                <option value="all">Всі типи файлів</option>
                                <option value="pdf" <?= isset($filters['file_extension']) && $filters['file_extension'] == 'pdf' ? 'selected' : '' ?>>PDF</option>
                                <option value="doc" <?= isset($filters['file_extension']) && $filters['file_extension'] == 'doc' ? 'selected' : '' ?>>DOC</option>
                                <option value="docx" <?= isset($filters['file_extension']) && $filters['file_extension'] == 'docx' ? 'selected' : '' ?>>DOCX</option>
                                <option value="xls" <?= isset($filters['file_extension']) && $filters['file_extension'] == 'xls' ? 'selected' : '' ?>>XLS</option>
                                <option value="xlsx" <?= isset($filters['file_extension']) && $filters['file_extension'] == 'xlsx' ? 'selected' : '' ?>>XLSX</option>
                                <option value="ppt" <?= isset($filters['file_extension']) && $filters['file_extension'] == 'ppt' ? 'selected' : '' ?>>PPT</option>
                                <option value="pptx" <?= isset($filters['file_extension']) && $filters['file_extension'] == 'pptx' ? 'selected' : '' ?>>PPTX</option>
                                <option value="txt" <?= isset($filters['file_extension']) && $filters['file_extension'] == 'txt' ? 'selected' : '' ?>>TXT</option>
                                <option value="jpg" <?= isset($filters['file_extension']) && ($filters['file_extension'] == 'jpg' || $filters['file_extension'] == 'jpeg') ? 'selected' : '' ?>>JPG/JPEG</option>
                                <option value="png" <?= isset($filters['file_extension']) && $filters['file_extension'] == 'png' ? 'selected' : '' ?>>PNG</option>
                            </select>
                        </div>
                        <div class="col-12 text-end">
                            <a href="<?= BASE_URL ?>admin/archived_documents.php" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-x-circle me-1"></i> Скинути
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-search me-1"></i> Застосувати фільтри
                            </button>
                        </div>
                    </form>
                    <?php if (!empty($filters) && (
                        (isset($filters['title']) && !empty($filters['title'])) || 
                        (isset($filters['type_id']) && !empty($filters['type_id'])) || 
                        (isset($filters['author_id']) && !empty($filters['author_id'])) || 
                        (isset($filters['department_id']) && !empty($filters['department_id'])) || 
                        (isset($filters['file_extension']) && !empty($filters['file_extension']))
                    )): ?>
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle me-2"></i> Знайдено архівованих документів: <strong><?= $total_documents ?></strong>
                            <?php if (isset($filters['title']) && !empty($filters['title'])): ?>
                                <span class="ms-2 badge bg-secondary">Заголовок: <?= htmlspecialchars($filters['title']) ?></span>
                            <?php endif; ?>
                            <?php if (isset($filters['type_id']) && !empty($filters['type_id'])): 
                                $selected_type_name = '';
                                foreach ($document_types as $type) {
                                    if ($type['id'] == $filters['type_id']) {
                                        $selected_type_name = $type['name'];
                                        break;
                                    }
                                }
                            ?>
                                <span class="ms-2 badge bg-orange text-white">Тип: <?= htmlspecialchars(ucfirst($selected_type_name)) ?></span>
                            <?php endif; ?>
                            <?php if (isset($filters['author_id']) && !empty($filters['author_id'])): 
                                $selected_author_name = '';
                                foreach ($authors as $author) {
                                    if ($author['id'] == $filters['author_id']) {
                                        $selected_author_name = $author['full_name'];
                                        break;
                                    }
                                }
                            ?>
                                <span class="ms-2 badge bg-success">Автор: <?= htmlspecialchars($selected_author_name) ?></span>
                            <?php endif; ?>
                            <?php if (isset($filters['department_id']) && !empty($filters['department_id'])): 
                                $selected_dept_name = '';
                                foreach ($departments as $dept) {
                                    if ($dept['id'] == $filters['department_id']) {
                                        $selected_dept_name = $dept['name'];
                                        break;
                                    }
                                }
                            ?>
                                <span class="ms-2 badge bg-warning text-dark">Відділення: <?= htmlspecialchars($selected_dept_name) ?></span>
                            <?php endif; ?>
                            <?php if (isset($filters['file_extension']) && !empty($filters['file_extension'])): ?>
                                <span class="ms-2 badge bg-danger">Розширення: <?= strtoupper(htmlspecialchars($filters['file_extension'])) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Список архівованих документів -->
            <div class="card border-dark shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0 text-success">
                        <i class="bi bi-archive me-2"></i> Архівовані документи
                    </h5>
                </div>
                <div class="card-body content-container">
                    <?php if (empty($documents)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i> Архівовані документи не знайдені. Спробуйте змінити фільтри або архівувати деякі документи.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th scope="col">ID</th>
                                        <th scope="col">Заголовок</th>
                                        <th scope="col">Тип</th>
                                        <th scope="col">Розширення</th>
                                        <th scope="col">Розмір</th>
                                        <th scope="col">Автор</th>
                                        <th scope="col">Відділення</th>
                                        <th scope="col">Дата архівації</th>
                                        <th scope="col">Дії</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr class="table-secondary text-muted">
                                            <td><?= $doc['id'] ?></td>
                                            <td>
                                                <?= htmlspecialchars($doc['title']) ?>
                                            </td>
                                            <td><span class="badge bg-orange text-white"><?= htmlspecialchars(ucfirst($doc['type_name'])) ?></span></td>
                                            <td><span class="badge bg-danger"><?= $doc['file_extension'] ?></span></td>
                                            <td><?= number_format($doc['file_size'], 2, '.', '') ?> МБ</td>
                                            <td><?= htmlspecialchars($doc['author_name']) ?></td>
                                            <td>
                                                <?php if (!empty($doc['department_name'])): ?>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars($doc['department_name']) ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-dark border">Не вказано</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('d.m.Y H:i', strtotime($doc['updated_at'] ?? $doc['created_at'])) ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary view-document" 
                                                           data-bs-toggle="modal" data-bs-target="#viewDocumentModal" 
                                                           data-document-path="<?= BASE_URL ?>uploads/<?= $doc['file_path'] ?>"
                                                           data-document-title="<?= htmlspecialchars($doc['title']) ?>"
                                                           data-document-type="<?= strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION)) ?>">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <a href="<?= BASE_URL ?>uploads/<?= $doc['file_path'] ?>" download="<?= htmlspecialchars($doc['title']) ?>.<?= strtolower($doc['file_extension']) ?>" class="btn btn-outline-success" title="Завантажити">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                    <a href="<?= BASE_URL ?>admin/archived_documents.php?action=unarchive&id=<?= $doc['id'] ?>" class="btn btn-outline-info" title="Відновити з архіву" onclick="return confirm('Відновити документ з архіву?');">
                                                        <i class="bi bi-arrow-counterclockwise"></i>
                                                    </a>
                                                    <a href="<?= BASE_URL ?>admin/archived_documents.php?action=delete&id=<?= $doc['id'] ?>" class="btn btn-outline-danger" title="Видалити" onclick="return confirm('Ви впевнені, що хочете видалити цей документ? Це незворотна дія.');">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Пагінація -->
                        <?php if ($total_pages > 1): ?>
                            <div class="d-flex justify-content-center mt-4">
                                <nav aria-label="Навігація сторінками">
                                    <ul class="pagination">
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                <a class="page-link" href="<?= BASE_URL ?>admin/archived_documents.php?page=<?= $i ?><?= 
                                                !empty($filters) ? '&' . http_build_query(array_filter($filters, function($key) { return $key != 'is_archived'; }, ARRAY_FILTER_USE_KEY)) : '' ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Модальне вікно для перегляду документа -->
<div class="modal fade" id="viewDocumentModal" tabindex="-1" aria-labelledby="viewDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="viewDocumentModalLabel">
                    <i class="bi bi-file-earmark-text me-2"></i> Перегляд документа
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="documentViewerContainer" class="document-viewer-container">
                    <!-- Вміст документа буде завантажено динамічно -->
                    <div class="text-center p-5" id="documentLoading">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Завантаження...</span>
                        </div>
                        <p class="mt-3">Завантаження документа...</p>
                    </div>
                    <div id="documentViewerContent" style="display: none;">
                        <!-- PDF та інші документи будуть відображатися тут -->
                    </div>
                    <div id="documentUnsupportedMessage" class="alert alert-warning m-4" style="display: none;">
                        <i class="bi bi-exclamation-triangle me-2"></i> Формат документа не підтримується для перегляду в браузері. Будь ласка, завантажте документ для перегляду.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" class="btn btn-success" id="downloadDocumentBtn" download>
                    <i class="bi bi-download me-1"></i> Завантажити
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрити</button>
            </div>
        </div>
    </div>
</div>
<style>
.document-viewer-container {
    min-height: 500px;
    max-height: 70vh;
    overflow: auto;
}
.document-viewer-container iframe, 
.document-viewer-container embed,
.document-viewer-container object {
    width: 100%;
    height: 100%;
    min-height: 500px;
}
.document-viewer-container img {
    max-width: 100%;
    max-height: 70vh;
    display: block;
    margin: 0 auto;
}
.bg-orange {
    background-color: #fd7e14 !important;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const viewButtons = document.querySelectorAll('.view-document');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const documentPath = this.getAttribute('data-document-path');
            const documentTitle = this.getAttribute('data-document-title');
            const documentType = this.getAttribute('data-document-type');
            document.getElementById('viewDocumentModalLabel').innerHTML = 
                `<i class="bi bi-file-earmark-text me-2"></i> ${documentTitle}`;
            const downloadBtn = document.getElementById('downloadDocumentBtn');
            downloadBtn.href = documentPath;
            downloadBtn.setAttribute('download', `${documentTitle}.${documentType}`);
            document.getElementById('documentLoading').style.display = 'block';
            document.getElementById('documentViewerContent').style.display = 'none';
            document.getElementById('documentUnsupportedMessage').style.display = 'none';
            if (documentType === 'pdf') {
                const viewer = document.createElement('embed');
                viewer.setAttribute('src', `${documentPath}#toolbar=0&navpanes=0`);
                viewer.setAttribute('type', 'application/pdf');
                document.getElementById('documentViewerContent').innerHTML = '';
                document.getElementById('documentViewerContent').appendChild(viewer);
                document.getElementById('documentLoading').style.display = 'none';
                document.getElementById('documentViewerContent').style.display = 'block';
            } else if (['jpg', 'jpeg', 'png'].includes(documentType)) {
                const img = document.createElement('img');
                img.setAttribute('src', documentPath);
                img.setAttribute('alt', documentTitle);
                document.getElementById('documentViewerContent').innerHTML = '';
                document.getElementById('documentViewerContent').appendChild(img);
                document.getElementById('documentLoading').style.display = 'none';
                document.getElementById('documentViewerContent').style.display = 'block';
            } else if (documentType === 'txt') {
                fetch(documentPath)
                    .then(response => response.text())
                    .then(text => {
                        const pre = document.createElement('pre');
                        pre.style.padding = '20px';
                        pre.style.whiteSpace = 'pre-wrap';
                        pre.textContent = text;
                        document.getElementById('documentViewerContent').innerHTML = '';
                        document.getElementById('documentViewerContent').appendChild(pre);
                        document.getElementById('documentLoading').style.display = 'none';
                        document.getElementById('documentViewerContent').style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Error loading text file:', error);
                        document.getElementById('documentLoading').style.display = 'none';
                        document.getElementById('documentUnsupportedMessage').style.display = 'block';
                    });
            } else {
                document.getElementById('documentLoading').style.display = 'none';
                document.getElementById('documentUnsupportedMessage').style.display = 'block';
            }
        });
    });
});
</script>
<?php
$conn->close();
require_once '../../app/views/includes/footer.php';
?> 