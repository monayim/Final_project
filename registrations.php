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

// Only APPROVED admins and organizers can access
if (!isset($_SESSION['user']) || 
   !in_array($_SESSION['user']['role'], ['admin','organizer']) ||
   ($_SESSION['user']['role'] == 'organizer' && $_SESSION['user']['approved'] == 0)) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['user']['role'] == 'organizer') {
    $stmt = $pdo->prepare("SELECT r.*, u.name as student, u.email, e.title 
                           FROM registrations r 
                           JOIN users u ON r.user_id=u.id 
                           JOIN events e ON r.event_id=e.id 
                           WHERE e.created_by=?");
    $stmt->execute([$_SESSION['user']['id']]);
} else {
    $stmt = $pdo->query("SELECT r.*, u.name as student, u.email, e.title 
                         FROM registrations r 
                         JOIN users u ON r.user_id=u.id 
                         JOIN events e ON r.event_id=e.id");
}
$regs = $stmt->fetchAll();
?>
<?php include 'includes/header.php'; ?>
<div class="container">
  <h2>Event Registrations</h2>
  <table class="table">
    <tr><th>Event</th><th>Student</th><th>Email</th><th>Status</th><th>Action</th></tr>
    <?php foreach($regs as $r): ?>
      <tr>
        <td><?= e($r['title']); ?></td>
        <td><?= e($r['student']); ?></td>
        <td><?= e($r['email']); ?></td>
        <td><?= e($r['status']); ?></td>
        <td>
          <?php if($r['status']=='pending'): ?>
            <form method="post" action="update_status.php">
              <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">
              <input type="hidden" name="id" value="<?= $r['id']; ?>">
              <button type="submit" name="status" value="approved">Approve</button>
              <button type="submit" name="status" value="rejected">Reject</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php include 'includes/footer.php'; ?>