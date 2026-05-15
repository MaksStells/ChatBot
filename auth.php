<?php
session_start();
header("Content-Type: application/json");
require_once "db.php";

$body = json_decode(file_get_contents("php://input"), true);
$action = $body["action"] ?? $_GET["action"] ?? "";

switch ($action) {

  case "register": // creating an account
    $username = trim($body["username"] ?? ""); 
    $email    = trim($body["email"] ?? ""); 
    $password = $body["password"] ?? "";

    if (!$username || !$email || !$password) { 
      echo json_encode(["error" => "All fields are required"]);
      exit();
    }

    if (strlen($password) < 6) { 
      echo json_encode(["error" => "Password must be at least 6 characters"]);
      exit();
    }

    // Check if username already exists
    $check = supabase_request("GET", "users?username=eq." . urlencode($username) . "&select=id");
    if (!empty($check["data"])) {
      echo json_encode(["error" => "Username already exists"]);
      exit();
    }

    // Check if email already exists
    $checkEmail = supabase_request("GET", "users?email=eq." . urlencode($email) . "&select=id");
    if (!empty($checkEmail["data"])) {
      echo json_encode(["error" => "Email already exists"]);
      exit();
    }

    // Hash password for security
    $hash   = password_hash($password, PASSWORD_DEFAULT);
    // create new user on database
    $result = supabase_request("POST", "users", [
      "username" => $username,
      "email"    => $email,
      "password" => $hash
    ]);

    if ($result["code"] === 201 && !empty($result["data"])) { // if successful, login with provided details
      $user = $result["data"][0];
      $_SESSION["user_id"]    = $user["id"];
      $_SESSION["username"]   = $user["username"];
      $_SESSION["first_name"] = $user["first_name"] ?? "";
      echo json_encode(["success" => true, "username" => $user["username"]]);
    } else {
      echo json_encode(["error" => "Registration failed"]);
    }
    break;

  case "login":
    $username = trim($body["username"] ?? ""); 
    $password = $body["password"] ?? "";
    if (!$username || !$password) {
      echo json_encode(["error" => "All fields are required"]);
      exit();
    }

    $result = supabase_request("GET", "users?username=eq." . urlencode($username) . "&select=*");

    if ($result["code"] === 200 && !empty($result["data"])) { 
      $user = $result["data"][0];

      // Check if password matches the hashed password in the database, then login if so
      if (password_verify($password, $user["password"])) {
        $_SESSION["user_id"]    = $user["id"];
        $_SESSION["username"]   = $user["username"];
        $_SESSION["first_name"] = $user["first_name"] ?? "";
        echo json_encode(["success" => true, "username" => $user["username"]]);
      } else {
        echo json_encode(["error" => "Invalid username or password"]);
      }
    } else {
      echo json_encode(["error" => "Invalid username or password"]);
    }
    break;

  case "logout":
    session_destroy(); // stops session
    echo json_encode(["success" => true]);
    break;

  case "check": // used for checking current user information
    if (isset($_SESSION["user_id"])) {
      echo json_encode(["loggedIn" => true, "username" => $_SESSION["username"]]);
    } else {
      echo json_encode(["loggedIn" => false]);
    }
    break;

  default:
    echo json_encode(["error" => "Invalid action"]);
}
?>