<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set("session.cookie_httponly", 1);
session_start();
require_once 'utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(["success" => false, "message" => "Invalid request"], 405);
}



$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    send_json(["success" => false, "message" => "Username and password required"]);
}

$result = register_user($mysqli, $username, $email, $password);

if ($result['success']) {
    $_SESSION['user_id']  = $result['user_id'];
    $_SESSION['username'] = $result['username'];
}
send_json($result);
?>

