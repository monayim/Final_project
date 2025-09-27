<?php 
// 1. Functions first
require_once 'includes/functions.php';

// 2. Start session safely
safe_session_start();

// 3. Database connection
require_once 'config/db.php';

// 4. CSRF functions
require_once 'includes/csrf.php';

if (!function_exists('e')) {
    die('Error: functions.php not loaded before header.php');
}
?>

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventFlow - Event Management System</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<header>
    <h1>🎉 EventFlow</h1>
    <nav>
        <a href="index.php"><i class="fas fa-home"></i> Home</a>
        <?php if(isset($_SESSION['user'])): ?>
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <?php if($_SESSION['user']['role'] == 'admin' || $_SESSION['user']['role'] == 'organizer'): ?>
                <a href="create_event.php"><i class="fas fa-plus"></i> Create Event</a>
                <a href="registrations.php"><i class="fas fa-users"></i> Manage Registrations</a>
            <?php endif; ?>
            <?php if($_SESSION['user']['role'] == 'student'): ?>
                <a href="events.php"><i class="fas fa-calendar-alt"></i> Browse Events</a>
                <a href="my_registration.php"><i class="fas fa-ticket-alt"></i> My Registrations</a>
            <?php endif; ?>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout (<?= $_SESSION['user']['name'] ?>)</a>
        <?php else: ?>
            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
            <a href="signup.php"><i class="fas fa-user-plus"></i> Sign Up</a>
        <?php endif; ?>
    </nav>
</header>
<main>