<?php
// Set CORS and JSON content headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// Load the Predis library
require 'vendor/autoload.php';

// Handle CORS preflight requests from the browser
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Rate Limiting Configuration ---
const MAX_REQUESTS = 11; // Max requests
const TIME_WINDOW = 60;  // In seconds

try {
    // Connect to Redis using a persistent connection
    $redis = new Predis\Client(getenv('REDIS_URL'), [
        'parameters' => ['persistent' => 1],
    ]);

    // --- Rate Limiting Logic ---
    // Limit database requests
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $rateLimitKey = 'rate_limit:' . $ipAddress;
    $requestCount = $redis->incr($rateLimitKey);
    
    if ($requestCount == 1) {
        $redis->expire($rateLimitKey, TIME_WINDOW);
    }

    if ($requestCount > MAX_REQUESTS) {
        http_response_code(429); // Too Many Requests
        echo json_encode(['error' => 'Too many requests. Please try again later.']);
        $redis->disconnect();
        exit;
    }

    // --- Score Submission Logic ---
    if (!isset($_POST['player']) || !isset($_POST['score'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Player initials and score are required']);
        $redis->disconnect();
        exit;
    }

    $playerInitials = strtoupper(trim($_POST['player']));
    $playerScore = (int)$_POST['score'];
    $leaderboardKey = 'tetris-leaderboard';

    // Get the player's current personal best score
    $oldScore = $redis->zscore($leaderboardKey, $playerInitials);

    // Only update the database if the new score is a new personal best
    if ($playerScore > (int)$oldScore) {
        // Add the new high score to the sorted set
        // If the player is already in the set, this updates their score
        $redis->zadd($leaderboardKey, [$playerInitials => $playerScore]);
        echo json_encode(['status' => 'updated', 'message' => 'New high score saved!']);
    } else {
        echo json_encode(['status' => 'not_a_high_score', 'message' => 'Score was not a new personal best']);
    }

    // Disconnect from the Redis server
    $redis->disconnect();

} catch (Exception $e) {
    // Handle any connection or command errors
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update leaderboard', 'message' => $e->getMessage()]);
}
?>
