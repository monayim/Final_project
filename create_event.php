<?php
// 1. Functions first - Correct path
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

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin','organizer'])) {
    header("Location: login.php");
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $message = "Invalid request";
    } else {
        $title = sanitizeInput($_POST['title']);
        $desc  = sanitizeInput($_POST['description']);
        $date  = $_POST['event_date'];
        $limit = (int)$_POST['registration_limit'];
        $cost  = floatval($_POST['cost']);

        // Validate date
        if (strtotime($date) < strtotime('today')) {
            $message = "Event date cannot be in the past";
        } elseif ($limit < 1) {
            $message = "Registration limit must be at least 1";
        } elseif ($cost < 0) {
            $message = "Cost cannot be negative";
        } else {
            $stmt = $pdo->prepare("INSERT INTO events (title, description, event_date, registration_limit, cost, created_by) VALUES (?,?,?,?,?,?)");
            if ($stmt->execute([$title, $desc, $date, $limit, $cost, $_SESSION['user']['id']])) {
                $cost_display = $cost > 0 ? "Cost: $" . number_format($cost, 2) : "Free event";
                $message = "Event created successfully! Registration limit: " . $limit . " participants. " . $cost_display;
                $_POST = array();
            } else {
                $message = "Error creating event.";
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>
<div class="container">
  <h2>Create Event</h2>
  
  <?php if ($message): ?>
    <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-danger' : 'alert-success'; ?>">
        <?= e($message); ?>
    </div>
  <?php endif; ?>
  
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">
    
    <label>Event Title:</label>
    <input type="text" name="title" placeholder="Event Title" value="<?= isset($_POST['title']) ? e($_POST['title']) : ''; ?>" required>
    
    <label>Event Description:</label>
    <textarea name="description" placeholder="Event Description" required><?= isset($_POST['description']) ? e($_POST['description']) : ''; ?></textarea>
    
    <label>Event Date:</label>
    <input type="date" name="event_date" value="<?= isset($_POST['event_date']) ? e($_POST['event_date']) : ''; ?>" min="<?= date('Y-m-d'); ?>" required>
    
    <label>Registration Limit (Max Participants):</label>
    <input type="number" name="registration_limit" min="1" max="1000" value="<?= isset($_POST['registration_limit']) ? e($_POST['registration_limit']) : '10'; ?>" required>
    <small>Maximum number of students who can register for this event</small>
    
    <label>Event Cost ($):</label>
    <input type="number" name="cost" min="0" step="0.01" value="<?= isset($_POST['cost']) ? e($_POST['cost']) : '0.00'; ?>" required>
    <small>Enter 0 for free events</small>
    
    <button type="submit" class="btn btn-primary">Create Event</button>
  </form>
</div>
<?php include 'includes/footer.php'; ?>