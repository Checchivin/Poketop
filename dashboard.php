<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
session_start();
require_once 'config.php';

// Redirect non-logged-in users
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Connect to database
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

// AJAX endpoint: return latest encounter JSON with caught flag
if (isset($_GET['ajax']) && $_GET['ajax'] == '1' && isset($_GET['campaign_id'])) {
    $cid = intval($_GET['campaign_id']);
    $stmt = $mysqli->prepare(
        'SELECT * FROM encounters WHERE campaign_id = ? ORDER BY created_at DESC LIMIT 1'
    );
    $stmt->bind_param('i', $cid);
    $stmt->execute();
    $enc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($enc) {
        // Check if caught by this user
        $caughtStmt = $mysqli->prepare(
            'SELECT 1 FROM campaign_caught WHERE encounter_id = ? AND user_id = ?'
        );
        $caughtStmt->bind_param('ii', $enc['id'], $user_id);
        $caughtStmt->execute();
        $caughtStmt->store_result();
        $enc['caught'] = $caughtStmt->num_rows > 0;
        $caughtStmt->close();
    }

    header('Content-Type: application/json');
    echo json_encode($enc ?: []);
    exit;
}

// Handle catch submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['catch_encounter_id'])) {
    $encounter_id = intval($_POST['catch_encounter_id']);
    $stmt = $mysqli->prepare(
        'INSERT IGNORE INTO campaign_caught (encounter_id, user_id) VALUES (?, ?)'
    );
    $stmt->bind_param('ii', $encounter_id, $user_id);
    $stmt->execute();
    $stmt->close();
    $message = 'Pokémon caught!';
}

// Fetch campaigns user has joined
$stmt = $mysqli->prepare(
    'SELECT c.id, c.name, c.join_code
     FROM campaign_participants cp
     JOIN campaigns c ON cp.campaign_id = c.id
     WHERE cp.user_id = ?'
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$campaigns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Pokéball icon
$pokeballIcon = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/items/poke-ball.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Dashboard — PokéTop</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-5">
    <h1 class="mb-4">Player Dashboard</h1>
    <?php if (!empty($message)): ?>
      <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="mb-3">
      <a href="join_campaign.php" class="btn btn-primary">Join New Campaign</a>
      <a href="pokedex.php" class="btn btn-info">View Pokédex</a>
      <?php if ($_SESSION['user_type'] === 'admin'): ?>
        <a href="admin_dashboard.php" class="btn btn-secondary">Admin Dashboard</a>
      <?php endif; ?>
      <a href="logout.php" class="btn btn-link">Log Out</a>
    </div>

    <?php if (empty($campaigns)): ?>
      <div class="alert alert-info">You are not part of any campaigns yet.</div>
    <?php else: ?>
      <?php foreach ($campaigns as $c): ?>
        <div id="campaign-<?= $c['id'] ?>" class="card mb-4 shadow-sm campaign-card">
          <div class="card-header">
            <strong><?= htmlspecialchars($c['name']) ?></strong>
            <span class="text-muted">(Code: <?= htmlspecialchars($c['join_code']) ?>)</span>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-2">
                <img src="" alt="" class="img-fluid encounter-sprite">
              </div>
              <div class="col-md-8 encounter-info">
                <h5 class="encounter-name"></h5>
                <p class="encounter-level"></p>
                <p>HP: <span class="encounter-hp"></span> / <span class="encounter-max-hp"></span></p>
                <div class="progress mb-2">
                  <div class="progress-bar" role="progressbar"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <script>
    const campaignIds = <?= json_encode(array_column($campaigns, 'id')) ?>;
    const pokeballIcon = '<?= $pokeballIcon ?>';
    function updateCampaign(cid) {
      fetch(`dashboard.php?ajax=1&campaign_id=${cid}`)
        .then(res => res.json())
        .then(data => {
          const card = document.getElementById(`campaign-${cid}`);
          if (!data) return;
          const spriteEl = card.querySelector('.encounter-sprite');
          if (data.caught) {
            spriteEl.src = pokeballIcon;
          } else {
            spriteEl.src = data.sprite_url || '';
          }
          card.querySelector('.encounter-name').textContent = data.pokemon_name.charAt(0).toUpperCase() + data.pokemon_name.slice(1) + (data.is_shiny ? ' ★' : '');
          card.querySelector('.encounter-level').textContent = 'Level: ' + data.level;
          card.querySelector('.encounter-hp').textContent = data.current_health;
          card.querySelector('.encounter-max-hp').textContent = data.health;
          const perc = (data.current_health / Math.max(1, data.health)) * 100;
          const bar = card.querySelector('.progress-bar');

          // width + aria
          bar.style.width = perc + '%';
          bar.setAttribute('aria-valuenow', Math.round(perc));

          // color: green >50%, yellow 50–21%, red 20–0%
          bar.classList.remove('bg-success', 'bg-warning', 'bg-danger');
          if (perc <= 20) {
            bar.classList.add('bg-danger');
          } else if (perc <= 50) {
            bar.classList.add('bg-warning');
          } else {
            bar.classList.add('bg-success');
          }
          // Update catch form value
          const form = card.querySelector('.catch-form');
          if (form && data.id) {
            form.querySelector('input[name="catch_encounter_id"]').value = data.id;
          }
        })
        .catch(console.error);
    }
    // Initial load and polling
    campaignIds.forEach(updateCampaign);
    setInterval(() => campaignIds.forEach(updateCampaign), 5000);
  </script>
</body>
</html>
