<div class="col-lg-2 sidebar">
    <div class="sidebar-logo">
        <img src="/assets/img/logo.png" alt="GV Industries">
        <div class="text-muted mt-2" style="font-size:0.8rem;">Backoffice</div>
    </div>
    <nav class="nav flex-column mt-3">
        <a class="nav-link <?php echo ($current_page === 'index') ? 'active' : ''; ?>" href="/backoffice/index.php">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a class="nav-link <?php echo ($current_page === 'orders' || $current_page === 'order') ? 'active' : ''; ?>" href="/backoffice/orders.php">
            <i class="bi bi-bag"></i> Orders
        </a>
        <a class="nav-link <?php echo ($current_page === 'customers') ? 'active' : ''; ?>" href="/backoffice/customers.php">
            <i class="bi bi-people"></i> Customers
        </a>
        <a class="nav-link <?php echo ($current_page === 'availability') ? 'active' : ''; ?>" href="/backoffice/availability.php">
            <i class="bi bi-box-seam"></i> Product Availability
        </a>
        <a class="nav-link <?php echo ($current_page === 'delivery_zones') ? 'active' : ''; ?>" href="/backoffice/delivery_zones.php">
            <i class="bi bi-geo-alt"></i> Delivery Zones
        </a>
        <a class="nav-link <?php echo ($current_page === 'users') ? 'active' : ''; ?>" href="/backoffice/users.php">
            <i class="bi bi-person-gear"></i> Users
        </a>
        <hr style="border-color:#444; margin: 10px 20px;">
        <a class="nav-link" href="/" target="_blank">
            <i class="bi bi-globe"></i> View Website
        </a>
        <a class="nav-link" href="/backoffice/logout.php">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </nav>
</div>
