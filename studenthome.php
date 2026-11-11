<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['usertype'] !== 'student') {
    header("location: login.php");
    exit();
}

$host = "localhost";
$user = "root";
$password = "";
$db = "studentportal_db";

$data = mysqli_connect($host, $user, $password, $db);

if ($data === false) {
    die("Connection error");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Kolehiyo Ng Subic</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <div class="sidebar">
        <div>
            <h5 class="fw-bold mb-1"><i class="fa-solid fa-graduation-cap me-2"></i>KNS Portal</h5>
            <small style="color: #b7e4c7;">Student Subject Portal</small>
            <ul class="sidebar-menu">
                <li><a href="studenthome.php" class="active"><span><i class="fa-solid fa-house me-2"></i> Dashboard</span></a></li>
                <li><a href="#subjectsSection"><span><i class="fa-solid fa-book-open me-2"></i> My Subjects</span></a></li>
            </ul>
        </div>
        <div>
            <div class="border-top border-success pt-3 mb-2 d-flex align-items-center gap-2">
                <i class="fa-solid fa-user-graduate fs-4 text-success bg-light p-2 rounded-circle"></i>
                <div>
                    <h6 class="mb-0 fw-bold text-white"><?php echo htmlspecialchars($_SESSION['username']); ?></h6>
                    <small style="color: #b7e4c7;">Student Account</small>
                </div>
            </div>
            <a href="logout.php" class="text-danger fw-bold text-decoration-none p-2 d-block"><i class="fa-solid fa-right-from-bracket me-2"></i> Sign Out</a>
        </div>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <div>
                <h4 class="fw-bold mb-0 text-dark">Student Dashboard & Workload Monitoring</h4>
                <p class="text-muted small mb-0">Centralized Subject Portal with Activity Tracking</p>
            </div>
            <div>
                <span class="badge bg-success p-2 px-3 rounded-pill">2025-2026 | 1st Semester</span>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="metric-card border-top border-primary border-4">
                    <h6 class="text-muted fw-bold small">TOTAL TASKS</h6>
                    <h3 class="fw-bold text-primary mb-0">12</h3>
                    <small class="text-muted">Assigned across subjects</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card border-top border-success border-4">
                    <h6 class="text-muted fw-bold small">COMPLETED</h6>
                    <h3 class="fw-bold text-success mb-0">8</h3>
                    <small class="text-muted">Successfully turned in</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card border-top border-danger border-4">
                    <h6 class="text-muted fw-bold small">REMAINING</h6>
                    <h3 class="fw-bold text-danger mb-0">4</h3>
                    <small class="text-muted">Pending submission</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card border-top border-warning border-4">
                    <h6 class="text-muted fw-bold small">WORKLOAD STATUS</h6>
                    <span class="badge bg-success fs-6 mt-1 px-3">Balanced</span>
                    <small class="text-muted d-block mt-1">Optimal schedule</small>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card-custom">
                    <h5 class="fw-bold text-dark mb-1"><i class="fa-solid fa-chart-column me-2 text-success"></i>Weekly Workload Density Chart</h5>
                    <p class="text-muted small mb-3">Visualizing task deadlines and effort distribution across the week.</p>
                    <canvas id="studentWorkloadChart" height="135"></canvas>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card-custom text-center">
                    <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-circle-notch me-2 text-primary"></i>Completion Rate</h5>
                    <div style="width: 140px; margin: 0 auto;">
                        <canvas id="completionRateChart"></canvas>
                    </div>
                    <p class="text-muted small mt-3 fw-bold"><b>66% Done</b> - On track with milestones.</p>
                </div>
            </div>
        </div>

        <div id="subjectsSection">
            <h4 class="fw-bold text-dark mb-3 mt-3"><i class="fa-solid fa-book me-2 text-success"></i>My Enrolled Subjects (Database Live)</h4>
            <div class="row">
                <?php
                $subj_query = "SELECT * FROM subjects";
                $subj_result = mysqli_query($data, $subj_query);

                if ($subj_result && mysqli_num_rows($subj_result) > 0) {
                    while($subj = mysqli_fetch_assoc($subj_result)) {
                        echo '<div class="col-md-4 mb-3">';
                        echo '<div class="card card-custom h-100 border-start border-success border-4 d-flex flex-column justify-content-between">';
                        echo '<div class="card-body">';
                        echo '<h5 class="fw-bold text-dark mb-2">' . htmlspecialchars($subj['subject_name']) . '</h5>';
                        echo '<p class="text-secondary small mb-1"><i class="fa-solid fa-user-tie me-1"></i> ' . htmlspecialchars($subj['instructor']) . '</p>';
                        echo '<p class="text-muted small mb-3">' . htmlspecialchars($subj['description']) . '</p>';
                        echo '</div>';
                        echo '<div class="card-footer bg-white border-0 pt-0">';
                        echo '<a href="subjectview.php?id=' . $subj['id'] . '" class="btn btn-sm btn-success w-100 fw-bold"><i class="fa-solid fa-arrow-right me-1"></i> Open Subject Page</a>';
                        echo '</div>';
                        echo '</div>';
                        echo '</div>';
                    }
                } else {
                    echo '<p class="text-muted">No enrolled subjects found in database.</p>';
                }
                ?>
            </div>
        </div>
    </div>

    <script>
        const ctxWorkload = document.getElementById('studentWorkloadChart').getContext('2d');
        new Chart(ctxWorkload, {
            type: 'bar',
            data: {
                labels: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                datasets: [{
                    label: 'Task Effort / Workload Level',
                    data: [35, 22, 40, 60, 28, 48, 40],
                    backgroundColor: 'rgba(40, 167, 69, 0.7)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true, max: 80 } }
            }
        });

        const ctxCompletion = document.getElementById('completionRateChart').getContext('2d');
        new Chart(ctxCompletion, {
            type: 'doughnut',
            data: {
                labels: ['Done', 'Remaining'],
                datasets: [{ data: [66, 34], backgroundColor: ['#1b4332', '#e9ecef'] }]
            },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });
    </script>
</body>
</html>