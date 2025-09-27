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

// Only APPROVED organizers can access
if (!isset($_SESSION['user']) || 
   $_SESSION['user']['role'] != 'organizer' || 
   $_SESSION['user']['approved'] == 0) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['csrf_token']) || !validateCsrfToken($_GET['csrf_token'])) {
    header("Location: organizer_dashboard.php");
    exit;
}

$event_id = (int)$_GET['event_id'];
$format = $_GET['format'];

if (!isset($_GET['event_id']) || !isset($_GET['format'])) {
    header("Location: organizer_dashboard.php");
    exit;
}

$event_id = (int)$_GET['event_id'];
$format = $_GET['format'];

// Verify that the event belongs to this organizer
$check_stmt = $pdo->prepare("SELECT id FROM events WHERE id = ? AND created_by = ?");
$check_stmt->execute([$event_id, $_SESSION['user']['id']]);

if ($check_stmt->rowCount() == 0) {
    header("Location: organizer_dashboard.php");
    exit;
}

// Fetch event details
$event_stmt = $pdo->prepare("SELECT title FROM events WHERE id = ?");
$event_stmt->execute([$event_id]);
$event = $event_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch registrations for this event
$reg_stmt = $pdo->prepare("
    SELECT users.name, users.email, users.phone, users.department, 
           registrations.status, registrations.registered_at
    FROM registrations 
    JOIN users ON registrations.user_id = users.id 
    WHERE registrations.event_id = ?
    ORDER BY registrations.status, users.name
");
$reg_stmt->execute([$event_id]);
$registrations = $reg_stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers based on format
if ($format == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_]/', '_', $event['title']) . '_registrations.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Email', 'Phone', 'Department', 'Status', 'Registered At']);
    
    foreach ($registrations as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
    
} elseif ($format == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_]/', '_', $event['title']) . '_registrations.xls"');
    
    echo "<table border='1'>";
    echo "<tr><th>Name</th><th>Email</th><th>Phone</th><th>Department</th><th>Status</th><th>Registered At</th></tr>";
    
    foreach ($registrations as $row) {
        echo "<tr>";
        echo "<td>" . e($row['name']) . "</td>";
        echo "<td>" . e($row['email']) . "</td>";
        echo "<td>" . e($row['phone']) . "</td>";
        echo "<td>" . e($row['department']) . "</td>";
        echo "<td>" . e($row['status']) . "</td>";
        echo "<td>" . e($row['registered_at']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    exit;
} else {
    header("Location: organizer_dashboard.php");
    exit;
}
?>

<script>
  document.addEventListener("DOMContentLoaded", () => {
    if(window.location.search.includes("format=")){
      console.log("Preparing your file download...");
    }
  });
</script>