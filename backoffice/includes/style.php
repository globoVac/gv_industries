<style>
    body { background-color: #f0f0f0; }

    /* Sidebar */
    .sidebar {
        min-height: 100vh;
        background-color: #2c2c2c;
        padding: 0;
    }
    .sidebar-logo {
        padding: 20px;
        text-align: center;
        border-bottom: 1px solid #444;
    }
    .sidebar-logo img { height: 50px; }
    .sidebar .nav-link {
        color: #aaa;
        padding: 12px 20px;
        border-radius: 0;
        transition: 0.2s;
    }
    .sidebar .nav-link:hover,
    .sidebar .nav-link.active {
        background-color: #e8612c;
        color: #fff;
    }
    .sidebar .nav-link i { margin-right: 8px; }

    /* Layout */
    .main-content { padding: 30px; }
    .topbar {
        background: #fff;
        padding: 15px 30px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Cards */
    .info-card {
        background: #fff;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .stat-card {
        background: #fff;
        border-radius: 10px;
        padding: 20px;
        border-left: 4px solid #e8612c;
    }
    .stat-card .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: #e8612c;
    }
    .stat-card .stat-label {
        color: #888;
        font-size: 0.9rem;
    }

    /* Buttons */
    .btn-gvi {
        background-color: #e8612c;
        border-color: #e8612c;
        color: #fff;
    }
    .btn-gvi:hover {
        background-color: #d4521f;
        border-color: #d4521f;
        color: #fff;
    }
</style>
