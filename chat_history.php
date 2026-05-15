<?php
session_start();
header("Content-Type: application/json");
require_once "db.php";

$userId = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;

if (!$userId) {
  http_response_code(401);
  echo json_encode(["error" => "Not logged in"]);
  exit();
}

$method = $_SERVER["REQUEST_METHOD"];
$body   = json_decode(file_get_contents("php://input"), true);
$chatId = isset($_GET["chat_id"]) ? (int)$_GET["chat_id"] : (int)($body["chat_id"] ?? 0);

if (!$chatId) {
  echo json_encode(["error" => "Missing chat_id"]);
  exit();
}

if ($method === "GET") {
  $result = supabase_request("GET",
    "chat_history?chat_id=eq." . $chatId . "&user_id=eq." . $userId
      . "&order=created_at.asc&limit=100&select=role,content,created_at"
  );
  if ($result["code"] === 200) {
    echo json_encode(["success" => true, "messages" => $result["data"]]);
  } else {
    echo json_encode(["success" => false, "messages" => []]);
  }
  exit();
}

if ($method === "DELETE") {
  $result = supabase_request("DELETE",
    "chat_history?chat_id=eq." . $chatId . "&user_id=eq." . $userId,
    null,
    ["Prefer: return=minimal"]
  );
  if ($result["code"] === 200 || $result["code"] === 204) {
    echo json_encode(["success" => true]);
  } else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to clear history", "code" => $result["code"]]);
  }
  exit();
}

http_response_code(405);
echo json_encode(["error" => "Method not allowed"]);