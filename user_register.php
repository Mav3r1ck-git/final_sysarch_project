<?php
require_once 'database.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = trim($_POST['user_id']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $year_level = $_POST['year_level'];
    $course = $_POST['course'];

    // Validation
    if (empty($user_id) || empty($first_name) || empty($last_name) || empty($password) || empty($confirm_password) || empty($year_level) || empty($course)) {
        $error = "All fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        // Check if user_id already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        if ($stmt->rowCount() > 0) {
            $error = "User ID already exists";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $stmt = $conn->prepare("INSERT INTO users (user_id, first_name, middle_name, last_name, password, year_level, course, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            try {
                $stmt->execute([$user_id, $first_name, $middle_name, $last_name, $hashed_password, $year_level, $course, 'default_profile_picture.png']);
                $success = "Registration successful! You can now login.";
            } catch(PDOException $e) {
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">User Registration</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="user_id" class="form-label">User ID</label>
                                <input type="text" class="form-control" id="user_id" name="user_id" required>
                            </div>
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name">
                            </div>
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="year_level" class="form-label">Year Level</label>
                                <select class="form-select" id="year_level" name="year_level" required>
                                    <option value="">Select Year Level</option>
                                    <option value="1st Year">1st Year</option>
                                    <option value="2nd Year">2nd Year</option>
                                    <option value="3rd Year">3rd Year</option>
                                    <option value="4th Year">4th Year</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="course" class="form-label">Course</label>
                                <select class="form-select" id="course" name="course" required>
                                    <option value="">Select Course</option>
                                    <option value="BSA">Bachelor of Science in Accountancy (BSA)</option>
                                    <option value="BAA">Bachelor of Arts (BAA)</option>
                                    <option value="BSEd-BioSci">BSEd Major in Biological Science (BSEd-BioSci)</option>
                                    <option value="BSBA">Bachelor of Science in Business Administration (BSBA)</option>
                                    <option value="BSCE">Bachelor of Science in Civil Engineering (BSCE)</option>
                                    <option value="BSCpE">Bachelor of Science in Computer Engineering (BSCpE)</option>
                                    <option value="BSIT">Bachelor of Science in Information Technology (BSIT)</option>
                                    <option value="BSEE">Bachelor of Science in Electrical Engineering (BSEE)</option>
                                    <option value="BSECE">Bachelor of Science in Electronics and Communication Engineering (BSECE)</option>
                                    <option value="BSME">Bachelor of Science in Mechanical Engineering (BSME)</option>
                                    <option value="BSOA">Bachelor of Science in Office Administration (BSOA)</option>
                                    <option value="BSREM">Bachelor of Science in Real Estate Management (BSREM)</option>
                                    <option value="BSCS">Bachelor of Science in Computer Studies(BSCS)</option>
                                </select>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Register</button>
                            </div>
                        </form>
                        <div class="text-center mt-3">
                            <p>Already have an account? <a href="user_login.php">Login here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 