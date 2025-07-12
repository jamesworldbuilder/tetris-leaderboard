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

// Ensure 'player' and 'score' were sent
if (!isset($_POST['player']) || !isset($_POST['score'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Player initials and score are required']);
    exit;
}

// Sanitize the input from the 'player' field
$playerInitials = strtoupper(trim($_POST['player']));
$playerScore = (int)$_POST['score'];

try {
    // Connect to Redis using the URL from Render's environment variables
    $redis = new Predis\Client(getenv('REDIS_URL'));
    $leaderboardKey = 'leaderboard';

    // Get the player's current score from the sorted set
    $oldScore = $redis->zscore($leaderboardKey, $playerInitials);

    // Only update the leaderboard if the new score is higher
    if ($playerScore > (int)$oldScore) {
        // Add or update the player's score in the sorted set
        $redis->zadd($leaderboardKey, [$playerInitials => $playerScore]);
        echo json_encode(['status' => 'updated', 'message' => 'High score updated successfully']);
    } else {
        echo json_encode(['status' => 'not_a_high_score', 'message' => 'Score was not higher']);
    }

} catch (Exception $e) {
    // Handle any connection or command errors
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update leaderboard', 'message' => $e->getMessage()]);
}
?>
