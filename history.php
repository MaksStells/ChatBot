<?php

session_start();
header("Content-Type: application/json");

require_once "db.php";

if (empty($_SESSION["user_id"])) {
  http_response_code(401);
  echo json_encode(["error" => "Not authenticated"]);
  exit();
}

$userId = $_SESSION["user_id"];
$action = $_GET["action"] ?? ($_POST["action"] ?? "");

if ($_SERVER["REQUEST_METHOD"] === "GET" && $action === "load") {
  $result = supabase_request(
    "GET",
    "chat_history?user_id=eq." . urlencode($userId)
      . "&order=created_at.asc"
      . "&limit=200",
    null
  );

  if ($result["code"] !== 200) {
    http_response_code(502);
    echo json_encode(["error" => "Could not load history"]);
    exit();
  }

  echo json_encode(["success" => true, "messages" => $result["data"] ?? []]);
  exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $body    = json_decode(file_get_contents("php://input"), true);
  $action  = $body["action"] ?? "";
  $role    = $body["role"]   ?? "";
  $content = trim($body["content"] ?? "");

  if ($action === "save") {
    if (!in_array($role, ["user", "bot"]) || $content === "") {
      http_response_code(400);
      echo json_encode(["error" => "Invalid payload"]);
      exit();
    }

    $result = supabase_request("POST", "chat_history", [
      "user_id" => $userId,
      "role"    => $role,
      "content" => $content
    ]);

    if ($result["code"] !== 201) {
      http_response_code(502);
      echo json_encode(["error" => "Could not save message"]);
      exit();
    }

    echo json_encode(["success" => true]);
    exit();
  }

  if ($action === "clear") {
    $result = supabase_request(
      "DELETE",
      "chat_history?user_id=eq." . urlencode($userId),
      null
    );

    if ($result["code"] !== 204 && $result["code"] !== 200) {
      http_response_code(502);
      echo json_encode(["error" => "Could not clear history"]);
      exit();
    }

    echo json_encode(["success" => true]);
    exit();
  }
}

http_response_code(400);
echo json_encode(["error" => "Unknown action"]);
?>
