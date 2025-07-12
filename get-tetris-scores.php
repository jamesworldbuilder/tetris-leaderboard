<?php
// Set CORS and JSON content headers
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

// Load the Predis library
require 'vendor/autoload.php';

try {
    // Connect to Redis using the URL from Render's environment variables
    $redis = new Predis\Client(getenv('REDIS_URL'));

    // Get top 3 scores from the 'leaderboard' sorted set
    $scores = $redis->zrevrange('leaderboard', 0, 2, 'withscores');

    $leaderboard = [];
    // Format the raw Redis data into a clean array
    foreach ($scores as $initials => $score) {
        $leaderboard[] = ['player' => $initials, 'score' => (int)$score];
    }

    // Send the formatted data as a JSON response
    echo json_encode($leaderboard);

} catch (Exception $e) {
    // Handle any connection or command errors
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve leaderboard', 'message' => $e->getMessage()]);
}
?>
