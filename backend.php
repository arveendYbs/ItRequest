<?php
session_start();
require_once 'config.php';

// Helper function to check role and redirect
function checkRole($requiredRole, $pdo) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $requiredRole) {
        header('Location: index.php'); // Redirect to home page if unauthorized
        exit();
    }
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
    $category_id = $_POST['category_id'] ?? null; // New field
    $subcategory_id = $_POST['subcategory_id'] ?? null; // New field
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare('INSERT INTO requests ( title, description, category_id, subcategory_id, user_id) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$title, $description, $category_id, $subcategory_id, $user_id]);
    header('Location: index.php');
    exit();
}

// Approve request (manager only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_request'])) {
    checkRole('manager', $pdo);
    $request_id = $_POST['id'];
    $manager_id = $_SESSION['user_id'];

    // Verify if the request belongs to a subordinate of the current manager
    $stmt_check = $pdo->prepare('SELECT r.id FROM requests r JOIN users u ON r.user_id = u.id WHERE r.id = ? AND u.reporting_manager_id = ?');
    $stmt_check->execute([$request_id, $manager_id]);

    if ($stmt_check->fetch()) {
        $stmt = $pdo->prepare("UPDATE requests SET status = 'Approved' WHERE id = ?");
        $stmt->execute([$request_id]);
    } else {
        // Optionally, handle unauthorized approval attempt
        echo "You are not authorized to approve this request.";
    }
    header('Location: manager_dashboard.php');
    exit();
}

// Reject request (manager only, for their subordinates)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_request'])) {
    checkRole('manager', $pdo);
    $request_id = $_POST['id'];
    $manager_id = $_SESSION['user_id'];

    // Verify if the request belongs to a subordinate of the current manager
    $stmt_check = $pdo->prepare('SELECT r.id FROM requests r JOIN users u ON r.user_id = u.id WHERE r.id = ? AND u.reporting_manager_id = ?');
    $stmt_check->execute([$request_id, $manager_id]);
    
    if ($stmt_check->fetch()) {
        $stmt = $pdo->prepare("UPDATE requests SET status = 'Rejected' WHERE id = ?");
        $stmt->execute([$request_id]);
    } else {
        // Optionally, handle unauthorized rejection attempt
        echo "You are not authorized to reject this request.";
    }
    header('Location: manager_dashboard.php');
    exit();
}

// Delete request (user, manager, admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_request'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }

    $request_id = $_POST['id'];
    $current_user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];

    // Only allow deletion if user owns the request or is manager/admin
    if ($role === 'user') {
        $stmt_check = $pdo->prepare('SELECT user_id FROM requests WHERE id = ?');
        $stmt_check->execute([$request_id]);
        $request = $stmt_check->fetch();
        
        if (!$request || $request['user_id'] != $current_user_id) {
            header('Location: index.php');
            exit();
        }
    }
    
    $stmt_delete = $pdo->prepare('DELETE FROM requests WHERE id = ?');
    $stmt_delete->execute([$request_id]);
    
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
    $reporting_manager_id = $_POST['reporting_manager_id'] !== '' ? $_POST['reporting_manager_id'] : null; // New field

    $stmt = $pdo->prepare('INSERT INTO users (username, password, role, reporting_manager_id) VALUES (?, ?, ?, ?)');
    $stmt->execute([$username, $password_hashed, $role, $reporting_manager_id]);
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