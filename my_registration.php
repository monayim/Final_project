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

// Only students can view this page
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user']['id'];

// Fetch all registrations for this student with payment info
$stmt = $pdo->prepare("
    SELECT events.title, events.description, events.event_date, events.cost,
           registrations.status, registrations.payment_status,
           payments.transaction_id, payments.paid_at
    FROM registrations 
    JOIN events ON registrations.event_id = events.id 
    LEFT JOIN payments ON registrations.id = payments.registration_id
    WHERE registrations.user_id = ?
    ORDER BY events.event_date ASC
");

$stmt->execute([$user_id]);
$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include 'includes/header.php'; ?>

<div class="container">
    <h2>My Registrations</h2>

    <?php if (count($registrations) > 0): ?>
        <div class="registrations-list">
            <?php foreach ($registrations as $r): ?>
            <div class="registration-card">
                <h3><?= e($r['title']); ?></h3>
                <p><strong>Description:</strong> <?= e($r['description']); ?></p>
                <p><strong>Date:</strong> <?= e($r['event_date']); ?></p>
                <p><strong>Cost:</strong> 
                    <?php if ($r['cost'] > 0): ?>
                        $<?= number_format($r['cost'], 2); ?>
                    <?php else: ?>
                        Free
                    <?php endif; ?>
                </p>
                <p><strong>Registration Status:</strong> 
                    <span class="status-badge status-<?= $r['status']; ?>">
                        <?= ucfirst($r['status']); ?>
                    </span>
                </p>
                <p><strong>Payment Status:</strong> 
                    <span class="payment-badge payment-<?= $r['payment_status']; ?>">
                        <?= ucfirst($r['payment_status']); ?>
                    </span>
                </p>
                <?php if ($r['transaction_id']): ?>
                    <p><strong>Transaction ID:</strong> <?= e($r['transaction_id']); ?></p>
                    <p><strong>Paid At:</strong> <?= e($r['paid_at']); ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>You haven't registered for any events yet.</p>
    <?php endif; ?>
</div>

<style>
.registrations-list {
    display: grid;
    gap: 20px;
}

.registration-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    border-left: 4px solid #4361ee;
}

.status-badge, .payment-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: bold;
}

.status-pending, .payment-pending {
    background: #fff3cd;
    color: #856404;
}

.status-approved, .payment-paid {
    background: #d4edda;
    color: #155724;
}

.status-rejected, .payment-failed {
    background: #f8d7da;
    color: #721c24;
}

.payment-free {
    background: #d1ecf1;
    color: #0c5460;
}
</style>

<?php include 'includes/footer.php'; ?>
