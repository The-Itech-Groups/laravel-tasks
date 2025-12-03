<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "premier_league_manager");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Form Chart php
$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : null;

if (!$team_id) {
    // Last 10 matches overall
    $query = "
        SELECT home_score, away_score 
        FROM matches 
        WHERE played = 1 
        ORDER BY match_date DESC, match_time DESC 
        LIMIT 10
    ";
} else {
    // Last 5 matches for specific team
    $query = "
        SELECT home_team_id, away_team_id, home_score, away_score 
        FROM matches 
        WHERE played = 1 AND (home_team_id = $team_id OR away_team_id = $team_id) 
        ORDER BY match_date DESC, match_time DESC 
        LIMIT 5
    ";
}

$result = $conn->query($query);
$formData = ['W' => 0, 'D' => 0, 'L' => 0];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        if (!$team_id) {
            // Overall match outcome (not tied to a team, just counts W/D/L globally)
            if ($row['home_score'] == $row['away_score']) {
                $formData['D']++;
            } elseif ($row['home_score'] > $row['away_score']) {
                $formData['W']++; // Home team wins
                $formData['L']++; // Away team loses
            } else {
                $formData['W']++; // Away team wins
                $formData['L']++; // Home team loses
            }
        } else {
            // Result from the team's perspective
            $isHome = ($row['home_team_id'] == $team_id);
            $teamScore = $isHome ? $row['home_score'] : $row['away_score'];
            $opponentScore = $isHome ? $row['away_score'] : $row['home_score'];

            if ($teamScore > $opponentScore) {
                $formData['W']++;
            } elseif ($teamScore == $opponentScore) {
                $formData['D']++;
            } else {
                $formData['L']++;
            }
        }
    }

    echo "<script>
    const formStats = {
        labels: ['W', 'D', 'L'],
        data: [{$formData['W']}, {$formData['D']}, {$formData['L']}]
    };
    </script>";
} else {
    echo "Query error: " . $conn->error;
}


// Fetch match stats with team names
$result = $conn->query("
    SELECT ms.*, 
           m.home_team_id, 
           m.away_team_id,
           m.match_date,
           t1.name AS home_team, 
           t2.name AS away_team,
           m.home_score, 
           m.away_score
    FROM match_stats ms
    JOIN matches m ON ms.match_id = m.id
    JOIN teams t1 ON m.home_team_id = t1.id
    JOIN teams t2 ON m.away_team_id = t2.id
    ORDER BY ms.match_id DESC LIMIT 10");


// Fetch goal scorers and assists, grouped by match
$goals_query = $conn->query("
    SELECT g.match_id, 
           g.minute,
           p.id AS scorer_id,
           p.name AS scorer_name, 
           a.id AS assist_id,
           a.name AS assist_name,
           t.name AS team_name,
           t.id AS team_id,
           g.own_goal
    FROM goals g
    JOIN players p ON g.player_id = p.id
    LEFT JOIN players a ON g.assist_by = a.id
    JOIN teams t ON g.team_id = t.id
");

$goalData = [];
while ($goal = $goals_query->fetch_assoc()) {
    $matchId = $goal['match_id'];
    $teamId = $goal['team_id'];
    $goalData[$matchId][$teamId][] = $goal;
}

// Fetch goal scorers and assists
$goals_query = $conn->query("
    SELECT g.match_id, 
           g.minute,
           p.id AS scorer_id,
           p.name AS scorer_name, 
           a.id AS assist_id,
           a.name AS assist_name,
           t.name AS team_name,
           t.id AS team_id,
           g.own_goal
    FROM goals g
    JOIN players p ON g.player_id = p.id
    LEFT JOIN players a ON g.assist_by = a.id
    JOIN teams t ON g.team_id = t.id
	ORDER BY g.minute ASC
");

$goalData = [];
while ($goal = $goals_query->fetch_assoc()) {
    $matchId = $goal['match_id'];
    $teamId = $goal['team_id'];
    $goalData[$matchId][$teamId][] = $goal;
}

// Fetch cards grouped by match and team
$cards_query = $conn->query("
    SELECT c.match_id, 
           c.minute, 
           c.card_type, 
           p.name AS player_name, 
           t.id AS team_id,
           t.name AS team_name
    FROM cards c
    JOIN players p ON c.player_id = p.id
    JOIN teams t ON c.team_id = t.id
	ORDER BY c.minute ASC
");

$cardData = [];
while ($card = $cards_query->fetch_assoc()) {
    $matchId = $card['match_id'];
    $teamId = $card['team_id'];
    $cardData[$matchId][$teamId][] = $card;
}



// Calculate total goals and assists
$total_goals = $conn->query("SELECT SUM(home_score+away_score) as sum FROM matches")->fetch_assoc()['sum'] ?: 0;
$total_assists = $conn->query("SELECT SUM(assist) as sum FROM players")->fetch_assoc()['sum'] ?: 0;

// Fetch next fixture for countdown
$next = $conn->query("SELECT match_date, match_time, t1.name AS home, t2.name AS away, t1.logo AS hlogo, t2.logo AS alogo
    FROM matches m
    JOIN teams t1 ON m.home_team_id=t1.id
    JOIN teams t2 ON m.away_team_id=t2.id
    WHERE m.played=0
    ORDER BY m.match_date, m.match_time LIMIT 1")->fetch_assoc();

// --- Insert: pull previous positions from session (if any) ---
$prev_positions = $_SESSION['prev_positions'] ?? [];
$current_positions = [];  // will fill as we loop
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>South Sudan Premier League</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.css"/>
	
    <style>
        :root {
            --primary-color: #1f2937;
            --accent-color: #f59e0b;
            --text-color: #e5e7eb;
            --bg-overlay: rgba(31, 41, 55, 0.9);
            --card-bg: #374151;
            --header-bg: #111827;
        }
        body { background: url('images/football_wallpaper.jpg') no-repeat center center fixed; background-size: cover; color: var(--text-color); }
        .overlay { background-color: var(--bg-overlay); padding: 2rem; border-radius: 1rem; animation: fadeIn 1s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .header-center { text-align: center; animation: fadeInDown 1s; }
        @keyframes fadeInDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .header-center img.logo { width: 80px; margin: 0 1rem; animation: rotateIn 1s; }
        .header-center h1 { display: inline-block; color: var(--accent-color); font-weight: bold; animation: fadeIn 1.5s; }
        .nav-buttons .btn { background-color: var(--primary-color); color: var(--text-color); border: 1px solid var(--accent-color); transition: transform 0.3s; }
        .nav-buttons .btn:hover { background-color: var(--accent-color); color: #000; transform: scale(1.1); }
        table { background-color: var(--card-bg); border-radius: 0.5rem; }
        table th { background-color: var(--header-bg) !important; color: var(--accent-color) !important; text-align: left; }
        .team-cell { display: flex; align-items: center; gap: 0.5rem; }
        .team-logo { width: 30px; height: 30px; object-fit: cover; border-radius: 50%; border: 2px solid var(--accent-color); transition: transform 0.3s; }
        .team-logo:hover { transform: rotate(360deg); }
        .match-table { border-radius: 0.75rem; overflow: hidden; margin-bottom: 2rem; animation: fadeInUp 1s; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .score-cell, .vs-cell { color: var(--accent-color); font-weight: bold; text-align: left; }
        .marquee-score { display: inline-block; white-space: nowrap; overflow: hidden; }
        .marquee-score span { display: inline-block; animation: marquee 5s linear infinite; }
        @keyframes marquee { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }
        .counter { font-weight: bold; }
        h3 { color: var(--accent-color); margin-top: 2rem; margin-bottom: 1rem; animation: fadeInDown 1s; }
        footer { padding: 2rem 0; color: var(--text-color); animation: fadeIn 1s; }
    </style>
	    <style>
        :root {
            --bg-light: #f3f4f6;
            --bg-dark: #1f2937;
            --text-light: #111;
            --text-dark: #e5e7eb;
        }
        body {
            background: var(--bg-dark);
            color: var(--text-dark);
            transition: background 0.3s, color 0.3s;
        }
        :root { --bg-light: #f3f4f6; --text-light: #111;}
        .light-mode { background: var(--bg-light); color: var(--text-light);}
        .nav-buttons .btn { margin:2px; }
        .team-logo { width:30px; height:30px; border-radius:50%; object-fit:cover; }
        .match-table { margin-bottom:2rem; }
        .sortable:hover { cursor:pointer; text-decoration:underline; }
        #countdown { font-size:2rem; font-weight:bold; }
        #searchBox { max-width:270px; margin-bottom:1rem; }
        #playerSpotlight { background: var(--bg-light); color: var(--text-light); padding:1rem; border-radius:0.75rem; }
    </style>
<style>
	@keyframes bounce {
  0%, 100%   { transform: translateY(0); }
  50%        { transform: translateY(-4px); }
}
.movement-icon {
  display: inline-block;
  margin-left: 0.5rem;
  animation: bounce 1.5s ease-in-out infinite;
  font-size: 1rem;
  vertical-align: middle;
}
</style>

<style>
.no-underline {
    text-decoration: none;
}
</style>

</head>
<body>
<div id="mainContainer" class="container mt-0 overlay">
    <div class="header-left mb-0">
        <img src="images/a.jpg" alt="banner" class="banner" style="border-radius:5%; width: 1250px; height: 170px;">
    </div>
	    <div class="header-center mb-0">
        <h1>South Sudan Premier League</h1>
    </div>

	
    <!-- Next Fixture Countdown & Carousel -->
    <div class="row mb-3">
       <div class="col-md-6 text-center animate__animated animate__fadeInUp">
            <img src="images/c.png" alt="banner" class="banner" style="border-radius:5%; width: auto; height: 380px;">
        </div>
		
		<!-- Carousel/Slide show -->
        <div class="col-md-4 animate__animated animate__fadeInUp">
            <div id="fixtureCarousel" class="carousel slide" data-bs-ride="carousel">
              <div class="carousel-inner">
                <?php
                $slides = $conn->query("SELECT m.match_date, m.match_time, t1.logo AS hlogo, t2.logo AS alogo FROM matches m JOIN teams t1 ON m.home_team_id = t1.id JOIN teams t2 ON m.away_team_id = t2.id WHERE m.played = 0 ORDER BY m.match_date ASC LIMIT 3");
                $first=true;
                while($s=$slides->fetch_assoc()):
                ?>
                <div class="carousel-item <?= $first? 'active':'' ?>">
                  <img src="images/slide_bg1.png" class="d-block w-100" alt="...">
                  <div class="carousel-caption d-none d-md-block">
                    <div class="team-cell justify-content-center">
                      <img src="data:image/png;base64,<?= base64_encode($s['hlogo']) ?>" class="team-logo me-3">
                      <span class="text-light fs-4"><?= $s['match_date'] ?> <?= $s['match_time'] ?></span>
                      <img src="data:image/png;base64,<?= base64_encode($s['alogo']) ?>" class="team-logo ms-3">
                    </div>
                  </div>
                </div>
                <?php $first=false; endwhile; ?>
              </div>
              <button class="carousel-control-prev" type="button" data-bs-target="#fixtureCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon"></span>
              </button>
              <button class="carousel-control-next" type="button" data-bs-target="#fixtureCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon"></span>
              </button>
            </div>
        </div>
		
	<!--Menu-->	
    </div>
	    <div class="nav-links text-left mb-3">
        <a href="match_results.php" class="no-underline">📅 Results</a>
        <a href="view_match_stats.php" class="no-underline">📊 Stats</a>
        <a href="fixtures.php" class="no-underline">📋 Fixtures</a>
        <a href="scorers_assists_Leaderboard.php" class="no-underline">⚽ Leaderboards</a>
        <a href="players.php" class="no-underline">👥 Players</a>
	    <a href="teams.php" class="no-underline">🏆 Teams</a>
		
    <label id="modeToggle" class="no-underline"><img src="images/ssfa.jpg" alt="banner" class="banner" style="border-radius:5%; width: 17px; height: 17px;"> Toggle light/dark</label>   
    </div> 	
	<hr class="border-light">

	<!-- Recent Match Results -->
    <h3>📅 Recent Match Results</h3>
    <div class="table-responsive match-table">
        <table class="table table-striped table-borderless">
            <thead><tr><th>Date & Time</th><th>Home</th><th>Score</th><th>Away</th><th>Referee</th></tr></thead>
            <tbody>
            <?php
            $fx = $conn->query("SELECT f.match_date,f.match_time,f.home_score,f.away_score,o.name AS referee,t1.name home,t2.name away,t1.logo hlogo,t2.logo alogo FROM matches f JOIN teams t1 ON f.home_team_id=t1.id JOIN teams t2 ON f.away_team_id=t2.id LEFT JOIN officials o ON f.referee = o.id WHERE f.played=1 ORDER BY f.match_date DESC,f.match_time DESC LIMIT 10");
            while ($r = $fx->fetch_assoc()) {
                echo "<tr>
                    <td>{$r['match_date']} {$r['match_time']}</td>
                    <td><div class='team-cell'><img src='data:image/png;base64,".base64_encode($r['hlogo'])."' class='team-logo'><span>{$r['home']}</span></div></td>
                    <td class='score-cell marquee-score'><span>{$r['home_score']} - {$r['away_score']}</span></td>
                    <td><div class='team-cell'><span>{$r['away']}</span><img src='data:image/png;base64,".base64_encode($r['alogo'])."' class='team-logo'></div></td>
					<td>{$r['referee']}</td>
                </tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
	



<!-- Recent Match Results -->
<h3 class="mt-5">Recent Match Statistics</h3>
	
<!--Toggle button-->
<button id="toggleGoals" class="btn btn-outline-light btn-sm">
        Hide Goal Scorers
</button>

<!--Stats table-->	
<div class="table-responsive match-table">
<table class="table table-striped table-borderless text-left align-middle" style="font-size: 0.9rem;">
    <thead>
    <tr>
        <th class="text-start" style="min-width: 160px;">Match</th>
        <th>Possession</th>
        <th>Goals</th>
        <th>Shots On</th>
        <th>Shots Off</th>
        <th>Passes</th>
        <th>Fouls</th>
        <th>Corners</th>
        <th>Offsides</th>
        <th>Yellow</th>
        <th>Red</th>
    </tr>
    </thead>
    <tbody>
    <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td class="text-start"><?= $row['home_team'] . ' vs ' . $row['away_team'] ?></td>
            <td><?= $row['home_possession'] ?? '-' ?> - <?= $row['away_possession'] ?? '-' ?></td>
		    <td><?= $row['home_score'] ?? '-' ?> - <?= $row['away_score'] ?? '-' ?></td>
            <td><?= $row['home_shots_on'] ?? '-' ?> - <?= $row['away_shots_on'] ?? '-' ?></td>
            <td><?= $row['home_shots_off'] ?? '-' ?> - <?= $row['away_shots_off'] ?? '-' ?></td>
            <td><?= $row['home_passes'] ?? '-' ?> - <?= $row['away_passes'] ?? '-' ?></td>
            <td><?= $row['home_fouls'] ?? '-' ?> - <?= $row['away_fouls'] ?? '-' ?></td>
            <td><?= $row['home_corners'] ?? '-' ?> - <?= $row['away_corners'] ?? '-' ?></td>
            <td><?= $row['home_offsides'] ?? '-' ?> - <?= $row['away_offsides'] ?? '-' ?></td>
            <td><?= $row['home_yellow'] ?? '-' ?> - <?= $row['away_yellow'] ?? '-' ?></td>
            <td><?= $row['home_red'] ?? '-' ?> - <?= $row['away_red'] ?? '-' ?></td>
        </tr>
		
<?php if (!empty($goalData[$row['match_id']])): ?>
<tr class="bg-secondary text-center text-white goal-row d-none">
    <td colspan="11">
        <strong>Goal Scorers:</strong><br>
        <div class="row">
            <!-- Home Team Scorers -->
            <div class="col-md-6">
                <strong><?= $row['home_team'] ?>:</strong>
                <ul class="mb-0">
                    <?php foreach ($goalData[$row['match_id']][$row['home_team_id']] ?? [] as $goal): ?>
                        <li>
                            <?php if ($goal['own_goal']): ?>
                                <a href="view_player.php?id=<?= $goal['scorer_id'] ?>" class="text-danger text-decoration-underline">
                                    <?= $goal['scorer_name'] ?>
                                </a>
                                <em>(Own Goal)</em> - <?= $goal['team_name'] ?> (<?= $goal['minute'] ?>')
                            <?php else: ?>
                                <a href="view_player.php?id=<?= $goal['scorer_id'] ?>" class="text-black text-decoration-underline">
                                    <?= $goal['scorer_name'] ?>
                                </a> (<?= $goal['minute'] ?>')
                                <?php if ($goal['assist_name']): ?>
                                    - Assist:
                                    <a href="view_player.php?id=<?= $goal['assist_id'] ?>" class="text-blue text-decoration-underline">
                                        <?= $goal['assist_name'] ?>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Home Team Cards -->
                <?php if (!empty($cardData[$row['match_id']][$row['home_team_id']])): ?>
                    <strong>Cards:</strong>
                    <ul class="mb-0">
                        <?php foreach ($cardData[$row['match_id']][$row['home_team_id']] as $card): ?>
                            <li>
                                <?= htmlspecialchars($card['player_name']) ?> -
                                <?php if ($card['card_type'] === 'yellow'): ?> <?php elseif ($card['card_type'] === 'red'): ?> <?php endif; ?>
                                <?= ucfirst($card['card_type']) ?> Card (<?= $card['minute'] ?>')
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Away Team Scorers -->
            <div class="col-md-6">
                <strong><?= $row['away_team'] ?>:</strong>
                <ul class="mb-0">
                    <?php foreach ($goalData[$row['match_id']][$row['away_team_id']] ?? [] as $goal): ?>
                        <li>
                            <?php if ($goal['own_goal']): ?>
                                <a href="view_player.php?id=<?= $goal['scorer_id'] ?>" class="text-danger text-decoration-underline">
                                    <?= $goal['scorer_name'] ?>
                                </a>
                                <em>(Own Goal)</em> - <?= $goal['team_name'] ?> (<?= $goal['minute'] ?>')
                            <?php else: ?>
                                <a href="view_player.php?id=<?= $goal['scorer_id'] ?>" class="text-black text-decoration-underline">
                                    <?= $goal['scorer_name'] ?>
                                </a> (<?= $goal['minute'] ?>')
                                <?php if ($goal['assist_name']): ?>
                                    - Assist:
                                    <a href="view_player.php?id=<?= $goal['assist_id'] ?>" class="text-blue text-decoration-underline">
                                        <?= $goal['assist_name'] ?>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Away Team Cards -->
                <?php if (!empty($cardData[$row['match_id']][$row['away_team_id']])): ?>
                    <strong>Cards:</strong>
                    <ul class="mb-0">
                        <?php foreach ($cardData[$row['match_id']][$row['away_team_id']] as $card): ?>
                            <li>
                                <?= htmlspecialchars($card['player_name']) ?> -
                                <?php if ($card['card_type'] === 'yellow'): ?> <?php elseif ($card['card_type'] === 'red'): ?> <?php endif; ?>
                                <?= ucfirst($card['card_type']) ?> Card (<?= $card['minute'] ?>')
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </td>
</tr>
<?php endif; ?>
	
    <?php endwhile; ?>
    </tbody>
</table>
</div>
	
	<!-- Player of the Week Spotlight -->
    <div class="mb-4 animate__animated animate__zoomIn">
    <h4>Player of the Week</h4>
    <?php
    $pow = $conn->query("
        SELECT p.*, t.name AS team
        FROM players p
        JOIN teams t ON p.team_id = t.id
        ORDER BY RAND()
        LIMIT 1
    ")->fetch_assoc();
    ?>

    <div class="d-flex align-items-center">
        <img
            src="<?= !empty($pow['image'])
                ? 'data:image/jpeg;base64,'.base64_encode($pow['image'])
                : 'images/default_player.png' ?>"
            class="team-logo me-3"
            style="width:60px;height:60px;"
            alt="Player Photo"
        >
        <div>
            <h5 class="mb-0"><?= htmlspecialchars($pow['name']) ?> (<?= htmlspecialchars($pow['team']) ?>)</h5>
            <small>Goals: <?= intval($pow['goals']) ?> | Assists: <?= intval($pow['assist']) ?></small>
        </div>
    </div>
</div>
	
<!-- Search & Sort -->
    <input type="text" id="searchBox" class="form-control" placeholder="Search team...">
    <hr class="border-light">
	
    <!-- League Standings -->
    <h3>🏆 League Standings</h3>
<div class="table-responsive match-table">
        <table class="table table-striped table-borderless" id="standings">
            <thead>
                <tr>
                    <th class="sortable" onclick="sortTable(0)">#</th><th class="sortable" onclick="sortTable(1)">Team</th>
                    <th class="sortable" onclick="sortTable(2)">Pl</th><th onclick="sortTable(3)" class="sortable">W</th>
                    <th class="sortable" onclick="sortTable(4)">D</th><th class="sortable" onclick="sortTable(5)">L</th>
                    <th class="sortable" onclick="sortTable(6)">GF</th><th class="sortable" onclick="sortTable(7)">GA</th>
                    <th class="sortable" onclick="sortTable(8)">GD</th><th class="sortable" onclick="sortTable(9)">Pts</th>
                </tr>
            </thead>
            <tbody id="standingsBody">
			
			<?php
            // Fetch current standings
            $teams = $conn->query("SELECT * FROM teams ORDER BY points DESC, (gf - ga) DESC");
            $pos = 1;
            while ($r = $teams->fetch_assoc()) {
                // Track this team's new position
                $current_positions[$r['id']] = $pos;

                // figure out movement
                if (isset($prev_positions[$r['id']])) {
                    $old = $prev_positions[$r['id']];
                    if ($pos < $old) {
                        $move = ' <span style="color:#0f0;">▲</span>';
                    } elseif ($pos > $old) {
                        $move = ' <span style="color:#f00;">▼</span>';
                    } else {
                        $move = ' <span style="color:#999;">—</span>';
                    }
                } else {
                    // no prior data
                    $move = '';
                }
				
                // team logo
                $logo = !empty($r['logo'])
                    ? '<img src="data:image/png;base64,' . base64_encode($r['logo']) . '" class="team-logo">'
                    : '';

                echo "<tr>
                    <td>{$pos}</td>
                    <td>
                      <div class='team-cell'>
                        {$logo}
                        <span>{$r['name']}</span>{$move}
                      </div>
                    </td>
                    <td>{$r['played']}</td>
                    <td>{$r['win']}</td>
                    <td>{$r['draw']}</td>
                    <td>{$r['loss']}</td>
                    <td>{$r['gf']}</td>
                    <td>{$r['ga']}</td>
                    <td>".($r['gf'] - $r['ga'])."</td>
                    <td>{$r['points']}</td>
                </tr>";
                $pos++;
            }

            // Save current positions back to session for next load
            $_SESSION['prev_positions'] = $current_positions;
            ?>
						
            </tbody>
        </table>
    </div>
	<hr class="border-light">

    <!-- Upcoming Fixtures -->
    <h3>📅 Upcoming Matches</h3>
    <div class="table-responsive match-table">
        <table class="table table-striped table-borderless">
            <thead><tr><th>Date & Time</th><th>Home</th><th>VS</th><th>Away</th><th>Stadium</th><th>Referee</th></tr></thead>
            <tbody>
            <?php
            $ux = $conn->query("SELECT 
        f.match_date,
        f.match_time,
        f.stadium,
        o.name AS referee,
        t1.name AS home,
        t2.name AS away,
        t1.logo AS hlogo,
        t2.logo AS alogo
    FROM matches f
    JOIN teams t1 ON f.home_team_id = t1.id
    JOIN teams t2 ON f.away_team_id = t2.id
    LEFT JOIN officials o ON f.referee = o.id
    WHERE f.played = 0
    ORDER BY f.match_date, f.match_time
    LIMIT 10");
            while ($r = $ux->fetch_assoc()) {
                echo "<tr>
                    <td>{$r['match_date']} {$r['match_time']}</td>
                    <td><div class='team-cell'><img src='data:image/png;base64,".base64_encode($r['hlogo'])."' class='team-logo'><span>{$r['home']}</span></div></td>
                    <td class='vs-cell'>vs</td>
                    <td><div class='team-cell'><span>{$r['away']}</span><img src='data:image/png;base64,".base64_encode($r['alogo'])."' class='team-logo'></div></td>
                    <td>{$r['stadium']}</td>
					<td>{$r['referee']}</td>
                </tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
	<hr class="border-light">
	
    <!-- Top Scorers -->
    <h3>⚽ Top Scorers</h3>
    <div class="table-responsive match-table">
        <table class="table table-striped table-borderless">
            <thead><tr><th>#</th><th>Player</th><th>Team</th><th>Goals</th></tr></thead>
            <tbody>
            <?php
            $rs = $conn->query("SELECT p.image,p.id,p.name,t.name team,p.goals FROM players p JOIN teams t ON p.team_id=t.id WHERE p.goals>0 ORDER BY p.goals DESC,p.name LIMIT 5"); $rk=1;
            while ($p = $rs->fetch_assoc()) {
                echo "<tr>
                    <td class='counter'>{$rk}</td>
                    <td><div class='team-cell'><img src='" . (!empty($p['image']) ? "data:image/jpeg;base64," .base64_encode($p['image']) : "images/default_player.png") . "' class='team-logo'><span>{$p['name']}</span></div></td>
                    <td>{$p['team']}</td>
                    <td class='score-cell'>{$p['goals']}</td>
                </tr>";
                $rk++;
            }
            ?>
            </tbody>
        </table>
    </div>
    <!-- Top Assists -->
    <h3>🎯 Most Assists</h3>
    <div class="table-responsive match-table">
        <table class="table table-striped table-borderless">
            <thead><tr><th>#</th><th>Player</th><th>Team</th><th>Assists</th></tr></thead>
            <tbody>
            <?php
            $rs2 = $conn->query("SELECT p.image,p.id,p.name,t.name team,p.assist
                FROM players p JOIN teams t ON p.team_id=t.id
                WHERE p.assist>0 ORDER BY p.assist DESC,p.name LIMIT 5");
            $rk2 = 1;
            while ($p = $rs2->fetch_assoc()) {
                echo "<tr>
                    <td>{$rk2}</td>
                    <td><div class='team-cell'><img src='" . (!empty($p['image']) ? "data:image/jpeg;base64," . base64_encode($p['image']) : "images/default_player.png") . "' class='team-logo'><span>{$p['name']}</span></div></td>
                    <td>{$p['team']}</td>
                    <td class='score-cell'>{$p['assist']}</td>
                </tr>";
                $rk2++;
            }
            ?>
            </tbody>
        </table>
    </div>
	
	<!--Score_Assist Leaderboard-->
	<div class="topscorers-section">
    <?php include('includes/include_scorers_assists_leaderboard.php'); ?>
    </div>
	<hr class="border-light">
	
    <!-- Animated counters -->
    <div class="row text-center my-4">
        <div class="col animate__animated animate__fadeInUp animate__delay-1s">
            <h5>Total Assists</h5><div class="counter" id="assistCounter"><?= $total_assists ?></div>
        </div>
		<div class="col animate__animated animate__fadeInUp animate__delay-1s">
            <h5>Total Goals</h5><div class="counter" id="goalCounter"><?= $total_goals ?></div>
        </div>
    </div>
	
	<!-- Spinner Animation for charts -->
	<div id="spinner" style="display:none; text-align:center;">
    <img src="spinner.gif" alt="Loading..." width="40" />
    </div>

	<!-- Placeholder for charts -->
    <div class="row">
	
	<form method="GET">
    <label for="team">Teams Form:</label>
       <select id="teamSelect">
       <option value="">Last 10 Matches Overall</option>
         <?php
         $teams = $conn->query("SELECT id, name FROM teams ORDER BY name");
         while ($team = $teams->fetch_assoc()) {
         echo "<option value='{$team['id']}'>{$team['name']}</option>";
         }
        ?>
       </select>
    <!--<canvas id="scorerChart" width="400" height="200"></canvas>-->
    </form>
	
    <div class="col-md-6 mb-4"><canvas id="formChart"></canvas></div>
    <div class="col-md-6 mb-4"><canvas id="scorerChart"></canvas></div>
    </div>
	<hr class="border-light">
	
    <!-- Social Feed -->
    <h3>?? Latest Posts</h3>
    <a class="twitter-timeline" data-height="400" href="https://twitter.com/YourLeagueHandle?ref_src=twsrc%5Etfw">Posts by Official League</a>
    <!--Footer-->
    <footer class="text-center mt-5">
	      <div class="col-md-6 text-center animate__animated animate__fadeInUp">
            <img src="images/ssfa.jpg" alt="banner" class="banner" style="border-radius:5%; width: 40px; height: 40px;">
        </div>
        &copy; <?= date('Y') ?> South Sudan Premier League Manager by THE ITECH GROUPS - Inspired by EPL.
    </footer>
</div><!-- /#mainContainer -->

<!-- Bootstrap JS & Plugins (moved outside of #mainContainer) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
<!--Java Script for match Stats toggle button-->
<script>
    const toggleBtn = document.getElementById('toggleGoals');
    let goalsVisible = false; // starts hidden

    toggleBtn.addEventListener('click', () => {
        document.querySelectorAll('.goal-row').forEach(row => {
            row.classList.toggle('d-none');
        });
        goalsVisible = !goalsVisible;
        toggleBtn.textContent = goalsVisible ? 'Hide Goal Scorers' : 'Show Goal Scorers';
    });

    // Optional: set correct label on page load
    toggleBtn.textContent = 'Show Goal Scorers';
</script>

<script>
// Dark/light mode toggle
    document.getElementById('modeToggle').onclick = () =>
    document.getElementById('mainContainer').classList.toggle('light-mode');

// Countdown
<?php if($next): ?>
let target = new Date('<?= $next['match_date'] ?> <?= $next['match_time'] ?>').getTime();
setInterval(()=>{
  let now=Date.now(),d=target-now;
  if(d<0) return document.getElementById('countdown').innerText='00:00:00';
  let h=Math.floor(d/3600000),m=Math.floor((d%3600000)/60000),s=Math.floor((d%60000)/1000);
  document.getElementById('countdown').innerText= h+":"+m.toString().padStart(2,'0')+":"+s.toString().padStart(2,'0');
},1000);
<?php endif; ?>

// Sortable table
function sortTable(col) {
  let tbl=document.getElementById('standings'),tb=tbl.tBodies[0];
  Array.from(tb.rows).sort((a,b)=>isNaN(a.cells[col].innerText)?a.cells[col].innerText.localeCompare(b.cells[col].innerText):a.cells[col].innerText-b.cells[col].innerText)
    .forEach(r=>tb.appendChild(r));
}

// Search filter
document.getElementById('searchBox').addEventListener('input',e=>{
  let term=e.target.value.toLowerCase();
  document.querySelectorAll('#standingsBody tr').forEach(row=>{
    row.style.display = row.innerText.toLowerCase().includes(term)? '' : 'none';
  });
});

// Animated counters
function animateCounter(id) {
  let el = document.getElementById(id),start=0,end=parseInt(el.innerText),duration=2000,step=end/ (duration/16);
  function upd(){ start+=step; if(start<end){ el.innerText=Math.floor(start); requestAnimationFrame(upd);} else el.innerText=end;}
  upd();
}
animateCounter('goalCounter'); animateCounter('assistCounter');

// Sample Chart.js usage for mini-charts
let ctx1 = document.getElementById('formChart'), 
    ctx2 = document.getElementById('scorerChart');

</script>

<script>
const scorerCtx = document.getElementById('scorerChart').getContext('2d');
let scorerChart = new Chart(scorerCtx, {
    type: 'line', // or 'bar'
    data: {
        labels: [],
        datasets: [{
            label: 'Total Goals per Match Day',
            backgroundColor: 'rgba(0, 123, 255, 0.5)',
            borderColor: 'blue',
            data: [],
            fill: true,
            tension: 0.4,
            pointBackgroundColor: 'blue'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Goals in Last 10 Matches'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1 }
            }
        }
    }
});

// Load chart data
function loadScorerChart() {
    fetch('get_scorer_data.php')
        .then(response => response.json())
        .then(data => {
            scorerChart.data.labels = data.map(item => item.date);
            scorerChart.data.datasets[0].data = data.map(item => item.goals);
            scorerChart.update();
        })
        .catch(error => {
            console.error("Error loading scorer chart:", error);
        });
}

// Load chart on page load
loadScorerChart();
</script>

<script>
// (Form Chart) JavaScript for AJAX + Updating Chart.js
const ctx = document.getElementById('formChart').getContext('2d');
let formChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['W', 'D', 'L'],
        datasets: [{
            label: 'Form',
            backgroundColor: ['green', 'gold', 'red'],
            data: [0, 0, 0]
        }]
    },
    options: {
        responsive: true,
        animation: {
            duration: 1000,
            easing: 'easeOutBounce'
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

/*
// Spinner function (disabled)
function showSpinner(show = true) {
    document.getElementById('spinner').style.display = show ? 'block' : 'none';
}
*/
//Update form chart upon dropdwon selection
function updateFormChart(teamId = '') {
    // showSpinner(true);

    fetch(`get_form_data.php?team_id=${teamId}`)
        .then(response => response.json())
        .then(data => {
            formChart.data.datasets[0].data = [data.W, data.D, data.L];
            formChart.update();
            // showSpinner(false);
        })
        .catch(error => {
            console.error("Error loading form chart:", error);
            // showSpinner(false);
        });
}
//Update scorer chart upon dropdwon selection
function updateScorerChart(teamId = '') {
    fetch(`get_scorer_data.php?team_id=${teamId}`)
        .then(response => response.json())
        .then(data => {
            scorerChart.data.labels = data.map(item => item.date);
            scorerChart.data.datasets[0].data = data.map(item => item.goals);
            scorerChart.update();
        })
        .catch(error => {
            console.error("Error loading scorer chart:", error);
        });
}

// On dropdown change (Formchart)
document.getElementById('teamSelect').addEventListener('change', function () {
    updateFormChart(this.value);
});
//On dropdown change (Scorerchart)
document.getElementById('teamSelect').addEventListener('change', function () {
    const teamId = this.value;
    updateFormChart(teamId);
    updateScorerChart(teamId);
});

// Load default on page load
updateFormChart();      // Default (all teams)
updateScorerChart();    // Default (all teams)
</script>

<script>
document.addEventListener("DOMContentLoaded", function(){
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.forEach(function (el) {
    new bootstrap.Tooltip(el);
  });
});
</script>

</body>
</html>