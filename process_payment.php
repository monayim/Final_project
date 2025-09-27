
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

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'student') {
    header("Location: login.php");
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $message = "Invalid request";
    } else {
        $event_id = (int)$_POST['event_id'];
        $user_id = $_SESSION['user']['id'];
        $payment_method = sanitizeInput($_POST['payment_method']);
        
        try {
            // Get event details including cost
            $event_stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
            $event_stmt->execute([$event_id]);
            $event = $event_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$event) {
                $message = "Event not found.";
            } else {
                // Check if user already registered
                $check = $pdo->prepare("SELECT * FROM registrations WHERE event_id=? AND user_id=?");
                $check->execute([$event_id, $user_id]);
                
                if ($check->rowCount() > 0) {
                    $message = "You have already registered for this event.";
                } else {
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
                        $message = "Sorry, this event has reached the maximum limit of " . $event_data['registration_limit'] . " participants.";
                    } else {
                        $pdo->beginTransaction();
                        
                        // Determine payment status
                        $payment_status = $event['cost'] > 0 ? 'pending' : 'free';
                        
                        // Insert registration
                        $reg_stmt = $pdo->prepare("INSERT INTO registrations (event_id, user_id, status, payment_status) VALUES (?,?, 'pending', ?)");
                        $reg_stmt->execute([$event_id, $user_id, $payment_status]);
                        $registration_id = $pdo->lastInsertId();
                        
                        // If it's a paid event, create payment record
                        if ($event['cost'] > 0) {
                            $payment_stmt = $pdo->prepare("
                                INSERT INTO payments (registration_id, amount, payment_method, status) 
                                VALUES (?, ?, ?, 'pending')
                            ");
                            $payment_stmt->execute([$registration_id, $event['cost'], $payment_method]);
                            
                            // Simulate payment processing (in real app, integrate with payment gateway)
                            $transaction_id = 'TXN_' . uniqid();
                            
                            // Update payment as completed (simulation)
                            $update_payment = $pdo->prepare("
                                UPDATE payments 
                                SET status = 'completed', transaction_id = ?, paid_at = NOW() 
                                WHERE registration_id = ?
                            ");
                            $update_payment->execute([$transaction_id, $registration_id]);
                            
                            // Update registration payment status
                            $update_reg = $pdo->prepare("
                                UPDATE registrations 
                                SET payment_status = 'paid' 
                                WHERE id = ?
                            ");
                            $update_reg->execute([$registration_id]);
                        }
                        
                        $pdo->commit();
                        
                        if ($event['cost'] > 0) {
                            $message = "✅ Registration and payment successful! Transaction ID: " . $transaction_id;
                        } else {
                            $message = "✅ Registration successful for free event!";
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "❌ Error during registration: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<?php include 'includes/header.php'; ?>
<div class="container">
    <h2>Payment Processing</h2>
    <div class="alert alert-info">
        <?php echo $message; ?>
    </div>
    <a href="events.php" class="btn btn-primary">Back to Events</a>
    <a href="my_registration.php" class="btn btn-secondary">View My Registrations</a>
</div>
<?php include 'includes/footer.php'; ?>