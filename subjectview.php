<?php
session_start();

$host = "localhost";
$user = "root";
$password = "";
$db = "althea_database";

$data = mysqli_connect($host, $user, $password, $db);

if ($data === false) {
    die("Connection error");
}

if (!isset($_SESSION['username']) || $_SESSION['usertype'] !== 'student') {
    header("location: login.php");
    exit();
}

$subject_id = isset($_GET['id']) ? intval($_GET['id']) : 1;
$query = "SELECT * FROM subjects WHERE id = $subject_id";
$result = mysqli_query($data, $query);
$subject = mysqli_fetch_assoc($result);

if (!$subject) {
    $subject = [
        'subject_name' => 'Computational Science',
        'instructor' => 'Prof. Jomar Gonzaga',
        'description' => 'Mathematical modeling and numerical analysis for computing.'
    ];
}

$success_msg = "";
if (isset($_POST['send_message'])) {
    $sender = $_SESSION['username'];
    $receiver = $subject['instructor'];
    $subject_module = mysqli_real_escape_string($data, $_POST['subject_module']);
    $message = mysqli_real_escape_string($data, $_POST['message']);

    $insert_msg = "INSERT INTO messages (sender, receiver, subject_module, message) VALUES ('$sender', '$receiver', '$subject_module', '$message')";
    if (mysqli_query($data, $insert_msg)) {
        $success_msg = "Message sent successfully to your professor!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Portal - <?php echo htmlspecialchars($subject['subject_name']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="sidebar">
        <div>
            <h5 class="fw-bold mb-1"><i class="fa-solid fa-graduation-cap me-2"></i>KNS Portal</h5>
            <small style="color: #b7e4c7;">Subject View Portal</small>
            <ul class="sidebar-menu">
                <li><a href="studenthome.php"><span><i class="fa-solid fa-house me-2"></i> Dashboard</span></a></li>
                <li><a href="studenthome.php" class="active"><span><i class="fa-solid fa-book-open me-2"></i> My Subjects</span></a></li>
            </ul>
        </div>
        <div>
            <a href="logout.php" class="text-danger fw-bold text-decoration-none p-2 d-block"><i class="fa-solid fa-right-from-bracket me-2"></i> Sign Out</a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <div>
                <a href="studenthome.php" class="text-success fw-bold text-decoration-none small d-block mb-1"><i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard</a>
                <h4 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($subject['subject_name']); ?></h4>
                <p class="text-muted small mb-0">Instructor: <b><?php echo htmlspecialchars($subject['instructor']); ?></b> | Description: <?php echo htmlspecialchars($subject['description']); ?></p>
            </div>
            <div>
                <span class="badge bg-success p-2 px-3 rounded-pill">Active Subject Page</span>
            </div>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i> <?php echo $success_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card-custom">
                    <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-book me-2 text-success"></i>Subject Modules & Learning Materials</h5>
                    <div class="list-group">
                        <div class="list-group-item p-3 mb-2 rounded border">
                            <h6 class="fw-bold text-success mb-1">Module 1: Introduction & Fundamentals</h6>
                            <p class="mb-1 small text-secondary">Core concepts and overview of <?php echo htmlspecialchars($subject['subject_name']); ?>.</p>
                        </div>
                        <div class="list-group-item p-3 mb-2 rounded border bg-light border-danger">
                            <h6 class="fw-bold text-danger mb-1">Module 4: Advanced Topics & Simulation</h6>
                            <p class="mb-1 small text-secondary">In-depth laboratory exercises and problem sets.</p>
                        </div>
                    </div>
                </div>

                <div class="card-custom">
                    <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-upload me-2 text-success"></i>Turn In Activity / Schoolwork</h5>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Select Module Assignment</label>
                            <select class="form-select" required>
                                <option value="Mod 4">Module 4 Assignment</option>
                                <option value="Mod 5">Module 5 Final Output</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Upload Output File</label>
                            <input type="file" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-success fw-bold px-4"><i class="fa-solid fa-cloud-arrow-up me-1"></i> Submit Work</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card-custom border-start border-success border-5">
                    <h5 class="fw-bold text-dark mb-2"><i class="fa-solid fa-paper-plane me-2 text-success"></i>Private Message to Professor</h5>
                    <p class="text-muted small mb-3">Send a direct inquiry regarding <b><?php echo htmlspecialchars($subject['subject_name']); ?></b>.</p>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Module / Topic</label>
                            <select name="subject_module" class="form-select" required>
                                <option value="<?php echo htmlspecialchars($subject['subject_name']); ?> - Mod 1">Mod 1: Intro</option>
                                <option value="<?php echo htmlspecialchars($subject['subject_name']); ?> - Mod 4">Mod 4: Simulation/Advanced</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Your Question or Help Request</label>
                            <textarea name="message" class="form-control" rows="4" placeholder="Type your question here..." required></textarea>
                        </div>
                        <button type="submit" name="send_message" class="btn btn-success w-100 fw-bold">
                            <i class="fa-solid fa-paper-plane me-1"></i> Send to Professor
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>