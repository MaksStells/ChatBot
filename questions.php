<?php

session_start();
header("Content-Type: application/json");
require_once "db.php";

$body   = json_decode(file_get_contents("php://input"), true);
$action = $body["action"] ?? $_GET["action"] ?? "";

if ($action === "get") {
  $result = supabase_request("GET", "questions?select=*&order=id.asc");

  if ($result["code"] === 200) {
    echo json_encode(["success" => true, "questions" => $result["data"]]);
  } else {
    echo json_encode(["error" => "Failed to load questions"]);
  }
}

else if ($action === "add") {
  if (!isset($_SESSION["user_id"])) { echo json_encode(["error" => "Not logged in"]); exit(); }
  $q       = trim($body["q"] ?? "");
  $a       = $body["a"] ?? [];
  $correct = intval($body["correct"] ?? 0);

  if (!$q || count($a) !== 4) {
    echo json_encode(["error" => "Question and 4 answers are required"]);
    exit();
  }

  $result = supabase_request("POST", "questions", [
    "q"       => $q,
    "a"       => $a,
    "correct" => $correct
  ]);

  if ($result["code"] === 201) {
    echo json_encode(["success" => true, "id" => $result["data"][0]["id"]]);
  } else {
    echo json_encode(["error" => "Failed to add question"]);
  }
}

else if ($action === "delete") {
  if (!isset($_SESSION["user_id"])) { echo json_encode(["error" => "Not logged in"]); exit(); }
  $id = intval($body["id"] ?? 0);

  if (!$id) {
    echo json_encode(["error" => "Question ID required"]);
    exit();
  }

  supabase_request("DELETE", "questions?id=eq.$id");
  echo json_encode(["success" => true]);
}

else {
  echo json_encode(["error" => "Invalid action"]);
}
?>