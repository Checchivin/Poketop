<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$user_id = current_user_id();
$mysqli = app_db();

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
    flash_set('dashboard', 'Pokémon caught!', 'success');
    redirect_to('dashboard.php');
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
$flash = flash_get('dashboard');
$campaignCount = count($campaigns);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Dashboard — PokéTop</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/theme.css">
</head>
<body>
  <div class="container py-5 game-layout">
    <div class="page-hero mb-4">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
        <h1 class="mb-1">Player Dashboard</h1>
          <p class="page-subtitle">Track active encounters, catches, and your current campaign links.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a href="join_campaign.php" class="btn btn-primary">Join Campaign</a>
          <a href="pokedex.php" class="btn btn-info">View Pokedex</a>
          <?php if (is_admin_user()): ?>
            <a href="admin_dashboard.php" class="btn btn-secondary">Admin Dashboard</a>
            <a href="users.php" class="btn btn-outline-secondary">Users</a>
          <?php endif; ?>
          <a href="logout.php" class="btn btn-outline-dark">Log Out</a>
        </div>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>

    <div class="status-strip">
      <div class="status-tile">
        <div class="status-tile-label">Campaign Links</div>
        <div class="status-tile-value"><?= $campaignCount ?></div>
      </div>
      <div class="status-tile">
        <div class="status-tile-label">Trainer Class</div>
        <div class="status-tile-value"><?= is_admin_user() ? 'Admin' : 'Player' ?></div>
      </div>
      <div class="status-tile">
        <div class="status-tile-label">Trainer Name</div>
        <div class="status-tile-value"><?= h((string) current_username()) ?></div>
      </div>
    </div>

    <?php if (empty($campaigns)): ?>
      <div class="alert alert-info">You are not part of any campaigns yet.</div>
    <?php else: ?>
      <?php foreach ($campaigns as $c): ?>
        <div id="campaign-<?= $c['id'] ?>" class="card mb-4 shadow-sm campaign-card battle-card animate-panel">
          <div class="card-header">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center">
              <strong><?= htmlspecialchars($c['name']) ?></strong>
              <span class="game-chip">Link Code <code><?= htmlspecialchars($c['join_code']) ?></code></span>
            </div>
          </div>
          <div class="card-body">
            <div class="battle-field">
              <div class="battle-sprite-wrap">
                <img src="" alt="" class="img-fluid encounter-sprite battle-sprite">
              </div>
              <div class="encounter-info battle-hud">
                <div class="battle-name-row">
                  <h5 class="encounter-name battle-name"></h5>
                  <span class="encounter-level battle-meta"></span>
                </div>
                <div class="type-badges mb-3"></div>
                <div class="hud-row">
                  <span>HP</span>
                  <span><span class="encounter-hp"></span> / <span class="encounter-max-hp"></span></span>
                </div>
                <div class="progress mb-2">
                  <div class="progress-bar" role="progressbar"></div>
                </div>
                <form method="post" class="catch-form">
                  <input type="hidden" name="catch_encounter_id" value="">
                  <button type="submit" class="btn btn-success btn-sm catch-button">Catch Encounter</button>
                </form>
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
          const catchButton = card.querySelector('.catch-button');
          const typeWrap = card.querySelector('.type-badges');
          if (data.caught) {
            spriteEl.src = pokeballIcon;
          } else {
            spriteEl.src = data.sprite_url || '';
          }
          if (!data.id) {
            card.querySelector('.encounter-name').textContent = 'No active encounter';
            card.querySelector('.encounter-level').textContent = 'Waiting for the admin to generate an encounter.';
            card.querySelector('.encounter-hp').textContent = '0';
            card.querySelector('.encounter-max-hp').textContent = '0';
            typeWrap.innerHTML = '<span class="type-badge type-normal">Waiting</span>';
            catchButton.disabled = true;
            catchButton.textContent = 'No Encounter';
            return;
          }
          card.querySelector('.encounter-name').textContent = data.pokemon_name.charAt(0).toUpperCase() + data.pokemon_name.slice(1) + (data.is_shiny ? ' ★' : '');
          card.querySelector('.encounter-level').textContent = 'Lv ' + data.level;
          card.querySelector('.encounter-hp').textContent = data.current_health;
          card.querySelector('.encounter-max-hp').textContent = data.health;
          const types = (data.types || '').split(',').filter(Boolean);
          typeWrap.innerHTML = types.length
            ? types.map(type => `<span class="type-badge type-${type}">${type}</span>`).join('')
            : '<span class="type-badge type-normal">Unknown</span>';
          catchButton.disabled = !!data.caught;
          catchButton.textContent = data.caught ? 'Already Caught' : 'Catch Encounter';
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
