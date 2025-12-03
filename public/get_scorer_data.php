<?php
$conn = new mysqli("localhost", "root", "", "premier_league_manager");


$teamId = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;

if ($teamId > 0) {
    // Filter for specific team (either home or away)
    $sql = "
        SELECT match_date, SUM(
            CASE 
                WHEN home_team_id = ? THEN home_score 
                WHEN away_team_id = ? THEN away_score 
                ELSE 0 
            END
        ) AS total_goals
        FROM matches
        WHERE home_team_id = ? OR away_team_id = ?
        GROUP BY match_date
        ORDER BY match_date ASC
        LIMIT 10
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiii', $teamId, $teamId, $teamId, $teamId);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // No team filter, show total goals across all matches
    $sql = "
        SELECT match_date, SUM(home_score + away_score) AS total_goals
        FROM matches
        GROUP BY match_date
        ORDER BY match_date ASC
        LIMIT 10
    ";
    $result = $conn->query($sql);
}

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = [
        'date' => $row['match_date'],
        'goals' => (int)$row['total_goals']
    ];
}

$data = array_reverse($data); // Chronological order
header('Content-Type: application/json');
echo json_encode($data);
?>