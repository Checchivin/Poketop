<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pokeapi.php';

require_login();

$user_id = current_user_id();
$mysqli = app_db();

// Fetch all seen Pokémon (distinct) for this user
$seenStmt = $mysqli->prepare(
    'SELECT DISTINCT e.pokemon_name, e.sprite_url, e.is_shiny
     FROM encounters e
     JOIN campaign_participants cp ON e.campaign_id = cp.campaign_id
     WHERE cp.user_id = ?
     ORDER BY e.pokemon_name'
);
$seenStmt->bind_param('i', $user_id);
$seenStmt->execute();
$seenResult = $seenStmt->get_result();

$seenNormal = [];
$seenShiny = [];
while ($row = $seenResult->fetch_assoc()) {
    if ($row['is_shiny']) {
        $seenShiny[] = $row;
    } else {
        $seenNormal[] = $row;
    }
}
$seenStmt->close();

// Fetch all caught Pokémon names for this user
$caughtStmt = $mysqli->prepare(
    'SELECT DISTINCT e.pokemon_name
     FROM campaign_caught cc
     JOIN encounters e ON cc.encounter_id = e.id
     WHERE cc.user_id = ?'
);
$caughtStmt->bind_param('i', $user_id);
$caughtStmt->execute();
$caughtResult = $caughtStmt->get_result();

$caughtNames = [];
while ($row = $caughtResult->fetch_assoc()) {
    $caughtNames[] = $row['pokemon_name'];
}
$caughtStmt->close();

// Pokéball icon URL
$pokeballIcon = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/items/poke-ball.png';
$caughtCount = count($caughtNames);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Pokédex — PokéTop</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/theme.css">
  <style>
    .pokedex-card { position: relative; }
    .caught-badge {
      position: absolute;
      top: 8px;
      right: 8px;
      width: 24px;
      height: 24px;
    }
  </style>
</head>
<body>
  <div class="container py-5 game-layout">
    <div class="page-hero mb-4">
      <h1 class="page-title mb-2">Trainer Pokédex</h1>
      <p class="page-subtitle">Review every species your trainer has seen and every encounter already secured.</p>
    </div>
    <div class="status-strip">
      <div class="status-tile">
        <div class="status-tile-label">Seen</div>
        <div class="status-tile-value"><?= count($seenNormal) + count($seenShiny) ?></div>
      </div>
      <div class="status-tile">
        <div class="status-tile-label">Caught</div>
        <div class="status-tile-value"><?= $caughtCount ?></div>
      </div>
      <div class="status-tile">
        <div class="status-tile-label">Shiny Seen</div>
        <div class="status-tile-value"><?= count($seenShiny) ?></div>
      </div>
    </div>
    <ul class="nav nav-tabs" id="pokedexTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="normal-tab" data-bs-toggle="tab" data-bs-target="#normal" type="button" role="tab" aria-controls="normal" aria-selected="true">
          Normal (<?= count($seenNormal) ?>)
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="shiny-tab" data-bs-toggle="tab" data-bs-target="#shiny" type="button" role="tab" aria-controls="shiny" aria-selected="false">
          Shiny (<?= count($seenShiny) ?>)
        </button>
      </li>
    </ul>
    <div class="tab-content mt-3" id="pokedexTabsContent">
      <div class="tab-pane fade show active" id="normal" role="tabpanel" aria-labelledby="normal-tab">
        <?php if (empty($seenNormal)): ?>
          <div class="alert alert-info">You haven't seen any normal Pokémon yet.</div>
        <?php else: ?>
          <div class="dex-grid">
            <?php foreach ($seenNormal as $p): ?>
                <div class="card text-center pokedex-card animate-panel">
                  <?php if (in_array($p['pokemon_name'], $caughtNames)): ?>
                    <img src="<?= $pokeballIcon ?>" class="caught-badge" alt="Caught">
                  <?php endif; ?>
                  <img src="<?= htmlspecialchars($p['sprite_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($p['pokemon_name']) ?>">
                  <div class="card-body p-2">
                    <div class="dex-counter mb-2"><?= strtoupper(substr($p['pokemon_name'], 0, 3)) ?></div>
                    <small class="card-title"><?= htmlspecialchars(ucfirst($p['pokemon_name'])) ?></small>
                  </div>
                </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="tab-pane fade" id="shiny" role="tabpanel" aria-labelledby="shiny-tab">
        <?php if (empty($seenShiny)): ?>
          <div class="alert alert-info">You haven't seen any shiny Pokémon yet.</div>
        <?php else: ?>
          <div class="dex-grid">
            <?php foreach ($seenShiny as $p): ?>
                <div class="card text-center pokedex-card border-warning animate-panel">
                  <?php if (in_array($p['pokemon_name'], $caughtNames)): ?>
                    <img src="<?= $pokeballIcon ?>" class="caught-badge" alt="Caught">
                  <?php endif; ?>
                  <?php
                    $shinyImg = get_shiny_art($p['pokemon_name']) ?: $p['sprite_url'];
                  ?>
                  <img src="<?= htmlspecialchars($shinyImg) ?>" class="card-img-top" alt="<?= htmlspecialchars($p['pokemon_name']) ?>">
                  <div class="card-body p-2">
                    <div class="dex-counter mb-2">SHINY</div>
                    <small class="card-title text-warning"><?= htmlspecialchars(ucfirst($p['pokemon_name'])) ?> ★</small>
                  </div>
                </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="mt-4">
      <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
