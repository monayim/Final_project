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

$organizer_id = $_SESSION['user']['id'];
$message = '';

// Handle event deletion with CSRF protection
if (isset($_POST['delete_event'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request";
    } else {
        $event_id = (int)$_POST['delete_event'];
        $check_stmt = $pdo->prepare("
            SELECT e.id, u.role as creator_role 
            FROM events e 
            JOIN users u ON e.created_by = u.id 
            WHERE e.id = ? AND (e.created_by = ? OR u.role = 'admin')
        ");
        $check_stmt->execute([$event_id, $organizer_id]);
        
        if ($check_stmt->rowCount() > 0) {
            $event_info = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Only allow deletion of their own events, not admin events
            if ($event_info['creator_role'] !== 'admin') {
                try {
                    $pdo->beginTransaction();
                    
                    // Delete registrations first
                    $delete_regs = $pdo->prepare("DELETE FROM registrations WHERE event_id = ?");
                    $delete_regs->execute([$event_id]);
                    
                    // Then delete the event
                    $delete_event = $pdo->prepare("DELETE FROM events WHERE id = ?");
                    $delete_event->execute([$event_id]);
                    
                    $pdo->commit();
                    $_SESSION['message'] = "🎉 Event deleted successfully!";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $_SESSION['error'] = "Error deleting event: " . $e->getMessage();
                }
            } else {
                $_SESSION['error'] = "Cannot delete admin-created events";
            }
        }
        header("Location: organizer_dashboard.php");
        exit;
    }
}

// Handle registration status updates with CSRF
if (isset($_POST['update_registration']) && isset($_POST['reg_id'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request";
    } else {
        $reg_id = (int)$_POST['reg_id'];
        $status = $_POST['status'];
        
        $check_stmt = $pdo->prepare("
            SELECT r.id 
            FROM registrations r 
            JOIN events e ON r.event_id = e.id 
            JOIN users u ON e.created_by = u.id
            WHERE r.id = ? AND (e.created_by = ? OR u.role = 'admin')
        ");
        $check_stmt->execute([$reg_id, $organizer_id]);
        
        if ($check_stmt->rowCount() > 0) {
            $update_stmt = $pdo->prepare("UPDATE registrations SET status = ? WHERE id = ?");
            $update_stmt->execute([$status, $reg_id]);
            $_SESSION['message'] = "✅ Registration status updated successfully!";
        }
        header("Location: organizer_dashboard.php");
        exit;
    }
}

// Handle bulk actions with CSRF
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request";
    } else {
        $selected_registrations = $_POST['selected_regs'] ?? [];
        $bulk_status = $_POST['bulk_status'];
        
        if (!empty($selected_registrations)) {
            // Convert all values to integers for safety
            $selected_registrations = array_map('intval', $selected_registrations);
            
            // Verify ownership of each registration
            $placeholders = implode(',', array_fill(0, count($selected_registrations), '?'));
            
            $verify_stmt = $pdo->prepare("
            SELECT r.id 
            FROM registrations r 
            JOIN events e ON r.event_id = e.id 
            JOIN users u ON e.created_by = u.id
            WHERE r.id IN ($placeholders) AND (e.created_by = ? OR u.role = 'admin')");
            $verify_params = array_merge($selected_registrations, [$organizer_id]);
            $verify_stmt->execute($verify_params);
            
            $valid_registrations = $verify_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($valid_registrations) > 0) {
                $valid_placeholders = implode(',', array_fill(0, count($valid_registrations), '?'));
                $update_stmt = $pdo->prepare("UPDATE registrations SET status = ? WHERE id IN ($valid_placeholders)");
                $update_stmt->execute(array_merge([$bulk_status], $valid_registrations));
                
                $affected_rows = $update_stmt->rowCount();
                $_SESSION['message'] = "🔄 Bulk action completed! Updated $affected_rows registration(s).";
            } else {
                $_SESSION['error'] = "No valid registrations found for bulk action";
            }
        } else {
            $_SESSION['error'] = "No registrations selected for bulk action";
        }
    }
    header("Location: organizer_dashboard.php");
    exit;
}

// Fetch events created by this organizer OR by admins with registration counts
$stmt = $pdo->prepare("
    SELECT e.*, 
           u.name as creator_name,
           u.role as creator_role,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) as total_registrations,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'approved') as approved_count,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'pending') as pending_count,
           (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = 'rejected') as rejected_count
    FROM events e 
    JOIN users u ON e.created_by = u.id
    WHERE (e.created_by = ? OR u.role = 'admin')
    ORDER BY e.event_date ASC
");
$stmt->execute([$organizer_id]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <!-- Header Section -->
    <div class="dashboard-header">
        <h2><i class="fas fa-tachometer-alt"></i> Organizer Dashboard</h2>
        <p>Welcome back, <?= e($_SESSION['user']['name']); ?>! Manage your events and participants.</p>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="message success">
            <?= $_SESSION['message']; ?>
            <?php unset($_SESSION['message']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="message error">
            <?= $_SESSION['error']; ?>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="create_event.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create New Event
        </a>
        <a href="registrations.php" class="btn btn-secondary">
            <i class="fas fa-users"></i> View All Registrations
        </a>
    </div>

    <!-- Global Bulk Actions -->
    <div class="bulk-actions-global" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px;">
        <h5><i class="fas fa-cogs"></i> Global Bulk Actions</h5>
        <form method="post" id="bulkActionForm">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">
            <div style="display: flex; gap: 10px; align-items: center;">
                <select name="bulk_status" class="form-select" style="flex: 1;">
                    <option value="approved">Approve Selected</option>
                    <option value="rejected">Reject Selected</option>
                    <option value="pending">Set as Pending</option>
                </select>
                <button type="submit" name="bulk_action" value="1" class="btn btn-secondary">
                    <i class="fas fa-cogs"></i> Apply to Selected Registrations
                </button>
            </div>
            
            <!-- Hidden container for selected registration IDs -->
            <div id="selectedRegistrations"></div>
        </form>
    </div>

    <!-- Events Summary -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--primary);">
                <i class="fas fa-calendar"></i>
            </div>
            <div class="stat-info">
                <h3><?= count($events); ?></h3>
                <p>Total Events</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--success);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?= array_sum(array_column($events, 'approved_count')); ?></h3>
                <p>Approved Participants</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--warning);">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3><?= array_sum(array_column($events, 'pending_count')); ?></h3>
                <p>Pending Approvals</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--accent);">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3><?= array_sum(array_column($events, 'total_registrations')); ?></h3>
                <p>Total Registrations</p>
            </div>
        </div>
    </div>

    <!-- Events List -->
    <div class="events-section">
        <h3><i class="fas fa-calendar-alt"></i> Your Events</h3>
        
        <?php if (count($events) > 0): ?>
            <?php foreach ($events as $event): ?>
                <!-- Event Card -->
                <div class="event-card">
                    
                    <div class="event-header">
                        <h4><?= e($event['title']); ?></h4>
                        <span class="event-date">
                            <i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($event['event_date'])); ?>
                        </span>
                    </div>
                    
                    <!-- Add creator information -->
                    <div class="event-creator">
                        <small>
                            <i class="fas fa-user"></i> 
                            Created by: <?= e($event['creator_name']); ?> 
                            (<?= e($event['creator_role']); ?>)
                            <?php if ($event['creator_role'] == 'admin'): ?>
                                <span class="badge admin-badge">Admin Event</span>
                            <?php endif; ?>
                        </small>
                    </div>
                    
                    <p class="event-description"><?= e($event['description']); ?></p>
                    
                    <!-- Event Cost -->
                    <p class="event-cost">
                        <strong>Cost:</strong> 
                        <?php if ($event['cost'] > 0): ?>
                            <span style="color: #e74c3c; font-weight: bold;">$<?= number_format($event['cost'], 2); ?></span>
                        <?php else: ?>
                            <span style="color: #27ae60; font-weight: bold;">FREE</span>
                        <?php endif; ?>
                    </p>
                    
                    <!-- Event Statistics -->
                    <div class="event-stats">
                        <span class="stat-badge approved">
                            <i class="fas fa-check"></i> <?= $event['approved_count']; ?> Approved
                        </span>
                        <span class="stat-badge pending">
                            <i class="fas fa-clock"></i> <?= $event['pending_count']; ?> Pending
                        </span>
                        <span class="stat-badge rejected">
                            <i class="fas fa-times"></i> <?= $event['rejected_count']; ?> Rejected
                        </span>
                        <span class="stat-badge total">
                            <i class="fas fa-users"></i> <?= $event['total_registrations']; ?> Total
                        </span>
                    </div>

                    <!-- Event Management Buttons -->
                    <div class="event-actions">
                        <a href="create_event.php?edit=<?= $event['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Edit Event
                        </a>
                        <?php if ($event['creator_role'] !== 'admin'): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">
                                <input type="hidden" name="delete_event" value="<?= $event['id']; ?>">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-trash"></i> Delete Event
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-danger" disabled title="Cannot delete admin-created events">
                                <i class="fas fa-trash"></i> Delete Event
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Participants Section -->
                    <?php
                    // FIXED: Added payment_status to the SELECT query
                    $reg_stmt = $pdo->prepare("
                        SELECT r.id, r.status, r.payment_status, r.registered_at, 
                               users.name, users.email, users.phone, users.department
                        FROM registrations r 
                        JOIN users ON r.user_id = users.id 
                        WHERE r.event_id = ?
                        ORDER BY r.status, users.name
                    ");
                    $reg_stmt->execute([$event['id']]);
                    $registrations = $reg_stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <?php if (count($registrations) > 0): ?>
                        <div class="participants-section">
                            <h5><i class="fas fa-users"></i> Registered Participants (<?= count($registrations); ?>)</h5>
                            
                            <!-- Registration Summary -->
                            <div class="registration-summary">
                                <strong>Registration Summary:</strong>
                                <span class="badge approved">Approved: <?= $event['approved_count']; ?></span>
                                <span class="badge pending">Pending: <?= $event['pending_count']; ?></span>
                                <span class="badge rejected">Rejected: <?= $event['rejected_count']; ?></span>
                            </div>
                            
                            <!-- Participants Table -->
                            <div class="participants-table">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="select-all-<?= $event['id']; ?>"></th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Department</th>
                                            <th>Registered At</th>
                                            <th>Status</th>
                                            <th>Payment Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($registrations as $r): ?>
                                        <tr>
                                            <!-- FIXED: Checkbox in first column -->
                                            <td>
                                                <input type="checkbox" value="<?= $r['id']; ?>" 
                                                       class="registration-checkbox select-all-<?= $event['id']; ?>">
                                            </td>
                                            <td><?= e($r['name']); ?></td>
                                            <td>
                                                <a href="mailto:<?= e($r['email']); ?>" class="email-link">
                                                    <i class="fas fa-envelope"></i> <?= e($r['email']); ?>
                                                </a>
                                            </td>
                                            <td><?= e($r['phone'] ?? 'N/A'); ?></td>
                                            <td><?= e($r['department'] ?? 'N/A'); ?></td>
                                            <td><?= date('M j, Y g:i A', strtotime($r['registered_at'])); ?></td>
                                            <td>
                                                <span class="status-badge status-<?= $r['status']; ?>">
                                                    <i class="fas fa-<?= $r['status'] == 'approved' ? 'check' : ($r['status'] == 'rejected' ? 'times' : 'clock'); ?>"></i>
                                                    <?= ucfirst($r['status']); ?>
                                                </span>
                                            </td>
                                            <!-- FIXED: Payment status in correct column -->
                                            <td>
                                                <span class="payment-badge payment-<?= $r['payment_status']; ?>">
                                                    <i class="fas fa-<?= $r['payment_status'] == 'paid' ? 'check-circle' : ($r['payment_status'] == 'pending' ? 'clock' : 'tag'); ?>"></i>
                                                    <?= ucfirst($r['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <?php if ($r['status'] == 'pending'): ?>
                                                        <form method="post" style="display:inline;">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">
                                                            <input type="hidden" name="reg_id" value="<?= $r['id']; ?>">
                                                            <input type="hidden" name="status" value="approved">
                                                            <button type="submit" name="update_registration" class="btn btn-success btn-sm">
                                                                <i class="fas fa-check"></i> Approve
                                                            </button>
                                                        </form>
                                                        <form method="post" style="display:inline;">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">
                                                            <input type="hidden" name="reg_id" value="<?= $r['id']; ?>">
                                                            <input type="hidden" name="status" value="rejected">
                                                            <button type="submit" name="update_registration" class="btn btn-danger btn-sm">
                                                                <i class="fas fa-times"></i> Reject
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="post" style="display:inline;">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken(); ?>">
                                                            <input type="hidden" name="reg_id" value="<?= $r['id']; ?>">
                                                            <input type="hidden" name="status" value="pending">
                                                            <button type="submit" name="update_registration" class="btn btn-warning btn-sm">
                                                                <i class="fas fa-undo"></i> Reset
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Export Options -->
                            <div class="export-options">
                              <h6><i class="fas fa-download"></i> Export Participant List:</h6>
                                <div class="export-buttons">
                                    <a href="export_registration.php?event_id=<?= $event['id']; ?>&format=csv&csrf_token=<?= generateCsrfToken(); ?>" class="btn btn-outline">
                                        <i class="fas fa-file-csv"></i> CSV
                                    </a>
                                    <a href="export_registration.php?event_id=<?= $event['id']; ?>&format=excel&csrf_token=<?= generateCsrfToken(); ?>" class="btn btn-outline">
                                        <i class="fas fa-file-excel"></i> Excel
                                    </a>
                                    <button onclick="window.print()" class="btn btn-outline">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="no-participants">
                            <i class="fas fa-users-slash"></i>
                            <p>No students have registered for this event yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- END of event-card -->
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-events">
                <i class="fas fa-calendar-plus"></i>
                <h4>No events created yet</h4>
                <p>Get started by creating your first event!</p>
                <a href="create_event.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Your First Event
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript for Enhanced Functionality -->
<script>
// Update the bulk form handling
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to all select-all checkboxes
    const selectAllCheckboxes = document.querySelectorAll('[id^="select-all-"]');
    selectAllCheckboxes.forEach(checkbox => {
        const eventId = checkbox.id.replace('select-all-', '');
        checkbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.select-all-' + eventId);
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            updateBulkForm();
        });
    });
    
    // Global bulk form handling
    const bulkForm = document.getElementById('bulkActionForm');
    const selectedContainer = document.getElementById('selectedRegistrations');
    
    if (bulkForm && selectedContainer) {
        // Update bulk form on checkbox changes
        function updateBulkForm() {
            selectedContainer.innerHTML = '';
            document.querySelectorAll('.registration-checkbox:checked').forEach(checkbox => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'selected_regs[]';
                hiddenInput.value = checkbox.value;
                selectedContainer.appendChild(hiddenInput);
            });
        }
        
        document.querySelectorAll('.registration-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkForm);
        });
        
        // Form submission handling
        bulkForm.addEventListener('submit', function(e) {
            const selectedCount = document.querySelectorAll('.registration-checkbox:checked').length;
            if (selectedCount === 0) {
                e.preventDefault();
                alert('Please select at least one registration to apply the action.');
                return false;
            }
            
            const action = this.querySelector('select[name="bulk_status"]').value;
            if (!confirm(`Are you sure you want to ${action} ${selectedCount} registration(s)?`)) {
                e.preventDefault();
                return false;
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
