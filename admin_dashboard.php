<?php
session_start();
require_once 'config.php';

// Access control: only admins can view this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$totalRequests = $pdo->query('SELECT COUNT(*) FROM requests')->fetchColumn();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="custom.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">IT Request System</a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="admin_dashboard.php">Admin Dashboard</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="btn btn-primary" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container mt-5">
        <h1 class="mb-4">Welcome, Admin <?php echo htmlspecialchars($_SESSION['username']); ?></h1>

        <!-- Total Requests Card -->
        <div class="card p-4 shadow mb-5">
            <h2 class="h4 text-dark mb-0">Total Requests: <span class="badge bg-primary"><?php echo $totalRequests; ?></span></h2>
        </div>

        <!-- All Requests Table Card -->
        <div class="card p-4 shadow mb-5">
            <h3 class="mb-3">All Requests</h3>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-purple-header">
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th>User</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query('SELECT r.*, u.username FROM requests r JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC');
                        
                        while ($request = $stmt->fetch()) {
                            $status_class = '';
                            switch ($request['status']) {
                                case 'Approved':
                                    $status_class = 'bg-success';
                                    break;
                                case 'Pending':
                                    $status_class = 'bg-warning text-dark';
                                    break;
                                case 'Rejected':
                                    $status_class = 'bg-danger';
                                    break;
                                default:
                                    $status_class = 'bg-secondary';
                                    break;
                            }
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($request['title']) . '</td>';
                            echo '<td>' . htmlspecialchars($request['description']) . '</td>';
                            echo '<td>' . htmlspecialchars($request['username']) . '</td>';
                            echo '<td><span class="badge ' . $status_class . '">' . htmlspecialchars($request['status']) . '</span></td>';
                            echo '<td>';
                            echo '<form method="POST" action="backend.php" class="d-inline-block">
                                      <input type="hidden" name="id" value="' . htmlspecialchars($request['id']) . '">
                                      <button type="submit" name="delete_request" class="btn btn-danger btn-sm">Delete</button>
                                  </form>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- User Management Card -->
        <div class="card p-4 shadow">
            <h3 class="mb-3">User Management</h3>
            <form method="POST" action="backend.php" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="text" name="username" class="form-control" placeholder="New Username" required>
                    </div>
                    <div class="col-md-4">
                        <input type="password" name="password" class="form-control" placeholder="New Password" required>
                    </div>
                    <div class="col-md-2">
                        <select name="role" class="form-select" required>
                            <option value="user">User</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="create_user" class="btn btn-primary w-100">Create User</button>
                    </div>
                </div>
            </form>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-purple-thead">
                        <tr>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $users = $pdo->query('SELECT * FROM users');
                        foreach ($users as $u) {
                            echo '<tr><td>' . htmlspecialchars($u['username']) . '</td><td>' . htmlspecialchars($u['role']) . '</td><td>';
                            echo '<form method="POST" action="backend.php" class="d-inline-block">
                                      <input type="hidden" name="id" value="' . htmlspecialchars($u['id']) . '">
                                      <button type="submit" name="delete_user" class="btn btn-danger btn-sm">Delete</button>
                                  </form>';
                            echo '</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- Bootstrap JS (optional, for some components like dropdowns) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
