<?php
session_start();
require_once 'config.php';

// Helper function to check role and redirect
function checkRole($requiredRole, $pdo) {
    if (!isset($_SESSION['role'])) {
        header('Location: login.php');
        exit();
    }
    $current_role = $_SESSION['role'];

    if ($requiredRole === 'admin' && $current_role !== 'admin') {
        header('Location: index.php');
        exit();
    }
    if ($requiredRole === 'manager' && !in_array($current_role, ['manager', 'admin', 'it_hod'])){
        header('Location: index.php');
        exit();
    }

}
// Function to get IT HOD ID (assuming admin user is IT HOD )
function getItHodId($pdo) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'it_hod' LIMIT 1");
    $stmt->execute();
    return $stmt->fetchColumn();
}


// Login logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$_POST['username']]);
    $user = $stmt->fetch();

    if ($user && password_verify($_POST['password'], $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        // Redirect based on role
        if ($user['role'] === 'admin') {
            header('Location: admin_dashboard.php');
        } elseif ($user['role'] === 'manager') {
            header('Location: manager_dashboard.php');
        } elseif ($user['role'] === 'it_hod') {
            header('Location: admin_dashboard.php');
        } else {
            header('Location: index.php');
        }
        exit();
    } else {
        // Handle invalid login
        // You might want to redirect back to login.php with an error message
        echo "Invalid username or password!";
        // For a more user-friendly experience, consider redirecting with a GET parameter for error:
    
    }
}

// Create request (user)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_request'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
    $title = $_POST['title'];
    $description = $_POST['description'];
    $category_id = $_POST['category_id'] ?? null;
    $subcategory_id = $_POST['subcategory_id'] ?? null;
    $priority = $_POST['priority'] ?? 'Medium';
    $user_id = $_SESSION['user_id'];
    $attachment_path = null;

    // --- START: Logic to determine initial approver and status ---
    $current_approver_id = null;
    $initial_status = 'Pending IT HOD'; // Default status if no specific manager

    // Fetch user's direct reporting manager
    $stmt_user_manager = $pdo->prepare("SELECT reporting_manager_id FROM users WHERE id = ?");
    $stmt_user_manager->execute([$user_id]);
    $user_reporting_manager_id = $stmt_user_manager->fetchColumn();

    $it_hod_id = getItHodId($pdo); // Get the ID of the IT HOD (admin)

    // If user has a reporting manager AND that manager is not the IT HOD
    if ($user_reporting_manager_id && $user_reporting_manager_id != $it_hod_id) {
        // Verify the reporting manager actually exists and is a manager role
        $stmt_check_manager = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'manager'");
        $stmt_check_manager->execute([$user_reporting_manager_id]);
        if ($stmt_check_manager->fetch()) {
            $current_approver_id = $user_reporting_manager_id;
            $initial_status = 'Pending Manager';
        } else {
            // Reporting manager is not a manager, or doesn't exist. Send to IT HOD.
            if ($it_hod_id) {
                $current_approver_id = $it_hod_id;
                $initial_status = 'Pending IT HOD';
            } else {
                $initial_status = 'Approved'; // Fallback if no IT HOD set
                $current_approver_id = null;
            }
        }
    } else {
        // If no reporting manager, or reporting manager IS the IT HOD,
        // it goes directly to IT HOD for initial approval.
        if ($it_hod_id) {
            $current_approver_id = $it_hod_id;
            $initial_status = 'Pending IT HOD';
        } else {
            $initial_status = 'Approved'; // Fallback if no IT HOD set
            $current_approver_id = null;
        }
    }
    // --- END: Logic to determine initial approver and status ---


    // Handle file upload for new request
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true); // Create uploads directory if it doesn't exist
        }
        $file_name = uniqid() . '_' . basename($_FILES['attachment']['name']);
        $target_file = $upload_dir . $file_name;

        // Basic file type and size validation
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        $max_size = 2 * 1024 * 1024; // 2 MB

        if (in_array($_FILES['attachment']['type'], $allowed_types) && $_FILES['attachment']['size'] <= $max_size) {
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
                $attachment_path = $target_file;
            } else {
                echo "Error uploading file.";
            }
        } else {
            echo "Invalid file type or size. Allowed: JPG, PNG, PDF (Max 2MB).";
        }
    }

    // Insert statement including current_approver_id and initial_status
    $stmt = $pdo->prepare('INSERT INTO requests (title, description, category_id, subcategory_id, priority, user_id, attachment_path, status, current_approver_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$title, $description, $category_id, $subcategory_id, $priority, $user_id, $attachment_path, $initial_status, $current_approver_id]);
    header('Location: index.php');
    exit();
}

// update request for creator only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }

    $request_id = $_POST['id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $category_id = $_POST['category_id'] ?? null;
    $subcategory_id = $_POST['subcategory_id'] ?? null;
    $current_user_id = $_SESSION['user_id'];
    $priority = $_SESSION['priority'] ?? null;

    // Verify request ownership and status
    $stmt_check = $pdo->prepare('SELECT user_id, status, attachment_path FROM requests WHERE id = ?');
    $stmt_check->execute([$request_id]);
    $request = $stmt_check->fetch();

   // Only allow edit if request owner and status is Pending Manager (initial pending state for user)
    // If it's Approved by Manager, user should no longer edit.
    if (!$request || $request['user_id'] != $current_user_id || trim($request['status']) !== 'Pending Manager' || trim($request['status'] !== 'Pending IT HOD')) {
        echo "You are not authorized to edit this request or its status is not pending manager.";
        exit();
    }


    $priority = $request['priority'];
    $attachment_path = $request['attachment_path']; // Keep existing path by default

    // Handle new file upload for update
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_name = uniqid() . '_' . basename($_FILES['attachment']['name']);
        $target_file = $upload_dir . $file_name;

        // Delete old attachment if exists
        if ($attachment_path && file_exists($attachment_path)) {
            unlink($attachment_path);
        }

        // Basic file type and size validation (optional but recommended)
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        $max_size = 2 * 1024 * 1024; // 2 MB

        if (in_array($_FILES['attachment']['type'], $allowed_types) && $_FILES['attachment']['size'] <= $max_size) {
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
                $attachment_path = $target_file;
            } else {
                echo "Error uploading new file.";
            }
        } else {
            echo "Invalid file type or size for new attachment. Allowed: JPG, PNG, PDF (Max 2MB).";
        }
    } elseif (isset($_POST['delete_attachment']) && $_POST['delete_attachment'] === '1') {
        // Handle deletion of existing attachment if checkbox is checked
        if ($attachment_path && file_exists($attachment_path)) {
            unlink($attachment_path);
        }
        $attachment_path = null;
    }


    $stmt = $pdo->prepare('UPDATE requests SET title = ?, description = ?, category_id = ?, subcategory_id = ?, attachment_path = ?, priority = ? WHERE id = ?');
    $stmt->execute([$title, $description, $category_id, $subcategory_id, $attachment_path, $request_id, $priority]);
    header('Location: index.php');
    exit();
}



// Approve request (Manager or IT HOD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_request'])) {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['manager', 'admin', 'it_hod'])) {
        header('Location: login.php');
        exit();
    }
    $request_id = $_POST['id'];
    $approver_id = $_SESSION['user_id'];
    $approver_role = $_SESSION['role'];
    $it_hod_id = getItHodId($pdo);

    // Fetch request details to determine current status and approver
    $stmt_request = $pdo->prepare("SELECT status, current_approver_id FROM requests WHERE id = ?");
    $stmt_request->execute([$request_id]);
    $request = $stmt_request->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo "Request not found.";
        exit();
    }

    $current_status = trim($request['status']);
    $expected_approver_id = $request['current_approver_id'];

    // Check if the current user is the expected approver for this stage
    if ($approver_id != $expected_approver_id) {
        echo $approver_id ;
        echo "it_hod_id:  $it_hod_id + $approver_role" ;
        echo "You are not authorized to approve this request at this stage.";
        exit();
    }

    $new_status = $current_status;
    $new_approver_id = $expected_approver_id; // Remains the same if not advancing

    switch ($current_status) {
        case 'Pending Manager':
            // If current approver is a manager and is the expected approver
            if ($approver_role === 'manager' || $approver_id == $expected_approver_id) {
                $new_status = 'Approved by Manager';
                $new_approver_id = $it_hod_id; // Next approver is IT HOD
                if (!$new_approver_id) { // Fallback if IT HOD not found
                    $new_status = 'Approved'; // Directly approve if no IT HOD set
                    $new_approver_id = null;
                }
            } else {
                echo "Invalid approval attempt for Pending Manager status.";
                exit();
            }
            break;

        case 'Pending IT HOD':
            // If current approver is admin (IT HOD) and is the expected approver
           // if ($approver_role === 'it_hod' && $approver_id == $it_hod_id && $approver_id == $expected_approver_id) {
        if ($approver_role === 'it_hod' && $approver_id == $it_hod_id) {
                $new_status = 'Approved'; // Final approval
                $new_approver_id = null;
            } else {
                echo "Invalid approval attempt for Pending IT HOD status.";
                exit();
            }
            break;
        case 'Approved by Manager':
            if ($approver_role === 'it_hod' && $approver_id == $it_hod_id) {
                $new_status = 'Approved'; // Final approval
                $new_approver_id = null;
            } else { 
                echo "Invalid approval attempt for Pending IT HOD status.";
                exit();
            }
            break;

        default:
            echo "Request is not in an approvable state.";
            exit();
    }

    // Update the request with the new status and next approver
    $stmt_update = $pdo->prepare("UPDATE requests SET status = ?, current_approver_id = ? WHERE id = ?");
    $stmt_update->execute([$new_status, $new_approver_id, $request_id]);

    // Redirect based on approver role
    if ($approver_role === 'manager') {
        header('Location: manager_dashboard.php');
    } elseif ($approver_role === 'it_hod') { // IT HODs redirect to admin_dashboard (their dashboard)
        header('Location: admin_dashboard.php');
    } else { // Fallback for other roles if any somehow got here (e.g., admin)
         header('Location: admin_dashboard.php');
    }
    exit();
}
// Reject request (Manager or IT HOD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_request'])) {
   // if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'manager' && $_SESSION['role'] !== 'admin')) {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['manager', 'admin', 'it_hod'])) {

        header('Location: login.php'); // Only managers/admins can reject
        exit();
    }

    $request_id = $_POST['id'];
    $approver_id = $_SESSION['user_id'];
    $approver_role = $_SESSION['role'];
    $it_hod_id = getItHodId($pdo);

    // Fetch request details to determine current status and approver
    $stmt_request = $pdo->prepare("SELECT status, current_approver_id FROM requests WHERE id = ?");
    $stmt_request->execute([$request_id]);
    $request = $stmt_request->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo "Request not found.";
        exit();
    }

    $current_status = trim($request['status']);
    $expected_approver_id = $request['current_approver_id'];

    // Check if the current user is the expected approver for this stage
    if ($approver_id != $expected_approver_id) {
        echo "You are not authorized to reject this request at this stage.";
        exit();
    }

    // Only allow rejection if status is Pending Manager or Pending IT HOD
    if ($current_status === 'Pending Manager' || $current_status === 'Approved by Manager') {
        $stmt = $pdo->prepare("UPDATE requests SET status = 'Rejected', current_approver_id = NULL WHERE id = ?");
        $stmt->execute([$request_id]);
    } else {
        echo "Request is not in a rejectable state.";
        exit();
    }
    
    // Redirect based on approver role
    if ($approver_role === 'manager') {
        header('Location: manager_dashboard.php');
    } elseif ($approver_role === 'admin') {
        header('Location: admin_dashboard.php');
    }
    exit();
}

// Delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_request'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }

    $request_id = $_POST['id'];
    $current_user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];

    // Fetch request details to check status and ownership
    $stmt_fetch_request = $pdo->prepare('SELECT user_id, status, attachment_path FROM requests WHERE id = ?');
    $stmt_fetch_request->execute([$request_id]);
    $request_details = $stmt_fetch_request->fetch();

    if (!$request_details) {
        echo "Request not found.";
        exit();
    }

    $request_status = trim($request_details['status']);
    $request_owner_id = $request_details['user_id'];
    $attachment_path = $request_details['attachment_path'];

    // Authorization for deletion
    $can_delete = false;
    if ($role === 'admin') {
        // Admin can delete any request regardless of status
        $can_delete = true;
    } elseif ($role === 'manager') {
        // Managers can delete ONLY requests that they are currently assigned to approve, and that are pending.
        // Or if they are the direct manager of the user who created it and it's pending their approval.
        // For simplicity, let's allow managers to delete only Pending Manager requests that they are assigned to approve
        // or any subordinate's requests that are still Pending Manager.
        $stmt_manager_subordinate_request = $pdo->prepare(
            "SELECT r.id FROM requests r JOIN users u ON r.user_id = u.id 
             WHERE r.id = ? AND u.reporting_manager_id = ? AND r.status = 'Pending Manager'"
        );
        $stmt_manager_subordinate_request->execute([$request_id, $current_user_id]);
        if ($stmt_manager_subordinate_request->fetch()) {
            $can_delete = true;
        }

    } elseif ($role === 'user') {
        // User can only delete their own requests if the status is 'Pending Manager'
        if ($request_owner_id == $current_user_id && $request_status === 'Pending Manager') {
            $can_delete = true;
        }
    }

    if (!$can_delete) {
        echo "You are not authorized to delete this request at this stage.";
        exit();
    }

    // If authorized, proceed with deletion
    $stmt_delete = $pdo->prepare('DELETE FROM requests WHERE id = ?');
    $stmt_delete->execute([$request_id]);

    // Delete associated attachment file
    if ($attachment_path && file_exists($attachment_path)) {
        unlink($attachment_path);
    }
    
    // Redirect based on role
    if ($role === 'admin') {
        header('Location: admin_dashboard.php');
    } elseif ($role === 'manager') {
        header('Location: manager_dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

// CRUD for users (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    checkRole('admin', $pdo);
    
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $checkStmt->execute([$_POST['username']]);
    if ($checkStmt->fetch()) {
        echo "Username already exists!";
        exit();
    }
    
    $username = $_POST['username'];
    $password_hashed = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role'];
    $reporting_manager_id = $_POST['reporting_manager_id'] !== '' ? $_POST['reporting_manager_id'] : null;
    $company_id = $_POST['company_id'] !== '' ? $_POST['company_id'] : null;
    $department_type_id = $_POST['department_type_id'] !== '' ? $_POST['department_type_id'] : null;


    $stmt = $pdo->prepare('INSERT INTO users (username, password, role, reporting_manager_id, company_id, department_type_id) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$username, $password_hashed, $role, $reporting_manager_id, $company_id, $department_type_id]);
    header('Location: admin_dashboard.php');
    exit();
}

// Update user (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    checkRole('admin', $pdo);

    $user_id = $_POST['user_id'];
    $username = $_POST['username'];
    $role = $_POST['role'];
    // Important: Convert empty string from 'None' selection to NULL for database
    $reporting_manager_id = $_POST['reporting_manager_id'] !== '' ? $_POST['reporting_manager_id'] : null;
    $company_id = $_POST['company_id'] !== '' ? $_POST['company_id'] : null;
    $department_type_id = $_POST['department_type_id'] !== '' ? $_POST['department_type_id'] : null;

    $sql = 'UPDATE users SET username = ?, role = ?, reporting_manager_id = ?, company_id = ?, department_type_id = ? WHERE id = ?';
    $params = [$username, $role, $reporting_manager_id, $company_id, $department_type_id, $user_id];

    if (!empty($_POST['password'])) {
        $password_hashed = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $sql = 'UPDATE users SET username = ?, password = ?, role = ?, reporting_manager_id = ?, company_id = ?, department_type_id = ? WHERE id = ?';
        $params = [$username, $password_hashed, $role, $reporting_manager_id, $company_id, $department_type_id, $user_id];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    header('Location: admin_dashboard.php');
    exit();
}
// Delete user (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    checkRole('admin', $pdo);
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$_POST['id']]);
    header('Location: admin_dashboard.php');
    exit();
}   
// CRUD for Companies (admin only) - Changed from Divisions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_company'])) { // Changed from create_division
    checkRole('admin', $pdo);
    $stmt = $pdo->prepare('INSERT INTO companies (name) VALUES (?)'); // Changed table name
    $stmt->execute([$_POST['company_name']]); // Changed from division_name
    header('Location: admin_dashboard.php');
    exit();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_company'])) { // Changed from delete_division
    checkRole('admin', $pdo);
    $stmt = $pdo->prepare('DELETE FROM companies WHERE id = ?'); // Changed table name
    $stmt->execute([$_POST['id']]);
    header('Location: admin_dashboard.php');
    exit();
}


// CRUD for Department Types (admin only) - Changed from Departments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_department_type'])) { // Changed from create_department
    checkRole('admin', $pdo);
    $stmt = $pdo->prepare('INSERT INTO department_types (company_id, name) VALUES (?, ?)'); // Changed table and column names
    $stmt->execute([$_POST['parent_company_id'], $_POST['department_type_name']]); // Changed names
    header('Location: admin_dashboard.php');
    exit();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_department_type'])) { // Changed from delete_department
    checkRole('admin', $pdo);
    $stmt = $pdo->prepare('DELETE FROM department_types WHERE id = ?'); // Changed table name
    $stmt->execute([$_POST['id']]);
    header('Location: admin_dashboard.php');
    exit();
}
// CRUD for Categories (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_category'])) {
    checkRole('admin', $pdo);
    $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (?)');
    $stmt->execute([$_POST['category_name']]);
    header('Location: admin_dashboard.php');
    exit();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    checkRole('admin', $pdo);
    $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
    $stmt->execute([$_POST['id']]);
    header('Location: admin_dashboard.php');
    exit();
}

// CRUD for Subcategories (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_subcategory'])) {
    checkRole('admin', $pdo);
    $stmt = $pdo->prepare('INSERT INTO subcategories (category_id, name) VALUES (?, ?)');
    $stmt->execute([$_POST['parent_category_id'], $_POST['subcategory_name']]);
    header('Location: admin_dashboard.php');
    exit();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_subcategory'])) {
    checkRole('admin', $pdo);
    $stmt = $pdo->prepare('DELETE FROM subcategories WHERE id = ?');
    $stmt->execute([$_POST['id']]);
    header('Location: admin_dashboard.php');
    exit();
}


// AJAX endpoint to fetch subcategories (no session check needed for this specific endpoint)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_subcategories') {
    header('Content-Type: application/json');
    $category_id = $_GET['category_id'] ?? 0;
    $stmt = $pdo->prepare('SELECT id, name FROM subcategories WHERE category_id = ? ORDER BY name');
    $stmt->execute([$category_id]);
    $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($subcategories);
    exit();
}


// AJAX endpoint to fetch department types by company
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_department_types') {
    header('Content-Type: application/json');
    $company_id = $_GET['company_id'] ?? 0;
    $stmt = $pdo->prepare('SELECT id, name FROM department_types WHERE company_id = ? ORDER BY name');
    $stmt->execute([$company_id]);
    $department_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($department_types);
    exit();
}