<?php

$host = "localhost";
$user = "root";
$password = "";
$db = "althea_database";

$data = mysqli_connect($host, $user, $password, $db);

if ($data === false) {
    die("connection error:");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM user WHERE username='".$username."' AND password='".$password."'";
    $result = mysqli_query($data, $sql);
    $row = mysqli_fetch_array($result);

    if ($row) {
        session_start();
    
        $_SESSION['username'] = $username;
        $_SESSION['usertype'] = $row["usertype"];

        if ($row["usertype"] == "student") {
            header("location: studenthome.php");
        } 
        elseif ($row["usertype"] == "admin") {
            header("location: adminhome.php");
        } 
        else {
            $_SESSION['loginMessage'] = "Invalid user role.";
            header("location: login.php");
        }
    } 
    else {
        session_start();
        $_SESSION['loginMessage'] = "Username or password do not match";
        header("location: login.php");
    }
}
?>