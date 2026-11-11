<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Login Form</title>

    <link rel="stylesheet" type="text/css" href="style.css?v=1">

    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
     
    <!-- Optional theme -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">
     
    <!-- Latest compiled and minified JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
</head>
<body class="body_deg">
     
    <div class="overlay_container">
        <div class="main_container">
            <div class="brand_section">
                <img class="logo_img" src="logo.jpg" alt="logo.jpg">
                <h1 class="brand_title">Kolehiyo Ng Subic</h1>
                <p class="brand_subtitle">CENTRALIZED SUBJECT PORTAL</p>
            </div>
    
            <div class="login_form">
                <h2 class="title_deg">Login Form</h2>

                <div style="min-height: 30px; margin-bottom: 15px;">
                    <?php 
                    error_reporting(0);
                    session_start();

                    if (isset($_SESSION['loginMessage']) && !empty($_SESSION['loginMessage'])) {
                        echo '<div style="background-color: rgba(217, 83, 79, 0.2); border: 1px solid #d9534f; padding: 8px; border-radius: 8px; margin-bottom: 15px;">';
                        echo '<p style="color: #ff6b6b; font-weight: bold; font-size: 13px;">' . $_SESSION['loginMessage'] . '</p>';
                        unset($_SESSION['loginMessage']); 
                    }
                    ?>
                </div>

     
                <form action="login_check.php" method="POST">
                    <div class="input_deg">
                        <input type="text" name="username" placeholder="Username" required autocomplete="off">
                    </div>
                    <div class="input_deg">
                        <input type="password" name="password" placeholder="Password" required>
                    </div>
                    <div>
                        <input class="btn btn-primary btn_login" type="submit" name="submit" value="LOG IN">
                    </div>
                </form>
     
                <div class="form_links">
                    <a href="#">FORGOT PASSWORD?</a>
                    <a href="#">FREQUENTLY ASKED QUESTIONS</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>