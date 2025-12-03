<?php
$conn = new mysqli("localhost", "root", "", "premier_league_manager");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


//................
$filter_date = $_GET['filter_date'] ?? '';
$filter_team = $_GET['filter_team'] ?? '';

$whereConditions = [];

if (!empty($filter_date)) {
    $filter_date = $conn->real_escape_string($filter_date);
    $whereConditions[] = "DATE(m.match_date) = '$filter_date'";
}

if (!empty($filter_team)) {
    $filter_team = $conn->real_escape_string($filter_team);
    $whereConditions[] = "(m.home_team_id = '$filter_team' OR m.away_team_id = '$filter_team')";
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = "WHERE " . implode(' AND ', $whereConditions);
}

//.................

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
    $whereClause
    ORDER BY ms.match_id DESC
");


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
// End of fetch goal scorers and assist, grouped by match

// Pagination variables
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$filter_date = $_GET['filter_date'] ?? '';
$filter_team = $_GET['filter_team'] ?? '';

$whereConditions = [];

if (!empty($filter_date)) {
    $filter_date = $conn->real_escape_string($filter_date);
    $whereConditions[] = "DATE(m.match_date) = '$filter_date'";
}

if (!empty($filter_team)) {
    $filter_team = $conn->real_escape_string($filter_team);
    $whereConditions[] = "(m.home_team_id = '$filter_team' OR m.away_team_id = '$filter_team')";
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = "WHERE " . implode(' AND ', $whereConditions);
}

// Get total rows for pagination
$total_result = $conn->query("SELECT COUNT(*) as total FROM match_stats ms JOIN matches m ON ms.match_id = m.id $whereClause");
$total_rows = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Fetch match stats with team names using LIMIT for pagination
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
    $whereClause
    ORDER BY ms.match_id DESC
    LIMIT $limit OFFSET $offset
");

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Top Scorers & Assists</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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
</head>
<body>
<div class="container mt-4 overlay">

<!--Filter by Date and team-->
<?php
$teams_result = $conn->query("SELECT id, name FROM teams ORDER BY name ASC");
?>

<form method="get" class="row mb-4">
    <div class="col-md-4">
        <label class="form-label text-white">Filter by Match Date:</label>
        <input type="date" name="filter_date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label text-white">Filter by Team:</label>
        <select name="filter_team" class="form-select">
            <option value="">All Teams</option>
            <?php while ($team = $teams_result->fetch_assoc()): ?>
                <option value="<?= $team['id'] ?>" <?= $filter_team == $team['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($team['name']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
	<div class="col-md-4 align-self-end d-flex gap-2">
    <button type="submit" class="btn btn-primary w-50">Apply Filters</button>
    <a href="view_match_stats.php" class="btn btn-secondary w-50">Clear Filters</a>
	<a href="index.php" class="btn btn-secondary w-50">Home</a>
    </div>
	
</form>
<!--Filter by Date and Team End-->

<!-- Pagination navigation -->
<?php if ($total_pages > 1): ?>
<nav aria-label="Page navigation" class="mt-4">
    <ul class="pagination justify-content-center">
        <?php if ($page > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?= $page - 1 ?>&filter_date=<?= $filter_date ?>&filter_team=<?= $filter_team ?>">Prev</a>
            </li>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&filter_date=<?= $filter_date ?>&filter_team=<?= $filter_team ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <li class="page-item">
                <a class="page-link" href="?page=<?= $page + 1 ?>&filter_date=<?= $filter_date ?>&filter_team=<?= $filter_team ?>">Next</a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>
<!--End of pagination-->

<!--Toggle button-->
<div class="d-flex justify-content-end mb-3">
    <button id="toggleGoals" class="btn btn-outline-light btn-sm">
        Hide Goal Scorers
    </button>
</div>
<!--End of toggle button-->


<!-- Recent Match Results -->
    <h3 class="mt-5">Match Statistics</h3>
	
	<!--Show a “No Match Stats Found” Message -->
	<?php if ($result->num_rows > 0): ?>
    <div class="table-responsive">
        <table class="table table-dark table-hover table-bordered table-sm text-center align-middle" style="font-size: 0.9rem;">
            <!-- table headers and content here --> 
        </table>
    </div>
    <?php else: ?>
    <div class="alert alert-warning text-center mt-4">
        <strong>No match statistics found</strong> for the selected filter criteria.
    </div>
    <?php endif; ?>

<!--Stats table-->	
<div class="table-responsive">
<table class="table table-dark table-hover table-bordered table-sm text-center align-middle" style="font-size: 0.9rem;">
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
<tr class="bg-secondary text-white goal-row">
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
                                <a href="view_player.php?id=<?= $goal['scorer_id'] ?>" class="text-white text-decoration-underline">
                                    <?= $goal['scorer_name'] ?>
                                </a> (<?= $goal['minute'] ?>')
                                <?php if ($goal['assist_name']): ?>
                                    - Assist:
                                    <a href="view_player.php?id=<?= $goal['assist_id'] ?>" class="text-white text-decoration-underline">
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
                                <a href="view_player.php?id=<?= $goal['scorer_id'] ?>" class="text-white text-decoration-underline">
                                    <?= $goal['scorer_name'] ?>
                                </a> (<?= $goal['minute'] ?>')
                                <?php if ($goal['assist_name']): ?>
                                    - Assist:
                                    <a href="view_player.php?id=<?= $goal['assist_id'] ?>" class="text-white text-decoration-underline">
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

<!--Java Script for toggle button-->
<script>
    const toggleBtn = document.getElementById('toggleGoals');
    let goalsVisible = true;

    toggleBtn.addEventListener('click', () => {
        document.querySelectorAll('.goal-row').forEach(row => {
            row.classList.toggle('d-none');
        });
        goalsVisible = !goalsVisible;
        toggleBtn.textContent = goalsVisible ? 'Hide Goal Scorers' : 'Show Goal Scorers';
    });
</script>

</body>
</html>