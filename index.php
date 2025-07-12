<?php
// JSON message to confirm the service is running
echo json_encode(['status' => 'ok', 'message' => 'Tetris Leaderboard API is online']);

// Define the SVG favicon data in a variable
$favicon_svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🧢</text></svg>";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Tetris Leaderboard API by James</title>
        <?php

        // Echo the link tag into the head of the document
        // Using htmlspecialchars to ensure the SVG data is encoded safely
        echo '<link rel="icon" href="data:image/svg+xml,' . htmlspecialchars($favicon_svg, ENT_QUOTES) . '">';
        ?>
</head>
</html>
