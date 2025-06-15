<?php
function renderDepartmentTree($department, $isDirector, $isDeputyDirector, $userDepartments = []) {
    $hasChildren = !empty($department['children']);
    $deptId = $department['id'];
    $canCreateDocuments = $isDirector || $isDeputyDirector || in_array($deptId, $userDepartments);
    ?>
    <div class="department-item" data-dept-id="<?= $deptId ?>">
        <div class="department-header d-flex align-items-center p-2 rounded mb-2 <?= $canCreateDocuments ? 'bg-light-success' : 'bg-light' ?>">
            <?php if ($hasChildren): ?>
                <span class="me-2 toggle-icon" data-bs-toggle="collapse" data-bs-target="#dept-<?= $deptId ?>-children">
                    <i class="bi bi-chevron-right"></i>
                </span>
            <?php else: ?>
                <span class="me-2">
                    <i class="bi bi-circle-fill" style="font-size: 0.5rem;"></i>
                </span>
            <?php endif; ?>
            <span class="me-2">
                <i class="bi bi-folder-fill text-warning fs-5"></i>
            </span>
            <a href="<?= BASE_URL ?>dashboard.php?tab=documents&subtab=department_documents&dept_id=<?= $deptId ?>" class="text-decoration-none flex-grow-1 text-dark">
                <?= htmlspecialchars($department['name']) ?>
            </a>
        </div>
        <?php if ($hasChildren): ?>
        <div class="collapse department-children ms-4 ps-2 border-start" id="dept-<?= $deptId ?>-children">
            <?php foreach ($department['children'] as $child): ?>
                <?php renderDepartmentTree($child, $isDirector, $isDeputyDirector, $userDepartments); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
<?php
}
?>
<!-- Стилі для відділень -->
<style>
.department-item {
    margin-bottom: 0.5rem;
}
.department-header {
    cursor: pointer;
    transition: background-color 0.2s;
    border: 1px solid #dee2e6;
}
.department-header:hover {
    background-color: rgba(0, 0, 0, 0.05) !important;
}
.toggle-icon {
    transition: transform 0.2s;
    display: inline-block;
    width: 20px;
    height: 20px;
    text-align: center;
}
.toggle-icon[aria-expanded="true"] .bi-chevron-right {
    transform: rotate(90deg);
}
.bg-light-success {
    background-color: rgba(25, 135, 84, 0.1);
}
.department-children {
    padding-top: 0.5rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userId = <?= $_SESSION['user_id'] ?? 0 ?>;
    const STORAGE_KEY = `ncntu_sed_expandedDepartments_${userId}`;
    function saveExpandedState() {
        const expandedElements = document.querySelectorAll('.department-children.show');
        const expandedIds = Array.from(expandedElements).map(el => {
            return el.id.replace('dept-', '').replace('-children', '');
        });
        localStorage.setItem(STORAGE_KEY, JSON.stringify(expandedIds));
        console.log(`Збережено стан відділень для користувача ${userId}:`, expandedIds);
    }
    function restoreExpandedState() {
        try {
            const expandedIds = JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
            console.log(`Відновлюємо стан відділень для користувача ${userId}:`, expandedIds);
            expandedIds.forEach(id => {
                const collapseElement = document.getElementById(`dept-${id}-children`);
                if (collapseElement) {
                    collapseElement.classList.add('show');
                    const toggleIcon = document.querySelector(`.toggle-icon[data-bs-target="#dept-${id}-children"]`);
                    if (toggleIcon) {
                        toggleIcon.setAttribute('aria-expanded', 'true');
                        const icon = toggleIcon.querySelector('.bi-chevron-right');
                        if (icon) {
                            icon.style.transform = 'rotate(90deg)';
                        }
                    }
                }
            });
        } catch (e) {
            console.error(`Помилка при відновленні стану відділень для користувача ${userId}:`, e);
        }
    }
    const collapseElements = document.querySelectorAll('.collapse');
    collapseElements.forEach(collapse => {
        const bsCollapse = new bootstrap.Collapse(collapse, {
            toggle: false
        });
        collapse.addEventListener('shown.bs.collapse', saveExpandedState);
        collapse.addEventListener('hidden.bs.collapse', saveExpandedState);
    });
    const toggleIcons = document.querySelectorAll('.toggle-icon');
    toggleIcons.forEach(icon => {
        icon.setAttribute('aria-expanded', 'false');
        icon.addEventListener('click', function(e) {
            e.stopPropagation();
            const targetSelector = this.getAttribute('data-bs-target');
            const targetElement = document.querySelector(targetSelector);
            if (targetElement) {
                const bsCollapse = bootstrap.Collapse.getInstance(targetElement);
                if (bsCollapse) {
                    bsCollapse.toggle();
                }
                const isExpanded = targetElement.classList.contains('show');
                this.setAttribute('aria-expanded', isExpanded);
                const iconElement = this.querySelector('.bi-chevron-right');
                if (iconElement) {
                    iconElement.style.transform = isExpanded ? 'rotate(90deg)' : '';
                }
            }
        });
    });
    const deptHeaders = document.querySelectorAll('.department-header');
    deptHeaders.forEach(header => {
        header.addEventListener('click', function(e) {
            if (!e.target.closest('.toggle-icon') && !e.target.closest('.btn') && !e.target.closest('a')) {
                const toggleIcon = this.querySelector('.toggle-icon');
                if (toggleIcon) {
                    toggleIcon.click();
                } else {
                    const link = this.querySelector('a');
                    if (link) {
                        window.location = link.href;
                    }
                }
            }
        });
    });
    restoreExpandedState();
});
</script> 