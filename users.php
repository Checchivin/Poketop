<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_admin();

$mysqli = app_db();

function users_page_redirect(?int $userId = null): void
{
    $target = 'users.php';
    if ($userId !== null) {
        $target .= '?user_id=' . $userId;
    }

    redirect_to($target);
}

function total_admin_count(mysqli $mysqli): int
{
    $result = $mysqli->query("SELECT COUNT(*) AS total FROM users WHERE user_type = 'admin'");
    $row = $result->fetch_assoc();

    return (int) ($row['total'] ?? 0);
}

function fetch_user_row(mysqli $mysqli, int $userId): ?array
{
    $stmt = $mysqli->prepare('SELECT id, username, user_type, created_at FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

if (is_post_request()) {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $targetUser = fetch_user_row($mysqli, $userId);

    if (!$targetUser) {
        flash_set('users', 'Selected user no longer exists.', 'warning');
        users_page_redirect();
    }

    if (isset($_POST['save_user'])) {
        $username = trim($_POST['username'] ?? '');
        $userType = $_POST['user_type'] ?? 'standard';
        $newPassword = $_POST['new_password'] ?? '';

        if (strlen($username) < 3) {
            flash_set('users', 'Username must be at least 3 characters.', 'danger');
            users_page_redirect($userId);
        }

        if (!in_array($userType, ['standard', 'admin'], true)) {
            $userType = 'standard';
        }

        if ($targetUser['user_type'] === 'admin' && $userType !== 'admin' && total_admin_count($mysqli) <= 1) {
            flash_set('users', 'At least one admin account must remain.', 'danger');
            users_page_redirect($userId);
        }

        $stmt = $mysqli->prepare('SELECT id FROM users WHERE username = ? AND id <> ?');
        $stmt->bind_param('si', $username, $userId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            flash_set('users', 'That username is already in use.', 'danger');
            users_page_redirect($userId);
        }
        $stmt->close();

        if ($newPassword !== '' && strlen($newPassword) < 6) {
            flash_set('users', 'New passwords must be at least 6 characters.', 'danger');
            users_page_redirect($userId);
        }

        if ($newPassword !== '') {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare('UPDATE users SET username = ?, user_type = ?, password_hash = ? WHERE id = ?');
            $stmt->bind_param('sssi', $username, $userType, $passwordHash, $userId);
        } else {
            $stmt = $mysqli->prepare('UPDATE users SET username = ?, user_type = ? WHERE id = ?');
            $stmt->bind_param('ssi', $username, $userType, $userId);
        }
        $stmt->execute();
        $stmt->close();

        if ($userId === current_user_id()) {
            $_SESSION['username'] = $username;
            $_SESSION['user_type'] = $userType;
        }

        flash_set('users', 'User profile updated.', 'success');
        users_page_redirect($userId);
    }

    if (isset($_POST['save_memberships'])) {
        $campaignIds = array_values(array_unique(array_map('intval', $_POST['campaign_ids'] ?? [])));

        $mysqli->begin_transaction();
        try {
            $stmt = $mysqli->prepare('DELETE FROM campaign_participants WHERE user_id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $mysqli->prepare('INSERT IGNORE INTO campaign_participants (campaign_id, user_id) VALUES (?, ?)');
            foreach ($campaignIds as $campaignId) {
                $stmt->bind_param('ii', $campaignId, $userId);
                $stmt->execute();
            }
            $stmt->close();

            $stmt = $mysqli->prepare(
                'DELETE cc
                 FROM campaign_caught cc
                 LEFT JOIN encounters e ON e.id = cc.encounter_id
                 LEFT JOIN campaign_participants cp ON cp.campaign_id = e.campaign_id AND cp.user_id = cc.user_id
                 WHERE cc.user_id = ? AND cp.user_id IS NULL'
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();

            $mysqli->commit();
        } catch (Throwable $throwable) {
            $mysqli->rollback();
            throw $throwable;
        }

        flash_set('users', 'Campaign memberships updated.', 'success');
        users_page_redirect($userId);
    }

    if (isset($_POST['save_catches'])) {
        $encounterIds = array_values(array_unique(array_map('intval', $_POST['encounter_ids'] ?? [])));

        $mysqli->begin_transaction();
        try {
            $stmt = $mysqli->prepare('DELETE FROM campaign_caught WHERE user_id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $mysqli->prepare(
                'INSERT IGNORE INTO campaign_caught (encounter_id, user_id)
                 SELECT e.id, ?
                 FROM encounters e
                 JOIN campaign_participants cp ON cp.campaign_id = e.campaign_id
                 WHERE e.id = ? AND cp.user_id = ?'
            );
            foreach ($encounterIds as $encounterId) {
                $stmt->bind_param('iii', $userId, $encounterId, $userId);
                $stmt->execute();
            }
            $stmt->close();

            $mysqli->commit();
        } catch (Throwable $throwable) {
            $mysqli->rollback();
            throw $throwable;
        }

        flash_set('users', 'Caught encounter assignments updated.', 'success');
        users_page_redirect($userId);
    }

    if (isset($_POST['delete_user'])) {
        if ($userId === current_user_id()) {
            flash_set('users', 'You cannot delete the account you are currently using.', 'danger');
            users_page_redirect($userId);
        }

        if ($targetUser['user_type'] === 'admin' && total_admin_count($mysqli) <= 1) {
            flash_set('users', 'At least one admin account must remain.', 'danger');
            users_page_redirect($userId);
        }

        $stmt = $mysqli->prepare('DELETE FROM users WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        flash_set('users', 'User deleted.', 'warning');
        users_page_redirect();
    }
}

$flash = flash_get('users');

$usersResult = $mysqli->query(
    'SELECT u.id, u.username, u.user_type, u.created_at,
            COUNT(DISTINCT cp.campaign_id) AS campaign_count,
            COUNT(DISTINCT cc.encounter_id) AS caught_count
     FROM users u
     LEFT JOIN campaign_participants cp ON cp.user_id = u.id
     LEFT JOIN campaign_caught cc ON cc.user_id = u.id
     GROUP BY u.id, u.username, u.user_type, u.created_at
     ORDER BY u.user_type DESC, u.username ASC'
);
$users = $usersResult->fetch_all(MYSQLI_ASSOC);

$selectedUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : (isset($users[0]) ? (int) $users[0]['id'] : 0);
$selectedUser = $selectedUserId > 0 ? fetch_user_row($mysqli, $selectedUserId) : null;
$userCount = count($users);
$adminCount = 0;
foreach ($users as $user) {
    if ($user['user_type'] === 'admin') {
        $adminCount++;
    }
}

$campaignRows = [];
$encounterRows = [];
$caughtEncounterIds = [];

if ($selectedUser) {
    $stmt = $mysqli->prepare(
        'SELECT c.id, c.name, c.join_code,
                COUNT(DISTINCT e.id) AS encounter_count,
                CASE WHEN cp.user_id IS NULL THEN 0 ELSE 1 END AS is_member
         FROM campaigns c
         LEFT JOIN encounters e ON e.campaign_id = c.id
         LEFT JOIN campaign_participants cp ON cp.campaign_id = c.id AND cp.user_id = ?
         GROUP BY c.id, c.name, c.join_code, cp.user_id
         ORDER BY c.name'
    );
    $stmt->bind_param('i', $selectedUserId);
    $stmt->execute();
    $campaignRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $mysqli->prepare(
        'SELECT e.id, e.pokemon_name, e.level, e.is_shiny, e.created_at, c.name AS campaign_name
         FROM encounters e
         JOIN campaigns c ON c.id = e.campaign_id
         JOIN campaign_participants cp ON cp.campaign_id = e.campaign_id
         WHERE cp.user_id = ?
         ORDER BY c.name, e.created_at DESC'
    );
    $stmt->bind_param('i', $selectedUserId);
    $stmt->execute();
    $encounterRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $mysqli->prepare('SELECT encounter_id FROM campaign_caught WHERE user_id = ?');
    $stmt->bind_param('i', $selectedUserId);
    $stmt->execute();
    $caughtEncounterIds = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'encounter_id'));
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Users — PokéTop</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/theme.css">
</head>
<body>
  <div class="container py-5 game-layout">
    <div class="page-hero mb-4">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
        <h1 class="mb-1">Users</h1>
          <p class="page-subtitle">Manage trainers, campaign links, and every encounter attached to them.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a href="admin_dashboard.php" class="btn btn-outline-primary">Admin Dashboard</a>
          <a href="dashboard.php" class="btn btn-outline-secondary">Player View</a>
          <a href="logout.php" class="btn btn-outline-dark">Log Out</a>
        </div>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>

    <div class="status-strip">
      <div class="status-tile">
        <div class="status-tile-label">Trainers</div>
        <div class="status-tile-value"><?= $userCount ?></div>
      </div>
      <div class="status-tile">
        <div class="status-tile-label">Admins</div>
        <div class="status-tile-value"><?= $adminCount ?></div>
      </div>
      <div class="status-tile">
        <div class="status-tile-label">Selected</div>
        <div class="status-tile-value"><?= $selectedUser ? h($selectedUser['username']) : 'None' ?></div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-lg-4">
        <div class="card shadow-sm trainer-panel animate-panel">
          <div class="card-body">
            <h5 class="card-title">All Users</h5>
            <?php if (empty($users)): ?>
              <div class="alert alert-info mb-0">No users found.</div>
            <?php else: ?>
              <div class="list-group">
                <?php foreach ($users as $user): ?>
                  <a href="users.php?user_id=<?= (int) $user['id'] ?>" class="list-group-item list-group-item-action<?= (int) $user['id'] === $selectedUserId ? ' active' : '' ?>">
                    <div class="d-flex justify-content-between align-items-start">
                      <div>
                        <div class="fw-semibold"><?= h($user['username']) ?></div>
                        <small><?= h($user['user_type']) ?></small>
                      </div>
                      <small><?= (int) $user['campaign_count'] ?> campaigns</small>
                    </div>
                    <small><?= (int) $user['caught_count'] ?> caught encounters</small>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <?php if (!$selectedUser): ?>
          <div class="alert alert-info">Select a user to manage their account and linked records.</div>
        <?php else: ?>
          <div class="card shadow-sm mb-4 animate-panel">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                  <h5 class="card-title mb-1"><?= h($selectedUser['username']) ?></h5>
                  <p class="text-muted mb-0">Created <?= h($selectedUser['created_at']) ?></p>
                </div>
                <span class="game-chip"><?= h($selectedUser['user_type']) ?></span>
              </div>

              <form method="post" class="row g-3">
                <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
                <div class="col-md-6">
                  <label class="form-label">Username</label>
                  <input type="text" name="username" class="form-control" value="<?= h($selectedUser['username']) ?>" required minlength="3" maxlength="50">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Role</label>
                  <select name="user_type" class="form-select">
                    <option value="standard"<?= $selectedUser['user_type'] === 'standard' ? ' selected' : '' ?>>Standard</option>
                    <option value="admin"<?= $selectedUser['user_type'] === 'admin' ? ' selected' : '' ?>>Admin</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">New Password</label>
                  <input type="password" name="new_password" class="form-control" placeholder="Leave blank">
                </div>
                <div class="col-12 d-flex gap-2 flex-wrap">
                  <button type="submit" name="save_user" class="btn btn-primary">Save User</button>
                  <button type="submit" name="delete_user" class="btn btn-danger" onclick="return confirm('Delete this user and all linked records?');">Delete User</button>
                </div>
              </form>
            </div>
          </div>

          <div class="card shadow-sm mb-4 animate-panel">
            <div class="card-body">
              <h5 class="card-title">Campaign Memberships</h5>
              <?php if (empty($campaignRows)): ?>
                <div class="alert alert-info mb-0">No campaigns exist yet.</div>
              <?php else: ?>
                <form method="post">
                  <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
                  <div class="row g-3 mb-3">
                    <?php foreach ($campaignRows as $campaign): ?>
                      <div class="col-md-6">
                        <label class="border rounded p-3 w-100 bg-white">
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="campaign_ids[]" value="<?= (int) $campaign['id'] ?>"<?= !empty($campaign['is_member']) ? ' checked' : '' ?>>
                            <span class="form-check-label fw-semibold"><?= h($campaign['name']) ?></span>
                          </div>
                          <div class="text-muted small mt-2">
                            Join code: <code><?= h($campaign['join_code']) ?></code><br>
                            <?= (int) $campaign['encounter_count'] ?> encounters
                          </div>
                        </label>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <button type="submit" name="save_memberships" class="btn btn-primary">Save Memberships</button>
                </form>
              <?php endif; ?>
            </div>
          </div>

          <div class="card shadow-sm animate-panel">
            <div class="card-body">
              <h5 class="card-title">Caught Encounters</h5>
              <?php if (empty($encounterRows)): ?>
                <div class="alert alert-info mb-0">This user is not attached to any campaign encounters yet.</div>
              <?php else: ?>
                <form method="post">
                  <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
                  <div class="table-responsive mb-3">
                    <table class="table table-bordered align-middle mb-0">
                      <thead>
                        <tr>
                          <th>Campaign</th>
                          <th>Pokémon</th>
                          <th>Level</th>
                          <th>Created</th>
                          <th>Caught</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($encounterRows as $encounter): ?>
                          <tr>
                            <td><?= h($encounter['campaign_name']) ?></td>
                            <td><?= h(ucfirst($encounter['pokemon_name'])) ?><?= !empty($encounter['is_shiny']) ? ' ★' : '' ?></td>
                            <td><?= (int) $encounter['level'] ?></td>
                            <td><?= h($encounter['created_at']) ?></td>
                            <td>
                              <input type="checkbox" name="encounter_ids[]" value="<?= (int) $encounter['id'] ?>"<?= in_array((int) $encounter['id'], $caughtEncounterIds, true) ? ' checked' : '' ?>>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                  <button type="submit" name="save_catches" class="btn btn-primary">Save Catches</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
