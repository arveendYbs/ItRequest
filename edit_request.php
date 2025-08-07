<?php
session_start();
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$request_id = $_GET['id'] ?? null;
if (!$request_id) {
    header('Location: index.php');
    exit();
}

// Fetch request details and verify ownership/status
$stmt = $pdo->prepare('SELECT r.*, c.name as category_name, sc.name as subcategory_name 
                     FROM requests r 
                     LEFT JOIN categories c ON r.category_id = c.id
                     LEFT JOIN subcategories sc ON r.subcategory_id = sc.id
                     WHERE r.id = ? AND r.user_id = ?'); // Ensure user owns the request
$stmt->execute([$request_id, $_SESSION['user_id']]);
$request = $stmt->fetch();

if (!$request || trim($request['status']) !== 'Pending') {
    // If request not found, not owned by user, or not pending, deny access
    echo "You are not authorized to edit this request or its status is not pending.";
    // Optionally, redirect to index.php with an error message
    // header('Location: index.php?error=unauthorized_edit');
    exit();
}

// Fetch all categories for dropdowns
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

// Fetch subcategories for the selected category (if any)
$subcategories = [];
if ($request['category_id']) {
    $stmt_sub = $pdo->prepare('SELECT id, name FROM subcategories WHERE category_id = ? ORDER BY name');
    $stmt_sub->execute([$request['category_id']]);
    $subcategories = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Request</title>
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
        <h1 class="mb-4">Edit Request #<?php echo htmlspecialchars($request['id']); ?></h1>

        <div class="card p-4 shadow mb-5">
            <h2 class="h4 text-dark mb-3">Modify Request Details</h2>
            <form method="POST" action="backend.php" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($request['id']); ?>">
                <div class="mb-3">
                    <label for="requestTitle" class="form-label">Request Title</label>
                    <input type="text" name="title" id="requestTitle" class="form-control" value="<?php echo htmlspecialchars($request['title']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="requestDescription" class="form-label">Request Description</label>
                    <textarea name="description" id="requestDescription" class="form-control" rows="3" required><?php echo htmlspecialchars($request['description']); ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="categorySelect" class="form-label">Category</label>
                    <select name="category_id" id="categorySelect" class="form-select" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['id']); ?>" <?php echo ($request['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="subcategorySelect" class="form-label">Subcategory</label>
                    <select name="subcategory_id" id="subcategorySelect" class="form-select" required <?php echo empty($subcategories) ? 'disabled' : ''; ?>>
                        <option value="">Select Subcategory</option>
                        <?php foreach ($subcategories as $subcat): ?>
                            <option value="<?php echo htmlspecialchars($subcat['id']); ?>" <?php echo ($request['subcategory_id'] == $subcat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subcat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="attachment" class="form-label">Attachment</label>
                    <?php if ($request['attachment_path']): ?>
                        <p>Current Attachment: <a href="<?php echo htmlspecialchars($request['attachment_path']); ?>" target="_blank"><?php echo basename($request['attachment_path']); ?></a></p>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="delete_attachment" value="1" id="deleteAttachment">
                            <label class="form-check-label" for="deleteAttachment">
                                Delete current attachment
                            </label>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="attachment" id="attachment" class="form-control">
                    <small class="form-text text-muted">Upload a new file to replace the existing one, or check "Delete current attachment" to remove it. Max file size: 2MB. Allowed types: jpg, png, pdf.</small>
                </div>
                <button type="submit" name="update_request" class="btn btn-primary">Save Changes</button>
                <a href="index.php" class="btn btn-secondary ms-2">Cancel</a>
            </form>
        </div>
    </div>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JavaScript for dynamic subcategory dropdown
        document.addEventListener('DOMContentLoaded', function() {
            const categorySelect = document.getElementById('categorySelect');
            const subcategorySelect = document.getElementById('subcategorySelect');

            // Function to load subcategories
            function loadSubcategories(categoryId, selectedSubcategoryId = null) {
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
                                    if (selectedSubcategoryId && subcat.id == selectedSubcategoryId) {
                                        option.selected = true;
                                    }
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
            }

            // Event listener for category change
            categorySelect.addEventListener('change', function() {
                loadSubcategories(this.value);
            });

            // Load subcategories on page load if a category is already selected (for existing request)
            const initialCategoryId = categorySelect.value;
            const initialSubcategoryId = "<?php echo htmlspecialchars($request['subcategory_id'] ?? ''); ?>";
            if (initialCategoryId) {
                loadSubcategories(initialCategoryId, initialSubcategoryId);
            }
        });
    </script>
</body>
</html>
