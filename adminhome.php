<?php
session_start();

if (!isset($_SESSION['username'] ) || $_SESSION['usertype'] !== 'admin') {
    header("location: login.php");
    exit();
}

$host = "localhost";
$user = "root";
$password = "";
$db = "althea_database";

$data = mysqli_connect($host, $user, $password, $db);

if ($data === false) {
    die("Connection error");
}

// 1. Bilangin ang kabuuang inquiries para sa live badge
$badge_q = mysqli_query($data, "SELECT COUNT(*) as total FROM messages");
$badge_r = mysqli_fetch_assoc($badge_q);
$total_inquiries = $badge_r['total'];

// 2. Bilangin ang kabuuang estudyante na nakarehistro (Total Enrolled Students)
$student_q = mysqli_query($data, "SELECT COUNT(*) as total FROM user WHERE usertype = 'student'");
$student_r = mysqli_fetch_assoc($student_q);
$total_students = $student_r['total'];

// Handle reply submission (kung nag-reply ang admin sa modal)
$reply_success = "";
if (isset($_POST['send_reply'])) {
    $reply_msg = mysqli_real_escape_string($data, $_POST['reply_message']);
    $student_sender = mysqli_real_escape_string($data, $_POST['student_sender']);
    
    // Pwede nating i-save sa database o magpalabas ng success alert
    $reply_success = "Reply successfully sent to " . $student_sender . "!";
}

// Kunin ang mga mensahe mula sa database
$msg_query = "SELECT * FROM messages ORDER BY created_at DESC";
$msg_result = mysqli_query($data, $msg_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Admin Dashboard - KNS Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div>
            <h5 class="fw-bold mb-1"><i class="fa-solid fa-graduation-cap me-2"></i>KNS Portal</h5>
            <small style="color: #b7e4c7;">Faculty Admin Dashboard</small>
            <ul class="sidebar-menu">
                <li><a href="adminhome.php" class="active"><span><i class="fa-solid fa-gauge me-2"></i> Dashboard</span></a></li>
                <li>
                    <a href="#messagesSection">
                        <span><i class="fa-solid fa-comments me-2"></i> Faculty Messaging</span>
                        <?php if ($total_inquiries > 0): ?>
                            <span class="badge bg-danger rounded-pill"><?php echo $total_inquiries; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>
        <div>
            <a href="logout.php" class="text-danger fw-bold text-decoration-none p-2 d-block"><i class="fa-solid fa-right-from-bracket me-2"></i> Sign Out</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <div>
                <h4 class="fw-bold mb-0 text-dark">Faculty Analytics & Messaging Hub</h4>
                <p class="text-muted small mb-0">Centralized Subject Portal with Integrated Faculty Messaging</p>
            </div>
            <div>
                <span class="badge bg-success p-2 px-3 rounded-pill">System Live - 2026</span>
            </div>
        </div>

        <?php if (!empty($reply_success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i> <?php echo $reply_success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Metrics Panel (Idinagdag ang Total Enrolled Students Card) -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="metric-card border-top border-primary border-4">
                    <h6 class="text-muted fw-bold small">ENROLLED STUDENTS</h6>
                    <h3 class="fw-bold text-primary mb-0"><?php echo $total_students; ?></h3>
                    <small class="text-muted">Total registered learners</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card border-top border-danger border-4">
                    <h6 class="text-muted fw-bold small">TOTAL INQUIRIES</h6>
                    <h3 class="fw-bold text-danger mb-0"><?php echo $total_inquiries; ?></h3>
                    <small class="text-muted">Direct messages received</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card border-top border-success border-4">
                    <h6 class="text-muted fw-bold small">RESOLUTION RATE</h6>
                    <h3 class="fw-bold text-success mb-0">88%</h3>
                    <small class="text-muted">Responded by instructor</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card border-top border-warning border-4">
                    <h6 class="text-muted fw-bold small">DIFFICULTY BOTTLENECK</h6>
                    <h3 class="fw-bold text-warning-emphasis mb-0 fs-5 mt-1">Mod 4: Simulation</h3>
                    <small class="text-muted">Most common topic asked</small>
                </div>
            </div>
        </div>

        <!-- Faculty Messaging Inbox Section -->
        <div class="card-custom" id="messagesSection">
            <h4 class="fw-bold text-dark mb-2"><i class="fa-solid fa-comments me-2 text-success"></i>Integrated Faculty Messaging Inbox</h4>
            <p class="text-muted small mb-3">Direct messages sent by students regarding difficult assignments or module help requests.</p>
            
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Sender (Student)</th>
                            <th>Subject / Module</th>
                            <th>Message / Inquiry</th>
                            <th>Date / Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($msg_result && mysqli_num_rows($msg_result) > 0) {
                            $modal_id = 1;
                            while($row = mysqli_fetch_assoc($msg_result)) {
                                echo "<tr>";
                                echo "<td class='fw-bold'>" . htmlspecialchars($row['sender']) . "</td>";
                                echo "<td><span class='badge bg-secondary'>" . htmlspecialchars($row['subject_module']) . "</span></td>";
                                echo "<td>" . htmlspecialchars($row['message']) . "</td>";
                                echo "<td class='small text-muted'>" . htmlspecialchars($row['created_at']) . "</td>";
                                echo "<td>
                                        <button class='btn btn-sm btn-success fw-bold' data-bs-toggle='modal' data-bs-target='#replyModal{$modal_id}'>
                                            <i class='fa-solid fa-reply me-1'></i> Reply
                                        </button>
                                      </td>";
                                echo "</tr>";

                                // Bootstrap Modal para sa bawat mensahe (In-system reply sa halip na mailto)
                                echo "
                                <div class='modal fade' id='replyModal{$modal_id}' tabindex='-1' aria-hidden='true'>
                                    <div class='modal-dialog'>
                                        <div class='modal-content'>
                                            <div class='modal-header bg-success text-white'>
                                                <h5 class='modal-title fw-bold'><i class='fa-solid fa-reply me-2'></i>Reply to " . htmlspecialchars($row['sender']) . "</h5>
                                                <button type='button' class='btn-close btn-close-white' data-bs-dismiss='modal' aria-label='Close'></button>
                                            </div>
                                            <form method='POST'>
                                                <div class='modal-body'>
                                                    <input type='hidden' name='student_sender' value='" . htmlspecialchars($row['sender']) . "'>
                                                    <div class='mb-3'>
                                                        <label class='form-label small text-muted fw-bold'>Student's Message:</label>
                                                        <div class='p-2 bg-light border rounded text-dark small'>" . htmlspecialchars($row['message']) . "</div>
                                                    </div>
                                                    <div class='mb-3'>
                                                        <label class='form-label fw-bold small'>Your Response / Feedback</label>
                                                        <textarea name='reply_message' class='form-control' rows='4' placeholder='Type your reply or guidance here...' required></textarea>
                                                    </div>
                                                </div>
                                                <div class='modal-footer'>
                                                    <button type='button' class='btn btn-secondary btn-sm' data-bs-dismiss='modal'>Cancel</button>
                                                    <button type='submit' name='send_reply' class='btn btn-success btn-sm fw-bold'>Send Response</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>";
                                $modal_id++;
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center text-muted'>No messages received yet.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS para gumana ang Modals -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>