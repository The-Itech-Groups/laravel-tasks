<?php
$conn = new mysqli("localhost", "root", "", "premier_league_manager");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : null;

if (!$team_id) {
    $query = "
        SELECT home_score, away_score 
        FROM matches 
        WHERE played = 1 
        ORDER BY match_date DESC, match_time DESC 
        LIMIT 10
    ";
} else {
    $query = "
        SELECT home_team_id, away_team_id, home_score, away_score 
        FROM matches 
        WHERE played = 1 AND (home_team_id = $team_id OR away_team_id = $team_id) 
        ORDER BY match_date DESC, match_time DESC 
        LIMIT 10
    ";
}

$result = $conn->query($query);
$formData = ['W' => 0, 'D' => 0, 'L' => 0];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        if (!$team_id) {
            if ($row['home_score'] == $row['away_score']) {
                $formData['D']++;
            } elseif ($row['home_score'] > $row['away_score']) {
                $formData['W']++;
                $formData['L']++;
            } else {
                $formData['W']++;
                $formData['L']++;
            }
        } else {
            $isHome = ($row['home_team_id'] == $team_id);
            $teamScore = $isHome ? $row['home_score'] : $row['away_score'];
            $oppScore = $isHome ? $row['away_score'] : $row['home_score'];

            if ($teamScore > $oppScore) $formData['W']++;
            elseif ($teamScore == $oppScore) $formData['D']++;
            else $formData['L']++;
        }
    }
}

header('Content-Type: application/json');
echo json_encode($formData);
?>
