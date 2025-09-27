<?php
// 1. Functions first - Correct path for includes folder
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

// Get all events
$stmt = $pdo->query("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include 'includes/header.php'; ?>
<div class="container">
  <h2>Available Events</h2>
  
  <?php if (count($events) > 0): ?>
    <div class="events-list">
      <?php foreach($events as $e): ?>
        <div class="event-card">
          <h3><?php echo htmlspecialchars($e['title']); ?></h3>
          <p class="event-date"><strong>Date:</strong> <?php echo date('F j, Y', strtotime($e['event_date'])); ?></p>
          <p class="event-cost"><strong>Cost:</strong> 
            <?php if ($e['cost'] > 0): ?>
              <span style="color: #e74c3c; font-weight: bold;">$<?php echo number_format($e['cost'], 2); ?></span>
            <?php else: ?>
              <span style="color: #27ae60; font-weight: bold;">FREE</span>
            <?php endif; ?>
          </p>
          <p class="event-description"><?php echo htmlspecialchars($e['description']); ?></p>
          
          <?php
          // Show current registration count vs limit
          $count_stmt = $pdo->prepare("
              SELECT e.registration_limit, 
                     COUNT(r.id) as current_registrations 
              FROM events e 
              LEFT JOIN registrations r ON e.id = r.event_id AND r.status = 'approved' 
              WHERE e.id = ? 
              GROUP BY e.id
          ");
          $count_stmt->execute([$e['id']]);
          $event_data = $count_stmt->fetch(PDO::FETCH_ASSOC);

          if ($event_data) {
              $current = $event_data['current_registrations'];
              $limit = $event_data['registration_limit'];
              $spots_left = $limit - $current;
          ?>
          <p class="event-registrations">
              <strong>Registrations:</strong> <?php echo $current; ?>/<?php echo $limit ?> 
              (<?php echo $spots_left ?> spots left)
          </p>
          <?php } ?>
          
          <?php if(isset($_SESSION['user']) && $_SESSION['user']['role']=='student'): ?>
            <form method="post" action="registration.php">
              <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
              <input type="hidden" name="event_id" value="<?php echo $e['id']; ?>">
              <button type="submit" class="btn btn-primary">
                <?php if ($e['cost'] > 0): ?>
                  Register - $<?php echo number_format($e['cost'], 2); ?>
                <?php else: ?>
                  Register for Free
                <?php endif; ?>
              </button>
            </form>
          <?php elseif(!isset($_SESSION['user'])): ?>
            <p><a href="login.php">Login</a> to register for this event</p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p>No upcoming events available.</p>
  <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>