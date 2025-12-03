<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "premier_league_manager");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch next fixture for countdown
$next = $conn->query("SELECT match_date, match_time, t1.name AS home, t2.name AS away, t1.logo AS hlogo, t2.logo AS alogo
    FROM matches m
    JOIN teams t1 ON m.home_team_id=t1.id
    JOIN teams t2 ON m.away_team_id=t2.id
    WHERE m.played=0
    ORDER BY m.match_date, m.match_time LIMIT 1")->fetch_assoc();

// Calculate total goals and assists
$total_goals = $conn->query("SELECT SUM(goals) as sum FROM players")->fetch_assoc()['sum'] ?: 0;
$total_assists = $conn->query("SELECT SUM(assist) as sum FROM players")->fetch_assoc()['sum'] ?: 0;

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
        #searchBox { max-width:300px; margin-bottom:1rem; }
        #playerSpotlight { background: var(--bg-light); color: var(--text-light); padding:1rem; border-radius:0.75rem; }
    </style>
</head>
<body>
<div id="mainContainer" class="container mt-4 overlay">
    <div class="header-center mb-4">
        <img src="images/south_sudan_flag.jpg" alt="South Sudan Flag" class="logo" style="border-radius:20%">
        <h1>South Sudan Premier League</h1>
    </div>
    <div class="nav-buttons text-center mb-4">
        <a href="teams.php" class="btn me-2 animate__animated animate__pulse">🏆 Teams</a>
        <a href="players.php" class="btn me-2 animate__animated animate__pulse animate__delay-1s">👥 Players</a>
        <a href="match_results.php" class="btn me-2 animate__animated animate__pulse animate__delay-2s">📅 Results</a>
        <a href="view_match_stats.php" class="btn me-2 animate__animated animate__pulse animate__delay-3s">📊 Stats</a>
        <a href="admin/fixtures.php" class="btn me-2 animate__animated animate__pulse animate__delay-4s">📋 Fixtures</a>
        <a href="scorers_assists_Leaderboard.php" class="btn btn-outline-light me-2 animate__animated animate__pulse animate__delay-5s">⚽ Leaderboards</a>
        <a href="admin/login.php" class="btn me-2 animate__animated animate__pulse animate__delay-6s">⚙️ Admin</a>
		<!-- Dark/Light Toggle -->
    <button id="modeToggle" class="btn me-2 animate__animated animate__pulse animate__delay-7s">Toggle Light/Dark</button>
    </div>
	
     <!-- Player of the Week Spotlight -->
    <div class="mb-4 animate__animated animate__zoomIn" id="playerSpotlight">
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
	
	    <!-- Recent Match Results -->
    <h3>📅 Recent Match Results</h3>
    <div class="table-responsive match-table">
        <table class="table table-striped table-borderless">
            <thead><tr><th>Date & Time</th><th>Home</th><th>Score</th><th>Away</th></tr></thead>
            <tbody>
            <?php
            $fx = $conn->query("SELECT f.match_date,f.match_time,f.home_score,f.away_score,t1.name home,t2.name away,t1.logo hlogo,t2.logo alogo FROM matches f JOIN teams t1 ON f.home_team_id=t1.id JOIN teams t2 ON f.away_team_id=t2.id WHERE f.played=1 ORDER BY f.match_date DESC,f.match_time DESC LIMIT 10");
            while ($r = $fx->fetch_assoc()) {
                echo "<tr>
                    <td>{$r['match_date']} {$r['match_time']}</td>
                    <td><div class='team-cell'><img src='data:image/png;base64,".base64_encode($r['hlogo'])."' class='team-logo'><span>{$r['home']}</span></div></td>
                    <td class='score-cell marquee-score'><span>{$r['home_score']} - {$r['away_score']}</span></td>
                    <td><div class='team-cell'><span>{$r['away']}</span><img src='data:image/png;base64,".base64_encode($r['alogo'])."' class='team-logo'></div></td>
                </tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
	
<!-- Search & Sort -->
    <input type="text" id="searchBox" class="form-control" placeholder="Search team/player...">	
	
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
	
    <!-- Next Fixture Countdown & Carousel -->
    <div class="row mb-4">
        <div class="col-md-6 text-center animate__animated animate__fadeInUp">
            <h3>Next Match</h3>
            <?php if($next): ?>
            <div><span id="countdown"></span></div>
            <div class="team-cell my-2">
                <img src="data:image/png;base64,<?= base64_encode($next['hlogo']) ?>" class="team-logo me-2"> <?= $next['home'] ?> vs <?= $next['away'] ?> <img src="data:image/png;base64,<?= base64_encode($next['alogo']) ?>" class="team-logo ms-2">
            </div>
            <?php else: ?>
            <p>No upcoming fixture.</p>
            <?php endif; ?>
        </div>
		<!-- Carousel/ Slide show -->
        <div class="col-md-4 animate__animated animate__fadeInUp">
            <div id="fixtureCarousel" class="carousel slide" data-bs-ride="carousel">
              <div class="carousel-inner">
                <?php
                $slides = $conn->query("SELECT m.match_date,m.match_time,t1.logo hlogo,t2.logo alogo FROM matches m JOIN teams t1 ON m.home_team_id=t1.id JOIN teams t2 ON m.away_team_id=t2.id WHERE m.played = 0 ORDER BY m.match_date ASC LIMIT 3");
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
    </div>

    <!-- Upcoming Fixtures -->
    <h3>📅 Upcoming Matches</h3>
    <div class="table-responsive match-table">
        <table class="table table-striped table-borderless">
            <thead><tr><th>Date & Time</th><th>Home</th><th>VS</th><th>Away</th><th>Stadium</th></tr></thead>
            <tbody>
            <?php
            $ux = $conn->query("SELECT f.match_date,f.match_time,f.stadium,t1.name home,t2.name away,t1.logo hlogo,t2.logo alogo FROM matches f JOIN teams t1 ON f.home_team_id=t1.id JOIN teams t2 ON f.away_team_id=t2.id WHERE f.played=0 ORDER BY f.match_date,f.match_time LIMIT 10");
            while ($r = $ux->fetch_assoc()) {
                echo "<tr>
                    <td>{$r['match_date']} {$r['match_time']}</td>
                    <td><div class='team-cell'><img src='data:image/png;base64,".base64_encode($r['hlogo'])."' class='team-logo'><span>{$r['home']}</span></div></td>
                    <td class='vs-cell'>vs</td>
                    <td><div class='team-cell'><span>{$r['away']}</span><img src='data:image/png;base64,".base64_encode($r['alogo'])."' class='team-logo'></div></td>
                    <td>{$r['stadium']}</td>
                </tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
    <!-- Top Scorers -->
    <h3>⚽ Top Scorers</h3>
    <div class="table-responsive match-table">
        <table class="table table-striped table-borderless">
            <thead><tr><th>#</th><th>Player</th><th>Team</th><th>Goals</th></tr></thead>
            <tbody>
            <?php
            $rs = $conn->query("SELECT p.image,p.id,p.name,t.name team,p.goals FROM players p JOIN teams t ON p.team_id=t.id WHERE p.goals>0 ORDER BY p.goals DESC,p.name LIMIT 10"); $rk=1;
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
    <h3>🎯 Top Assists</h3>
    <div class="table-responsive match-table">
        <table class="table table-striped table-borderless">
            <thead><tr><th>#</th><th>Player</th><th>Team</th><th>Assists</th></tr></thead>
            <tbody>
            <?php
            $rs2 = $conn->query("SELECT p.image,p.id,p.name,t.name team,p.assist
                FROM players p JOIN teams t ON p.team_id=t.id
                WHERE p.assist>0 ORDER BY p.assist DESC,p.name LIMIT 10");
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
	
    <!-- Animated counters -->
    <div class="row text-center my-4">
        <div class="col animate__animated animate__fadeInUp">
            <h5>Total Goals</h5><div class="counter" id="goalCounter"><?= $total_goals ?></div>
        </div>
        <div class="col animate__animated animate__fadeInUp animate__delay-1s">
            <h5>Total Assists</h5><div class="counter" id="assistCounter"><?= $total_assists ?></div>
        </div>
    </div>
	<!-- Placeholder for charts -->
    <div class="row">
        <div class="col-md-6 mb-4"><canvas id="formChart"></canvas></div>
        <div class="col-md-6 mb-4"><canvas id="scorerChart"></canvas></div>
    </div>
    <!-- Social Feed -->
    <h3>?? Latest Posts</h3>
    <a class="twitter-timeline" data-height="400" href="https://twitter.com/YourLeagueHandle?ref_src=twsrc%5Etfw">Posts by Official League</a>
    <!--Footer-->
    <footer class="text-center mt-5">
        &copy; <?= date('Y') ?> Premier League Manager by THE ITECH GROUPS - Inspired by EPL.
    </footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
<script>
// Dark/light mode toggle
const main = document.getElementById('mainContainer');
document.getElementById('modeToggle').onclick = ()=>main.classList.toggle('light-mode');

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
let ctx1=document.getElementById('formChart'),ctx2=document.getElementById('scorerChart');
new Chart(ctx1,{type:'bar',data:{labels:['W','D','L'],datasets:[{label:'Form (Last 5)',data:[3,1,1],backgroundColor:'var(--accent-color)'}]}});
new Chart(ctx2,{type:'line',data:{labels:['Jan','Feb','Mar','Apr','May'],datasets:[{label:'Top Scorer Goals',data:[5,7,6,8,9],borderColor:'var(--accent-color)',fill:false}]}});
</script>
</body>
</html>