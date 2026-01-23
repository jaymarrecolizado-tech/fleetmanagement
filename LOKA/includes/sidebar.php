<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-content">
        <ul class="nav flex-column">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link <?= activeMenu('dashboard') ?>" href="<?= APP_URL ?>/?page=dashboard">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <!-- Requests -->
            <li class="nav-item">
                <a class="nav-link <?= activeMenu('requests') ?>" href="<?= APP_URL ?>/?page=requests">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Requests</span>
                </a>
            </li>
            
            <!-- Schedule Calendar (All Users) -->
            <li class="nav-item">
                <a class="nav-link <?= activeMenu('schedule') ?>" href="<?= APP_URL ?>/?page=schedule&action=calendar">
                    <i class="bi bi-calendar3"></i>
                    <span>Availability</span>
                </a>
            </li>
            
            <?php if (isApprover()): ?>
            <!-- Approvals -->
            <li class="nav-item">
                <a class="nav-link <?= activeMenu('approvals') ?>" href="<?= APP_URL ?>/?page=approvals">
                    <i class="bi bi-check-circle"></i>
                    <span>Approvals</span>
                    <?php
                    $pendingCount = 0;
                    if (isMotorpool()) {
                        $pendingCount = db()->count('requests', "status = 'pending_motorpool'");
                    } else {
                        $pendingCount = db()->count('requests', "status = 'pending' AND department_id = ?", [currentUser()->department_id]);
                    }
                    if ($pendingCount > 0):
                    ?>
                    <span class="badge bg-warning ms-auto"><?= $pendingCount ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-header">Fleet Management</li>
            
            <?php if (isApprover()): ?>
            <!-- Vehicles -->
            <li class="nav-item">
                <a class="nav-link <?= activeMenu('vehicles') ?>" href="<?= APP_URL ?>/?page=vehicles">
                    <i class="bi bi-car-front"></i>
                    <span>Vehicles</span>
                </a>
            </li>
            
            <!-- Drivers -->
            <li class="nav-item">
                <a class="nav-link <?= activeMenu('drivers') ?>" href="<?= APP_URL ?>/?page=drivers">
                    <i class="bi bi-person-badge"></i>
                    <span>Drivers</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (isApprover()): ?>
            <li class="nav-header">Reports</li>
            
            <!-- Reports -->
            <li class="nav-item">
                <a class="nav-link <?= activeMenu('reports') ?>" href="<?= APP_URL ?>/?page=reports">
                    <i class="bi bi-bar-chart"></i>
                    <span>Reports</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (isMotorpool()): ?>
            <li class="nav-header">Administration</li>
            
            <!-- Users -->
            <li class="nav-item">
                <a class="nav-link <?= activeMenu('users') ?>" href="<?= APP_URL ?>/?page=users">
                    <i class="bi bi-people"></i>
                    <span>Users</span>
                </a>
            </li>
            
            <!-- Departments -->
            <li class="nav-item">
                <a class="nav-link <?= activeMenu('departments') ?>" href="<?= APP_URL ?>/?page=departments">
                    <i class="bi bi-building"></i>
                    <span>Departments</span>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (isAdmin()): ?>
            <!-- Audit Logs -->
            <li class="nav-item">
                <a class="nav-link <?= activeMenu('audit') ?>" href="<?= APP_URL ?>/?page=audit">
                    <i class="bi bi-journal-text"></i>
                    <span>Audit Logs</span>
                </a>
            </li>
            
            <!-- Settings -->
            <li class="nav-item">
                <a class="nav-link <?= activeMenu('settings') ?>" href="<?= APP_URL ?>/?page=settings">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <small class="text-muted">
            <?= APP_NAME ?> v<?= APP_VERSION ?>
        </small>
    </div>
</nav>
