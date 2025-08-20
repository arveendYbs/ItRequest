<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$request_id = $_GET['id'] ?? null;
if (!$request_id) {
    header('Location: index.php'); // Redirect if no request ID is provided
    exit();
}

// Fetch request details, including join with users for names of approvers
$stmt_request = $pdo->prepare('SELECT 
    r.*, 
    u.username as requested_by_username,
    u.role as requested_by_role,
    uc.name as user_company_name,
    udt.name as user_department_type_name,
    c.name as category_name, 
    sc.name as subcategory_name,
    ca.username as current_approver_username,
    ma.username as manager_approved_by_username,
    itha.username as it_hod_approved_by_username
FROM requests r 
JOIN users u ON r.user_id = u.id 
LEFT JOIN companies uc ON u.company_id = uc.id
LEFT JOIN department_types udt ON u.department_type_id = udt.id
LEFT JOIN categories c ON r.category_id = c.id
LEFT JOIN subcategories sc ON r.subcategory_id = sc.id
LEFT JOIN users ca ON r.current_approver_id = ca.id
LEFT JOIN users ma ON r.manager_approved_by = ma.id
LEFT JOIN users itha ON r.it_hod_approved_by = itha.id
WHERE r.id = ?');
$stmt_request->execute([$request_id]);
$request = $stmt_request->fetch(PDO::FETCH_ASSOC);

if (!$request) {
     echo "<!DOCTYPE html><html><head><title>Request Not Found</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
    echo "<link rel='stylesheet' href='custom.css'></head><body>";
    echo "<div class='container mt-5'><div class='alert alert-danger'>Request with ID " . htmlspecialchars($request_id) . " not found.</div>";
    echo "<a href='javascript:history.back()' class='btn btn-secondary mt-3'>Back</a></div>";
    echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'></script></body></html>";
    exit();
}

$logged_in_user_id = $_SESSION['user_id'];
$logged_in_user_role = $_SESSION['role'];
$status = trim($request['status']);

// Determine if the current user can approve/reject this request
$can_act_on_request = false;
if ($request['current_approver_id'] == $logged_in_user_id) {
    if ($logged_in_user_role === 'manager' && $status === 'Pending Manager') {
        $can_act_on_request = true;
    } elseif ($logged_in_user_role === 'it_hod' && ($status === 'Pending IT HOD' || $status === 'Approved by Manager')) {
        $can_act_on_request = true;
    }
}

// Determine if the current user can delete this request (stricter rules now)
$can_delete_request = false;
if ($logged_in_user_role === 'admin' || $logged_in_user_role === 'it_hod') {
    $can_delete_request = true; // Admin/IT HOD can delete any request
} elseif ($logged_in_user_role === 'manager') {
    // Manager can delete if pending their approval, or if it's their subordinate's and still pending manager approval
    if ($status === 'Pending Manager' && $request['current_approver_id'] == $logged_in_user_id) {
        $can_delete_request = true;
    }
} elseif ($logged_in_user_role === 'user') {
    // User can delete their own request only if it's Pending Manager approval
    if ($request['user_id'] == $logged_in_user_id && $status === 'Pending Manager') {
        $can_delete_request = true;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Request #<?php echo htmlspecialchars($request['id']); ?></title>
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
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <?php if (in_array($logged_in_user_role, ['manager', 'admin', 'it_hod'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="manager_dashboard.php">Manager Dashboard</a>
                        </li>
                    <?php endif; ?>
                    <?php if (in_array($logged_in_user_role, ['admin', 'it_hod'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_dashboard.php">Admin Dashboard</a>
                        </li>
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
        <h1 class="mb-4">Request Details #<?php echo htmlspecialchars($request['id']); ?></h1>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Request <?php echo htmlspecialchars($_GET['success']); ?> successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                Error: <?php echo htmlspecialchars(str_replace('_', ' ', $_GET['error'])); ?>.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card p-4 shadow mb-4">
            <h2 class="h4 text-dark mb-3">Request Information</h2>
            <div class="row mb-2">
                <div class="col-md-3"><strong>Title:</strong></div>
                <div class="col-md-9"><?php echo htmlspecialchars($request['title']); ?></div>
            </div>
            <div class="row mb-2">
                <div class="col-md-3"><strong>Description:</strong></div>
                <div class="col-md-9"><?php echo htmlspecialchars($request['description']); ?></div>
            </div>
            <div class="row mb-2">
                <div class="col-md-3"><strong>Category:</strong></div>
                <div class="col-md-9"><?php echo htmlspecialchars($request['category_name'] ?? 'N/A'); ?></div>
            </div>
            <div class="row mb-2">
                <div class="col-md-3"><strong>Subcategory:</strong></div>
                <div class="col-md-9"><?php echo htmlspecialchars($request['subcategory_name'] ?? 'N/A'); ?></div>
            </div>
            <div class="row mb-2">
                <div class="col-md-3"><strong>Priority:</strong></div>
                <div class="col-md-9"><?php echo htmlspecialchars($request['priority']); ?></div>
            </div>
            <div class="row mb-2">
                <div class="col-md-3"><strong>Requested By:</strong></div>
                <div class="col-md-9"><?php echo htmlspecialchars($request['requested_by_username']); ?> (<?php echo htmlspecialchars(ucfirst($request['requested_by_role'])); ?>)</div>
            </div>
            <div class="row mb-2">
                <div class="col-md-3"><strong>User Company:</strong></div>
                <div class="col-md-9"><?php echo htmlspecialchars($request['user_company_name'] ?? 'N/A'); ?></div>
            </div>
            <div class="row mb-2">
                <div class="col-md-3"><strong>User Department Type:</strong></div>
                <div class="col-md-9"><?php echo htmlspecialchars($request['user_department_type_name'] ?? 'N/A'); ?></div>
            </div>
            <div class="row mb-2">
                <div class="col-md-3"><strong>Current Status:</strong></div>
                <div class="col-md-9"><span class="badge 
                    <?php 
                        switch ($status) {
                            case 'Approved': echo 'bg-success'; break;
                            case 'Pending Manager': echo 'bg-warning text-dark'; break;
                            case 'Approved by Manager': echo 'bg-info'; break;
                            case 'Pending IT HOD': echo 'bg-warning text-dark'; break;
                            case 'Rejected': echo 'bg-danger'; break;
                            default: echo 'bg-secondary'; break;
                        }
                    ?>"><?php echo htmlspecialchars($status); ?></span></div>
            </div>
            <div class="row mb-2">
                <div class="col-md-3"><strong>Current Approver:</strong></div>
                <div class="col-md-9"><?php echo htmlspecialchars($request['current_approver_username'] ?? 'N/A'); ?></div>
            </div>
            <div class="row mb-2">
                <div class="col-md-3"><strong>Created At:</strong></div>
                <div class="col-md-9"><?php echo htmlspecialchars($request['created_at']); ?></div>
            </div>
            <div class="row mb-2">
                <div class="col-md-3"><strong>Attachment:</strong></div>
                <div class="col-md-9">
                    <?php if ($request['attachment_path']): ?>
                        <a href="<?php echo htmlspecialchars($request['attachment_path']); ?>" target="_blank" class="btn btn-info btn-sm">View Attachment</a>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Approval History Section -->
        <div class="card p-4 shadow mb-4">
            <h2 class="h4 text-dark mb-3">Approval History</h2>
            <div class="row mb-2">
                <div class="col-md-4"><strong>Approved by Manager:</strong></div>
                <div class="col-md-8">
                    <?php 
                        if ($request['manager_approved_by_username']) {
                            echo htmlspecialchars($request['manager_approved_by_username']) . ' on ' . htmlspecialchars($request['manager_approved_at']);
                        } else {
                            echo 'Pending or N/A';
                        }
                    ?>
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-md-4"><strong>Approved by IT HOD:</strong></div>
                <div class="col-md-8">
                    <?php 
                        if ($request['it_hod_approved_by_username']) {
                            echo htmlspecialchars($request['it_hod_approved_by_username']) . ' on ' . htmlspecialchars($request['it_hod_approved_at']);
                        } else {
                            echo 'Pending or N/A';
                        }
                    ?>
                </div>
            </div>
        </div>

        <!-- Action Buttons Section -->
        <div class="card p-4 shadow mb-4">
            <h2 class="h4 text-dark mb-3">Actions</h2>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($can_act_on_request): ?>
                    <form method="POST" action="backend.php" class="d-inline-block">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($request['id']); ?>">
                        <button type="submit" name="approve_request" class="btn btn-success me-2">Approve</button>
                    </form>
                    <form method="POST" action="backend.php" class="d-inline-block">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($request['id']); ?>">
                        <button type="submit" name="reject_request" class="btn btn-danger">Reject</button>
                    </form>
                <?php else: ?>
                    <span class="text-muted">No approval/rejection actions available for you on this request at this stage.</span>
                <?php endif; ?>

                <?php 
                // Edit button for user if status is Pending Manager
                if ($logged_in_user_role === 'user' && $request['user_id'] == $logged_in_user_id && $status === 'Pending Manager') {
                    echo '<a href="edit_request.php?id=' . htmlspecialchars($request['id']) . '" class="btn btn-secondary ms-2">Edit Request</a>';
                }
                ?>

                <?php if ($can_delete_request): ?>
                    <form method="POST" action="backend.php" class="d-inline-block ms-auto">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($request['id']); ?>">
                        <button type="submit" name="delete_request" class="btn btn-danger">Delete Request</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <a href="javascript:history.back()" class="btn btn-secondary mt-3">Back</a>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
