
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

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $message = "Invalid request";
    } else {
        $name  = sanitizeInput($_POST['name']);
        $email = sanitizeInput($_POST['email']);
        $phone = sanitizeInput($_POST['phone']);
        $department = sanitizeInput($_POST['department']);
        $pass  = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $role  = sanitizeInput($_POST['role']);

        // Validate email
        if (!validateEmail($email)) {
            $message = "Invalid email format";
        } else {
            // Check if email already exists
            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check_stmt->execute([$email]);
            
            if ($check_stmt->rowCount() > 0) {
                $message = "Email already exists!";
            } else {
                if ($role == 'organizer') {
                    $approved = 0;
                } else {
                    $approved = 1;
                }

                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, department, password, role, approved) VALUES (?,?,?,?,?,?,?)");
                if ($stmt->execute([$name, $email, $phone, $department, $pass, $role, $approved])) {
                    $message = "User registered successfully!";
                    if($role == 'organizer') {
                        $message .= " Your account is pending approval.";
                    }
                } else {
                    $message = "Error occurred during registration!";
                }
            }
        }
    }
}
?>
<?php include 'includes/header.php'; ?>
<div class="container">
  <h2>Sign Up</h2>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">
    
    <input type="text" name="name" placeholder="Full Name" value="<?= isset($_POST['name']) ? e($_POST['name']) : ''; ?>" required><br>
    <input type="email" name="email" placeholder="Email" value="<?= isset($_POST['email']) ? e($_POST['email']) : ''; ?>" required><br>
    <input type="text" name="phone" placeholder="Phone Number" value="<?= isset($_POST['phone']) ? e($_POST['phone']) : ''; ?>"><br>
    <input type="text" name="department" placeholder="Department" value="<?= isset($_POST['department']) ? e($_POST['department']) : ''; ?>"><br>
    <input type="password" name="password" placeholder="Password" required minlength="6"><br>
    
    <select name="role" required>
      <option value="student" <?= (isset($_POST['role']) && $_POST['role'] == 'student') ? 'selected' : ''; ?>>Student</option>
      <option value="organizer" <?= (isset($_POST['role']) && $_POST['role'] == 'organizer') ? 'selected' : ''; ?>>Organizer</option>
      <?php 
      // Only allow admin registration from specific conditions (or remove this option)
      if (isset($_GET['admin']) && $_GET['admin'] == 'secret_key'): ?>
      <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
      <?php endif; ?>
    </select><br>
    
    <button type="submit" class="btn btn-primary">Register</button>
  </form>
  
  <?php if ($message): ?>
    <div class="alert <?= strpos($message, 'successfully') !== false ? 'alert-success' : 'alert-danger'; ?>">
        <?= e($message); ?>
    </div>
  <?php endif; ?>
  
  <p>Already have an account? <a href="login.php">Login here</a></p>
</div>
<?php include 'includes/footer.php'; ?>