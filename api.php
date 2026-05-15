<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
require_once "db.php";

$username = $_SESSION["username"] ?? "Student";
$userId   = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
  http_response_code(200);
  exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode(["error" => "Method not allowed"]);
  exit();
}

$body            = json_decode(file_get_contents("php://input"), true);
$message         = isset($body["message"])         ? trim($body["message"])         : "";
$calendarContext = isset($body["calendarContext"]) ? $body["calendarContext"]        : "";
$chatId          = isset($body["chat_id"])         ? (int)$body["chat_id"]          : 0;
$clientHistory   = isset($body["history"])         ? $body["history"]               : null;

if (empty($message)) {
  http_response_code(400);
  echo json_encode(["error" => "Message is required"]);
  exit();
}

if (strlen($message) > 2000) {
  http_response_code(400);
  echo json_encode(["error" => "Message too long (max 2000 characters)"]);
  exit();
}

// Get the student's first name and profile info from the database
$firstname = $username;
$educationLevel = "";
$studyInterests = "";

if ($userId) {
  $profileResult = supabase_request("GET", "users?id=eq.$userId&select=first_name,education_level,study_interests");
  if ($profileResult["code"] === 200 && !empty($profileResult["data"][0])) {
    $profile = $profileResult["data"][0];
    if (!empty($profile["first_name"]))
      $firstname = $profile["first_name"];
    if (!empty($profile["education_level"]))
      $educationLevel = $profile["education_level"];
    if (!empty($profile["study_interests"]))
      $studyInterests = $profile["study_interests"];
  }
}

$historyMessages = [];
if (is_array($clientHistory) && count($clientHistory) > 0) {
  foreach ($clientHistory as $msg) {
    $role = ($msg["role"] ?? "") === "user" ? "user" : "assistant";
    $historyMessages[] = ["role" => $role, "content" => (string)($msg["content"] ?? "")];
  }
} elseif ($userId && $chatId) {
  $hist = supabase_request("GET",
    "chat_history?chat_id=eq." . $chatId . "&user_id=eq." . $userId
      . "&order=created_at.desc&limit=10&select=role,content"
  );
  if ($hist["code"] === 200 && is_array($hist["data"])) {
    foreach (array_reverse($hist["data"]) as $row) {
      $historyMessages[] = ["role" => $row["role"], "content" => $row["content"]];
    }
  }
}

$groqApiKey = GROQ_API_KEY;

$today     = date("Y-m-d");
$tomorrow  = date("Y-m-d", strtotime("+1 day"));
$nextWeek  = date("Y-m-d", strtotime("+7 days"));
$dayOfWeek = date("l");
$thisYear  = date("Y");
$nextYear  = (int)date("Y") + 1;

// Pre-calculate named days so the LLM never has to
$namedDays = [];
for ($i = 1; $i <= 14; $i++) {
  $ts  = strtotime("+$i days");
  $key = strtolower(date("l", $ts));
  if (!isset($namedDays[$key])) {
    $namedDays[$key] = date("Y-m-d", $ts);
  }
}
$namedDaysStr = "";
foreach ($namedDays as $name => $date) {
  $namedDaysStr .= "next $name = $date, ";
}

$profileContext = "";
if ($educationLevel !== "")
  $profileContext .= "\nEducation level: " . $educationLevel;
if ($studyInterests !== "")
  $profileContext .= "\nStudy interests: " . $studyInterests;

$systemPrompt = <<<PROMPT
You are BrightBot, a friendly, concise and encouraging student assistant for the University of Brighton.
If the student asks for direct answers, provide information to give the answer instead of proving an exact answer.
Student: {$firstname}
Today: {$today} ({$dayOfWeek})

When referencing dates, use {$thisYear} unless the date has already passed, in which case use {$nextYear}.

{$profileContext}

## Calendar Management
Use the provided tools to manage the student's calendar:
- `add_event` — add a new event
- `delete_event` — remove an event by ID
- `get_events` — list all events

Event types: `deadline` (assignments/essays), `exam` (tests/exams), `lecture` (classes), `other`

{$calendarContext}
PROMPT;
$llmMessages = [
    ["role" => "system", "content" => $systemPrompt],
    ...$historyMessages,
    ["role" => "user",   "content" => $message],
];

// Calendar tools the LLM can call
$tools = [
  [
    "type" => "function",
    "function" => [
      "name"        => "add_event",
      "description" => "Add an event to the student calendar.",
      "parameters"  => [
        "type"       => "object",
        "properties" => [
          "title" => ["type" => "string",  "description" => "Event title"],
          "date"  => ["type" => "string",  "description" => "Date as YYYY-MM-DD"],
          "type"  => ["type" => "string",  "enum" => ["deadline","exam","lecture","other"]]
        ],
        "required" => ["title", "date", "type"]
      ]
    ]
  ],
  [
    "type" => "function",
    "function" => [
      "name"        => "delete_event",
      "description" => "Delete an event from the student calendar by ID.",
      "parameters"  => [
        "type"       => "object",
        "properties" => [
          "id" => ["type" => "integer", "description" => "Event ID to delete"]
        ],
        "required" => ["id"]
      ]
    ]
  ],
  [
    "type" => "function",
    "function" => [
      "name"        => "get_events",
      "description" => "Get all calendar events for the student.",
      "parameters"  => ["type" => "object", "properties" => (object)[]]
    ]
  ]
];

$payload = json_encode([
  "model"       => "llama-3.3-70b-versatile",
  "messages"    => $llmMessages,
  "tools"       => $tools,
  "tool_choice" => "auto",
  "temperature" => 0
]);

$ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  "Content-Type: application/json",
  "Authorization: Bearer " . $groqApiKey
]);

$response = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

$isFailedGeneration = false;
if ($httpCode !== 200) {
  $errorMsg = $data["error"]["message"] ?? "";
  if (str_contains($errorMsg, "failed_generation") || str_contains($errorMsg, "Failed to generate")) {
    $isFailedGeneration = true;
  } else {
    http_response_code(502);
    echo json_encode(["error" => "Groq error", "details" => $errorMsg ?: "Unknown error"]);
    exit();
  }
}

$choice = $isFailedGeneration ? [] : ($data["choices"][0] ?? []);
$reply  = "";
$tokensUsed = $data["usage"]["total_tokens"] ?? 0;
error_log("Tokens used: " . $tokensUsed);

// If Groq failed to generate a valid tool call, retry without tools
if ($isFailedGeneration || isset($data["error"]) || ($choice["finish_reason"] ?? "") === "failed_generation") {
  $retryPayload = json_encode([
    "model"       => "llama-3.3-70b-versatile",
    "messages"    => $llmMessages,
    "temperature" => 0
  ]);
  $chR = curl_init("https://api.groq.com/openai/v1/chat/completions");
  curl_setopt($chR, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($chR, CURLOPT_POST, true);
  curl_setopt($chR, CURLOPT_POSTFIELDS, $retryPayload);
  curl_setopt($chR, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "Authorization: Bearer " . $groqApiKey]);
  $retryResp = curl_exec($chR);
  curl_close($chR);
  $retryData  = json_decode($retryResp, true);
  $retryReply = $retryData["choices"][0]["message"]["content"] ?? "Sorry, I could not process that. Please try again.";
  echo json_encode(["reply" => $retryReply, "auto_title" => null]);
  exit();
}

// Handle tool calls if the LLM wants to use a calendar tool
if (isset($choice["message"]["tool_calls"]) && count($choice["message"]["tool_calls"]) > 0) {
  $toolCall  = $choice["message"]["tool_calls"][0];
  $toolName  = $toolCall["function"]["name"];
  $toolArgs  = json_decode($toolCall["function"]["arguments"], true);
  $toolResult = "";

  $allowedTypes = ["deadline", "exam", "lecture", "other"];

  if ($toolName === "add_event") {
    $evTitle = trim($toolArgs["title"] ?? "");
    $evDate  = trim($toolArgs["date"] ?? "");
    $evType  = in_array($toolArgs["type"] ?? "", $allowedTypes) ? $toolArgs["type"] : "other";

    if (!$evTitle || !$evDate) {
      $toolResult = "Cannot add event: title and date are required.";
    } else {
      $evResult = supabase_request("POST", "events", [
        "user_id" => $userId,
        "date"    => $evDate,
        "title"   => $evTitle,
        "type"    => $evType
      ]);
      $toolResult = $evResult["code"] === 201
        ? "Done! Added \"" . $evTitle . "\" (" . $evType . ") on " . $evDate . " to your calendar."
        : "Failed to save event (code " . $evResult["code"] . "): " . json_encode($evResult["data"]);
    }

  } elseif ($toolName === "delete_event") {
    $evId = intval($toolArgs["id"] ?? 0);
    if (!$evId) {
      $toolResult = "Cannot delete: invalid event ID.";
    } else {
      $evResult = supabase_request("DELETE",
        "events?id=eq." . $evId . "&user_id=eq." . $userId,
        null,
        ["Prefer: return=minimal"]
      );
      $toolResult = ($evResult["code"] === 200 || $evResult["code"] === 204)
        ? "Done! The event has been removed from your calendar."
        : "Failed to delete event (code " . $evResult["code"] . ").";
    }

  } elseif ($toolName === "get_events") {
    $evResult = supabase_request("GET",
      "events?user_id=eq." . $userId . "&order=date.asc&select=*"
    );
    if ($evResult["code"] === 200 && is_array($evResult["data"])) {
      $lines = [];
      foreach ($evResult["data"] as $ev) {
        $lines[] = "ID:" . $ev["id"] . " | " . $ev["title"] . " (" . $ev["type"] . ") on " . $ev["date"];
      }
      $toolResult = count($lines) > 0 ? implode("\n", $lines) : "No events found.";
    } else {
      $toolResult = "Failed to fetch events (code " . $evResult["code"] . ").";
    }
  }

  // Send tool result back to LLM for a natural language response
  $followUp = $llmMessages;
  $followUp[] = $choice["message"];
  $followUp[] = [
    "role"        => "tool",
    "tool_call_id" => $toolCall["id"],
    "content"     => $toolResult
  ];

  $payload2 = json_encode([
    "model"    => "llama-3.3-70b-versatile",
    "messages" => $followUp
  ]);

  $ch2 = curl_init("https://api.groq.com/openai/v1/chat/completions");
  curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch2, CURLOPT_POST, true);
  curl_setopt($ch2, CURLOPT_POSTFIELDS, $payload2);
  curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $groqApiKey
  ]);
  $response2 = curl_exec($ch2);
  curl_close($ch2);

  $data2    = json_decode($response2, true);
  $replyRaw = $data2["choices"][0]["message"]["content"] ?? $toolResult;
  $reply    = is_array($replyRaw) ? implode("", array_column($replyRaw, "text")) : (string)$replyRaw;

} else {
  $replyRaw = $choice["message"]["content"] ?? "";
  $reply    = is_array($replyRaw) ? implode("", array_column($replyRaw, "text")) : (string)$replyRaw;
}

// Save messages and auto-title chat after first message
$autoTitle = null;
if ($userId && $chatId && !empty($reply)) {
  supabase_request("POST", "chat_history", [
    "user_id" => $userId,
    "chat_id" => $chatId,
    "role"    => "user",
    "content" => $message
  ]);
  supabase_request("POST", "chat_history", [
    "user_id" => $userId,
    "chat_id" => $chatId,
    "role"    => "assistant",
    "content" => $reply
  ]);

  $countResult = supabase_request("GET",
    "chat_history?chat_id=eq." . $chatId . "&user_id=eq." . $userId . "&select=id"
  );
  if ($countResult["code"] === 200 && count($countResult["data"]) <= 2) {
    $autoTitle = strlen($message) > 40 ? substr($message, 0, 40) . "..." : $message;
    supabase_request("PATCH",
      "chats?id=eq." . $chatId . "&user_id=eq." . $userId,
      ["title" => $autoTitle]
    );
  }
}

// Award random XP between 5 and 30 for sending a message
$xpGained = 0;
if ($userId) {
  $xpGained = rand(5, 30);

  // Get or create user stats row
  $statsResult = supabase_request("GET", "user_stats?user_id=eq.$userId&select=*");
  if (empty($statsResult["data"])) {
    supabase_request("POST", "user_stats", ["user_id" => $userId, "xp" => 0, "level" => 1, "streak" => 0]);
    $stats = ["xp" => 0, "level" => 1];
  } else {
    $stats = $statsResult["data"][0];
  }

  // Calculate new XP and level
  $newXP = (int) $stats["xp"] + $xpGained;
  $newLevel = (int) $stats["level"];
  while ($newXP >= $newLevel * 100) {
    $newXP -= $newLevel * 100;
    $newLevel++;
  }

  supabase_request("PATCH", "user_stats?user_id=eq.$userId", [
    "xp" => $newXP,
    "level" => $newLevel
  ]);
  $reply .= "\n\n+" . $xpGained . " XP! 🌟";
}

echo json_encode(["reply" => $reply, "auto_title" => $autoTitle, "tokens_used" => $tokensUsed, "xp_gained" => $xpGained]);