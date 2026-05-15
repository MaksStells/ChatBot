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
$action = $body["action"] ?? $_GET["action"] ?? "";

if ($method === "GET" && $action === "list") {
  $result = supabase_request("GET",
    "chats?user_id=eq." . $userId . "&order=created_at.desc&select=id,title,created_at"
  );
  if ($result["code"] === 200) {
    echo json_encode(["success" => true, "chats" => $result["data"]]);
  } else {
    echo json_encode(["success" => false, "chats" => []]);
  }
  exit();
}

// create a new chat
if ($method === "POST" && $action === "create") {
  $title  = trim($body["title"] ?? "New Chat");
  $result = supabase_request("POST", "chats", [
    "user_id" => $userId,
    "title"   => $title
  ]);
  if ($result["code"] === 201 && !empty($result["data"])) {
    echo json_encode(["success" => true, "chat" => $result["data"][0]]);
  } else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to create chat"]);
  }
  exit();
}

// rename a chat
if ($method === "POST" && $action === "rename") {
  $chatId = (int)($body["chat_id"] ?? 0);
  $title  = trim($body["title"] ?? "");
  if (!$chatId || !$title) {
    echo json_encode(["error" => "Missing chat_id or title"]);
    exit();
  }
  $result = supabase_request("PATCH",
    "chats?id=eq." . $chatId . "&user_id=eq." . $userId,
    ["title" => $title]
  );
  echo json_encode(["success" => true]);
  exit();
}

// delete a chat and its messages
if ($method === "DELETE") {
  $chatId = (int)($body["chat_id"] ?? 0);
  if (!$chatId) {
    echo json_encode(["error" => "Missing chat_id"]);
    exit();
  }
  $result = supabase_request("DELETE",
    "chats?id=eq." . $chatId . "&user_id=eq." . $userId,
    null,
    ["Prefer: return=minimal"]
  );
  if ($result["code"] === 200 || $result["code"] === 204) {
    echo json_encode(["success" => true]);
  } else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to delete chat"]);
  }
  exit();
}

http_response_code(405);
echo json_encode(["error" => "Method not allowed"]);