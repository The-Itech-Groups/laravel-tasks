<?php
session_start();
$conn = new mysqli("localhost", "root", "", "premier_league_manager");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$team_id = $_GET['id'] ?? null;
if (!$team_id) {
    echo "Invalid Team ID.";
    exit;
}

$team = $conn->query("SELECT * FROM teams WHERE id = $team_id")->fetch_assoc();
if (!$team) {
    echo "Team not found.";
    exit;
}

$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

$players = $conn->query("SELECT * FROM players WHERE team_id = $team_id");

$recent_matches = $conn->query("SELECT m.*, t1.name AS home_team, t2.name AS away_team, m.stadium
    FROM matches m
    JOIN teams t1 ON m.home_team_id = t1.id
    JOIN teams t2 ON m.away_team_id = t2.id
    WHERE (m.home_team_id = $team_id OR m.away_team_id = $team_id) AND m.played = 1
    ORDER BY m.match_date DESC, m.match_time DESC LIMIT 5");

$goals_stmt = $conn->prepare("SELECT p.name, g.match_id, g.assist_by, g.minute FROM goals g 
    JOIN players p ON g.player_id = p.id 
    WHERE g.match_id = ? AND p.team_id = ? AND deleted = 0");

$avg_goals = ($team['played'] > 0) ? round($team['gf'] / $team['played'], 2) : 0;
$win_rate = ($team['played'] > 0) ? round(($team['win'] / $team['played']) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $team['name']; ?> - Team Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #111; color: #fff; }
        .logo { max-height: 60px; margin-right: 15px; }
        .player-box {
            background-color: #1c1c3c;
            border-radius: 10px;
            padding: 10px;
            margin: 5px;
            width: 220px;
            flex: 0 0 auto;
        }
        .scroll-container {
            display: flex;
            overflow-x: auto;
            padding-bottom: 10px;
        }
        .player-img {
            width: 100%;
            max-height: 120px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 6px;
        }
        .form-inline input { margin-right: 10px; }
    </style>
</head>
<body class="container py-4">
    <h2>
        <img src="data:image/jpeg;base64,<?php echo base64_encode($team['logo']); ?>" class="logo">
        <?php echo $team['name']; ?>
    </h2>
    <hr>

    <h4>? Stats Overview</h4>
    <ul>
        <li><strong>Played:</strong> <?php echo $team['played']; ?></li>
        <li><strong>Wins:</strong> <?php echo $team['win']; ?> |
            <strong>Draws:</strong> <?php echo $team['draw']; ?> |
            <strong>Losses:</strong> <?php echo $team['loss']; ?></li>
        <li><strong>Goals For:</strong> <?php echo $team['gf']; ?> |
            <strong>Against:</strong> <?php echo $team['ga']; ?></li>
        <li><strong>Points:</strong> <?php echo $team['points']; ?></li>
        <li><strong>Average Goals/Match:</strong> <?php echo $avg_goals; ?></li>
        <li><strong>Win Rate:</strong> <?php echo $win_rate; ?>%</li>
    </ul>

    <h4 class="mt-5">??? Squad</h4>
    <div class="scroll-container">
        <?php while ($player = $players->fetch_assoc()) { ?>
            <div class="player-box text-center">
                <?php if (!empty($player['image'])): ?>
                    <img src="data:image/jpeg;base64,<?php echo base64_encode($player['image']); ?>" class="player-img">
                <?php endif; ?>
                <strong>#<?php echo $player['jersey_number']; ?> <?php echo $player['name']; ?></strong><br>
                <small><?php echo $player['position']; ?></small><br>
                ? Goals: <?php echo $player['goals']; ?><br>
                ?? Assists: <?php echo $player['assist']; ?><br>
                ?? <?php echo $player['yellow_cards']; ?> | ?? <?php echo $player['red_cards']; ?>
            </div>
        <?php } ?>
    </div>

    <h4 class="mt-5">?? Goals/Assists Chart</h4>
    <canvas id="chart" height="100"></canvas>
    <?php
    $statData = $conn->query("SELECT name, goals, assist FROM players WHERE team_id = $team_id ORDER BY goals + assist DESC LIMIT 10");
    $names = []; $goals = []; $assists = [];
    while ($row = $statData->fetch_assoc()) {
        $names[] = $row['name'];
        $goals[] = $row['goals'];
        $assists[] = $row['assist'];
    }
    ?>
    <script>
    const ctx = document.getElementById('chart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($names); ?>,
            datasets: [
                { label: 'Goals', data: <?php echo json_encode($goals); ?>, backgroundColor: 'green' },
                { label: 'Assists', data: <?php echo json_encode($assists); ?>, backgroundColor: 'orange' }
            ]
        },
        options: {
            plugins: { legend: { labels: { color: '#fff' } } },
            scales: {
                x: { ticks: { color: '#fff' }},
                y: { ticks: { color: '#fff' }}
            }
        }
    });
    </script>

    <h4 class="mt-5">?? Top Performers</h4>
    <ul>
        <?php
        $top = $conn->query("SELECT name, goals + assist AS total FROM players WHERE team_id = $team_id ORDER BY total DESC LIMIT 5");
        while ($row = $top->fetch_assoc()) {
            echo "<li><strong>{$row['name']}</strong> - {$row['total']} (Goals + Assists)</li>";
        }
        ?>
    </ul>

    <h4 class="mt-5">?? Injury List</h4>
    <ul class="list-group">
        <?php
        $injuries = $conn->query("SELECT p.name, i.injury_description, i.injury_date, i.return_date FROM injuries i 
            JOIN players p ON i.player_id = p.id 
            WHERE p.team_id = $team_id 
            ORDER BY i.injury_date DESC");
        if ($injuries->num_rows == 0) {
            echo "<li class='list-group-item bg-dark text-white'>No current injuries reported.</li>";
        } else {
            while ($i = $injuries->fetch_assoc()) {
                echo "<li class='list-group-item bg-dark text-white'><strong>{$i['name']}</strong>: {$i['injury_description']} (Injured: {$i['injury_date']}, Return: {$i['return_date']})</li>";
            }
        }
        ?>
    </ul>

    <h4 class="mt-5">?? Recent Matches</h4>
    <?php while ($match = $recent_matches->fetch_assoc()) {
        $is_home = ($match['home_team_id'] == $team_id);
        $opponent = $is_home ? $match['away_team'] : $match['home_team'];
        $score = $is_home ? "{$match['home_score']} - {$match['away_score']}" : "{$match['away_score']} - {$match['home_score']}";
        echo "<div class='mb-3'><strong>{$match['match_date']}:</strong> vs <strong>{$opponent}</strong> ({$score}) at <em>{$match['stadium']}</em><br><small>Scorers:</small><ul>";

        $goals_stmt->bind_param("ii", $match['id'], $team_id);
        $goals_stmt->execute();
        $goal_result = $goals_stmt->get_result();
        while ($goal = $goal_result->fetch_assoc()) {
            echo "<li>{$goal['name']} ({$goal['minute']}')" . ($goal['assist_by'] ? " - Assist by {$goal['assist_by']}" : "") . "</li>";
        }
        echo "</ul></div>";
    } ?>

    <h4 class="mt-5">?? Full Match History</h4>
    <form method="get" class="mb-3">
        <input type="hidden" name="id" value="<?php echo $team_id; ?>">
        <label>Select Date Range:</label>
        <input type="date" name="start_date"> to
        <input type="date" name="end_date">
        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
    </form>
    <?php
    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        $start = $_GET['start_date'];
        $end = $_GET['end_date'];
        $filtered = $conn->query("SELECT m.*, t1.name AS home_team, t2.name AS away_team 
            FROM matches m 
            JOIN teams t1 ON m.home_team_id = t1.id 
            JOIN teams t2 ON m.away_team_id = t2.id 
            WHERE (m.home_team_id = $team_id OR m.away_team_id = $team_id) 
            AND m.match_date BETWEEN '$start' AND '$end' 
            ORDER BY m.match_date DESC");

        echo "<ul class='list-group'>";
        while ($m = $filtered->fetch_assoc()) {
            echo "<li class='list-group-item bg-dark text-white'>" .
                "{$m['match_date']}: {$m['home_team']} {$m['home_score']} - {$m['away_score']} {$m['away_team']} at {$m['stadium']}" .
                "</li>";
        }
        echo "</ul>";
    }
    ?>

    <?php if ($is_admin): ?>
    <h4 class="mt-5">?? Rate Player Performance (Admin Only)</h4>
    <form action="submit_rating.php" method="post" class="form-inline">
        <input type="hidden" name="team_id" value="<?php echo $team_id; ?>">
        <label>Player ID: <input type="number" name="player_id" required></label>
        <label>Match ID: <input type="number" name="match_id" required></label>
        <label>Rating (0-10): <input type="number" step="0.1" name="rating" required></label>
        <button type="submit" class="btn btn-success">Submit</button>
    </form>
    <?php endif; ?>

    <a href="index.php" class="btn btn-outline-light mt-4">Back to Home</a>
</body>
</html>