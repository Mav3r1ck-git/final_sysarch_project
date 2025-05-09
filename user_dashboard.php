<?php
session_start();
require_once 'database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'user') {
    header("Location: user_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user information
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get current sit-in sessions
$stmt = $conn->prepare("SELECT * FROM current_sitins WHERE user_id = ?");
$stmt->execute([$user_id]);
$current_sitins = $stmt->fetchAll();

// Get logged out sit-in sessions
$stmt = $conn->prepare("SELECT * FROM logged_out_sitins WHERE user_id = ? ORDER BY logout_time DESC");
$stmt->execute([$user_id]);
$logged_out_sitins = $stmt->fetchAll();

// Get uploaded materials
$stmt = $conn->prepare("SELECT * FROM materials ORDER BY uploaded_at DESC");
$stmt->execute();
$materials = $stmt->fetchAll();

// Get uploaded lab schedules
$stmt = $conn->prepare("SELECT * FROM lab_schedules ORDER BY uploaded_at DESC");
$stmt->execute();
$lab_schedules = $stmt->fetchAll();

// Get leaderboard data
$stmt = $conn->prepare("
    SELECT u.user_id, u.first_name, u.last_name, u.points,
           (SELECT COUNT(*) FROM logged_out_sitins WHERE user_id = u.user_id) as total_sessions
    FROM users u
    ORDER BY (u.points + (SELECT COUNT(*) FROM logged_out_sitins WHERE user_id = u.user_id)) DESC
    LIMIT 5
");
$stmt->execute();
$leaderboard = $stmt->fetchAll();

// Get announcements
$stmt = $conn->prepare("SELECT * FROM announcements ORDER BY created_at DESC");
$stmt->execute();
$announcements = $stmt->fetchAll();

// Fetch notifications for the user
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

// Handle profile picture upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['profile_picture'])) {
    $target_dir = "uploads/profile_pictures/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION));
    $new_filename = $user_id . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
        $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
        $stmt->execute([$new_filename, $user_id]);
        header("Location: user_dashboard.php");
        exit();
    }
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, middle_name = ?, last_name = ? WHERE user_id = ?");
    $stmt->execute([$first_name, $middle_name, $last_name, $user_id]);
    
    $_SESSION['first_name'] = $first_name;
    $_SESSION['last_name'] = $last_name;
    
    header("Location: user_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Sit-in Management System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <!-- Profile Section -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Profile</h5>
                    </div>
                    <div class="card-body text-center">
                        <img src="<?php echo $user['profile_picture'] ? 'uploads/profile_pictures/' . $user['profile_picture'] : 'https://via.placeholder.com/150'; ?>" 
                             class="rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                        
                        <form method="POST" enctype="multipart/form-data" class="mb-3">
                            <div class="mb-3">
                                <input type="file" class="form-control" name="profile_picture" accept="image/*">
                            </div>
                            <button type="submit" class="btn btn-primary">Upload Picture</button>
                        </form>

                        <form method="POST">
                            <input type="hidden" name="update_profile" value="1">
                            <div class="mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" value="<?php echo $user['first_name']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" class="form-control" name="middle_name" value="<?php echo $user['middle_name']; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" value="<?php echo $user['last_name']; ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </form>

                        <div class="mt-3">
                            <p><strong>User ID:</strong> <?php echo $user['user_id']; ?></p>
                            <p><strong>Year Level:</strong> <?php echo $user['year_level']; ?></p>
                            <p><strong>Course:</strong> <?php echo $user['course']; ?></p>
                            <p><strong>Remaining Sessions:</strong> <?php echo $user['remaining_sessions']; ?></p>
                            <p><strong>Points:</strong> <?php echo $user['points']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Materials Section -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Learning Materials</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($materials)): ?>
                            <p class="text-muted">No materials available.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>File Name</th>
                                            <th>Uploaded Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($materials as $material): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($material['original_name']); ?></td>
                                                <td><?php echo date('M d, Y h:i A', strtotime($material['uploaded_at'])); ?></td>
                                                <td>
                                                    <a href="uploads/materials/<?php echo $material['filename']; ?>" 
                                                       class="btn btn-sm btn-primary" 
                                                       download="<?php echo htmlspecialchars($material['original_name']); ?>">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Lab Schedule Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Lab Schedules</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($lab_schedules)): ?>
                            <p class="text-muted">No lab schedules available.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>File Name</th>
                                            <th>Uploaded Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lab_schedules as $schedule): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($schedule['original_name']); ?></td>
                                                <td><?php echo date('M d, Y h:i A', strtotime($schedule['uploaded_at'])); ?></td>
                                                <td>
                                                    <a href="uploads/lab_schedules/<?php echo $schedule['filename']; ?>" 
                                                       class="btn btn-sm btn-primary" 
                                                       download="<?php echo htmlspecialchars($schedule['original_name']); ?>">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Announcements Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Announcements</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($announcements)): ?>
                            <p class="text-muted">No announcements available.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($announcements as $announcement): ?>
                                    <div class="list-group-item">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                        <small class="text-muted">Posted: <?php echo date('M d, Y h:i A', strtotime($announcement['created_at'])); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Leaderboard Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Leaderboard</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Name</th>
                                        <th>Points</th>
                                        <th>Total Sessions</th>
                                        <th>Total Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leaderboard as $index => $student): ?>
                                        <tr class="<?php echo $index === 0 ? 'table-warning' : ''; ?>">
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                            <td><?php echo $student['points']; ?></td>
                                            <td><?php echo $student['total_sessions']; ?></td>
                                            <td><?php echo $student['points'] + $student['total_sessions']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Current Sit-ins Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Current Sit-in Sessions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($current_sitins)): ?>
                            <p class="text-muted">No current sit-in sessions.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Lab</th>
                                            <th>PC Number</th>
                                            <th>Purpose</th>
                                            <th>Time In</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($current_sitins as $session): ?>
                                            <tr>
                                                <td><?php echo $session['lab']; ?></td>
                                                <td><?php echo $session['pc_number']; ?></td>
                                                <td><?php echo $session['purpose']; ?></td>
                                                <td><?php echo date('M d, Y h:i A', strtotime($session['sitin_time'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Logged Out Sessions Section -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Logged Out Sessions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($logged_out_sitins)): ?>
                            <p class="text-muted">No logged out sessions.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Lab</th>
                                            <th>PC Number</th>
                                            <th>Purpose</th>
                                            <th>Time In</th>
                                            <th>Time Out</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logged_out_sitins as $session): ?>
                                            <tr>
                                                <td><?php echo $session['lab']; ?></td>
                                                <td><?php echo $session['pc_number']; ?></td>
                                                <td><?php echo $session['purpose']; ?></td>
                                                <td><?php echo date('M d, Y h:i A', strtotime($session['sitin_time'])); ?></td>
                                                <td><?php echo date('M d, Y h:i A', strtotime($session['logout_time'])); ?></td>
                                                <td>
                                                    <?php if ($session['was_rewarded']): ?>
                                                        <span class="badge bg-success">Rewarded</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Normal</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (empty($session['feedback'])): ?>
                                                        <button type="button" class="btn btn-sm btn-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#feedbackModal<?php echo $session['id']; ?>">
                                                            Add Feedback
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-info" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#viewFeedbackModal<?php echo $session['id']; ?>">
                                                            View Feedback
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>

                                            <!-- Feedback Modal -->
                                            <div class="modal fade" id="feedbackModal<?php echo $session['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Add Feedback</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form action="add_feedback.php" method="POST">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Feedback</label>
                                                                    <textarea class="form-control" name="feedback" rows="3" required></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                <button type="submit" class="btn btn-primary">Submit Feedback</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- View Feedback Modal -->
                                            <div class="modal fade" id="viewFeedbackModal<?php echo $session['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">View Feedback</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p><?php echo $session['feedback']; ?></p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Reservation Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Reserve a Sit-in</h5>
                    </div>
                    <div class="card-body">
                        <form action="submit_reservation.php" method="POST">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Lab</label>
                                    <select name="lab" class="form-select" required>
                                        <option value="">Select Lab</option>
                                        <option value="Lab 524">Lab 524</option>
                                        <option value="Lab 526">Lab 526</option>
                                        <option value="Lab 528">Lab 528</option>
                                        <option value="Lab 530">Lab 530</option>
                                        <option value="Lab 542">Lab 542</option>
                                        <option value="Lab 544">Lab 544</option>
                                        <option value="Lab 517">Lab 517</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">PC Number</label>
                                    <select name="pc_number" class="form-select" required>
                                        <option value="" disabled selected>Select PC Number</option>
                                        <?php for ($i = 1; $i <= 30; $i++): ?>
                                            <option value="<?php echo $i; ?>">PC <?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Date</label>
                                    <input type="date" name="date" class="form-control" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Time</label>
                                    <input type="time" name="time" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Purpose</label>
                                    <select name="purpose" class="form-select" required onchange="toggleOtherPurpose(this)">
                                        <option value="" disabled selected>Select Purpose</option>
                                        <option value="C Programming">C Programming</option>
                                        <option value="Java Programming">Java Programming</option>
                                        <option value="System Integration & Architecture">System Integration & Architecture</option>
                                        <option value="Embeded System & IOT">Embeded System & IOT</option>
                                        <option value="Digital Logic & Design">Digital Logic & Design</option>
                                        <option value="Computer Application">Computer Application</option>
                                        <option value="Database">Database</option>
                                        <option value="Project Management">Project Management</option>
                                        <option value="Others">Others</option>
                                    </select>
                                    <div class="mt-2" id="otherPurposeDiv" style="display: none;">
                                        <input type="text" class="form-control" name="other_purpose" placeholder="Please specify other purpose">
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit Reservation</button>
                        </form>
                    </div>
                </div>

                <!-- Notifications Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Notifications</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($notifications)): ?>
                            <p class="text-muted">No notifications.</p>
                        <?php else: ?>
                            <ul class="list-group">
                                <?php foreach ($notifications as $notif): ?>
                                    <li class="list-group-item<?php echo !$notif['is_read'] ? ' list-group-item-info' : ''; ?>">
                                        <?php echo htmlspecialchars($notif['message']); ?>
                                        <br><small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?></small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Rules & Regulations Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Rules & Regulations</h5>
                    </div>
                    <div class="card-body">
                        <div class="scroll-box" style="max-height: 300px; overflow-y: auto;">
                            <h5 class="text-center fw-bold">University of Cebu - College of Information & Computer Studies</h5>
                            <p><strong>Laboratory Rules and Regulations</strong></p>
                            <ul>
                                <li>Maintain silence, proper decorum, and discipline inside the lab.</li>
                                <li>Games, unauthorized surfing, and software installations are not allowed.</li>
                                <li>Accessing illicit websites is strictly prohibited.</li>
                                <li>Deleting files or modifying computer settings is a major offense.</li>
                                <li>Observe computer usage time. Exceeding limits will result in loss of access.</li>
                                <li>Follow seating arrangements and return chairs properly.</li>
                                <li>No eating, drinking, smoking, or vandalism inside the lab.</li>
                                <li>Disruptive behavior may result in being asked to leave.</li>
                                <li>For serious offenses, security personnel may be called.</li>
                                <li>Report technical issues to lab supervisors immediately.</li>
                            </ul>
                            <p><strong>DISCIPLINARY ACTION</strong></p>
                            <ul>
                                <li><strong>First Offense:</strong> Warning or possible suspension.</li>
                                <li><strong>Second Offense:</strong> Heavier disciplinary action.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function toggleOtherPurpose(select) {
        const otherPurposeDiv = select.parentElement.querySelector('#otherPurposeDiv');
        if (select.value === 'Others') {
            otherPurposeDiv.style.display = 'block';
            otherPurposeDiv.querySelector('input').required = true;
        } else {
            otherPurposeDiv.style.display = 'none';
            otherPurposeDiv.querySelector('input').required = false;
        }
    }
    </script>
</body>
</html> 