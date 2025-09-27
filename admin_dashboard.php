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

// Only admins can access
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// Fetch all events with organizer info
$stmt = $pdo->prepare("
    SELECT events.*, users.name AS organizer_name, users.email AS organizer_email 
    FROM events 
    JOIN users ON events.created_by = users.id
    ORDER BY events.event_date ASC
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all users
try {
    $stmt = $pdo->prepare("SELECT * FROM users ORDER BY name ASC");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $users = [];
}

// Handle messages from redirects
$message = null; // ✅ initialize first
if (isset($_GET['msg'])) {
    $message = urldecode($_GET['msg']);
}

// Handle add organizer form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_organizer'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $message = "Invalid request";
    } else {
        $name = sanitizeInput($_POST['name']);
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        
        if (!validateEmail($email)) {
            $message = "Invalid email format";
        } elseif (strlen($password) < 6) {
            $message = "Password must be at least 6 characters";
        } else {
            // Check if email already exists
            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check_stmt->execute([$email]);
            
            if ($check_stmt->rowCount() > 0) {
                $message = "Email already exists!";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert_stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, approved) VALUES (?, ?, ?, 'organizer', 1)");
                if ($insert_stmt->execute([$name, $email, $hashed_password])) {
                    $message = "Organizer added successfully!";
                } else {
                    $message = "Error adding organizer!";
                }
            }
        }
    }
}
?>
<?php include 'includes/header.php'; ?>

<div class="container">
    <h2>Admin Dashboard</h2>
    
    <!-- Display messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= e($_SESSION['success']); ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= e($_SESSION['error']); ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="alert alert-info"><?= e($message); ?></div>
    <?php endif; ?>

    <!-- Add Organizer Form -->
    <div class="card">
        <h3>Add New Organizer</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">
            <input type="text" name="name" placeholder="Organizer Name" required>
            <input type="email" name="email" placeholder="Organizer Email" required>
            <input type="password" name="password" placeholder="Password (min 6 characters)" required minlength="6">
            <button type="submit" name="add_organizer">Add Organizer</button>
        </form>
    </div>

    <h2>Manage Users</h2>
    <table class="table">
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Approved</th>
            <th>Actions</th>
        </tr>
        <?php foreach($users as $u): ?>
        <tr>
            <td><?= e($u['name']); ?></td>
            <td><?= e($u['email']); ?></td>
            <td><?= e($u['role']); ?></td>
            <td><?= $u['approved'] ? '✅ Yes' : '❌ No'; ?></td>
            <td>
                <?php if ($u['id'] != $_SESSION['user']['id']): ?>
                    <form method="get" action="admin_action.php" style="display:inline;">
                    <input type="hidden" name="id" value="<?= $u['id']; ?>">
                    <input type="hidden" name="action" value="remove_user">
                    <button type="submit" onclick="return confirm('Remove this user?');" 
                    class="btn btn-danger btn-sm">🗑 Remove</button>
                    </form>
                    <?php if ($u['role'] == 'organizer' && !$u['approved']): ?>
                        <form method="post" action="admin_action.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">
                            <input type="hidden" name="id" value="<?= $u['id']; ?>">
                            <input type="hidden" name="action" value="approve_user">
                            <button type="submit" class="btn btn-success btn-sm">✔ Approve</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>Manage Events</h2>
    <?php if (count($events) > 0): ?>
        <?php foreach ($events as $event): ?>
            <div class="event-card">
                <h3><?= e($event['title']); ?> (<?= e($event['event_date']); ?>)</h3>
                <p><strong>Description:</strong> <?= e($event['description']); ?></p>
                <p><strong>Organizer:</strong> <?= e($event['organizer_name']); ?> (<?= e($event['organizer_email']); ?>)</p>
                
                <form method="get" action="admin_action.php">
                <input type="hidden" name="id" value="<?= $event['id']; ?>">
                <input type="hidden" name="action" value="delete_event">
                <button type="submit" onclick="return confirm('Are you sure you want to delete this event?');" 
                class="btn btn-danger">🗑 Delete Event</button>
                </form>

                <?php
                // Fetch registrations for this event
                $reg_stmt = $pdo->prepare("
                    SELECT users.name, users.email 
                    FROM registrations 
                    JOIN users ON registrations.user_id = users.id 
                    WHERE registrations.event_id = ?
                ");
                $reg_stmt->execute([$event['id']]);
                $registrations = $reg_stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <?php if (count($registrations) > 0): ?>
                    <h4>Registered Students (<?= count($registrations); ?>)</h4>
                    <table class="table">
                        <tr>
                            <th>Student Name</th>
                            <th>Email</th>
                        </tr>
                        <?php foreach ($registrations as $r): ?>
                        <tr>
                            <td><?= e($r['name']); ?></td>
                            <td><?= e($r['email']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p>No students registered for this event yet.</p>
                <?php endif; ?>
            </div>
            <hr>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No events created yet.</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const dashboardLinks = document.querySelectorAll('a[href="dashboard.php"]');
    dashboardLinks.forEach(link => {
        link.href = 'admin_dashboard.php';
    });
});
</script>

<?php include 'includes/footer.php'; ?>
