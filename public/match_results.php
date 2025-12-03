<?php
session_start();
// Database connection
$conn = new mysqli("localhost", "root", "", "premier_league_manager");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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

	<!-- Recent Match Results -->
    <h3>Recent Match Results</h3>
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
</body>
</html>