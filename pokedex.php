<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

session_start();
require_once 'config.php';

// Redirect non-logged-in users to login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Connect to database
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    die('DB connection failed: ' . $mysqli->connect_error);
}

function fetch_json(string $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false || $http >= 400) return false;
    return json_decode($resp, true);
}

// Get shiny official-artwork for a species name, with simple in-request cache
function get_shiny_art(string $speciesName): ?string {
    static $cache = [];

    $key = strtolower(trim($speciesName));
    if (isset($cache[$key])) return $cache[$key];

    // Normalize to PokéAPI slug (mr mime -> mr-mime; remove dots/apostrophes)
    $slug = preg_replace('/\s+/', '-', $key);
    $slug = str_replace(['.', "'"], ['', ''], $slug);

    $data = fetch_json("https://pokeapi.co/api/v2/pokemon/{$slug}");
    if (!$data) return $cache[$key] = null;

    $url =
        $data['sprites']['other']['official-artwork']['front_shiny']
        ?? $data['sprites']['other']['home']['front_shiny']
        ?? $data['sprites']['front_shiny']
        ?? null;

    return $cache[$key] = $url;
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Pokédex — PokéTop</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
<body class="bg-light">
  <div class="container py-5">
    <h1 class="mb-4">Your Pokédex</h1>
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
          <div class="row g-3">
            <?php foreach ($seenNormal as $p): ?>
              <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <div class="card text-center pokedex-card">
                  <?php if (in_array($p['pokemon_name'], $caughtNames)): ?>
                    <img src="<?= $pokeballIcon ?>" class="caught-badge" alt="Caught">
                  <?php endif; ?>
                  <img src="<?= htmlspecialchars($p['sprite_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($p['pokemon_name']) ?>">
                  <div class="card-body p-2">
                    <small class="card-title"><?= htmlspecialchars(ucfirst($p['pokemon_name'])) ?></small>
                  </div>
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
          <div class="row g-3">
            <?php foreach ($seenShiny as $p): ?>
              <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <div class="card text-center pokedex-card border-warning">
                  <?php if (in_array($p['pokemon_name'], $caughtNames)): ?>
                    <img src="<?= $pokeballIcon ?>" class="caught-badge" alt="Caught">
                  <?php endif; ?>
                  <?php
                    $shinyImg = get_shiny_art($p['pokemon_name']) ?: $p['sprite_url'];
                  ?>
                  <img src="<?= htmlspecialchars($shinyImg) ?>" class="card-img-top" alt="<?= htmlspecialchars($p['pokemon_name']) ?>">
                  <div class="card-body p-2">
                    <small class="card-title text-warning"><?= htmlspecialchars(ucfirst($p['pokemon_name'])) ?> ★</small>
                  </div>
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
