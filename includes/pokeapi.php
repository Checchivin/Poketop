<?php
declare(strict_types=1);

function fetch_json(string $url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        return false;
    }

    return json_decode($response, true);
}

function normalize_pokemon_slug(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/\s+/', '-', $slug);

    return str_replace(['.', "'"], ['', ''], $slug);
}

function pokemon_sprite_url(array $poke, bool $isShiny): string
{
    if ($isShiny) {
        return $poke['sprites']['other']['official-artwork']['front_shiny']
            ?? $poke['sprites']['other']['home']['front_shiny']
            ?? $poke['sprites']['front_shiny']
            ?? $poke['sprites']['other']['official-artwork']['front_default']
            ?? $poke['sprites']['other']['home']['front_default']
            ?? $poke['sprites']['front_default']
            ?? '';
    }

    return $poke['sprites']['other']['official-artwork']['front_default']
        ?? $poke['sprites']['other']['home']['front_default']
        ?? $poke['sprites']['front_default']
        ?? '';
}

function compute_hp_from_poke(array $poke, int $level, ?int $iv = null, ?int $ev = null): array
{
    $baseHp = 45;
    if (isset($poke['stats']) && is_array($poke['stats'])) {
        foreach ($poke['stats'] as $statRow) {
            if (($statRow['stat']['name'] ?? '') === 'hp') {
                $baseHp = (int) ($statRow['base_stat'] ?? 45);
                break;
            }
        }
    }

    if ($iv === null) {
        $iv = random_int(0, 31);
    }

    if ($ev === null) {
        $evValues = [];
        for ($value = 0; $value <= 252; $value += 4) {
            $evValues[] = $value;
        }
        $ev = $evValues[array_rand($evValues)];
    }

    $hp = (int) floor(((3 * $baseHp + $iv + floor($ev / 4)) * $level) / 100) + $level + 10;

    return [
        'hp' => $hp,
        'base' => $baseHp,
        'iv' => $iv,
        'ev' => $ev,
    ];
}

function get_shiny_art(string $speciesName): ?string
{
    static $cache = [];

    $key = strtolower(trim($speciesName));
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $slug = normalize_pokemon_slug($speciesName);
    $data = fetch_json("https://pokeapi.co/api/v2/pokemon/{$slug}");
    if (!$data) {
        $cache[$key] = null;
        return null;
    }

    $cache[$key] =
        $data['sprites']['other']['official-artwork']['front_shiny']
        ?? $data['sprites']['other']['home']['front_shiny']
        ?? $data['sprites']['front_shiny']
        ?? null;

    return $cache[$key];
}
