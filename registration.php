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

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student') {
    header("Location: login.php");
    exit;
}

// Get event details
$event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : (isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0);

if (!$event_id) {
    header("Location: events.php");
    exit;
}

$event_stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$event_stmt->execute([$event_id]);
$event = $event_stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    header("Location: events.php");
    exit;
}

// Check if already registered
$check = $pdo->prepare("SELECT * FROM registrations WHERE event_id=? AND user_id=?");
$check->execute([$event_id, $_SESSION['user']['id']]);

if ($check->rowCount() > 0) {
    $message = "⚠️ You have already registered for this event.";
    header("Location: events.php?message=" . urlencode($message));
    exit;
}

// Check registration limit
$limit_stmt = $pdo->prepare("
    SELECT e.registration_limit, 
           COUNT(r.id) as current_registrations 
    FROM events e 
    LEFT JOIN registrations r ON e.id = r.event_id AND r.status = 'approved' 
    WHERE e.id = ? 
    GROUP BY e.id
");
$limit_stmt->execute([$event_id]);
$event_data = $limit_stmt->fetch(PDO::FETCH_ASSOC);

if ($event_data && $event_data['current_registrations'] >= $event_data['registration_limit']) {
    $message = "❌ Sorry, this event has reached the maximum limit of " . $event_data['registration_limit'] . " participants.";
    header("Location: events.php?message=" . urlencode($message));
    exit;
}
?>

<?php include 'includes/header.php'; ?>
<div class="container">
    <h2>Event Registration</h2>
    
    <div class="event-summary">
        <h3><?= e($event['title']); ?></h3>
        <p><strong>Date:</strong> <?= date('F j, Y', strtotime($event['event_date'])); ?></p>
        <p><strong>Cost:</strong> 
            <?php if ($event['cost'] > 0): ?>
                <span style="color: #e74c3c; font-size: 1.2em; font-weight: bold;">
                    $<?= number_format($event['cost'], 2); ?>
                </span>
            <?php else: ?>
                <span style="color: #27ae60; font-size: 1.2em; font-weight: bold;">FREE</span>
            <?php endif; ?>
        </p>
        <p><strong>Description:</strong> <?= e($event['description']); ?></p>
    </div>

    <?php if ($event['cost'] > 0): ?>
        <div class="payment-form">
            <h3>Payment Information</h3>
            <form method="post" action="process_payment.php">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">
                <input type="hidden" name="event_id" value="<?= $event['id']; ?>">
                
                <div class="form-group">
                    <label>Payment Method:</label>
                    <select name="payment_method" class="form-control" required>
                        <option value="">Select Payment Method</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="debit_card">Debit Card</option>
                        <option value="paypal">PayPal</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                </div>
                
                <div class="payment-details" id="credit_card_details" style="display: none;">
                    <div class="form-group">
                        <label>Card Number:</label>
                        <input type="text" class="form-control" placeholder="1234 5678 9012 3456" maxlength="19">
                    </div>
                    <div class="form-group">
                        <label>Expiry Date:</label>
                        <input type="text" class="form-control" placeholder="MM/YY" maxlength="5">
                    </div>
                    <div class="form-group">
                        <label>CVV:</label>
                        <input type="text" class="form-control" placeholder="123" maxlength="3">
                    </div>
                </div>
                
                <div class="amount-summary">
                    <h4>Total Amount: $<?= number_format($event['cost'], 2); ?></h4>
                </div>
                
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-credit-card"></i> Pay & Register
                </button>
                <a href="events.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    <?php else: ?>
        <div class="free-registration">
            <h3>Free Registration</h3>
            <p>This event is free of charge. Click the button below to register.</p>
            <form method="post" action="process_payment.php">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">
                <input type="hidden" name="event_id" value="<?= $event['id']; ?>">
                <input type="hidden" name="payment_method" value="free">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-check-circle"></i> Register for Free
                </button>
                <a href="events.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethod = document.querySelector('select[name="payment_method"]');
    const creditCardDetails = document.getElementById('credit_card_details');
    
    if (paymentMethod && creditCardDetails) {
        paymentMethod.addEventListener('change', function() {
            if (this.value === 'credit_card' || this.value === 'debit_card') {
                creditCardDetails.style.display = 'block';
            } else {
                creditCardDetails.style.display = 'none';
            }
        });
    }
});
</script>

<style>
.event-summary, .payment-form, .free-registration {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #4361ee;
}

.form-group {
    margin-bottom: 15px;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
}

.amount-summary {
    background: #e8f5e8;
    padding: 15px;
    border-radius: 5px;
    margin: 20px 0;
    text-align: center;
}

.btn-lg {
    padding: 12px 30px;
    font-size: 1.1em;
}
</style>

<?php include 'includes/footer.php'; ?>