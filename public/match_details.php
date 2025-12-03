<?php
$match_id = $_GET['id'] ?? null;
if (!$match_id) {
    echo "<p class='text-danger'>Invalid match ID.</p>";
    exit;
}

// Fetch stats
$stats_result = $conn->query("SELECT ms.*, t.name AS team_name FROM match_stats ms
                              JOIN teams t ON ms.team_id = t.id
                              WHERE ms.match_id = $match_id");

$stats = [];
while ($row = $stats_result->fetch_assoc()) {
    $stats[$row['team_name']] = $row;
}

if (count($stats) === 2):
    $teams = array_keys($stats);
?>
<h3 class="mt-5">📊 Match Stats</h3>
<table class="table table-bordered table-dark text-center">
    <thead>
        <tr>
            <th><?php echo htmlspecialchars($teams[0]); ?></th>
            <th>Stat</th>
            <th><?php echo htmlspecialchars($teams[1]); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        $categories = [
            'goals' => 'Goals',
            'assists' => 'Assists',
            'possession' => 'Possession (%)',
            'shots_on_target' => 'Shots on Target',
            'shots_off_target' => 'Shots off Target',
            'passes_completed' => 'Passes Completed',
            'fouls' => 'Fouls',
            'corners' => 'Corners',
            'offsides' => 'Offsides',
            'yellow_cards' => 'Yellow Cards',
            'red_cards' => 'Red Cards'
        ];

        foreach ($categories as $field => $label) {
            echo "<tr>
                    <td>{$stats[$teams[0]][$field]}</td>
                    <td>{$label}</td>
                    <td>{$stats[$teams[1]][$field]}</td>
                </tr>";
        }
        ?>
    </tbody>
</table>
<?php else: ?>
    <p class="text-warning">Match stats not yet available.</p>
<?php endif; ?>