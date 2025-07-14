<?php
// Set CORS and JSON content headers
header("Access-Control-Allow-Origin: *");
header("Access-control-allow-methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// Load the Predis library
require 'vendor/autoload.php';

// Handle CORS preflight requests from the browser
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- DIAGNOSTICS ---
$report = [];

// Step 1: Report what data was received from the game
$report['step1_received_data'] = [
    'message' => 'Checking POST data from the game...',
    'post_data' => $_POST
];

if (!isset($_POST['player']) || !isset($_POST['score'])) {
    $report['error'] = 'CRITICAL: The script did not receive a `player` or `score` field.';
    http_response_code(400);
    echo json_encode($report, JSON_PRETTY_PRINT);
    exit;
}

$playerInitials = strtoupper(trim($_POST['player']));
$playerScore = (int)$_POST['score'];

$report['step2_sanitized_data'] = [
    'message' => 'Sanitized the received data.',
    'player_initials' => $playerInitials,
    'player_score' => $playerScore
];

try {
    // Connect to Redis using a persistent connection
    $redis = new Predis\Client(getenv('REDIS_URL'), [
        'parameters' => ['persistent' => 1],
    ]);
    
    $leaderboardKey = 'tetris-leaderboard';
    
    // Step 3: Report what the existing score is
    $oldScore = $redis->zscore($leaderboardKey, $playerInitials);
    $report['step3_check_existing_score'] = [
        'message' => 'Checking for an existing high score for this player...',
        'player_initials' => $playerInitials,
        'existing_score_in_db' => $oldScore === null ? 'No existing score found' : (int)$oldScore
    ];

    // Step 4: Report the result of the comparison
    $isNewHighScore = $playerScore > (int)$oldScore;
    $report['step4_compare_scores'] = [
        'message' => 'Comparing new score to the existing score...',
        'comparison' => "$playerScore > " . (int)$oldScore,
        'is_new_high_score' => $isNewHighScore
    ];

    if ($isNewHighScore) {
        // Step 5: Report an attempt to save the data
        $report['step5_save_new_score'] = [
            'message' => 'New score is higher. Attempting to save to the database...'
        ];
        $redis->zadd($leaderboardKey, [$playerInitials => $playerScore]);
        $report['step5_save_new_score']['result'] = 'Save command sent successfully.';
    } else {
        $report['step5_save_new_score'] = [
            'message' => 'New score is not higher. No database update is needed.'
        ];
    }
    
    $redis->disconnect();

    // Send the full diagnostic report
    echo json_encode($report, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Handle any connection or command errors
    http_response_code(500);
    $report['error'] = 'An exception occurred during the process.';
    $report['error_details'] = [
        'exception_type' => get_class($e),
        'error_message' => $e->getMessage()
    ];
    echo json_encode($report, JSON_PRETTY_PRINT);
}
?>
