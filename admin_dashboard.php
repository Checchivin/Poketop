<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/pokeapi.php';

require_admin();

$mysqli = app_db();

function admin_dashboard_redirect(?int $encounterId = null): void
{
    $target = 'admin_dashboard.php';
    if ($encounterId !== null) {
        $target .= '?encounter_id=' . $encounterId;
    }

    redirect_to($target);
}

function insert_encounter(mysqli $mysqli, int $campaignId, string $pokemonName, int $level, int $health, int $isShiny, int $isLegendary, int $isMythical, string $spriteUrl, string $types): int
{
    $stmt = $mysqli->prepare(
        'INSERT INTO encounters (campaign_id, pokemon_name, level, health, current_health, is_shiny, is_legendary, is_mythical, sprite_url, types)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'isiiiiiiss',
        $campaignId,
        $pokemonName,
        $level,
        $health,
        $health,
        $isShiny,
        $isLegendary,
        $isMythical,
        $spriteUrl,
        $types
    );
    $stmt->execute();
    $encounterId = (int) $stmt->insert_id;
    $stmt->close();

    return $encounterId;
}

if (is_post_request()) {
    if (isset($_POST['create_campaign'])) {
        $campaignName = trim($_POST['campaign_name'] ?? '');
        if ($campaignName === '') {
            flash_set('admin_dashboard', 'Campaign name is required.', 'danger');
            admin_dashboard_redirect();
        }

        $joinCode = strtoupper(bin2hex(random_bytes(3)));
        $stmt = $mysqli->prepare('INSERT INTO campaigns (name, join_code) VALUES (?, ?)');
        $stmt->bind_param('ss', $campaignName, $joinCode);
        $stmt->execute();
        $stmt->close();

        flash_set('admin_dashboard', "Campaign created. Join code: {$joinCode}", 'success');
        admin_dashboard_redirect();
    }

    if (isset($_POST['delete_campaign'])) {
        $campaignId = (int) ($_POST['campaign_id'] ?? 0);
        $stmt = $mysqli->prepare('DELETE FROM campaigns WHERE id = ?');
        $stmt->bind_param('i', $campaignId);
        $stmt->execute();
        $stmt->close();

        flash_set('admin_dashboard', 'Campaign and related records deleted.', 'warning');
        admin_dashboard_redirect();
    }

    if (isset($_POST['generate_encounter'])) {
        $campaignId = (int) ($_POST['campaign_id'] ?? 0);
        $region = strtolower(trim($_POST['region'] ?? 'kanto'));
        $types = $_POST['types'] ?? [];
        $allowShiny = isset($_POST['shiny']);
        $forceShiny = isset($_POST['force_shiny']);
        $allowLegendary = isset($_POST['legendary']);
        $allowMythical = isset($_POST['mythical']);
        $allowBaseOnly = isset($_POST['base_only']);
        $minLevel = max(1, (int) ($_POST['min_level'] ?? 1));
        $maxLevel = max($minLevel, (int) ($_POST['max_level'] ?? $minLevel));
        $autoHp = isset($_POST['auto_hp']);

        $regionData = fetch_json("https://pokeapi.co/api/v2/region/{$region}");
        if (!$regionData) {
            flash_set('admin_dashboard', 'Failed to fetch region data.', 'danger');
            admin_dashboard_redirect();
        }

        $pokedexName = $regionData['pokedexes'][0]['name'] ?? $region;
        $pokedexData = fetch_json("https://pokeapi.co/api/v2/pokedex/{$pokedexName}");
        if (!$pokedexData) {
            flash_set('admin_dashboard', 'Failed to fetch Pokédex data.', 'danger');
            admin_dashboard_redirect();
        }

        $speciesList = array_map(
            static fn(array $entry): string => $entry['name'],
            array_column($pokedexData['pokemon_entries'], 'pokemon_species')
        );

        if (!empty($types)) {
            $allowedSpecies = [];
            foreach ($types as $type) {
                $typeData = fetch_json('https://pokeapi.co/api/v2/type/' . strtolower($type));
                if (!$typeData) {
                    continue;
                }

                foreach ($typeData['pokemon'] as $pokemonRow) {
                    $allowedSpecies[] = $pokemonRow['pokemon']['name'];
                }
            }
            $speciesList = array_values(array_intersect($speciesList, $allowedSpecies));
        }

        shuffle($speciesList);

        $species = null;
        $speciesData = null;
        foreach ($speciesList as $candidate) {
            $candidateData = fetch_json("https://pokeapi.co/api/v2/pokemon-species/{$candidate}");
            if (!$candidateData) {
                continue;
            }
            if ((!$allowLegendary && !empty($candidateData['is_legendary'])) || (!$allowMythical && !empty($candidateData['is_mythical']))) {
                continue;
            }
            if ($allowBaseOnly && $candidateData['evolves_from_species'] !== null) {
                continue;
            }

            $species = $candidate;
            $speciesData = $candidateData;
            break;
        }

        if ($species === null || $speciesData === null) {
            flash_set('admin_dashboard', 'No Pokémon matched the selected filters.', 'warning');
            admin_dashboard_redirect();
        }

        $pokemonData = fetch_json("https://pokeapi.co/api/v2/pokemon/{$species}");
        if (!$pokemonData) {
            flash_set('admin_dashboard', 'Failed to fetch Pokémon data.', 'danger');
            admin_dashboard_redirect();
        }

        $isShiny = $forceShiny || ($allowShiny && rand(1, 100) <= 10);
        $level = rand($minLevel, $maxLevel);
        $health = $autoHp
            ? max(1, (int) round(compute_hp_from_poke($pokemonData, $level)['hp'] * 4))
            : max(1, (int) ($_POST['health'] ?? 1));

        $encounterId = insert_encounter(
            $mysqli,
            $campaignId,
            $species,
            $level,
            $health,
            $isShiny ? 1 : 0,
            !empty($speciesData['is_legendary']) ? 1 : 0,
            !empty($speciesData['is_mythical']) ? 1 : 0,
            pokemon_sprite_url($pokemonData, $isShiny),
            implode(',', array_map(static fn(array $typeRow): string => $typeRow['type']['name'], $pokemonData['types']))
        );

        flash_set('admin_dashboard', 'Encounter generated successfully.', 'success');
        admin_dashboard_redirect($encounterId);
    }

    if (isset($_POST['generate_exact'])) {
        $campaignId = (int) ($_POST['campaign_id'] ?? 0);
        $pokemonInput = trim($_POST['pokemon_name'] ?? '');
        $allowShiny = isset($_POST['shiny']);
        $forceShiny = isset($_POST['force_shiny']);
        $minLevel = max(1, (int) ($_POST['min_level'] ?? 1));
        $maxLevel = max($minLevel, (int) ($_POST['max_level'] ?? $minLevel));
        $autoHp = isset($_POST['auto_hp']);

        if ($pokemonInput === '') {
            flash_set('admin_dashboard', 'Pokémon name is required.', 'danger');
            admin_dashboard_redirect();
        }

        $slug = normalize_pokemon_slug($pokemonInput);
        $speciesData = fetch_json("https://pokeapi.co/api/v2/pokemon-species/{$slug}");
        $pokemonData = fetch_json("https://pokeapi.co/api/v2/pokemon/{$slug}");
        if (!$speciesData || !$pokemonData) {
            flash_set('admin_dashboard', 'Failed to fetch Pokémon data. Check the name and try again.', 'danger');
            admin_dashboard_redirect();
        }

        $isShiny = $forceShiny || ($allowShiny && rand(1, 100) <= 10);
        $level = rand($minLevel, $maxLevel);
        $health = $autoHp
            ? max(1, (int) round(compute_hp_from_poke($pokemonData, $level)['hp'] * 4))
            : max(1, (int) ($_POST['health'] ?? 1));

        $encounterId = insert_encounter(
            $mysqli,
            $campaignId,
            (string) $speciesData['name'],
            $level,
            $health,
            $isShiny ? 1 : 0,
            !empty($speciesData['is_legendary']) ? 1 : 0,
            !empty($speciesData['is_mythical']) ? 1 : 0,
            pokemon_sprite_url($pokemonData, $isShiny),
            implode(',', array_map(static fn(array $typeRow): string => $typeRow['type']['name'], $pokemonData['types']))
        );

        flash_set('admin_dashboard', 'Exact encounter generated successfully.', 'success');
        admin_dashboard_redirect($encounterId);
    }

    if (isset($_POST['apply_damage'])) {
        $encounterId = (int) ($_POST['encounter_id'] ?? 0);
        $damage = max(1, (int) ($_POST['damage'] ?? 0));
        $stmt = $mysqli->prepare('UPDATE encounters SET current_health = GREATEST(current_health - ?, 0) WHERE id = ?');
        $stmt->bind_param('ii', $damage, $encounterId);
        $stmt->execute();
        $stmt->close();

        flash_set('admin_dashboard', 'Damage applied.', 'warning');
        admin_dashboard_redirect($encounterId);
    }

    if (isset($_POST['catch_pokemon'])) {
        $encounterId = (int) ($_POST['encounter_id'] ?? 0);
        $userIds = array_map('intval', $_POST['catch_user_ids'] ?? []);
        $stmt = $mysqli->prepare('INSERT IGNORE INTO campaign_caught (encounter_id, user_id) VALUES (?, ?)');
        foreach ($userIds as $userId) {
            $stmt->bind_param('ii', $encounterId, $userId);
            $stmt->execute();
        }
        $stmt->close();

        flash_set('admin_dashboard', 'Selected users have been marked as catching the encounter.', 'success');
        admin_dashboard_redirect($encounterId);
    }
}

$flash = flash_get('admin_dashboard');
$selectedEncounterId = isset($_GET['encounter_id']) ? (int) $_GET['encounter_id'] : 0;

$campaignsResult = $mysqli->query(
    'SELECT c.id, c.name, c.join_code,
            COUNT(DISTINCT cp.user_id) AS participant_count,
            COUNT(DISTINCT e.id) AS encounter_count
     FROM campaigns c
     LEFT JOIN campaign_participants cp ON cp.campaign_id = c.id
     LEFT JOIN encounters e ON e.campaign_id = c.id
     GROUP BY c.id, c.name, c.join_code
     ORDER BY c.created_at DESC, c.name'
);
$campaigns = $campaignsResult->fetch_all(MYSQLI_ASSOC);

$currentEncounter = null;
$participants = [];
if ($selectedEncounterId > 0) {
    $stmt = $mysqli->prepare('SELECT * FROM encounters WHERE id = ?');
    $stmt->bind_param('i', $selectedEncounterId);
    $stmt->execute();
    $currentEncounter = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($currentEncounter) {
        $stmt = $mysqli->prepare(
            'SELECT u.id, u.username
             FROM campaign_participants cp
             JOIN users u ON u.id = cp.user_id
             WHERE cp.campaign_id = ?
             ORDER BY u.username'
        );
        $stmt->bind_param('i', $currentEncounter['campaign_id']);
        $stmt->execute();
        $participants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$typeList = ['normal', 'fire', 'water', 'electric', 'grass', 'ice', 'fighting', 'poison', 'ground', 'flying', 'psychic', 'bug', 'rock', 'ghost', 'dragon', 'dark', 'steel', 'fairy'];
$campaignCount = count($campaigns);
$participantTotal = 0;
$encounterTotal = 0;
foreach ($campaigns as $campaign) {
    $participantTotal += (int) $campaign['participant_count'];
    $encounterTotal += (int) $campaign['encounter_count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Admin Dashboard — PokéTop</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/theme.css">
</head>
<body class="admin-page">
  <div class="container py-5 game-layout">
    <div class="page-hero mb-4">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
        <h1 class="mb-1">Campaign Control Room</h1>
          <p class="page-subtitle">Create campaigns, prepare encounters, and manage the live battle flow from one compact control panel.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a href="dashboard.php" class="btn btn-outline-primary">Player View</a>
          <a href="users.php" class="btn btn-secondary">Users</a>
          <a href="logout.php" class="btn btn-outline-dark">Log Out</a>
        </div>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>

    <div class="status-strip">
      <div class="status-tile">
        <div class="status-tile-label">Campaigns</div>
        <div class="status-tile-value"><?= $campaignCount ?></div>
      </div>
      <div class="status-tile">
        <div class="status-tile-label">Trainer Slots</div>
        <div class="status-tile-value"><?= $participantTotal ?></div>
      </div>
      <div class="status-tile">
        <div class="status-tile-label">Encounters Rolled</div>
        <div class="status-tile-value"><?= $encounterTotal ?></div>
      </div>
    </div>

    <div class="row g-4 mb-4">
      <div class="col-lg-4">
        <div class="card shadow-sm h-100 animate-panel">
          <div class="card-body">
            <h5 class="card-title">Create Campaign</h5>
            <p class="control-copy">Start a new adventure and generate a join code for your trainers.</p>
            <form method="post" class="mt-3">
              <label class="form-label" for="campaign_name">Campaign Name</label>
              <input id="campaign_name" type="text" name="campaign_name" class="form-control mb-3" required>
              <button type="submit" name="create_campaign" class="btn btn-primary">Create Campaign</button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="card shadow-sm h-100 animate-panel">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h5 class="card-title mb-1">Campaign Roster</h5>
                <p class="control-copy mb-0">Quick overview of join codes, trainer counts, and total encounters.</p>
              </div>
              <a href="users.php" class="btn btn-sm btn-outline-secondary">Manage Users</a>
            </div>
            <?php if (empty($campaigns)): ?>
              <div class="alert alert-info mb-0">No campaigns have been created yet.</div>
            <?php else: ?>
              <div class="campaign-roster">
                <?php foreach ($campaigns as $campaign): ?>
                  <div class="campaign-roster-item">
                    <div class="campaign-roster-main">
                      <div class="campaign-roster-name"><?= h($campaign['name']) ?></div>
                      <div class="campaign-roster-meta">Join code <code><?= h($campaign['join_code']) ?></code></div>
                    </div>
                    <div class="campaign-stat">
                      <div class="campaign-stat-label">Trainers</div>
                      <div class="campaign-stat-value"><?= (int) $campaign['participant_count'] ?></div>
                    </div>
                    <div class="campaign-stat">
                      <div class="campaign-stat-label">Encounters</div>
                      <div class="campaign-stat-value"><?= (int) $campaign['encounter_count'] ?></div>
                    </div>
                    <div>
                      <form method="post" onsubmit="return confirm('Delete this campaign and all related records?');">
                        <input type="hidden" name="campaign_id" value="<?= (int) $campaign['id'] ?>">
                        <button type="submit" name="delete_campaign" class="btn btn-danger btn-sm">Delete</button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4 mb-4">
      <div class="col-lg-6">
        <div class="card shadow-sm h-100 animate-panel">
          <div class="card-body">
            <h5 class="card-title">Generate Encounter</h5>
            <p class="control-copy">Roll a wild encounter from a region with optional type, rarity, shiny, and level filters.</p>
            <form method="post">
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">Campaign</label>
                  <select name="campaign_id" class="form-select" required>
                    <option value="" selected disabled>Choose a campaign</option>
                    <?php foreach ($campaigns as $campaign): ?>
                      <option value="<?= (int) $campaign['id'] ?>"><?= h($campaign['name']) ?></option>
                    <?php endforeach; ?>
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

              <label class="form-label d-block">Type Filters</label>
              <div class="mb-3 type-filter-grid">
                <?php foreach ($typeList as $type): ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="types[]" value="<?= h($type) ?>" id="type-<?= h($type) ?>">
                    <label class="form-check-label" for="type-<?= h($type) ?>"><?= ucfirst($type) ?></label>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">Min Level</label>
                  <input type="number" name="min_level" class="form-control" min="1" max="100" value="1" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Max Level</label>
                  <input type="number" name="max_level" class="form-control" min="1" max="100" value="5" required>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Manual Health</label>
                <input type="number" name="health" id="healthRandom" class="form-control" min="1" value="50">
              </div>

              <div class="row g-2 mb-3">
                <div class="col-md-6 form-check">
                  <input class="form-check-input" type="checkbox" name="shiny" id="allowShiny">
                  <label class="form-check-label" for="allowShiny">Allow Shiny</label>
                </div>
                <div class="col-md-6 form-check">
                  <input class="form-check-input" type="checkbox" name="force_shiny" id="forceShiny">
                  <label class="form-check-label" for="forceShiny">Force Shiny</label>
                </div>
                <div class="col-md-6 form-check">
                  <input class="form-check-input" type="checkbox" name="legendary" id="legendary">
                  <label class="form-check-label" for="legendary">Include Legendary</label>
                </div>
                <div class="col-md-6 form-check">
                  <input class="form-check-input" type="checkbox" name="mythical" id="mythical">
                  <label class="form-check-label" for="mythical">Include Mythical</label>
                </div>
                <div class="col-md-6 form-check">
                  <input class="form-check-input" type="checkbox" name="base_only" id="baseOnly">
                  <label class="form-check-label" for="baseOnly">Base Stage Only</label>
                </div>
                <div class="col-md-6 form-check">
                  <input class="form-check-input" type="checkbox" name="auto_hp" id="autoHpRandom" checked>
                  <label class="form-check-label" for="autoHpRandom">Auto-Calculate HP</label>
                </div>
              </div>

              <button type="submit" name="generate_encounter" class="btn btn-success">Generate Encounter</button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card shadow-sm h-100 animate-panel">
          <div class="card-body">
            <h5 class="card-title">Generate Exact Pokémon</h5>
            <p class="control-copy">Spawn a specific species into a campaign when you need a planned encounter.</p>
            <form method="post">
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">Campaign</label>
                  <select name="campaign_id" class="form-select" required>
                    <option value="" selected disabled>Choose a campaign</option>
                    <?php foreach ($campaigns as $campaign): ?>
                      <option value="<?= (int) $campaign['id'] ?>"><?= h($campaign['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Pokémon Name</label>
                  <input type="text" name="pokemon_name" class="form-control" placeholder="e.g. bulbasaur" required>
                </div>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">Min Level</label>
                  <input type="number" name="min_level" class="form-control" min="1" max="100" value="1" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Max Level</label>
                  <input type="number" name="max_level" class="form-control" min="1" max="100" value="5" required>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Manual Health</label>
                <input type="number" name="health" id="healthExact" class="form-control" min="1" value="50">
              </div>

              <div class="row g-2 mb-3">
                <div class="col-md-6 form-check">
                  <input class="form-check-input" type="checkbox" name="shiny" id="exactShiny">
                  <label class="form-check-label" for="exactShiny">Allow Shiny</label>
                </div>
                <div class="col-md-6 form-check">
                  <input class="form-check-input" type="checkbox" name="force_shiny" id="exactForceShiny">
                  <label class="form-check-label" for="exactForceShiny">Force Shiny</label>
                </div>
                <div class="col-md-6 form-check">
                  <input class="form-check-input" type="checkbox" name="auto_hp" id="autoHpExact" checked>
                  <label class="form-check-label" for="autoHpExact">Auto-Calculate HP</label>
                </div>
              </div>

              <button type="submit" name="generate_exact" class="btn btn-success">Generate Exact Encounter</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <?php if ($currentEncounter): ?>
      <div class="card shadow-sm battle-card animate-panel">
        <div class="card-body">
          <div class="battle-field">
            <div class="battle-sprite-wrap">
              <img src="<?= h($currentEncounter['sprite_url']) ?>" alt="<?= h($currentEncounter['pokemon_name']) ?>" class="img-fluid battle-sprite" style="max-width: 220px;">
            </div>
            <div class="flex-grow-1 battle-hud">
              <div class="battle-name-row">
                <h5 class="mb-1 battle-name"><?= h(ucfirst($currentEncounter['pokemon_name'])) ?><?= !empty($currentEncounter['is_shiny']) ? ' ★' : '' ?></h5>
                <span class="battle-meta">Lv <?= (int) $currentEncounter['level'] ?></span>
              </div>
              <div class="type-badges mb-3">
                <?php foreach (array_filter(explode(',', (string) ($currentEncounter['types'] ?? ''))) as $type): ?>
                  <span class="type-badge type-<?= h($type) ?>"><?= h($type) ?></span>
                <?php endforeach; ?>
              </div>
              <div class="hud-row">
                <span>HP</span>
                <span><?= (int) $currentEncounter['current_health'] ?> / <?= (int) $currentEncounter['health'] ?></span>
              </div>
              <div class="progress mb-3">
                <?php $encounterPercent = (int) round(((int) $currentEncounter['current_health'] / max(1, (int) $currentEncounter['health'])) * 100); ?>
                <div class="progress-bar <?= $encounterPercent <= 20 ? 'bg-danger' : ($encounterPercent <= 50 ? 'bg-warning' : 'bg-success') ?>" style="width: <?= $encounterPercent ?>%"></div>
              </div>

              <p class="control-copy">Apply battle damage or mark which trainers successfully caught this encounter.</p>
              <form method="post" class="row g-3 align-items-end mb-4 encounter-controls">
                <input type="hidden" name="encounter_id" value="<?= (int) $currentEncounter['id'] ?>">
                <div class="col-md-4">
                  <label class="form-label">Apply Damage</label>
                  <input type="number" name="damage" class="form-control" min="1" required>
                </div>
                <div class="col-md-4">
                  <button type="submit" name="apply_damage" class="btn btn-warning">Apply Damage</button>
                </div>
              </form>

              <?php if (!empty($participants)): ?>
                <form method="post">
                  <input type="hidden" name="encounter_id" value="<?= (int) $currentEncounter['id'] ?>">
                  <label class="form-label d-block">Mark Catch For</label>
                  <div class="mb-3">
                    <?php foreach ($participants as $participant): ?>
                      <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="catch_user_ids[]" value="<?= (int) $participant['id'] ?>" id="participant-<?= (int) $participant['id'] ?>">
                        <label class="form-check-label" for="participant-<?= (int) $participant['id'] ?>"><?= h($participant['username']) ?></label>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <button type="submit" name="catch_pokemon" class="btn btn-primary">Save Catches</button>
                </form>
              <?php else: ?>
                <div class="alert alert-info mb-0">This campaign has no participants yet.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <script>
    function wireAutoHp(toggleId, inputId) {
      const toggle = document.getElementById(toggleId);
      const input = document.getElementById(inputId);
      if (!toggle || !input) {
        return;
      }

      const sync = () => {
        input.disabled = toggle.checked;
        input.classList.toggle('bg-light', toggle.checked);
      };

      toggle.addEventListener('change', sync);
      sync();
    }

    wireAutoHp('autoHpRandom', 'healthRandom');
    wireAutoHp('autoHpExact', 'healthExact');
  </script>
</body>
</html>
