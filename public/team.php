<?php
session_start();

// DB connection
$conn = new mysqli("localhost", "root", "", "premier_league_manager");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$team_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($team_id <= 0) {
    echo "Invalid team ID.";
    exit;
}

// Fetch team info
$team = $conn->query("SELECT * FROM teams WHERE id = $team_id")->fetch_assoc();
if (!$team) {
    echo "Team not found.";
    exit;
}

// Fetch players
$players = $conn->query("SELECT * FROM players WHERE team_id = $team_id ORDER BY goals DESC");

// Fetch recent matches (last 5 played)
$recent_matches = $conn->query("SELECT m.match_date, t1.name AS home_team, t2.name AS away_team, m.home_score, m.away_score
                                FROM matches m
                                JOIN teams t1 ON m.home_team_id = t1.id
                                JOIN teams t2 ON m.away_team_id = t2.id
                                WHERE (m.home_team_id = $team_id OR m.away_team_id = $team_id) AND m.played = 1
                                ORDER BY m.match_date DESC LIMIT 5");

// Fetch upcoming fixtures
$upcoming = $conn->query("SELECT m.match_date, t1.name AS home_team, t2.name AS away_team
                          FROM matches m
                          JOIN teams t1 ON m.home_team_id = t1.id
                          JOIN teams t2 ON m.away_team_id = t2.id
                          WHERE (m.home_team_id = $team_id OR m.away_team_id = $team_id) AND m.played = 0
                          ORDER BY m.match_date ASC LIMIT 5");
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo $team['name']; ?> - Team Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #0a0a23;
            color: #fff;
        }
        .card {
            background-color: #1e1e2f;
            border: none;
        }
        .table {
            color: #fff;
        }
        .badge {
            font-size: 1em;
        }
    </style>
</head>
<body>
<div class="container py-5">
    <h1 class="text-center mb-4"><?php echo $team['name']; ?> - Team Profile</h1>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card p-3">
                <h4>Team Information</h4>
                <p><strong>Coach:</strong> <?php echo $team['coach']; ?></p>
                <p><strong>Stadium:</strong> <?php echo $team['stadium']; ?></p>
                <p><strong>Points:</strong> <?php echo $team['points']; ?></p>
                <p><strong>Form:</strong>
                    <?php
                    $form_result = $conn->query("SELECT home_team_id, away_team_id, home_score, away_score
                                                 FROM matches
                                                 WHERE (home_team_id = $team_id OR away_team_id = $team_id) AND played = 1
                                                 ORDER BY match_date DESC LIMIT 5");
                    while ($row = $form_result->fetch_assoc()) {
                        $is_home = ($row['home_team_id'] == $team_id);
                        $result = ($row['home_score'] == $row['away_score']) ? 'D' :
                                  (($is_home && $row['home_score'] > $row['away_score']) || (!$is_home && $row['away_score'] > $row['home_score']) ? 'W' : 'L');
                        echo "<span class='badge bg-" . ($result == 'W' ? 'success' : ($result == 'D' ? 'warning' : 'danger')) . " mx-1'>$result</span>";
                    }
                    ?>
                </p>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card p-3">
                <h4>Upcoming Fixtures</h4>
                <ul class="list-group">
                <?php while ($fix = $upcoming->fetch_assoc()) {
                    echo "<li class='list-group-item bg-dark text-white'>" .
                         "{$fix['match_date']} - {$fix['home_team']} vs {$fix['away_team']}" .
                         "</li>";
                } ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card p-3">
                <h4>Recent Matches</h4>
                <table class="table table-striped">
                    <thead><tr><th>Date</th><th>Match</th><th>Score</th></tr></thead>
                    <tbody>
                    <?php while ($match = $recent_matches->fetch_assoc()) {
                        echo "<tr>
                                <td>{$match['match_date']}</td>
                                <td>{$match['home_team']} vs {$match['away_team']}</td>
                                <td>{$match['home_score']} - {$match['away_score']}</td>
                              </tr>";
                    } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col">
            <div class="card p-3">
                <h4>Squad</h4>
                <table class="table table-hover">
                    <thead><tr><th>Name</th><th>Position</th><th>Goals</th></tr></thead>
                    <tbody>
                    <?php while ($player = $players->fetch_assoc()) {
                        echo "<tr>
                                <td>{$player['name']}</td>
                                <td>{$player['position']}</td>
                                <td>{$player['goals']}</td>
                              </tr>";
                    } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <footer class="text-center text-muted mt-5">
        &copy; <?php echo date('Y'); ?> Premier League Manager - All rights reserved.
    </footer>
</div>
</body>
</html>