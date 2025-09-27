<?php
// Move session settings to the very top
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

require_once 'includes/functions.php';
safe_session_start();
require_once 'config/db.php';

$message = '';

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $pass = trim($_POST['password']);

    if (!empty($email) && !empty($pass)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Check if user is an organizer and not approved
                if ($user['role'] == 'organizer' && $user['approved'] == 0) {
                    $message = "Your account is pending approval by an administrator.";
                } elseif (password_verify($pass, $user['password'])) {
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    // Login successful - set session variables
                    $_SESSION['user'] = $user;
                    $_SESSION['last_activity'] = time();
                    
                    // Redirect based on user role
                    if ($user['role'] == 'admin') {
                        header("Location: admin_dashboard.php");
                        exit;
                    } elseif ($user['role'] == 'organizer') {
                        header("Location: organizer_dashboard.php");
                        exit;
                    } else {
                        header("Location: dashboard.php");
                        exit;
                    }
                } else {
                    // Password is wrong
                    $message = "Invalid credentials!";
                }
            } else {
                // User doesn't exist
                $message = "Invalid credentials!";
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $message = "Database error: " . $e->getMessage();
        }
    } else {
        $message = "Please enter both email and password.";
    }
}
?>

<?php include 'includes/header.php'; ?>
<div class="container">
  <h2>Login</h2>
  <form method="post" onsubmit="return validateForm()">
    <input type="email" name="email" id="email" placeholder="Email" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"><br>
    <input type="password" name="password" id="password" placeholder="Password" required><br>
    <button type="submit">Login</button>
  </form>
  <p style="color: <?= strpos($message, 'successfully') !== false ? 'green' : 'red' ?>;"><?= htmlspecialchars($message) ?></p>
  
  <?php if (strpos($message, 'Database error') !== false): ?>
    <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-top: 20px;">
      <p><strong>Database Setup Required:</strong> It looks like your database isn't set up yet.</p>
      <p><a href="setup_database.php" style="background: #4361ee; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;">Setup Database Now</a></p>
    </div>
  <?php endif; ?>
  
  <p>Don't have an account? <a href="signup.php">Sign up here</a></p>
</div>

<script>
  function validateForm(){
    const email = document.querySelector("[name=email]").value.trim();
    const pass = document.querySelector("[name=password]").value.trim();
    if(email === "" || pass === ""){
      alert("Both fields are required!");
      return false;
    }
    if(!email.includes("@")){
      alert("Please enter a valid email address.");
      return false;
    }
    return true;
  }
</script>
<?php include 'includes/footer.php'; ?>