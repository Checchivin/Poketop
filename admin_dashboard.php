<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
session_start();
require_once 'config.php';

// Redirect non-admins
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Database connection
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_errno) {
    die('DB connection failed: ' . $mysqli->connect_error);
}

// Helper: fetch JSON via cURL
function fetch_json(string $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    $response = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false || $http >= 400) {
        return false;
    }
    return json_decode($response, true);
}

function compute_hp_from_poke(array $poke, int $level, ?int $iv = null, ?int $ev = null): array {
    // Base HP from PokeAPI payload
    $baseHp = 45; // safe default
    if (isset($poke['stats']) && is_array($poke['stats'])) {
        foreach ($poke['stats'] as $s) {
            if (($s['stat']['name'] ?? '') === 'hp') {
                $baseHp = (int)($s['base_stat'] ?? 45);
                break;
            }
        }
    }

    // Randomize IV/EV if not provided
    if ($iv === null) {
        $iv = random_int(0, 31);
    }
    if ($ev === null) {
        // EVs are effectively applied in multiples of 4 for stat calc
        $evBuckets = [];
        for ($e = 0; $e <= 252; $e += 4) $evBuckets[] = $e;
        $ev = $evBuckets[array_rand($evBuckets)];
    }

    $hp = (int) floor( ((3 * $baseHp + $iv + floor($ev / 4)) * $level) / 100 ) + $level + 10;

    return ['hp' => $hp, 'base' => $baseHp, 'iv' => $iv, 'ev' => $ev];
}

// Initialize variables
$new_campaign_code = '';
$encounter_id = null;
$current_enc = null;
$participants = [];
$message_delete = '';

// Handle Create Campaign
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_campaign'])) {
    $name = trim($_POST['campaign_name']);
    $code = strtoupper(bin2hex(random_bytes(3)));
    $stmt = $mysqli->prepare('INSERT INTO campaigns (name, join_code) VALUES (?, ?)');
    $stmt->bind_param('ss', $name, $code);
    $stmt->execute();
    $new_campaign_code = $code;
    $stmt->close();
}

// Handle Delete Campaign
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_campaign'])) {
    $del_id = intval($_POST['campaign_id']);
    // Delete campaign (cascades to encounters, participants, caught)
    $stmt = $mysqli->prepare('DELETE FROM campaigns WHERE id = ?');
    $stmt->bind_param('i', $del_id);
    if ($stmt->execute()) {
        $message_delete = "<div class='alert alert-warning'>Campaign ID {$del_id} and all related data have been deleted.</div>";
    }
    $stmt->close();
}

// Handle Generate Encounter with PokeAPI (filtered/random)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_encounter'])) {
    $campaign_id     = intval($_POST['campaign_id']);
    $region          = strtolower($_POST['region']);
    $types           = $_POST['types'] ?? [];
    $allow_shiny     = isset($_POST['shiny']);
    $force_shiny     = isset($_POST['force_shiny']);
    $allow_legendary = isset($_POST['legendary']);
    $allow_mythical  = isset($_POST['mythical']);
    $allow_base_only = isset($_POST['base_only']);
    $min_level       = max(1, intval($_POST['min_level']));
    $max_level       = max($min_level, intval($_POST['max_level']));
    $auto_hp         = isset($_POST['auto_hp']); // NEW
    // Health will be computed later if auto_hp, else read from POST after level is known.

    // 1. Fetch region data
    $region_url = "https://pokeapi.co/api/v2/region/{$region}";
    $region_data = fetch_json($region_url);
    if (!$region_data) {
        die('Failed to fetch region data.');
    }
    $pokedex_name = $region_data['pokedexes'][0]['name'] ?? $region;

    // 2. Fetch pokedex entries
    $pokedex_url = "https://pokeapi.co/api/v2/pokedex/{$pokedex_name}";
    $pokedex_data = fetch_json($pokedex_url);
    if (!$pokedex_data) {
        die('Failed to fetch pokedex data.');
    }
    $species_list = array_column($pokedex_data['pokemon_entries'], 'pokemon_species');
    $species_list = array_map(fn($e) => $e['name'], $species_list);

    // 3. Filter by types if provided
    if (!empty($types)) {
        $allowed = [];
        foreach ($types as $t) {
            $type_url = "https://pokeapi.co/api/v2/type/" . strtolower($t);
            $type_data = fetch_json($type_url);
            if (!$type_data) continue;
            foreach ($type_data['pokemon'] as $p) {
                $allowed[] = $p['pokemon']['name'];
            }
        }
        $species_list = array_values(array_intersect($species_list, $allowed));
    }

    // Shuffle to randomize selection
    shuffle($species_list);

    // 4. Select species matching rarity and base-only if needed
    $species = null;
    $species_data = null;
    foreach ($species_list as $candidate) {
        $sd = fetch_json("https://pokeapi.co/api/v2/pokemon-species/{$candidate}");
        if (!$sd) continue;
        if ((!$allow_legendary && $sd['is_legendary']) || (!$allow_mythical && $sd['is_mythical'])) {
            continue;
        }
        if ($allow_base_only && $sd['evolves_from_species'] !== null) {
            continue;
        }
        $species = $candidate;
        $species_data = $sd;
        break;
    }
    if (!$species || !$species_data) {
        die('No Pokémon matching filters.');
    }

    // 5. Fetch full Pokémon data
    $poke = fetch_json("https://pokeapi.co/api/v2/pokemon/{$species}");
    if (!$poke) {
        die('Failed to fetch Pokémon data.');
    }

    // 6. Determine shiny (10% chance if allowed)
    $is_shiny = $force_shiny || ($allow_shiny && (rand(1, 100) <= 10));
    // 7. Random level
    $level = rand($min_level, $max_level);

    // 8. Sprite & types
    $sprite = $is_shiny
        ? (
            $poke['sprites']['other']['official-artwork']['front_shiny']
            ?? $poke['sprites']['other']['home']['front_shiny']
            ?? $poke['sprites']['front_shiny']
            ?? ($poke['sprites']['other']['official-artwork']['front_default']
                ?? $poke['sprites']['other']['home']['front_default']
                ?? $poke['sprites']['front_default']
                ?? '')
        )
        : (
            $poke['sprites']['other']['official-artwork']['front_default']
            ?? $poke['sprites']['other']['home']['front_default']
            ?? $poke['sprites']['front_default']
            ?? ''
        );

    $types_str = implode(',', array_map(fn($t) => $t['type']['name'], $poke['types'])); 

    // --- Auto HP or manual (NEW) ---
    if ($auto_hp) {
        $hpInfo = compute_hp_from_poke($poke, $level, null, null);
        $health = max(1, (int) round($hpInfo['hp'] * 4));
    } else {
        $health = max(1, intval($_POST['health'] ?? 1));
    }

    // 9. Insert encounter
    $isShinyVal     = $is_shiny ? 1 : 0;
    $isLegendaryVal = $species_data['is_legendary'] ? 1 : 0;
    $isMythicalVal  = $species_data['is_mythical'] ? 1 : 0;
    $stmt = $mysqli->prepare(
        'INSERT INTO encounters (campaign_id, pokemon_name, level, health, current_health, is_shiny, is_legendary, is_mythical, sprite_url, types)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'issiiissss',
        $campaign_id,
        $species,
        $level,
        $health,
        $health,
        $isShinyVal,
        $isLegendaryVal,
        $isMythicalVal,
        $sprite,
        $types_str
    );
    $stmt->execute();
    $encounter_id = $stmt->insert_id;
    $stmt->close();
}

// Handle Generate Exact Pokémon Encounter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_exact'])) {
    $campaign_id = intval($_POST['campaign_id']);
    $pokemon_in  = trim($_POST['pokemon_name'] ?? '');
    $allow_shiny = isset($_POST['shiny']);
    $force_shiny = isset($_POST['force_shiny']);
    $min_level   = max(1, intval($_POST['min_level']));
    $max_level   = max($min_level, intval($_POST['max_level']));
    $auto_hp     = isset($_POST['auto_hp']); // NEW

    if ($pokemon_in === '') {
        die('Pokémon name is required.');
    }

    // Normalize to PokéAPI slug
    $slug = strtolower($pokemon_in);
    $slug = preg_replace('/\s+/', '-', $slug);
    $slug = str_replace(['.', "'"], ['', ''], $slug);

    // Fetch species & pokemon data
    $species_data = fetch_json("https://pokeapi.co/api/v2/pokemon-species/{$slug}");
    if (!$species_data) {
        die('Failed to fetch Pokémon species data (check the name).');
    }
    $poke = fetch_json("https://pokeapi.co/api/v2/pokemon/{$slug}");
    if (!$poke) {
        die('Failed to fetch Pokémon data (check the name).');
    }

    // Determine shiny (10% chance if allowed)
    $is_shiny = $force_shiny || ($allow_shiny && (rand(1, 100) <= 10));

    // Random level in range
    $level = rand($min_level, $max_level);

    // Sprite
    $sprite = $is_shiny
        ? (
            $poke['sprites']['other']['official-artwork']['front_shiny']
            ?? $poke['sprites']['other']['home']['front_shiny']
            ?? $poke['sprites']['front_shiny']
            ?? ($poke['sprites']['other']['official-artwork']['front_default']
                ?? $poke['sprites']['other']['home']['front_default']
                ?? $poke['sprites']['front_default']
                ?? '')
        )
        : (
            $poke['sprites']['other']['official-artwork']['front_default']
            ?? $poke['sprites']['other']['home']['front_default']
            ?? $poke['sprites']['front_default']
            ?? ''
        );

    $types_str = implode(',', array_map(fn($t) => $t['type']['name'], $poke['types']));

    // Flags
    $isLegendaryVal = $species_data['is_legendary'] ? 1 : 0;
    $isMythicalVal  = $species_data['is_mythical'] ? 1 : 0;
    $isShinyVal     = $is_shiny ? 1 : 0;

    // --- Auto HP or manual (NEW) ---
    if ($auto_hp) {
        $hpInfo = compute_hp_from_poke($poke, $level, null, null);
        $health = $health = max(1, (int) round($hpInfo['hp'] * 4));
    } else {
        $health = max(1, intval($_POST['health'] ?? 1));
    }

    // Insert encounter
    $stmt = $mysqli->prepare(
        'INSERT INTO encounters (campaign_id, pokemon_name, level, health, current_health, is_shiny, is_legendary, is_mythical, sprite_url, types)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $pokemon_name = $species_data['name']; // normalized API name
    $stmt->bind_param(
        'issiiissss',
        $campaign_id,
        $pokemon_name,
        $level,
        $health,
        $health,
        $isShinyVal,
        $isLegendaryVal,
        $isMythicalVal,
        $sprite,
        $types_str
    );
    $stmt->execute();
    $encounter_id = $stmt->insert_id;
    $stmt->close();
}

// Handle Apply Damage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_damage'])) {
    $eid = intval($_POST['encounter_id']);
    $d   = intval($_POST['damage']);
    $st = $mysqli->prepare('UPDATE encounters SET current_health = GREATEST(current_health - ?, 0) WHERE id = ?');
    $st->bind_param('ii', $d, $eid);
    $st->execute();
    $st->close();
    $encounter_id = $eid;
}

// Handle Catch Pokémon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['catch_pokemon'])) {
    $eid = intval($_POST['encounter_id']);
    $uids = $_POST['catch_user_ids'] ?? [];
    foreach ($uids as $u) {
        $st = $mysqli->prepare('INSERT IGNORE INTO campaign_caught (encounter_id, user_id) VALUES (?, ?)');
        $st->bind_param('ii', $eid, $u);
        $st->execute();
        $st->close();
    }
    $encounter_id = $eid;
}

// Fetch campaigns
$campaigns = $mysqli->query('SELECT id, name, join_code FROM campaigns');
$campaigns_select = $mysqli->query('SELECT id, name, join_code FROM campaigns');

// Load encounter & participants if set
if ($encounter_id) {
    $st = $mysqli->prepare('SELECT * FROM encounters WHERE id = ?');
    $st->bind_param('i', $encounter_id);
    $st->execute();
    $current_enc = $st->get_result()->fetch_assoc();
    $st->close();

    $st = $mysqli->prepare(
        'SELECT u.id, u.username
         FROM campaign_participants cp
         JOIN users u ON cp.user_id = u.id
         WHERE cp.campaign_id = ?'
    );
    $st->bind_param('i', $current_enc['campaign_id']);
    $st->execute();
    $participants = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width,initial-scale=1">
 <title>Admin Dashboard — PokéTop</title>
 <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
 <div class="container py-5">
  <h1 class="mb-4">Admin Dashboard</h1>

  <!-- Create Campaign -->
  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <h5>Create a New Campaign</h5>
      <form method="post">
        <div class="mb-3">
          <label class="form-label">Campaign Name</label>
          <input type="text" name="campaign_name" class="form-control" required>
        </div>
        <button type="submit" name="create_campaign" class="btn btn-primary">Create Campaign</button>
      </form>
      <?php if (!empty($new_campaign_code)): ?>
        <div class="alert alert-success mt-3">
          Join Code: <strong><?= htmlspecialchars($new_campaign_code) ?></strong>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Manage Campaigns -->
  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <h5>Manage Campaigns</h5>
      <?= $message_delete ?>
      <?php if ($campaigns->num_rows === 0): ?>
        <p class="text-muted">No campaigns available.</p>
      <?php else: ?>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>Name</th>
              <th>Join Code</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($campaigns as $c): ?>
              <tr>
                <td><?= htmlspecialchars($c['name']) ?></td>
                <td><?= htmlspecialchars($c['join_code']) ?></td>
                <td>
                  <form method="post" style="display:inline-block" onsubmit="return confirm('Delete this campaign and all related data?');">
                    <input type="hidden" name="campaign_id" value="<?= $c['id'] ?>">
                    <button type="submit" name="delete_campaign" class="btn btn-danger btn-sm">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Admin: Generate Character Sheet -->
  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <h5>Generate Character Sheet</h5>
      <form action="character_sheet.php" method="get" target="_blank" class="row g-3">
        <div class="col-md-5">
          <label class="form-label">Pokémon name</label>
          <input type="text" name="pokemon" class="form-control" placeholder="e.g., bulbasaur" required>
        </div>

        <div class="col-md-3">
          <label class="form-label">Level</label>
          <input type="number" name="level" class="form-control"
                min="1" max="100" value="10" aria-describedby="levelHelp">
          <div id="levelHelp" class="form-text">
            Filters level-up moves up to this level.
          </div>
        </div>

        <div class="col-md-2 form-check d-flex align-items-end">
          <input class="form-check-input" type="checkbox" id="shiny" name="shiny">
          <label class="form-check-label ms-2" for="shiny">Shiny</label>
        </div>

        <div class="col-md-2 form-check d-flex align-items-end">
          <input class="form-check-input" type="checkbox" id="print" name="print" checked>
          <label class="form-check-label ms-2" for="print">Open Print</label>
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary">Open</button>
          <!-- Optional quick-level buttons -->
          <button class="btn btn-outline-secondary" type="button"
                  onclick="this.closest('form').querySelector('[name=level]').value=5">Lv 5</button>
          <button class="btn btn-outline-secondary" type="button"
                  onclick="this.closest('form').querySelector('[name=level]').value=10">Lv 10</button>
          <button class="btn btn-outline-secondary" type="button"
                  onclick="this.closest('form').querySelector('[name=level]').value=20">Lv 20</button>
        </div>
      </form>
    </div>
  </div>


  <!-- Encounter Generator -->
  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <h5>Generate Pokémon Encounter</h5>
      <form method="post">
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Campaign</label>
            <select name="campaign_id" class="form-select" required>
              <option value="" disabled selected>-- Choose --</option>
              <?php while ($cs = $campaigns_select->fetch_assoc()): ?>
                <option value="<?= $cs['id'] ?>"><?= htmlspecialchars($cs['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Region</label>
            <select name="region" class="form-select" required>
              <option value="kanto">Kanto</option>
              <option value="johto">Johto</option>
              <option value="hoenn">Hoenn</option>
            </select>
          </div>
        </div>

        <!-- Type Filters -->
        <div class="mb-3">
          <label class="form-label d-block">Types</label>
          <?php $typeList = ['normal','fire','water','electric','grass','ice','fighting','poison','ground','flying','psychic','bug','rock','ghost','dragon','dark','steel','fairy']; ?>
          <?php foreach ($typeList as $t): ?>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="types[]" value="<?= $t ?>" id="type-<?= $t ?>">
              <label class="form-check-label" for="type-<?= $t ?>"><?= ucfirst($t) ?></label>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Rarity, Shiny & Base-only -->
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="shiny" id="shinyCheck">
          <label class="form-check-label" for="shinyCheck">Allow Shiny</label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="force_shiny" id="forceShinyCheck">
          <label class="form-check-label" for="forceShinyCheck">Force Shiny (guaranteed)</label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="legendary" id="legendaryCheck">
          <label class="form-check-label" for="legendaryCheck">Include Legendary</label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="mythical" id="mythicalCheck">
          <label class="form-check-label" for="mythicalCheck">Include Mythical</label>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="base_only" id="baseCheck">
          <label class="form-check-label" for="baseCheck">Base-only Pokémon</label>
        </div>

        <!-- Level & Health -->
        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Min Level</label>
            <input type="number" name="min_level" class="form-control" min="1" max="100" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Max Level</label>
            <input type="number" name="max_level" class="form-control" min="1" max="100" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Health</label>
            <input type="number" name="health" id="healthRandom" class="form-control" min="1" value="50">
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" id="autoHpRandom" name="auto_hp" checked>
              <label class="form-check-label" for="autoHpRandom">Auto-calc HP</label>
            </div>
          </div>
        </div>

        <button type="submit" name="generate_encounter" class="btn btn-success">Generate Encounter</button>
      </form>
    </div>
  </div>

  <!-- Generate Exact Pokémon -->
  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <h5>Generate Exact Pokémon</h5>
      <form method="post">
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Campaign</label>
            <select name="campaign_id" class="form-select" required>
              <option value="" disabled selected>-- Choose --</option>
              <?php
                $campaigns_for_exact = $mysqli->query('SELECT id, name FROM campaigns');
                while ($row = $campaigns_for_exact->fetch_assoc()):
              ?>
                <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Pokémon Name</label>
            <input type="text" name="pokemon_name" class="form-control" placeholder="e.g., bulbasaur" required>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Min Level</label>
            <input type="number" name="min_level" class="form-control" min="1" max="100" value="1" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Max Level</label>
            <input type="number" name="max_level" class="form-control" min="1" max="100" value="5" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Health</label>
            <input type="number" name="health" id="healthExact" class="form-control" min="1" value="50">
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" id="autoHpExact" name="auto_hp" checked>
              <label class="form-check-label" for="autoHpExact">Auto-calc HP</label>
            </div>
          </div>
        </div>

        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="shiny" id="exactShiny">
          <label class="form-check-label" for="exactShiny">Allow Shiny (10% chance)</label>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="force_shiny" id="exactForceShiny">
          <label class="form-check-label" for="exactForceShiny">Force Shiny (guaranteed)</label>
        </div>
        <button type="submit" name="generate_exact" class="btn btn-success">Generate Exact</button>
      </form>
    </div>
  </div>

  <?php if (!empty($current_enc)): ?>
    <div class="card mb-4 shadow-sm">
      <div class="card-body">
        <h5>Current Encounter</h5>
        <p><img src="<?= htmlspecialchars($current_enc['sprite_url']) ?>" alt=""></p>
        <p><strong><?= htmlspecialchars($current_enc['pokemon_name']) ?></strong>
        (Lvl <?= (int)$current_enc['level'] ?><?= $current_enc['is_shiny'] ? ' ★' : '' ?>)</p>
        <p>HP: <?= (int)$current_enc['current_health'] ?> / <?= (int)$current_enc['health'] ?></p>

        <!-- Damage Form -->
        <form method="post" class="mb-3">
          <input type="hidden" name="encounter_id" value="<?= (int)$current_enc['id'] ?>">
          <div class="mb-3">
            <label class="form-label">Damage</label>
            <input type="number" name="damage" class="form-control" min="1" required>
          </div>
          <button type="submit" name="apply_damage" class="btn btn-warning">Apply Damage</button>
        </form>

        <!-- Catch Form -->
        <?php if (!empty($participants)): ?>
          <form method="post">
            <input type="hidden" name="encounter_id" value="<?= (int)$current_enc['id'] ?>">
            <label class="form-label d-block">Catch for:</label>
            <?php foreach ($participants as $p): ?>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="catch_user_ids[]" value="<?= (int)$p['id'] ?>" id="user-<?= (int)$p['id'] ?>">
                <label class="form-check-label" for="user-<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['username']) ?></label>
              </div>
            <?php endforeach; ?>
            <button type="submit" name="catch_pokemon" class="btn btn-primary mt-2">Catch Pokémon</button>
          </form>
        <?php else: ?>
          <div class="alert alert-info">
            No participants yet. Users can join at <a href="join_campaign.php">Join Page</a>.
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
 </div>

 <!-- Tiny JS to disable manual HP when Auto-calc is checked -->
 <script>
  function wireAutoHp(toggleId, inputId) {
    const t = document.getElementById(toggleId);
    const i = document.getElementById(inputId);
    if (!t || !i) return;
    const sync = () => { i.disabled = t.checked; i.classList.toggle('bg-light', t.checked); };
    t.addEventListener('change', sync);
    sync();
  }
  wireAutoHp('autoHpRandom', 'healthRandom');
  wireAutoHp('autoHpExact',  'healthExact');
 </script>
</body>
</html>
