<?php
session_start();
header("Content-Type: application/json");
require "db.php";

if (!isset($_SESSION["user_id"])) {
  http_response_code(401);
  echo json_encode(["error" => "Not logged in"]);
  exit();
}

$userId = $_SESSION["user_id"];
$body   = json_decode(file_get_contents("php://input"), true);
$action = $body["action"] ?? $_GET["action"] ?? "";

switch ($action) {

  case "get": 
    $result = supabase_request("GET", "events?user_id=eq.$userId&order=date.asc&select=*");

    if ($result["code"] === 200) {
      $grouped = [];
      foreach ($result["data"] as $row) {
        $key = $row["date"];
        if (!isset($grouped[$key])) $grouped[$key] = [];
        $grouped[$key][] = [
          "id"    => $row["id"],
          "title" => $row["title"],
          "type"  => $row["type"]
        ];
      }
      echo json_encode(["success" => true, "events" => $grouped]);
    } else {
      echo json_encode(["error" => "Failed to fetch events"]);
    }
    break;

  case "add": 
    $date  = $body["date"] ?? "";
    $title = trim($body["title"] ?? "");
    $type  = $body["type"] ?? "other";

    if (!$date || !$title) {
      echo json_encode(["error" => "Date and title are required"]);
      exit();
    }

    $allowed = ["deadline", "lecture", "exam", "other"];
    if (!in_array($type, $allowed)) $type = "other";

    $result = supabase_request("POST", "events", [
      "user_id" => $userId,
      "date"    => $date,
      "title"   => $title,
      "type"    => $type
    ]);

    if ($result["code"] === 201) {
      echo json_encode(["success" => true, "id" => $result["data"][0]["id"]]);
    } else {
      echo json_encode(["error" => "Failed to add event"]);
    }
    break;

  case "delete": 
    $id = intval($body["id"] ?? 0);

    if (!$id) {
      echo json_encode(["error" => "Event ID required"]);
      exit();
    }

    $result = supabase_request("DELETE", "events?id=eq.$id&user_id=eq.$userId");

    echo json_encode(["success" => true]);
    break;

  default:
    echo json_encode(["error" => "Invalid action"]);
}
?>