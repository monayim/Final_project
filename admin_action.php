<?php
// 1. Functions first
require_once 'includes/functions.php';

// 2. Start session safely
safe_session_start();

// 3. Database connection
require_once 'config/db.php';

// 4. CSRF functions
require_once 'includes/csrf.php';

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// ✅ CSRF validation for POST requests only
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['csrf_token']) && !validateCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request";
        header("Location: admin_dashboard.php");
        exit;
    }
}

// ✅ CSRF validation for GET requests (for delete actions)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if (isset($_GET['csrf_token']) && !validateCsrfToken($_GET['csrf_token'])) {
        $_SESSION['error'] = "Invalid request";
        header("Location: admin_dashboard.php");
        exit;
    }
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];

    try {
        if ($action === 'delete_event') {
            $pdo->beginTransaction();

            // Delete registrations first
            $stmt = $pdo->prepare("DELETE FROM registrations WHERE event_id=?");
            $stmt->execute([$id]);

            // Delete the event
            $stmt = $pdo->prepare("DELETE FROM events WHERE id=?");
            $stmt->execute([$id]);

            $pdo->commit();
            $_SESSION['success'] = "Event deleted successfully";
        }

        elseif ($action === 'remove_user') {
            if ($id == $_SESSION['user']['id']) {
                $_SESSION['error'] = "Cannot delete your own account";
            } else {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("DELETE FROM registrations WHERE user_id=?");
                $stmt->execute([$id]);

                $stmt = $pdo->prepare("DELETE FROM events WHERE created_by=?");
                $stmt->execute([$id]);

                $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
                $stmt->execute([$id]);

                $pdo->commit();
                $_SESSION['success'] = "User removed successfully";
            }
        }

        elseif ($action === 'approve_user') {
            $stmt = $pdo->prepare("UPDATE users SET approved=1 WHERE id=?");
            $stmt->execute([$id]);
            $_SESSION['success'] = "User approved successfully";
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack(); // ✅ only rollback if transaction active
        }
        error_log("Admin action error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred: " . $e->getMessage();
    }

    header("Location: admin_dashboard.php");
    exit;
}
?>
