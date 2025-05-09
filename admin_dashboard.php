<?php
session_start();
require_once 'database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

// Get current sit-in sessions
$stmt = $conn->prepare("
    SELECT cs.*, u.first_name, u.last_name, u.course, u.year_level 
    FROM current_sitins cs 
    JOIN users u ON cs.user_id = u.user_id 
    ORDER BY cs.sitin_time DESC
");
$stmt->execute();
$current_sitins = $stmt->fetchAll();

// Get logged out sit-in sessions
$stmt = $conn->prepare("
    SELECT ls.*, u.first_name, u.last_name, u.course, u.year_level 
    FROM logged_out_sitins ls 
    JOIN users u ON ls.user_id = u.user_id 
    ORDER BY ls.logout_time DESC
");
$stmt->execute();
$logged_out_sitins = $stmt->fetchAll();

// Get all students with total sit-ins
$stmt = $conn->prepare("SELECT u.*, (SELECT COUNT(*) FROM logged_out_sitins ls WHERE ls.user_id = u.user_id) as total_sitins FROM users u ORDER BY last_name, first_name");
$stmt->execute();
$students = $stmt->fetchAll();

// Get announcements
$stmt = $conn->prepare("SELECT * FROM announcements ORDER BY created_at DESC");
$stmt->execute();
$announcements = $stmt->fetchAll();

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

// Handle student search
$search_results = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $stmt = $conn->prepare("
        SELECT * FROM users 
        WHERE user_id LIKE ? 
        OR first_name LIKE ? 
        OR last_name LIKE ? 
        OR course LIKE ?
        ORDER BY last_name, first_name
    ");
    $stmt->execute([$search, $search, $search, $search]);
    $search_results = $stmt->fetchAll();
}

// Fetch pending sit-in reservations
$stmt = $conn->prepare("SELECT r.*, u.first_name, u.last_name, u.course, u.year_level FROM sit_in_reservations r JOIN users u ON r.user_id = u.user_id WHERE r.status = 'pending' ORDER BY r.created_at DESC");
$stmt->execute();
$pending_reservations = $stmt->fetchAll();

// Computer Lab Management logic
$selected_lab = isset($_GET['manage_lab']) ? $_GET['manage_lab'] : '';
$pc_status_filter = isset($_GET['pc_status']) ? $_GET['pc_status'] : 'all';
$lab_pcs = [];
if ($selected_lab) {
    $query = "SELECT lp.*, cs.user_id AS used_by FROM lab_pcs lp LEFT JOIN current_sitins cs ON lp.lab = cs.lab AND lp.pc_number = cs.pc_number WHERE lp.lab = ?";
    $params = [$selected_lab];
    if ($pc_status_filter === 'available') {
        $query .= " AND lp.is_available = 1";
    } elseif ($pc_status_filter === 'used') {
        $query .= " AND lp.is_available = 0";
    }
    $query .= " ORDER BY lp.pc_number ASC";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $lab_pcs = $stmt->fetchAll();
}

// Logs module logic
$logs_search = isset($_GET['logs_search']) ? trim($_GET['logs_search']) : '';
$logs_filter = isset($_GET['logs_filter']) ? $_GET['logs_filter'] : 'all';
$logs_query = "SELECT r.*, u.first_name, u.last_name, u.course, u.year_level FROM sit_in_reservations r JOIN users u ON r.user_id = u.user_id WHERE r.status IN ('approved', 'rejected')";
$logs_params = [];
if ($logs_search !== '') {
    $logs_query .= " AND (u.user_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.course LIKE ?)";
    $search_term = "%$logs_search%";
    $logs_params = array_merge($logs_params, [$search_term, $search_term, $search_term, $search_term]);
}
if ($logs_filter === 'approved') {
    $logs_query .= " AND r.status = 'approved'";
} elseif ($logs_filter === 'rejected') {
    $logs_query .= " AND r.status = 'rejected'";
}
$logs_query .= " ORDER BY r.created_at DESC";
$stmt = $conn->prepare($logs_query);
$stmt->execute($logs_params);
$logs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Sit-in Management System - Admin</a>
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
        <!-- Search Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Search Student</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-10">
                        <input type="text" class="form-control" name="search" placeholder="Search by ID, name, or course..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Search</button>
                    </div>
                </form>

                <?php if (!empty($search_results)): ?>
                    <div class="table-responsive mt-3">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User ID</th>
                                    <th>Name</th>
                                    <th>Course</th>
                                    <th>Year Level</th>
                                    <th>Remaining Sessions</th>
                                    <th>Points</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($search_results as $student): ?>
                                    <tr>
                                        <td><?php echo $student['user_id']; ?></td>
                                        <td><?php echo $student['last_name'] . ', ' . $student['first_name']; ?></td>
                                        <td><?php echo $student['course']; ?></td>
                                        <td><?php echo $student['year_level']; ?></td>
                                        <td><?php echo $student['remaining_sessions']; ?></td>
                                        <td><?php echo $student['points']; ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#sitinModal<?php echo $student['id']; ?>">
                                                Sit-in
                                            </button>
                                            <button type="button" class="btn btn-sm btn-info" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editModal<?php echo $student['id']; ?>">
                                                Edit
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal<?php echo $student['id']; ?>">
                                                Delete
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Sit-in Modal -->
                                    <div class="modal fade" id="sitinModal<?php echo $student['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Sit-in Student</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="process_sitin.php" method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="user_id" value="<?php echo $student['user_id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Lab</label>
                                                            <select name="lab" class="form-select" required>
                                                                <option value="" disabled selected>Select Lab</option>
                                                                <option value="Lab 524">Lab 524</option>
                                                                <option value="Lab 526">Lab 526</option>
                                                                <option value="Lab 528">Lab 528</option>
                                                                <option value="Lab 530">Lab 530</option>
                                                                <option value="Lab 542">Lab 542</option>
                                                                <option value="Lab 544">Lab 544</option>
                                                                <option value="Lab 517">Lab 517</option>
                                                            </select>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label">PC Number</label>
                                                            <select name="pc_number" class="form-select" required>
                                                                <option value="" disabled selected>Select PC Number</option>
                                                                <?php for ($i = 1; $i <= 30; $i++): ?>
                                                                    <option value="<?php echo $i; ?>">PC <?php echo $i; ?></option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </div>

                                                        <div class="mb-3">
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
                                                                <option value="Python Programming">Python Programming</option>
                                                                <option value="Mobile Application">Mobile Application</option>
                                                                <option value="Others">Others</option>
                                                            </select>
                                                        </div>

                                                        <div class="mb-3" id="otherPurposeDiv" style="display: none;">
                                                            <label class="form-label">Other Purpose</label>
                                                            <input type="text" class="form-control" name="other_purpose">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary">Confirm Sit-in</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?php echo $student['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Student</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form action="edit_student.php" method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="user_id" value="<?php echo $student['user_id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">First Name</label>
                                                            <input type="text" class="form-control" name="first_name" value="<?php echo $student['first_name']; ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Middle Name</label>
                                                            <input type="text" class="form-control" name="middle_name" value="<?php echo $student['middle_name']; ?>">
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Last Name</label>
                                                            <input type="text" class="form-control" name="last_name" value="<?php echo $student['last_name']; ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Year Level</label>
                                                            <select name="year_level" class="form-select" required>
                                                                <option value="1st Year" <?php echo $student['year_level'] == '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                                                                <option value="2nd Year" <?php echo $student['year_level'] == '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                                                                <option value="3rd Year" <?php echo $student['year_level'] == '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                                                                <option value="4th Year" <?php echo $student['year_level'] == '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Course</label>
                                                            <select name="course" class="form-select" required>
                                                                <option value="BSA" <?php echo $student['course'] == 'BSA' ? 'selected' : ''; ?>>Bachelor of Science in Accountancy (BSA)</option>
                                                                <option value="BAA" <?php echo $student['course'] == 'BAA' ? 'selected' : ''; ?>>Bachelor of Arts (BAA)</option>
                                                                <option value="BSEd-BioSci" <?php echo $student['course'] == 'BSEd-BioSci' ? 'selected' : ''; ?>>BSEd Major in Biological Science (BSEd-BioSci)</option>
                                                                <option value="BSBA" <?php echo $student['course'] == 'BSBA' ? 'selected' : ''; ?>>Bachelor of Science in Business Administration (BSBA)</option>
                                                                <option value="BSCE" <?php echo $student['course'] == 'BSCE' ? 'selected' : ''; ?>>Bachelor of Science in Civil Engineering (BSCE)</option>
                                                                <option value="BSCpE" <?php echo $student['course'] == 'BSCpE' ? 'selected' : ''; ?>>Bachelor of Science in Computer Engineering (BSCpE)</option>
                                                                <option value="BSIT" <?php echo $student['course'] == 'BSIT' ? 'selected' : ''; ?>>Bachelor of Science in Information Technology (BSIT)</option>
                                                                <option value="BSEE" <?php echo $student['course'] == 'BSEE' ? 'selected' : ''; ?>>Bachelor of Science in Electrical Engineering (BSEE)</option>
                                                                <option value="BSECE" <?php echo $student['course'] == 'BSECE' ? 'selected' : ''; ?>>Bachelor of Science in Electronics and Communication Engineering (BSECE)</option>
                                                                <option value="BSME" <?php echo $student['course'] == 'BSME' ? 'selected' : ''; ?>>Bachelor of Science in Mechanical Engineering (BSME)</option>
                                                                <option value="BSOA" <?php echo $student['course'] == 'BSOA' ? 'selected' : ''; ?>>Bachelor of Science in Office Administration (BSOA)</option>
                                                                <option value="BSREM" <?php echo $student['course'] == 'BSREM' ? 'selected' : ''; ?>>Bachelor of Science in Real Estate Management (BSREM)</option>
                                                                <option value="BSCS" <?php echo $student['course'] == 'BSCS' ? 'selected' : ''; ?>>Bachelor of Science in Computer Studies(BSCS)</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $student['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Delete Student</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Are you sure you want to delete this student?</p>
                                                    <p><strong>Name:</strong> <?php echo $student['last_name'] . ', ' . $student['first_name']; ?></p>
                                                    <p><strong>User ID:</strong> <?php echo $student['user_id']; ?></p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <form action="delete_student.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?php echo $student['user_id']; ?>">
                                                        <button type="submit" class="btn btn-danger">Delete</button>
                                                    </form>
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
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Course</th>
                                    <th>Lab</th>
                                    <th>PC Number</th>
                                    <th>Purpose</th>
                                    <th>Time In</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($current_sitins as $session): ?>
                                    <tr>
                                        <td><?php echo $session['user_id']; ?></td>
                                        <td><?php echo $session['last_name'] . ', ' . $session['first_name']; ?></td>
                                        <td><?php echo $session['course']; ?></td>
                                        <td><?php echo $session['lab']; ?></td>
                                        <td><?php echo $session['pc_number']; ?></td>
                                        <td><?php echo $session['purpose']; ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($session['sitin_time'])); ?></td>
                                        <td>
                                            <form action="process_logout.php" method="POST" class="d-inline">
                                                <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $session['user_id']; ?>">
                                                <input type="hidden" name="was_rewarded" value="0">
                                                <button type="submit" class="btn btn-sm btn-warning">Logout</button>
                                            </form>
                                            <form action="process_logout.php" method="POST" class="d-inline">
                                                <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $session['user_id']; ?>">
                                                <input type="hidden" name="was_rewarded" value="1">
                                                <button type="submit" class="btn btn-sm btn-success">Reward</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upload Materials Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Upload Materials</h5>
            </div>
            <div class="card-body">
                <form action="upload_material.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <input type="file" class="form-control" name="material" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Upload Material</button>
                </form>
                <hr>
                <h6>Uploaded Materials</h6>
                <ul class="list-group">
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM materials ORDER BY uploaded_at DESC");
                    $stmt->execute();
                    $materials = $stmt->fetchAll();
                    foreach ($materials as $mat): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?php echo htmlspecialchars($mat['original_name']); ?></span>
                            <a href="uploads/materials/<?php echo urlencode($mat['filename']); ?>" download class="btn btn-sm btn-success">Download</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Upload Lab Schedules Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Upload Lab Schedules</h5>
            </div>
            <div class="card-body">
                <form action="upload_lab_schedule.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <input type="file" class="form-control" name="lab_schedule" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Upload Lab Schedule</button>
                </form>
                <hr>
                <h6>Uploaded Lab Schedules</h6>
                <ul class="list-group">
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM lab_schedules ORDER BY uploaded_at DESC");
                    $stmt->execute();
                    $schedules = $stmt->fetchAll();
                    foreach ($schedules as $sched): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?php echo htmlspecialchars($sched['original_name']); ?></span>
                            <a href="uploads/lab_schedules/<?php echo urlencode($sched['filename']); ?>" download class="btn btn-sm btn-success">Download</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Announcements Section -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Announcements</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newAnnouncementModal">
                    <i class="fas fa-plus"></i> New Announcement
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($announcements)): ?>
                    <p class="text-muted">No announcements yet.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editAnnouncementModal<?php echo $announcement['id']; ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteAnnouncementModal<?php echo $announcement['id']; ?>">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                                <p class="mb-1"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                <small class="text-muted">Posted: <?php echo date('M d, Y h:i A', strtotime($announcement['created_at'])); ?></small>
                            </div>

                            <!-- Edit Announcement Modal -->
                            <div class="modal fade" id="editAnnouncementModal<?php echo $announcement['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Announcement</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form action="edit_announcement.php" method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Title</label>
                                                    <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($announcement['title']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Content</label>
                                                    <textarea class="form-control" name="content" rows="5" required><?php echo htmlspecialchars($announcement['content']); ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Delete Announcement Modal -->
                            <div class="modal fade" id="deleteAnnouncementModal<?php echo $announcement['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Delete Announcement</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Are you sure you want to delete this announcement?</p>
                                            <p><strong>Title:</strong> <?php echo htmlspecialchars($announcement['title']); ?></p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <form action="delete_announcement.php" method="POST" class="d-inline">
                                                <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                                <button type="submit" class="btn btn-danger">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- New Announcement Modal -->
        <div class="modal fade" id="newAnnouncementModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">New Announcement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="create_announcement.php" method="POST">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Title</label>
                                <input type="text" class="form-control" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Content</label>
                                <textarea class="form-control" name="content" rows="5" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Post Announcement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Logged Out Sessions Section -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Logged Out Sessions</h5>
                <div class="btn-group">
                    <button type="button" class="btn btn-success btn-sm" onclick="exportData('csv')">CSV</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="exportData('excel')">Excel</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="exportData('pdf')">PDF</button>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <select class="form-select" id="labFilter">
                            <option value="">Filter by Lab</option>
                            <option value="Lab 524">Lab 524</option>
                            <option value="Lab 526">Lab 526</option>
                            <option value="Lab 528">Lab 528</option>
                            <option value="Lab 530">Lab 530</option>
                            <option value="Lab 542">Lab 542</option>
                            <option value="Lab 544">Lab 544</option>
                            <option value="Lab 517">Lab 517</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select class="form-select" id="purposeFilter">
                            <option value="">Filter by Purpose</option>
                            <option value="C Programming">C Programming</option>
                            <option value="Java Programming">Java Programming</option>
                            <option value="System Integration & Architecture">System Integration & Architecture</option>
                            <option value="Embeded System & IOT">Embeded System & IOT</option>
                            <option value="Digital Logic & Design">Digital Logic & Design</option>
                            <option value="Computer Application">Computer Application</option>
                            <option value="Database">Database</option>
                            <option value="Project Management">Project Management</option>
                            <option value="Python Programming">Python Programming</option>
                            <option value="Mobile Application">Mobile Application</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                </div>

                <?php if (empty($logged_out_sitins)): ?>
                    <p class="text-muted">No logged out sessions.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Course</th>
                                    <th>Lab</th>
                                    <th>PC Number</th>
                                    <th>Purpose</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Status</th>
                                    <th>Feedback</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logged_out_sitins as $session): ?>
                                    <tr class="session-row" 
                                        data-lab="<?php echo $session['lab']; ?>"
                                        data-purpose="<?php echo $session['purpose']; ?>">
                                        <td><?php echo $session['user_id']; ?></td>
                                        <td><?php echo $session['last_name'] . ', ' . $session['first_name']; ?></td>
                                        <td><?php echo $session['course']; ?></td>
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
                                            <?php if (!empty($session['feedback'])): ?>
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#viewFeedbackModal<?php echo $session['id']; ?>">
                                                    View Feedback
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">No feedback</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

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

        <!-- Student's List Table -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Student's List</h5>
                <form action="reset_all_sessions.php" method="POST" class="d-inline">
                    <button type="submit" class="btn btn-warning btn-sm">Reset All</button>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Course</th>
                                <th>Year Level</th>
                                <th>Remaining Sessions</th>
                                <th>Total Sit-ins</th>
                                <th>Points</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?php echo $student['user_id']; ?></td>
                                    <td><?php echo $student['last_name'] . ', ' . $student['first_name']; ?></td>
                                    <td><?php echo $student['course']; ?></td>
                                    <td><?php echo $student['year_level']; ?></td>
                                    <td><?php echo $student['remaining_sessions']; ?></td>
                                    <td><?php echo $student['total_sitins']; ?></td>
                                    <td><?php echo $student['points']; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editModalList<?php echo $student['id']; ?>">Edit</button>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModalList<?php echo $student['id']; ?>">Delete</button>
                                        <form action="reset_session.php" method="POST" class="d-inline">
                                            <input type="hidden" name="user_id" value="<?php echo $student['user_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-warning">Reset</button>
                                        </form>
                                    </td>
                                </tr>
                                <!-- Edit Modal for List Table -->
                                <div class="modal fade" id="editModalList<?php echo $student['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Student</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="edit_student.php" method="POST">
                                                <div class="modal-body">
                                                    <input type="hidden" name="user_id" value="<?php echo $student['user_id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">First Name</label>
                                                        <input type="text" class="form-control" name="first_name" value="<?php echo $student['first_name']; ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Middle Name</label>
                                                        <input type="text" class="form-control" name="middle_name" value="<?php echo $student['middle_name']; ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Last Name</label>
                                                        <input type="text" class="form-control" name="last_name" value="<?php echo $student['last_name']; ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Year Level</label>
                                                        <select name="year_level" class="form-select" required>
                                                            <option value="1st Year" <?php echo $student['year_level'] == '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                                                            <option value="2nd Year" <?php echo $student['year_level'] == '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                                                            <option value="3rd Year" <?php echo $student['year_level'] == '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                                                            <option value="4th Year" <?php echo $student['year_level'] == '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Course</label>
                                                        <select name="course" class="form-select" required>
                                                            <option value="BSA" <?php echo $student['course'] == 'BSA' ? 'selected' : ''; ?>>Bachelor of Science in Accountancy (BSA)</option>
                                                            <option value="BAA" <?php echo $student['course'] == 'BAA' ? 'selected' : ''; ?>>Bachelor of Arts (BAA)</option>
                                                            <option value="BSEd-BioSci" <?php echo $student['course'] == 'BSEd-BioSci' ? 'selected' : ''; ?>>BSEd Major in Biological Science (BSEd-BioSci)</option>
                                                            <option value="BSBA" <?php echo $student['course'] == 'BSBA' ? 'selected' : ''; ?>>Bachelor of Science in Business Administration (BSBA)</option>
                                                            <option value="BSCE" <?php echo $student['course'] == 'BSCE' ? 'selected' : ''; ?>>Bachelor of Science in Civil Engineering (BSCE)</option>
                                                            <option value="BSCpE" <?php echo $student['course'] == 'BSCpE' ? 'selected' : ''; ?>>Bachelor of Science in Computer Engineering (BSCpE)</option>
                                                            <option value="BSIT" <?php echo $student['course'] == 'BSIT' ? 'selected' : ''; ?>>Bachelor of Science in Information Technology (BSIT)</option>
                                                            <option value="BSEE" <?php echo $student['course'] == 'BSEE' ? 'selected' : ''; ?>>Bachelor of Science in Electrical Engineering (BSEE)</option>
                                                            <option value="BSECE" <?php echo $student['course'] == 'BSECE' ? 'selected' : ''; ?>>Bachelor of Science in Electronics and Communication Engineering (BSECE)</option>
                                                            <option value="BSME" <?php echo $student['course'] == 'BSME' ? 'selected' : ''; ?>>Bachelor of Science in Mechanical Engineering (BSME)</option>
                                                            <option value="BSOA" <?php echo $student['course'] == 'BSOA' ? 'selected' : ''; ?>>Bachelor of Science in Office Administration (BSOA)</option>
                                                            <option value="BSREM" <?php echo $student['course'] == 'BSREM' ? 'selected' : ''; ?>>Bachelor of Science in Real Estate Management (BSREM)</option>
                                                            <option value="BSCS" <?php echo $student['course'] == 'BSCS' ? 'selected' : ''; ?>>Bachelor of Science in Computer Studies(BSCS)</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <!-- Delete Modal for List Table -->
                                <div class="modal fade" id="deleteModalList<?php echo $student['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Delete Student</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete this student?</p>
                                                <p><strong>Name:</strong> <?php echo $student['last_name'] . ', ' . $student['first_name']; ?></p>
                                                <p><strong>User ID:</strong> <?php echo $student['user_id']; ?></p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form action="delete_student.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $student['user_id']; ?>">
                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sit-in Requests Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Sit-in Reservation Requests</h5>
            </div>
            <div class="card-body">
                <?php if (empty($pending_reservations)): ?>
                    <p class="text-muted">No pending sit-in reservation requests.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Course</th>
                                    <th>Year</th>
                                    <th>Lab</th>
                                    <th>PC Number</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Purpose</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_reservations as $res): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($res['last_name'] . ', ' . $res['first_name']); ?></td>
                                        <td><?php echo htmlspecialchars($res['course']); ?></td>
                                        <td><?php echo htmlspecialchars($res['year_level']); ?></td>
                                        <td><?php echo htmlspecialchars($res['lab']); ?></td>
                                        <td><?php echo htmlspecialchars($res['pc_number']); ?></td>
                                        <td><?php echo htmlspecialchars($res['date']); ?></td>
                                        <td><?php echo htmlspecialchars($res['time']); ?></td>
                                        <td><?php echo htmlspecialchars($res['purpose']); ?></td>
                                        <td>
                                            <form action="process_reservation.php" method="POST" class="d-inline">
                                                <input type="hidden" name="reservation_id" value="<?php echo $res['id']; ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                                            </form>
                                            <form action="process_reservation.php" method="POST" class="d-inline">
                                                <input type="hidden" name="reservation_id" value="<?php echo $res['id']; ?>">
                                                <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">Reject</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Computer Lab Management Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Computer Lab Management</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Select Lab</label>
                        <select name="manage_lab" class="form-select" onchange="this.form.submit()">
                            <option value="">Choose Lab</option>
                            <option value="Lab 524" <?php if($selected_lab=='Lab 524') echo 'selected'; ?>>Lab 524</option>
                            <option value="Lab 526" <?php if($selected_lab=='Lab 526') echo 'selected'; ?>>Lab 526</option>
                            <option value="Lab 528" <?php if($selected_lab=='Lab 528') echo 'selected'; ?>>Lab 528</option>
                            <option value="Lab 530" <?php if($selected_lab=='Lab 530') echo 'selected'; ?>>Lab 530</option>
                            <option value="Lab 542" <?php if($selected_lab=='Lab 542') echo 'selected'; ?>>Lab 542</option>
                            <option value="Lab 544" <?php if($selected_lab=='Lab 544') echo 'selected'; ?>>Lab 544</option>
                            <option value="Lab 517" <?php if($selected_lab=='Lab 517') echo 'selected'; ?>>Lab 517</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">PC Status</label>
                        <select name="pc_status" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php if($pc_status_filter=='all') echo 'selected'; ?>>All PC</option>
                            <option value="available" <?php if($pc_status_filter=='available') echo 'selected'; ?>>Available</option>
                            <option value="used" <?php if($pc_status_filter=='used') echo 'selected'; ?>>Used</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">View</button>
                    </div>
                </form>
                <?php if ($selected_lab): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>PC Number</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lab_pcs as $pc): ?>
                                    <tr>
                                        <td><?php echo $pc['pc_number']; ?></td>
                                        <td>
                                            <?php if ($pc['is_available']): ?>
                                                <span class="badge bg-success">Available</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Used</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Select a lab to view its PCs.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Logs Module Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Sit-in Reservation Logs</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Search Student</label>
                        <input type="text" name="logs_search" class="form-control" placeholder="Search by ID, name, or course..." value="<?php echo htmlspecialchars($logs_search); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Filter</label>
                        <select name="logs_filter" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php if($logs_filter=='all') echo 'selected'; ?>>All</option>
                            <option value="approved" <?php if($logs_filter=='approved') echo 'selected'; ?>>Approved</option>
                            <option value="rejected" <?php if($logs_filter=='rejected') echo 'selected'; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </form>
                <?php if (empty($logs)): ?>
                    <p class="text-muted">No logs found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Course</th>
                                    <th>Year</th>
                                    <th>Lab</th>
                                    <th>PC Number</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                    <th>Action Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['last_name'] . ', ' . $log['first_name']); ?></td>
                                        <td><?php echo htmlspecialchars($log['course']); ?></td>
                                        <td><?php echo htmlspecialchars($log['year_level']); ?></td>
                                        <td><?php echo htmlspecialchars($log['lab']); ?></td>
                                        <td><?php echo htmlspecialchars($log['pc_number']); ?></td>
                                        <td><?php echo htmlspecialchars($log['date']); ?></td>
                                        <td><?php echo htmlspecialchars($log['time']); ?></td>
                                        <td><?php echo htmlspecialchars($log['purpose']); ?></td>
                                        <td>
                                            <?php if ($log['status'] === 'approved'): ?>
                                                <span class="badge bg-success">Approved</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($log['updated_at'] ?? $log['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleOtherPurpose(select) {
            const otherPurposeDiv = select.parentElement.nextElementSibling;
            if (select.value === 'Others') {
                otherPurposeDiv.style.display = 'block';
                otherPurposeDiv.querySelector('input').required = true;
            } else {
                otherPurposeDiv.style.display = 'none';
                otherPurposeDiv.querySelector('input').required = false;
            }
        }

        // Filter functionality
        document.getElementById('labFilter').addEventListener('change', filterSessions);
        document.getElementById('purposeFilter').addEventListener('change', filterSessions);

        function filterSessions() {
            const labFilter = document.getElementById('labFilter').value;
            const purposeFilter = document.getElementById('purposeFilter').value;
            const rows = document.querySelectorAll('.session-row');

            rows.forEach(row => {
                const lab = row.dataset.lab;
                const purpose = row.dataset.purpose;
                const labMatch = !labFilter || lab === labFilter;
                const purposeMatch = !purposeFilter || purpose === purposeFilter;
                row.style.display = labMatch && purposeMatch ? '' : 'none';
            });
        }

        function exportData(format) {
            const labFilter = document.getElementById('labFilter').value;
            const purposeFilter = document.getElementById('purposeFilter').value;
            
            window.location.href = `export_data.php?format=${format}&lab=${labFilter}&purpose=${purposeFilter}`;
        }
    </script>
</body>
</html> 