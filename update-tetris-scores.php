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
// Maximum number of requests allowed
const MAX_REQUESTS = 10;
// Time window in seconds
const TIME_WINDOW = 60;

try {
    // Connect to Redis using a persistent connection
    $redis = new Predis\Client(getenv('REDIS_URL'), [
        'parameters' => [
            'persistent' => 1,
        ],
    ]);

    // --- Rate Limiting Logic ---
    // Get the user's IP address
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $rateLimitKey = 'rate_limit:' . $ipAddress;

    // Increment the request counter for this IP address
    $requestCount = $redis->incr($rateLimitKey);

    // If this is the first request from this IP in the time window, set an expiration on the key
    if ($requestCount == 1) {
        $redis->expire($rateLimitKey, TIME_WINDOW);
    }

    // If the request count exceeds the limit, reject the request
    if ($requestCount > MAX_REQUESTS) {
        http_response_code(429); // 429 Too Many Requests
        echo json_encode(['error' => 'Too many requests. Please try again later.']);
        $redis->disconnect();
        exit;
    }

    // --- (proceeds if rate limit is not exceeded) ---
    // Ensure 'player' and 'score' were sent
    if (!isset($_POST['player']) || !isset($_POST['score'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Player initials and score are required']);
        $redis->disconnect();
        exit;
    }

    // Sanitize the input from the 'player' field
    $playerInitials = strtoupper(trim($_POST['player']));
    $playerScore = (int)$_POST['score'];

    $leaderboardKey = 'leaderboard';

    // Create a unique entry for every score
    $uniqueMember = $playerInitials . ':' . uniqid();

    // Add the new, unique score to the sorted set
    $redis->zadd($leaderboardKey, [$uniqueMember => $playerScore]);
    
    echo json_encode(['status' => 'success', 'message' => 'Score saved successfully']);

    // Disconnect from the Redis server
    $redis->disconnect();

} catch (Exception $e) {
    // Handle any connection or command errors
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update leaderboard', 'message' => $e->getMessage()]);
}
?>
