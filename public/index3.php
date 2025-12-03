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
$total_goals   = $conn->query("SELECT SUM(goals)  AS sum FROM players")->fetch_assoc()['sum']  ?: 0;
$total_assists = $conn->query("SELECT SUM(assist) AS sum FROM players")->fetch_assoc()['sum'] ?: 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>South Sudan Premier League</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <style>
    :root {
      --primary-color: #1f2937;
      --accent-color: #f59e0b;
      --text-color: #e5e7eb;
      --bg-overlay: rgba(31,41,55,0.9);
      --card-bg: #374151;
      --header-bg: #111827;
    }
    body {
      background: url('images/football_wallpaper.jpg') no-repeat center center fixed;
      background-size: cover;
      color: var(--text-color);
    }
    .overlay {
      background-color: var(--bg-overlay);
      padding: 2rem;
      border-radius: 1rem;
      animation: fadeIn 1s;
    }
    @keyframes fadeIn { from{opacity:0} to{opacity:1} }
    .header-center { text-align:center; animation:fadeInDown 1s; }
    @keyframes fadeInDown { from{opacity:0;transform:translateY(-20px)} to{opacity:1;transform:translateY(0)} }
    .header-center img.logo { width:80px;margin:0 1rem;animation:rotateIn 1s; }
    .header-center h1 { display:inline-block;color:var(--accent-color);font-weight:bold;animation:fadeIn 1.5s; }
    .nav-buttons .btn {
      background-color: var(--primary-color);
      color: var(--text-color);
      border: 1px solid var(--accent-color);
      transition: transform .3s;
    }
    .nav-buttons .btn:hover {
      background-color: var(--accent-color);
      color:#000;
      transform: scale(1.1);
    }
    table { background-color: var(--card-bg); border-radius:.5rem; }
    table th {
      background-color: var(--header-bg) !important;
      color: var(--accent-color) !important;
      text-align: left;
    }
    .team-cell { display:flex;align-items:center;gap:.5rem; }
    .team-logo {
      width:30px;height:30px;object-fit:cover;
      border-radius:50%;
      border:2px solid var(--accent-color);
      transition:transform .3s;
    }
    .team-logo:hover { transform:rotate(360deg); }
    .match-table {
      border-radius:.75rem;overflow:hidden;
      margin-bottom:2rem;animation:fadeInUp 1s;
    }
    @keyframes fadeInUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
    .score-cell,.vs-cell {
      color: var(--accent-color);
      font-weight: bold;
      text-align: left;
    }
    .marquee-score {
      display:inline-block;white-space:nowrap;overflow:hidden;
    }
    .marquee-score span {
      display:inline-block;
      animation:marquee 5s linear infinite;
    }
    @keyframes marquee { 0%{transform:translateX(100%)}100%{transform:translateX(-100%)} }
    .counter { font-weight:bold; }
    h3 {
      color: var(--accent-color);
      margin-top:2rem;margin-bottom:1rem;
      animation:fadeInDown 1s;
    }
    footer {
      padding:2rem 0;
      color:var(--text-color);
      animation:fadeIn 1s;
    }
    /* Light/Dark toggle vars */
    :root { --bg-light:#f3f4f6; --bg-dark:#1f2937; --text-light:#111; --text-dark:#e5e7eb; }
    .light-mode { background:var(--bg-light); color:var(--text-light) !important; }
  </style>
</head>
<body>
<div id="mainContainer" class="container mt-4 overlay">

  <!-- Header -->
  <div class="header-center mb-4">
    <img src="images/south_sudan_flag.jpg" class="logo" alt="Flag" style="border-radius:20%">
    <h1>South Sudan Premier League</h1>
  </div>

  <!-- Nav -->
  <div class="nav-buttons text-center mb-4">
    <a href="teams.php" class="btn me-2 animate__animated animate__pulse">🏆 Teams</a>
    <a href="players.php" class="btn me-2 animate__animated animate__pulse animate__delay-1s">👥 Players</a>
    <a href="match_results.php" class="btn me-2 animate__animated animate__pulse animate__delay-2s">📅 Results</a>
    <a href="view_match_stats.php" class="btn me-2 animate__animated animate__pulse animate__delay-3s">📊 Stats</a>
    <a href="admin/fixtures.php" class="btn me-2 animate__animated animate__pulse animate__delay-4s">📋 Fixtures</a>
    <a href="scorers_assists_Leaderboard.php" class="btn btn-outline-light me-2 animate__animated animate__pulse animate__delay-5s">⚽ Leaderboards</a>
    <a href="admin/login.php" class="btn me-2 animate__animated animate__pulse animate__delay-6s">⚙️ Admin</a>
    <a href="fan_zone.php" class="btn animate__animated animate__pulse animate__delay-7s">💬 Fan Zone</a>
  </div>

  <!-- Player of the Week -->
  <div id="playerSpotlight" class="mb-4 animate__animated animate__zoomIn">
    <h4>Player of the Week</h4>
    <?php
      $pow = $conn->query("
        SELECT p.*, t.name AS team
        FROM players p
        JOIN teams t ON p.team_id=t.id
        ORDER BY RAND()
        LIMIT 1
      ")->fetch_assoc();
    ?>
    <div class="d-flex align-items-center">
      <img src="<?= !empty($pow['image'])
          ? 'data:image/jpeg;base64,'.base64_encode($pow['image'])
          : 'images/default_player.png' ?>"
          class="team-logo me-3" style="width:60px;height:60px;"
          alt="Player Photo">
      <div>
        <h5 class="mb-0"><?=htmlspecialchars($pow['name'])?> (<?=htmlspecialchars($pow['team'])?>)</h5>
        <small>Goals: <?=intval($pow['goals'])?> | Assists: <?=intval($pow['assist'])?></small>
      </div>
    </div>
  </div>

  <!-- Recent Results -->
  <h3>📅 Recent Match Results</h3>
  <div class="table-responsive match-table">
    <table class="table table-striped table-borderless">
      <thead>
        <tr><th>Date & Time</th><th>Home</th><th>Score</th><th>Away</th></tr>
      </thead>
      <tbody>
      <?php
      $fx = $conn->query("
        SELECT f.match_date,f.match_time,f.home_score,f.away_score,
               t1.name AS home,t2.name AS away,
               t1.logo AS hlogo,t2.logo AS alogo
        FROM matches f
        JOIN teams t1 ON f.home_team_id=t1.id
        JOIN teams t2 ON f.away_team_id=t2.id
        WHERE f.played=1
        ORDER BY f.match_date DESC,f.match_time DESC
        LIMIT 10
      ");
      while($r=$fx->fetch_assoc()):
      ?>
        <tr>
          <td><?=$r['match_date']?> <?=$r['match_time']?></td>
          <td>
            <div class="team-cell">
              <img src="data:image/png;base64,<?=base64_encode($r['hlogo'])?>" class="team-logo">
              <span><?=$r['home']?></span>
            </div>
          </td>
          <td class="score-cell marquee-score">
            <span><?=$r['home_score']?> - <?=$r['away_score']?></span>
          </td>
          <td>
            <div class="team-cell">
              <span><?=$r['away']?></span>
              <img src="data:image/png;base64,<?=base64_encode($r['alogo'])?>" class="team-logo">
            </div>
          </td>
        </tr>
      <?php endwhile;?>
      </tbody>
    </table>
  </div>

  <!-- Search Box -->
  <input id="searchBox" class="form-control" placeholder="Search team/player…" type="text">

  <hr class="border-light">

  <!-- League Standings -->
  <h3>🏆 League Standings</h3>
  <div class="text-end mb-2">
    <button id="modeToggle" class="btn btn-sm btn-secondary">Toggle Light/Dark</button>
  </div>
  <div class="table-responsive match-table">
    <table id="standings" class="table table-striped table-borderless">
      <thead>
        <tr>
          <th class="sortable" onclick="sortTable(0)">#</th>
          <th class="sortable" onclick="sortTable(1)">Team</th>
          <th class="sortable" onclick="sortTable(2)">Pl</th>
          <th class="sortable" onclick="sortTable(3)">W</th>
          <th class="sortable" onclick="sortTable(4)">D</th>
          <th class="sortable" onclick="sortTable(5)">L</th>
          <th class="sortable" onclick="sortTable(6)">GF</th>
          <th class="sortable" onclick="sortTable(7)">GA</th>
          <th class="sortable" onclick="sortTable(8)">GD</th>
          <th class="sortable" onclick="sortTable(9)">Pts</th>
        </tr>
      </thead>
      <tbody id="standingsBody">
      <?php
      $teams = $conn->query("SELECT * FROM teams ORDER BY points DESC,(gf-ga) DESC");
      $pos=1;
      while($r=$teams->fetch_assoc()):
        $logo = !empty($r['logo'])
          ? '<img src="data:image/png;base64,'.base64_encode($r['logo']).'" class="team-logo"> '
          : '';
      ?>
        <tr>
          <td class="counter"><?=$pos?></td>
          <td><div class="team-cell"><?=$logo?><?=$r['name']?></div></td>
          <td class="counter"><?=$r['played']?></td>
          <td class="counter"><?=$r['win']?></td>
          <td class="counter"><?=$r['draw']?></td>
          <td class="counter"><?=$r['loss']?></td>
          <td class="counter"><?=$r['gf']?></td>
          <td class="counter"><?=$r['ga']?></td>
          <td class="counter"><?=($r['gf']-$r['ga'])?></td>
          <td class="counter"><?=$r['points']?></td>
        </tr>
      <?php $pos++; endwhile;?>
      </tbody>
    </table>
  </div>

  <!-- Next Fixture & Carousel -->
  <div class="row mb-4">
    <div class="col-md-6 text-center animate__animated animate__fadeInUp">
      <h3>Next Match</h3>
      <?php if($next): ?>
        <div><span id="countdown"></span></div>
        <div class="team-cell my-2">
          <img src="data:image/png;base64,<?=base64_encode($next['hlogo'])?>" class="team-logo me-2">
          <?=$next['home']?> vs <?=$next['away']?>
          <img src="data:image/png;base64,<?=base64_encode($next['alogo'])?>" class="team-logo ms-2">
        </div>
      <?php else: ?>
        <p>No upcoming fixture.</p>
      <?php endif;?>
    </div>
    <div class="col-md-6 animate__animated animate__fadeInUp">
      <div id="fixtureCarousel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
          <?php
          $slides = $conn->query("
            SELECT m.match_date,m.match_time,
                   t1.logo AS hlogo,t2.logo AS alogo
            FROM matches m
            JOIN teams t1 ON m.home_team_id=t1.id
            JOIN teams t2 ON m.away_team_id=t2.id
            ORDER BY m.match_date DESC
            LIMIT 3
          ");
          $first=true;
          while($s=$slides->fetch_assoc()):
          ?>
            <div class="carousel-item <?= $first?'active':''?>">
              <img src="images/slide_bg.jpg" class="d-block w-100" alt="">
              <div class="carousel-caption d-none d-md-block">
                <div class="team-cell justify-content-center">
                  <img src="data:image/png;base64,<?=base64_encode($s['hlogo'])?>" class="team-logo me-3">
                  <span class="text-light fs-4"><?=$s['match_date']?> <?=$s['match_time']?></span>
                  <img src="data:image/png;base64,<?=base64_encode($s['alogo'])?>" class="team-logo ms-3">
                </div>
              </div>
            </div>
          <?php $first=false; endwhile;?>
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

  <!-- Upcoming Fixtures, Top Scorers, Top Assists, Counters, Charts… (unchanged) -->

  <footer class="text-center mt-5">
    &copy; <?=date('Y')?> Premier League Manager by THE ITECH GROUPS – Inspired by EPL.
  </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
<script>
// ---- Carousel auto-cycle initialization (Bootstrap 5) ----
var carouselEl = document.querySelector('#fixtureCarousel');
if (carouselEl) {
  new bootstrap.Carousel(carouselEl, {
    interval: 4000,   // change slide every 4s
    ride: 'carousel'  // start cycling on load
  });
}

// ---- Sortable standings ----
function sortTable(col) {
  const tbl = document.getElementById('standings');
  const tb  = tbl.tBodies[0];
  Array.from(tb.rows)
    .sort((a,b)=>{
      let A = a.cells[col].innerText.trim(),
          B = b.cells[col].innerText.trim();
      return isNaN(A) ? A.localeCompare(B) : A-B;
    })
    .forEach(r=>tb.appendChild(r));
}

// ---- Search filter ----
document.getElementById('searchBox').addEventListener('input', e=>{
  const term = e.target.value.toLowerCase();
  document.querySelectorAll('#standingsBody tr').forEach(row=>{
    row.style.display = row.innerText.toLowerCase().includes(term) ? '' : 'none';
  });
});

// ---- Countdown ----
<?php if($next): ?>
let target = new Date('<?=$next['match_date']?> <?=$next['match_time']?>').getTime();
setInterval(()=>{
  let d = target - Date.now();
  if(d<0) return document.getElementById('countdown').innerText='00:00:00';
  let h = Math.floor(d/3600000),
      m = Math.floor((d%3600000)/60000),
      s = Math.floor((d%60000)/1000);
  document.getElementById('countdown').innerText =
    h + ':' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
},1000);
<?php endif; ?>

// ---- Light/dark toggle ----
document.getElementById('modeToggle').onclick = ()=>{
  document.body.classList.toggle('light-mode');
};

// ---- Animated counters, charts… (unchanged) ----
</script>
</body>
</html>