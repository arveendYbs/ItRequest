<?php
session_start();
require_once 'config.php';

// Redirect users with specific roles to their dashboards
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin_dashboard.php');
        exit();
    } elseif ($_SESSION['role'] === 'manager') {
        header('Location: manager_dashboard.php');
        exit();
    }
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>IT Request System</title>
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
                        <a class="nav-link active" aria-current="page" href="index.php">Home</a>
                    </li>
                    <?php if (isset($_SESSION['role'])): ?>
                        <?php if ($_SESSION['role'] === 'manager'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="manager_dashboard.php">Manager Dashboard</a>
                            </li>
                        <?php endif; ?>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="admin_dashboard.php">Admin Dashboard</a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
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
        <h1 class="mb-4">Welcome to the IT Request, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>

        <!-- Create New Request Card -->
        <div class="card p-4 shadow mb-5">
            <h2 class="h4 text-dark mb-3">Create a New Request</h2>
            <form method="POST" action="backend.php">
                <div class="mb-3">
                    <input type="text" name="title" class="form-control" placeholder="Request Title" required>
                </div>
                <div class="mb-3">
                    <textarea name="description" class="form-control" placeholder="Request Description" rows="3" required></textarea>
                </div>
                <button type="submit" name="create_request" class="btn btn-primary">Create Request</button>
            </form>
        </div>

        <!-- Your Requests Card -->
        <div class="card p-4 shadow">
            <h2 class="h4 text-dark mb-3">Your Requests</h2>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-purple-header">
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->prepare('SELECT * FROM requests WHERE user_id = ? ORDER BY created_at DESC');
                        $stmt->execute([$_SESSION['user_id']]);
                        
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
    </div>
    <!-- Bootstrap JS (optional, for some components like dropdowns) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
