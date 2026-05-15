<?php

session_start();
header("Content-Type: application/json");
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
  http_response_code(401);
  echo json_encode(["error" => "Not logged in"]);
  exit();
}

$userId = $_SESSION["user_id"];
$body   = json_decode(file_get_contents("php://input"), true);
$action = $body["action"] ?? $_GET["action"] ?? "";

if ($action === "get") {
  $result = supabase_request("GET", "users?id=eq.$userId&select=username,email,first_name,education_level,study_interests,pfp_url");

  if ($result["code"] === 200 && !empty($result["data"])) {
    echo json_encode(["success" => true, "profile" => $result["data"][0]]);
  } else {
    echo json_encode(["error" => "Could not load profile"]);
  }
}

else if ($action === "save") {
  $firstname       = trim($body["first_name"]      ?? "");
  $educationLevel  = trim($body["education_level"] ?? "");
  $studyInterests  = trim($body["study_interests"] ?? "");
  $pfpUrl       = trim($body["pfp_url"]      ?? "");

  $updates = [];
  if ($firstname !== "")      $updates["first_name"]      = $firstname;
  if ($educationLevel !== "") $updates["education_level"] = $educationLevel;
  if ($studyInterests !== "") $updates["study_interests"] = $studyInterests;
  if ($pfpUrl !== "")      $updates["pfp_url"]      = $pfpUrl;

  if (empty($updates)) {
    echo json_encode(["error" => "Nothing to update"]);
    exit();
  }

  $result = supabase_request("PATCH", "users?id=eq.$userId", $updates);

  if ($result["code"] === 200 || $result["code"] === 204) {
    // Update session first name if changed
    if ($firstname !== "") {
      $_SESSION["first_name"] = $firstname;
    }
    echo json_encode(["success" => true]);
  } else {
    echo json_encode(["error" => "Failed to save profile"]);
  }
}

else {
  echo json_encode(["error" => "Invalid action"]);
}
?>