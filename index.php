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

//fetch all categrories fro the dropdown 
$categories = $pdo->query(
    'SELECT id, name FROM categories ORDER BY name')->fetchALL(PDO::FETCH_ASSOC);


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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      
                <span class="navbar-toggler-icon"></span>
            </button>

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
                <ul class="navbar-nav ms-auto">
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
            <form method="POST" action="backend.php" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="requestTitle" class="form-label">Request Title</label>
                    <input type="text" name="title" id="requestTitle" class="form-control" placeholder="Request Title" required>
                </div>
                <div class="mb-3">
                    <label for="requestDescription" class="form-label">Request Description</label>
                    <textarea name="description" id="requestDescription" class="form-control" placeholder="Request Description" rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <label for="categorySelect" class="form-label">Category</label>
                    <select name="category_id" id="categorySelect" class="form-select" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="subcategorySelect" class="form-label">Subcategory</label>
                    <select name="subcategory_id" id="subcategorySelect" class="form-select" required disabled>
                        <option value="">Select Subcategory</option>
                    </select>
                </div>

                <!-- Priority drop down -->
                 <div class="mb-3">
                    <label for="requestPriority" class="form-label">Priority</label>
                    <select name="priority" id="requestPriority" class="form-select" required>
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                    </select>
                 </div>


                <div class="mb-3">
                    <label for="attachment" class="form-label">Attachment (Optional)</label>
                    <input type="file" name="attachment" id="attachment" class="form-control">
                    <small class="form-text text-muted">Max file size: 2MB. Allowed types: jpg, png, pdf.</small>
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
                            <th>Category</th>
                            <th>Subcategory</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Attachment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        //$stmt = $pdo->prepare('SELECT * FROM requests WHERE user_id = ? ORDER BY created_at DESC');
                        $stmt = $pdo->prepare('SELECT r.*, c.name as category_name, sc.name as subcategory_name
                        FROM requests r
                        LEFT JOIN categories c ON r.category_id = c.id 
                        LEFT JOIN subcategories sc ON r.subcategory_id = sc.id
                        WHERE r.user_id = ? ORDER BY r.created_at DESC');
                        $stmt->execute([$_SESSION['user_id']]);
                        
                        while ($request = $stmt->fetch()) {
                            $status = trim($request['status']); 
                            $status_class = '';

                            switch ($request['status']) {
                                case 'Approved':
                                    $status_class = 'bg-success';
                                    break;
                                case 'Pending Manager':
                                case 'Pending IT HOD':
                                    $status_class = 'bg-warning text-dark';
                                    break;
                                case 'Rejected':
                                    $status_class = 'bg-danger';
                                    break;
                                case 'Approved By Manager':
                                    $status_class = 'bg-info';
                                    break;
            

                                default:
                                    $status_class = 'bg-secondary';
                                    break;
                            }
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($request['title']) . '</td>';
                            echo '<td>' . htmlspecialchars($request['description']) . '</td>';
                            echo '<td>' . htmlspecialchars($request['category_name'] ?? 'N/A') . '</td>';
                            echo '<td>' . htmlspecialchars($request['subcategory_name'] ?? 'N/A') . '</td>';
                            echo '<td><span class="badge ' . $status_class . '">' . htmlspecialchars($status) . '</span></td>'; 
                            echo '<td>' . htmlspecialchars($request['priority']) . '</td>';

                            echo '<td>';
                              if ($request['attachment_path']) {
                                echo '<a href="' . htmlspecialchars($request['attachment_path']) . '" target="_blank" class="btn btn-info btn-sm">View</a>';
                            } else {
                                echo 'N/A';
                            }
                            echo '</td>';
                            echo '<td>';
                            // Show Edit and Delete buttons only if status is Pending
                            if ($status === 'Pending Manager') {
                                echo '<a href="edit_request.php?id=' . htmlspecialchars($request['id']) . '" class="btn btn-secondary btn-sm me-2">Edit</a>';
                                echo '<form method="POST" action="backend.php" class="d-inline-block">
                                          <input type="hidden" name="id" value="' . htmlspecialchars($request['id']) . '">
                                          <button type="submit" name="delete_request" class="btn btn-danger btn-sm">Delete</button>
                                      </form>';
                            } else {
                                echo 'No actions';
                            }
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
<script>
        // JavaScript for dynamic subcategory dropdown
        document.addEventListener('DOMContentLoaded', function() {
            const categorySelect = document.getElementById('categorySelect');
            const subcategorySelect = document.getElementById('subcategorySelect');

            categorySelect.addEventListener('change', function() {
                const categoryId = this.value;
                subcategorySelect.innerHTML = '<option value="">Loading...</option>'; // Clear and show loading
                subcategorySelect.disabled = true;

                if (categoryId) {
                    fetch(`backend.php?action=get_subcategories&category_id=${categoryId}`)
                        .then(response => response.json())
                        .then(data => {
                            subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
                            if (data.length > 0) {
                                data.forEach(subcat => {
                                    const option = document.createElement('option');
                                    option.value = subcat.id;
                                    option.textContent = subcat.name;
                                    subcategorySelect.appendChild(option);
                                });
                                subcategorySelect.disabled = false;
                            } else {
                                subcategorySelect.innerHTML = '<option value="">No Subcategories Found</option>';
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching subcategories:', error);
                            subcategorySelect.innerHTML = '<option value="">Error loading subcategories</option>';
                        });
                } else {
                    subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
                    subcategorySelect.disabled = true;
                }
            });
        });
    </script>

</body>
</html>
