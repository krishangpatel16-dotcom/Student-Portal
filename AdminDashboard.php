<?php
declare(strict_types=1);

require_once __DIR__ . '/PHP Files/auth_helpers.php';

startSecureSession();
if ((string) ($_SESSION['student_email'] ?? '') === '') {
    header('Location: LoginHome.html');
    exit;
}
if (isSessionExpired()) {
    destroySession();
    header('Location: LoginHome.html');
    exit;
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: HomePage.html');
    exit;
}

refreshSessionLifetime();
$adminName = htmlspecialchars((string) ($_SESSION['student_name'] ?? 'Admin'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrfToken = (string) ($_SESSION['admin_dashboard_csrf'] ?? '');
if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['admin_dashboard_csrf'] = $csrfToken;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Student Portal</title>
    <link rel="stylesheet" href="CSS Files/AdminDashboard.css">
    <script src="JavaScript Files/AdminDashboard.js" defer></script>
</head>
<body data-dashboard-api="PHP Files/api/admin_dashboard.php" data-csrf-token="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="dashboard-shell">
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-mark">SP</span>
                <span>Student Portal</span>
            </div>
            <nav class="nav" aria-label="Administrator navigation" data-menu-role="admin">
                <a href="AdminDashboard.php" class="nav-item active" aria-current="page">Overview</a>
                <a href="PHP Files/student_management.php" class="nav-item">Student Management</a>
                <a href="PHP Files/event_management.php" class="nav-item">Event Management</a>
                <a href="#create-admin-account" class="nav-item">Create administrator</a>
                <a href="PHP Files/view_registrations.php" class="nav-item">Registrations</a>
                <a href="PHP Files/studenthub.php" class="nav-item">StudentHub Data</a>
                <a href="HomePage.html" class="nav-item">Student Home</a>
                <a href="Register.html" class="nav-item">Register student</a>
                <a href="LoginHome.html" class="nav-item">Portal sign in</a>
            </nav>
        </aside>

        <main class="main">
            <header class="topbar">
                <div>
                    <h1 class="welcome">Welcome, <span id="adminName"><?php echo $adminName; ?></span></h1>
                    <p class="subtext">Student portal administration and activity overview.</p>
                </div>
                <button class="logout-btn" id="logoutBtn" type="button">Logout</button>
            </header>

            <div class="status-pill">Administrator session active</div>
            <div class="dashboard-message" id="dashboardMessage" role="status" aria-live="polite" hidden></div>

            <section class="cards dashboard-stats" aria-label="Portal statistics">
                <article class="card"><div class="card-label">Student records</div><div class="card-value" id="studentCount">—</div></article>
                <article class="card"><div class="card-label">Active student accounts</div><div class="card-value" id="activeAccountCount">—</div></article>
                <article class="card"><div class="card-label">Events</div><div class="card-value" id="eventCount">—</div></article>
                <article class="card"><div class="card-label">Event registrations</div><div class="card-value" id="registrationCount">—</div></article>
            </section>

            <div class="dashboard-columns">
                <section class="dashboard-panel">
                    <div class="dashboard-panel-heading">
                        <div><h2>Latest registrations</h2><p>Most recent event sign-ups</p></div>
                    </div>
                    <div class="dashboard-table-wrap">
                        <table class="dashboard-table">
                            <thead><tr><th>Student</th><th>Course</th><th>Event</th><th>Registered</th></tr></thead>
                            <tbody id="recentRegistrations"><tr><td colspan="4">Loading registrations…</td></tr></tbody>
                        </table>
                    </div>
                </section>

                <section class="dashboard-panel">
                    <div class="dashboard-panel-heading">
                        <div><h2>Recent audit activity</h2><p>Administrator actions</p></div>
                    </div>
                    <ul class="audit-list" id="auditLogList"><li>Loading activity…</li></ul>
                </section>
            </div>

            <section class="dashboard-panel account-panel">
                <div class="dashboard-panel-heading">
                    <div><h2>Student account access</h2><p>Activate or deactivate student portal access. Changes are audited.</p></div>
                </div>
                <div class="dashboard-table-wrap">
                    <table class="dashboard-table">
                        <thead><tr><th>Student</th><th>Email</th><th>Account status</th><th>Action</th></tr></thead>
                        <tbody id="studentAccounts"><tr><td colspan="4">Loading student accounts…</td></tr></tbody>
                    </table>
                </div>
            </section>

            <section class="dashboard-panel admin-create-panel" id="create-admin-account">
                <div class="dashboard-panel-heading">
                    <div><h2>Create administrator account</h2><p>Administrator accounts can only be created by a signed-in administrator. Student profile fields are not required.</p></div>
                </div>
                <form class="admin-create-form" id="createAdminForm" novalidate>
                    <label class="admin-create-field">
                        <span>Administrator full name</span>
                        <input type="text" name="full_name" maxlength="120" autocomplete="name" required>
                    </label>
                    <label class="admin-create-field">
                        <span>Administrator email</span>
                        <input type="email" name="email" maxlength="254" autocomplete="email" required>
                    </label>
                    <label class="admin-create-field">
                        <span>Password</span>
                        <input type="password" name="password" minlength="8" maxlength="64" pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[@$!%*?&])[A-Za-z0-9@$!%*?&]{8,64}" autocomplete="new-password" required>
                        <small>Use 8–64 characters with uppercase, lowercase, a number, and one of @$!%*?&amp;.</small>
                    </label>
                    <label class="admin-create-field">
                        <span>Confirm password</span>
                        <input type="password" name="confirm_password" minlength="8" maxlength="64" autocomplete="new-password" required>
                    </label>
                    <button class="account-toggle admin-create-submit" type="submit">Create administrator</button>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
