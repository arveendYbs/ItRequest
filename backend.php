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
    $stmt = $pdo->prepare('INSERT INTO requests (title, description, user_id) VALUES (?, ?, ?)');
    $stmt->execute([$_POST['title'], $_POST['description'], $_SESSION['user_id']]);
    header('Location: index.php');
    exit();
}

// Approve request (manager only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_request'])) {
    checkRole('manager', $pdo);
    $stmt = $pdo->prepare("UPDATE requests SET status = 'Approved' WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    header('Location: manager_dashboard.php');
    exit();
}

// Reject request (manager only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_request'])) {
    checkRole('manager', $pdo);
    $stmt = $pdo->prepare("UPDATE requests SET status = 'Rejected' WHERE id = ?");
    $stmt->execute([$_POST['id']]);
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
            // Request not found or user is not authorized
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

// Create user (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    checkRole('admin', $pdo);
    
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $checkStmt->execute([$_POST['username']]);
    if ($checkStmt->fetch()) {
        echo "Username already exists!";
        exit();
    }
    
    $stmt = $pdo->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)');
    $stmt->execute([
        $_POST['username'],
        password_hash($_POST['password'], PASSWORD_BCRYPT),
        $_POST['role']
    ]);
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
