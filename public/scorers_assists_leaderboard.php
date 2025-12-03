<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "premier_league_manager");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle tab (scorers or assists)
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'scorers';

//No limit pagination
$view_all = isset($_GET['view_all']) ? true : false;

// Filters
$selected_team = $_GET['team'] ?? '';
$selected_position = $_GET['position'] ?? '';

$whereClauses = [];

if ($active_tab === 'scorers') {
    $whereClauses[] = "p.goals > 0";
} else {
    $whereClauses[] = "p.assist > 0";
}


if (!empty($selected_team)) {
    $whereClauses[] = "t.id = '" . mysqli_real_escape_string($conn, $selected_team) . "'";
}

if (!empty($selected_position)) {
    $whereClauses[] = "p.position = '" . mysqli_real_escape_string($conn, $selected_position) . "'";
}

$whereSQL = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Pagination setup
$items_per_page = 12;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Count total for pagination
$countQuery = "SELECT COUNT(*) as total
               FROM players p
               JOIN teams t ON p.team_id = t.id
               $whereSQL";
$countResult = $conn->query($countQuery);
$total_items = $countResult ? $countResult->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_items / $items_per_page);

// Main leaderboard query
$limit_clause = $view_all ? "" : "LIMIT $items_per_page OFFSET $offset";

$query = ($active_tab === 'scorers') ?
    "SELECT p.id as player_id, p.name, p.image, p.position, p.role, p.jersey_number, t.id as team_id, t.name as team_name, p.goals
     FROM players p
     JOIN teams t ON p.team_id = t.id
     $whereSQL
     ORDER BY p.goals DESC $limit_clause" :

    "SELECT p.id as player_id, p.name, p.image, p.position, p.role, p.jersey_number, t.id as team_id, t.name as team_name, p.assist
     FROM players p
     JOIN teams t ON p.team_id = t.id
     $whereSQL
     ORDER BY p.assist DESC $limit_clause";

$result = $conn->query($query);
$teams = $conn->query("SELECT id, name FROM teams");
$positions = ['goalkeeper', 'defender', 'midfielder', 'forward'];
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
<div class="container py-4">
    <h2 class="text-left mb-4 border-bottom pb-2">Top Scorers & Assists Leaderboard</h2>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'scorers' ? 'active' : '' ?>" href="?tab=scorers">Top Scorers</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'assists' ? 'active' : '' ?>" href="?tab=assists">Top Assists</a>
        </li>
    </ul>

    <form method="get" class="row g-3 mb-4" id="filterForm">
        <input type="hidden" name="tab" value="<?= $active_tab ?>">
        <div class="col-md-4">
            <select name="team" class="form-select">
                <option value="">Filter by Team</option>
                <?php while ($team = $teams->fetch_assoc()): ?>
                    <option value="<?= $team['id'] ?>" <?= $selected_team == $team['id'] ? 'selected' : '' ?>>
                        <?= $team['name'] ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-4">
            <select name="position" class="form-select">
                <option value="">Filter by Position</option>
                <?php foreach ($positions as $pos): ?>
                    <option value="<?= $pos ?>" <?= $selected_position == $pos ? 'selected' : '' ?>><?= $pos ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <a href="?tab=<?= $active_tab ?>" class="btn btn-primary flex-fill">Reset</a>
            <?php if (!$view_all): ?>
                <a href="?tab=<?= $active_tab ?>&team=<?= $selected_team ?>&position=<?= $selected_position ?>&view_all=1" class="btn btn-outline-primary">View All</a>
            <?php endif; ?>
        <a href="index.php" class="btn btn-primary flex-fill">Home</a>
        </div>
    </form>

    <?php if (!$view_all && $total_pages > 1): ?>
    <nav aria-label="Leaderboard pagination">
        <ul class="pagination justify-content-left mt-4">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link"
                       href="?tab=<?= $active_tab ?>&team=<?= $selected_team ?>&position=<?= $selected_position ?>&page=<?= $i ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
    <?php 
    $rank = 1;
    if ($result->num_rows > 0):
        while ($row = $result->fetch_assoc()):
            $photo_img = '';
            if (!empty($row['image'])) {
    $image_data = $row['image'];
    
    // Detect MIME type using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->buffer($image_data);

    // Ensure only valid image types are used
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (in_array($mime_type, $allowed_types)) {
        $base64_img = base64_encode($image_data);
        $photo_img = "data:$mime_type;base64,$base64_img";
    } else {
        $photo_img = "https://via.placeholder.com/60";
    }
    } else {
    $photo_img = "https://via.placeholder.com/60";
    }

    ?>
        <div class="col">
            <div class="card bg-white shadow-sm p-3 h-100">
                <div class="d-flex align-items-center">
                    <img src="<?= $photo_img ?>" alt="Player" class="me-3 rounded-circle" width="60" height="60">
                    <div>
                        <h5 class="mb-0">
                            #<?= $rank++ ?>
                            <a href="player_profile.php?id=<?= $row['player_id'] ?>" class="text-decoration-none fw-bold">
                                <?= htmlspecialchars($row['name']) ?>
                            </a> | <?= $row['jersey_number'] ?>
                        </h5>
                        <small class="text-muted">
                            <?= htmlspecialchars($row['position']) ?> |
                            <a href="team_profile.php?id=<?= $row['team_id'] ?>" class="text-decoration-none">
                                <?= $row['team_name'] ?>
                            </a> | <?= $row['role'] ?>
                        </small>
                        <span class="badge bg-primary mt-1">
                            <?= $active_tab === 'scorers' ? $row['goals'] . " Goals" : $row['assist'] . " Assists" ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    <?php 
        endwhile;
    else: ?>
        <div class="col">
            <div class="alert alert-warning w-100 text-center">No players found with the selected filters.</div>
        </div>
    <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('#filterForm select').forEach(select => {
    select.addEventListener('change', () => {
        document.getElementById('filterForm').submit();
    });
});
</script>
</body>
</html>