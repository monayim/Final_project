<?php
require_once 'includes/functions.php';
safe_session_start();

// Redirect admins to admin dashboard
if (isset($_SESSION['user']) && $_SESSION['user']['role'] == 'admin') {
    header("Location: admin_dashboard.php");
    exit;
}

// Redirect organizers to organizer dashboard
if (isset($_SESSION['user']) && $_SESSION['user']['role'] == 'organizer') {
    header("Location: organizer_dashboard.php");
    exit;
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
  <h2>Dashboard</h2>
  <?php if(isset($_SESSION['user'])): ?>
    <p>Welcome, <?= e($_SESSION['user']['name']); ?> (<?= e($_SESSION['user']['role']); ?>)</p>
    <a href="events.php">Browse Events</a>
  <?php else: ?>
    <p>You are not logged in.</p>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>