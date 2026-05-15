<?php

if (session_status() === PHP_SESSION_NONE) { // checks if sessions is alrdy active before starting a new one
  session_start();
}

header("Content-Type: application/json");
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit();
}

$userId = $_SESSION["user_id"];
$body = json_decode(file_get_contents("php://input"), true);
$action = $body["action"] ?? $_GET["action"] ?? "";

// xp needed for each lvl
function xpForLevel($level)
{
    return $level * 100; // level 1 = 100xp, level 3 = 300xp etc
}

// available badges
function getAllBadges()
{
    return [
        "first_login" => ["name" => "Welcome!", "icon" => "👋", "desc" => "Logged in for the first time"],
        "streak_3" => ["name" => "On a Roll", "icon" => "🔥", "desc" => "3 day login streak"],
        "streak_7" => ["name" => "Week Warrior", "icon" => "⚡", "desc" => "7 day login streak"],
        "streak_30" => ["name" => "Dedicated", "icon" => "💎", "desc" => "30 day login streak"],
        "first_quiz" => ["name" => "Quiz Taker", "icon" => "🧠", "desc" => "Completed your first quiz"],
        "perfect_quiz" => ["name" => "Perfect Score", "icon" => "⭐", "desc" => "Got 100% on a quiz"],
        "quiz_5" => ["name" => "Quiz Fan", "icon" => "📚", "desc" => "Completed 5 quizzes"],
        "quiz_10" => ["name" => "Quiz Master", "icon" => "🏆", "desc" => "Completed 10 quizzes"],
        "level_5" => ["name" => "Level 5", "icon" => "🚀", "desc" => "Reached level 5"],
        "level_10" => ["name" => "Level 10", "icon" => "🌟", "desc" => "Reached level 10"],
    ];
}

// get or make new stats for current user
function getOrCreateStats($userId)
{
    global $pdo;
    $result = supabase_request("GET", "user_stats?user_id=eq.$userId&select=*");

    if ($result["code"] === 200 && !empty($result["data"])) {
        return $result["data"][0];
    }

    // Create a new stats row for this user
    $create = supabase_request("POST", "user_stats", [
        "user_id" => $userId,
        "xp" => 0,
        "level" => 1,
        "streak" => 0,
        "last_login" => null
    ]);

    return $create["data"][0] ?? ["xp" => 0, "level" => 1, "streak" => 0, "last_login" => null];
}

// give badge
function awardBadge($userId, $badgeKey)
{
    $check = supabase_request("GET", "user_badges?user_id=eq.$userId&badge_key=eq.$badgeKey&select=id");
    if (!empty($check["data"]))
        return false; // already has it

    supabase_request("POST", "user_badges", [
        "user_id" => $userId,
        "badge_key" => $badgeKey
    ]);
    return true;
}

// give xp, and handle levelling up
function addXP($userId, $amount)
{
    $stats = getOrCreateStats($userId);
    $newXP = $stats["xp"] + $amount;
    $newLevel = $stats["level"];
    $badges = [];

    // Check for level up
    while ($newXP >= xpForLevel($newLevel)) {
        $newXP -= xpForLevel($newLevel);
        $newLevel++;

        // Award level badges
        if ($newLevel >= 5 && awardBadge($userId, "level_5"))
            $badges[] = "level_5";
        if ($newLevel >= 10 && awardBadge($userId, "level_10"))
            $badges[] = "level_10";
    }

    supabase_request("PATCH", "user_stats?user_id=eq.$userId", [
        "xp" => $newXP,
        "level" => $newLevel
    ]);

    return ["xp" => $newXP, "level" => $newLevel, "new_badges" => $badges];
}
// ACTIONS
// get stats
if ($action === "get") {
    $stats = getOrCreateStats($userId);
    $result = supabase_request("GET", "user_badges?user_id=eq.$userId&select=badge_key,earned_at&order=earned_at.asc");
    $earned = [];

    if ($result["code"] === 200) {
        foreach ($result["data"] as $row) {
            $earned[] = $row["badge_key"];
        }
    }

    $allBadges = getAllBadges();
    $badgeList = [];

    foreach ($allBadges as $key => $badge) {
        $badgeList[] = [
            "key" => $key,
            "name" => $badge["name"],
            "icon" => $badge["icon"],
            "desc" => $badge["desc"],
            "earned" => in_array($key, $earned)
        ];
    }

    echo json_encode([
        "success" => true,
        "stats" => [
            "xp" => (int) $stats["xp"],
            "level" => (int) $stats["level"],
            "streak" => (int) $stats["streak"],
            "xp_needed" => xpForLevel((int) $stats["level"]),
            "last_login" => $stats["last_login"]
        ],
        "badges" => $badgeList
    ]);
}
// dailly login handler
else if ($action === "daily_login") {
    $stats = getOrCreateStats($userId);
    $today = date("Y-m-d");
    $yesterday = date("Y-m-d", strtotime("-1 day"));
    $newBadges = [];
    $xpGained = 0;

    // Only award once per day
    if ($stats["last_login"] === $today) {
        echo json_encode(["success" => true, "already_claimed" => true, "new_badges" => []]);
        exit();
    }

    // Update streak
    $newStreak = 1;
    if ($stats["last_login"] === $yesterday) {
        $newStreak = (int) $stats["streak"] + 1;
    }

    // Award XP for login
    $xpGained = 10;
    $xpResult = addXP($userId, $xpGained);
    $newBadges = array_merge($newBadges, $xpResult["new_badges"]);

    // Update streak and last login
    supabase_request("PATCH", "user_stats?user_id=eq.$userId", [
        "streak" => $newStreak,
        "last_login" => $today
    ]);

    // Award first login badge
    if (awardBadge($userId, "first_login"))
        $newBadges[] = "first_login";

    // Award streak badges
    if ($newStreak >= 3 && awardBadge($userId, "streak_3"))
        $newBadges[] = "streak_3";
    if ($newStreak >= 7 && awardBadge($userId, "streak_7"))
        $newBadges[] = "streak_7";
    if ($newStreak >= 30 && awardBadge($userId, "streak_30"))
        $newBadges[] = "streak_30";

    // Build badge details for new badges
    $allBadges = getAllBadges();
    $badgeDetails = [];
    foreach ($newBadges as $key) {
        if (isset($allBadges[$key])) {
            $badgeDetails[] = $allBadges[$key];
        }
    }

    echo json_encode([
        "success" => true,
        "xp_gained" => $xpGained,
        "streak" => $newStreak,
        "new_badges" => $badgeDetails
    ]);
}

// completed a quiz
else if ($action === "quiz_complete") {
    $score = intval($body["score"] ?? 0);
    $total = intval($body["total"] ?? 0);
    $perfect = $total > 0 && $score === $total;
    $newBadges = [];
    $xpGained = 0;

    // XP for completing quiz
    $xpGained += 20;

    // Bonus XP for perfect score
    if ($perfect)
        $xpGained += 30;

    $xpResult = addXP($userId, $xpGained);
    $newBadges = array_merge($newBadges, $xpResult["new_badges"]);

    // Badge for first quiz
    if (awardBadge($userId, "first_quiz"))
        $newBadges[] = "first_quiz";

    // Badge for perfect score
    if ($perfect && awardBadge($userId, "perfect_quiz"))
        $newBadges[] = "perfect_quiz";

    // Count total quizzes completed - fetch from stats or use a counter
    $stats = getOrCreateStats($userId);
    $quizCount = ($stats["quiz_count"] ?? 0) + 1;

    supabase_request("PATCH", "user_stats?user_id=eq.$userId", [
        "quiz_count" => $quizCount
    ]);

    if ($quizCount >= 5 && awardBadge($userId, "quiz_5"))
        $newBadges[] = "quiz_5";
    if ($quizCount >= 10 && awardBadge($userId, "quiz_10"))
        $newBadges[] = "quiz_10";

    // Build badge details
    $allBadges = getAllBadges();
    $badgeDetails = [];
    foreach ($newBadges as $key) {
        if (isset($allBadges[$key])) {
            $badgeDetails[] = $allBadges[$key];
        }
    }

    echo json_encode([
        "success" => true,
        "xp_gained" => $xpGained,
        "perfect" => $perfect,
        "new_badges" => $badgeDetails
    ]);
} else {
    echo json_encode(["error" => "Invalid action"]);
}
?>