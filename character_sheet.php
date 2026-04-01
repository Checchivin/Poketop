<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// character_sheet.php
// Usage examples:
//   character_sheet.php?pokemon=bulbasaur
//   character_sheet.php?pokemon=charmander&shiny=1
//   character_sheet.php?pokemon=charmander&level=12   (only level-up moves learned up to 12)

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

$pokemonQuery = strtolower(trim($_GET['pokemon'] ?? ''));
$wantShiny    = isset($_GET['shiny']);
$autoPrint    = isset($_GET['print']);
$levelCap     = isset($_GET['level']) ? max(0, intval($_GET['level'])) : 0; // 0 = no cap
$moveLimit    = 30; // first 10 level-up moves only

if ($pokemonQuery === '') {
  http_response_code(400);
  echo '<!doctype html><html><body style="font-family:system-ui,Segoe UI,Roboto,Arial">Missing ?pokemon=name</body></html>';
  exit;
}

// Normalize spaces & punctuation to match PokéAPI slugs (e.g., "mr mime" -> "mr-mime")
$slug = preg_replace('/\s+/', '-', $pokemonQuery);
$slug = str_replace(['.', "'"], ['', ''], $slug);

$poke = fetch_json("https://pokeapi.co/api/v2/pokemon/{$slug}");
if (!$poke) {
  http_response_code(404);
  echo '<!doctype html><html><body style="font-family:system-ui,Segoe UI,Roboto,Arial">Pokémon not found.</body></html>';
  exit;
}
$species = fetch_json("https://pokeapi.co/api/v2/pokemon-species/{$slug}");

// Name / ID
$name = ucfirst($poke['name']);
$id   = $poke['id'];
$nationalDex = str_pad((string)$id, 4, '0', STR_PAD_LEFT);

// Types
$types = array_map(fn($t) => ucfirst($t['type']['name']), $poke['types']);
sort($types);

// Stats
$statMap = [];
foreach ($poke['stats'] as $s) {
  $statMap[$s['stat']['name']] = $s['base_stat'];
}
$hp  = $statMap['hp'] ?? null;
$atk = $statMap['attack'] ?? null;
$def = $statMap['defense'] ?? null;
$spa = $statMap['special-attack'] ?? null;
$spd = $statMap['special-defense'] ?? null;
$spe = $statMap['speed'] ?? null;

// Abilities
$abilities = array_map(function($ab) {
  return [
    'name' => ucfirst($ab['ability']['name']),
    'hidden' => $ab['is_hidden'] ? ' (Hidden)' : ''
  ];
}, $poke['abilities']);

// Height/Weight (API: decimeters, hectograms)
$height_m = number_format($poke['height'] / 10, 1);
$weight_kg = number_format($poke['weight'] / 10, 1);

// Artwork (prefer official-artwork; fallback to home/default; shiny if requested)
$art = $poke['sprites']['other']['official-artwork']['front_default'] ?? '';
$artShiny = $poke['sprites']['other']['official-artwork']['front_shiny'] ?? '';
if ($wantShiny) {
  $sprite = $artShiny ?: ($poke['sprites']['other']['home']['front_shiny'] ?? ($poke['sprites']['front_shiny'] ?? $art));
} else {
  $sprite = $art ?: ($poke['sprites']['other']['home']['front_default'] ?? ($poke['sprites']['front_default'] ?? ''));
}

// Flavor text (English, first match)
$flavor = '';
if ($species && !empty($species['flavor_text_entries'])) {
  foreach ($species['flavor_text_entries'] as $entry) {
    if (strtolower($entry['language']['name']) === 'en') {
      $flavor = preg_replace('/\s+/', ' ', $entry['flavor_text']);
      break;
    }
  }
}

// ====== Level-up moves ONLY ======
$levelUpMoves = [];
foreach ($poke['moves'] as $m) {
  $best = null;
  foreach ($m['version_group_details'] as $detail) {
    if (($detail['move_learn_method']['name'] ?? '') !== 'level-up') continue;
    // Keep the earliest level at which it can be learned
    if ($best === null || (int)($detail['level_learned_at'] ?? 0) < (int)($best['level_learned_at'] ?? 0)) {
      $best = $detail;
    }
  }
  if ($best) {
    $levelUpMoves[] = [
      'name' => $m['move']['name'],
      'url'  => $m['move']['url'],
      'lv'   => (int)($best['level_learned_at'] ?? 0)
    ];
  }
}

// Sort by level, then name
usort($levelUpMoves, fn($a,$b) => ($a['lv'] <=> $b['lv']) ?: strcmp($a['name'],$b['name']));

// Apply optional level cap (include Lv 0 "starting" moves)
if ($levelCap > 0) {
  $levelUpMoves = array_values(array_filter($levelUpMoves, fn($m) => $m['lv'] <= $levelCap));
}

// Take first 10 after filters
$levelUpMoves = array_slice($levelUpMoves, 0, $moveLimit);

// Fetch move details
$moveRows = [];
foreach ($levelUpMoves as $cm) {
  $md = fetch_json($cm['url']);
  if (!$md) continue;
  $moveRows[] = [
    'name'  => ucfirst(str_replace('-', ' ', $cm['name'])),
    'type'  => ucfirst($md['type']['name'] ?? ''),
    'class' => ucfirst($md['damage_class']['name'] ?? ''),
    'power' => $md['power'] !== null ? (int)$md['power'] : '—',
    'acc'   => $md['accuracy'] !== null ? (int)$md['accuracy'] : '—',
    'pp'    => $md['pp'] ?? '—',
    'learn' => 'Lv ' . (int)$cm['lv']
  ];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($name) ?> — Character Sheet</title>
  <link rel="stylesheet" href="assets/theme.css" media="all">
  <style>
    :root { --brand:#d63b30; --ink:#1a2433; --muted:#506070; --bg:#fffdf4; --border:#232323; }
    @page { size: Letter; margin: 0.5in; }
    body { font-family: 'VT323', monospace; background: linear-gradient(180deg, #9bd0ff 0%, #f7f1cf 100%); color: var(--ink); }
    .sheet { max-width: 8.5in; margin: 0 auto; border:4px solid var(--border); border-radius: 22px; padding: 24px; }
    .header { display:flex; gap:24px; align-items: center; }
    .art { width: 280px; height: 280px; object-fit: contain; background: radial-gradient(circle at top, #fff, #dcecff); border:4px solid var(--border); border-radius: 18px; }
    .meta { flex:1; }
    .title { font-family: 'Press Start 2P', cursive; font-size: 32px; font-weight: 800; line-height:1.3; margin:0 0 8px; }
    .subtitle { color: var(--muted); margin:0 0 8px; }
    .chips { display:flex; gap:8px; flex-wrap: wrap; }
    .chip { background: #fff8d8; border:3px solid var(--border); padding:6px 12px; border-radius: 999px; font-weight:600; }
    .grid { display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; margin-top: 20px; }
    .card { border:4px solid var(--border); border-radius: 18px; padding: 12px 14px; background: rgba(255,255,255,0.85); }
    .label { font-family: 'Press Start 2P', cursive; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
    .value { font-weight: 700; font-size: 18px; }
    .stats { display:grid; grid-template-columns: repeat(6, 1fr); gap:10px; margin-top: 20px; }
    .stat { text-align:center; border:4px solid var(--border); border-radius: 16px; padding: 10px 8px; background: rgba(255,255,255,0.9); }
    .printbar { display:flex; justify-content: space-between; align-items:center; margin-bottom:12px; }
    .btn { display:inline-block; border:3px solid var(--border); border-radius: 12px; padding:10px 14px; background:#fff8d8; text-decoration:none; color:var(--ink); font-family:'Press Start 2P', cursive; font-size:12px; }
    .btn.primary { background: var(--brand); color:#fff; border-color: var(--border); }
    .moves { margin-top: 18px; background: rgba(255,255,255,0.9); }
    .moves table { width:100%; border-collapse: collapse; border:0; }
    .moves th, .moves td { padding:8px 10px; border-bottom:1px solid var(--border); font-size: 14px; }
    .moves th { text-transform:uppercase; font-size:12px; letter-spacing:.06em; color:var(--muted); background:#dcecff; font-family:'Press Start 2P', cursive; }
    .moves td:nth-child(1) { font-weight:600; }
    @media print { .btn, .printbar { display:none !important; } .sheet { border:none; } }
  </style>
</head>
<body>
  <div class="printbar">
    <div>
      <a class="btn" href="javascript:window.print()">Print</a>
      <a class="btn" href="?pokemon=<?= urlencode($pokemonQuery) ?>&shiny=<?= $wantShiny?0:1 ?>">
        Toggle <?= $wantShiny ? 'Normal' : 'Shiny' ?>
      </a>
      <?php if ($levelCap > 0): ?>
        <span class="btn">Level Cap: <?= (int)$levelCap ?></span>
      <?php endif; ?>
    </div>
    <div><small>PokéTop Character Sheet</small></div>
  </div>

  <div class="sheet">
    <div class="header">
      <img class="art" src="<?= htmlspecialchars($sprite) ?>" alt="<?= htmlspecialchars($name) ?> artwork"/>
      <div class="meta">
        <h1 class="title"><?= htmlspecialchars($name) ?><?= $wantShiny ? ' ★' : '' ?></h1>
        <div class="subtitle">National Dex #<?= $nationalDex ?></div>
        <div class="chips">
          <?php foreach ($types as $t): ?>
            <span class="chip"><?= htmlspecialchars($t) ?></span>
          <?php endforeach; ?>
        </div>
        <div class="grid">
          <div class="card"><div class="label">Height</div><div class="value"><?= $height_m ?> m</div></div>
          <div class="card"><div class="label">Weight</div><div class="value"><?= $weight_kg ?> kg</div></div>
          <div class="card"><div class="label">Abilities</div><div class="value">
            <?php foreach ($abilities as $i=>$ab): ?>
              <?= htmlspecialchars($ab['name'] . $ab['hidden']) ?><?= $i < count($abilities)-1 ? ', ' : '' ?>
            <?php endforeach; ?>
          </div></div>
        </div>
      </div>
    </div>

    <div class="stats">
      <div class="stat"><div class="label">HP</div><div class="value"><?= $hp ?></div></div>
      <div class="stat"><div class="label">ATK</div><div class="value"><?= $atk ?></div></div>
      <div class="stat"><div class="label">DEF</div><div class="value"><?= $def ?></div></div>
      <div class="stat"><div class="label">SpA</div><div class="value"><?= $spa ?></div></div>
      <div class="stat"><div class="label">SpD</div><div class="value"><?= $spd ?></div></div>
      <div class="stat"><div class="label">SPE</div><div class="value"><?= $spe ?></div></div>
    </div>

    <div class="grid" style="margin-top:20px; grid-template-columns: 2fr 1fr;">
      <div class="card">
        <div class="label">Dex Entry</div>
        <div class="value" style="font-weight:500; line-height:1.5; font-size:15px;">
          <?= htmlspecialchars($flavor ?: '—') ?>
        </div>
      </div>
      <div class="card">
        <div class="label">Trainer Notes</div>
        <div style="height:120px; border:1px dashed var(--border); border-radius:8px; margin-top:8px;"></div>
      </div>
    </div>

    <div class="moves card">
      <div class="label">
        Moves (First 10 Level-Up<?= $levelCap > 0 ? ', up to Lv ' . (int)$levelCap : '' ?>)
      </div>
      <?php if (empty($moveRows)): ?>
        <div class="value" style="margin-top:8px;">No moves available.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Move</th>
              <th>Type</th>
              <th>Class</th>
              <th>Power</th>
              <th>Acc</th>
              <th>PP</th>
              <th>Learn</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($moveRows as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['type']) ?></td>
                <td><?= htmlspecialchars($row['class']) ?></td>
                <td><?= htmlspecialchars($row['power']) ?></td>
                <td><?= htmlspecialchars($row['acc']) ?></td>
                <td><?= htmlspecialchars($row['pp']) ?></td>
                <td><?= htmlspecialchars($row['learn']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($autoPrint): ?>
  <script>
    window.addEventListener('load', () => setTimeout(() => window.print(), 100));
  </script>
  <?php endif; ?>
</body>
</html>
