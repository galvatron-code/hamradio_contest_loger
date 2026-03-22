<?php
session_start();
require 'db.php';

$ADMIN_USER = 'admin';
$ADMIN_PASS = 'secret123';

$info = "";

if (isset($_POST['admin_login'])) {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    if ($user === $ADMIN_USER && $pass === $ADMIN_PASS) {
        $_SESSION['is_admin'] = true;
    } else {
        $info = "Błędny login lub hasło.";
    }
}

if (isset($_POST['reset_log'])) {
    if (empty($_SESSION['is_admin'])) {
        $info = "Błąd: nie jesteś zalogowany jako admin.";
    } elseif (isset($_POST['confirm']) && $_POST['confirm'] === 'TAK') {
        $conn->query("TRUNCATE TABLE qso_log");
        $conn->query("UPDATE kursanci SET display_name = NULL");
        $info = "Log został wyzerowany i nazwy drużyn skasowane.";
    } else {
        $info = "Reset przerwany – wpisz TAK żeby potwierdzić.";
    }
}

if (isset($_POST['save_contest']) && !empty($_SESSION['is_admin'])) {
    $name      = trim($_POST['contest_name']        ?? 'Zawody kursowe');
    $rounds    = (int)($_POST['rounds']             ?? 2);
    $dur_min   = (int)($_POST['round_duration_min'] ?? 5);
    $dur_sec   = $dur_min * 60;
    $break_min = (int)($_POST['break_duration_min'] ?? 3);
    $break_sec = $break_min * 60;
    $start_dt  = trim($_POST['start_datetime']      ?? '');
    $req_peer  = isset($_POST['require_peer_qso_no']) ? 1 : 0;
    $tapeta    = null;

    // Obsługa uploadu tapety
    if (!empty($_FILES['tapeta']['name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $ftype   = mime_content_type($_FILES['tapeta']['tmp_name']);
        if (!in_array($ftype, $allowed)) {
            $info = "Błąd: dozwolone formaty tapety to JPG, PNG, WEBP.";
        } elseif ($_FILES['tapeta']['size'] > 5 * 1024 * 1024) {
            $info = "Błąd: tapeta nie może być większa niż 5MB.";
        } else {
            $ext    = pathinfo($_FILES['tapeta']['name'], PATHINFO_EXTENSION);
            $fname  = 'tapeta_' . time() . '.' . $ext;
            $dest   = __DIR__ . '/pliki/tapety/' . $fname;
            if (move_uploaded_file($_FILES['tapeta']['tmp_name'], $dest)) {
                $tapeta = $fname;
            } else {
                $info = "Błąd: nie udało się zapisać tapety.";
            }
        }
    }

    if ($info === "" && $rounds >= 1 && $dur_min >= 1 && $start_dt !== '') {
        $start_mysql = date('Y-m-d H:i:s', strtotime($start_dt));
        $stmt = $conn->prepare(
            "INSERT INTO contests (name, rounds, round_duration_sec, break_duration_sec, start_datetime, require_peer_qso_no, tapeta)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("siiisis", $name, $rounds, $dur_sec, $break_sec, $start_mysql, $req_peer, $tapeta);
        $stmt->execute();
        $stmt->close();
        $req_label = $req_peer ? "wymagany" : "opcjonalny";
        $info = "Zawody zapisane. Start: $start_mysql, Rund: $rounds x {$dur_min} min, Nr QSO korespondenta: $req_label.";
    } elseif ($info === "") {
        $info = "Błąd: wypełnij wszystkie pola konfiguracji.";
    }
}

if (isset($_POST['start_now']) && !empty($_SESSION['is_admin'])) {
    $req_peer = isset($_POST['require_peer_qso_no_adhoc']) ? 1 : 0;
    $stmt = $conn->prepare(
        "INSERT INTO contests (name, rounds, round_duration_sec, break_duration_sec, start_datetime, require_peer_qso_no)
         VALUES ('Zawody ad-hoc', 2, 300, 180, NOW(), ?)"
    );
    $stmt->bind_param("i", $req_peer);
    $stmt->execute();
    $stmt->close();
    $req_label = $req_peer ? "wymagany" : "opcjonalny";
    $info = "Zawody ad-hoc wystartowały! 2 rundy x 5 min, przerwa 3 min. Nr QSO korespondenta: $req_label.";
}

if (isset($_POST['delete_contest']) && !empty($_SESSION['is_admin'])) {
    $del_id = (int)$_POST['contest_id'];
    // Usuń tapetę z dysku jeśli istnieje
    $stmt = $conn->prepare("SELECT tapeta FROM contests WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $stmt->bind_result($tapeta_file);
    $stmt->fetch();
    $stmt->close();
    if ($tapeta_file) {
        $tapeta_path = __DIR__ . '/pliki/tapety/' . $tapeta_file;
        if (file_exists($tapeta_path)) {
            unlink($tapeta_path);
        }
    }
    $stmt = $conn->prepare("DELETE FROM contests WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $stmt->close();
    $info = "Zawody usunięte.";
}

if (isset($_GET['logout'])) {
    unset($_SESSION['is_admin']);
    header("Location: admin.php");
    exit;
}

$contests_list = null;
if (!empty($_SESSION['is_admin'])) {
    $contests_list = $conn->query(
        "SELECT * FROM contests ORDER BY id DESC LIMIT 10"
    );
}
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <title>Panel admina – log QSOs</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="layout.css">
</head>
<body class="p-page" style="background-color:#2F4F2F; color:#ffffff;">
<div class="container">

  <h1 class="text-center mb-4">Panel admina – log QSOs</h1>

  <?php if ($info): ?>
    <div class="alert alert-info mt-3 text-center"><?= htmlspecialchars($info) ?></div>
  <?php endif; ?>

  <?php if (empty($_SESSION['is_admin'])): ?>

    <div class="d-flex justify-content-center">
      <div class="card shadow-sm"
           style="max-width:400px; width:100%; background-color:#F5F5F5; color:#000;">
        <div class="card-body">
          <form method="post">
            <div class="mb-3">
              <label class="form-label">Login</label>
              <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Hasło</label>
              <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" name="admin_login" class="btn w-100"
                    style="background-color:#A6CE39; border-color:#7FA32C; color:#000;">
              Zaloguj jako admin
            </button>
          </form>
          <div class="mt-3 text-center">
            <a href="index.php" class="btn btn-sm mt-1"
               style="background-color:#A6CE39; border-color:#7FA32C; color:#000;">
              Powrót do loga
            </a>
          </div>
        </div>
      </div>
    </div>

  <?php else: ?>

    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
      <h2 class="mb-0">Panel admina</h2>
      <a href="admin.php?logout=1" class="btn btn-sm"
         style="background-color:#A6CE39; border-color:#7FA32C; color:#000;">
        Wyloguj admina
      </a>
    </div>

    <!-- Konfiguracja zawodów -->
    <div class="card shadow-sm mb-4">
      <div class="card-body" style="background-color:#F5F5F5; color:#000;">
        <h3>Konfiguracja zawodów</h3>
        <form method="post" enctype="multipart/form-data" class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Nazwa zawodów</label>
            <input type="text" name="contest_name" class="form-control"
                   value="Zawody kursowe" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Liczba rund</label>
            <input type="number" name="rounds" class="form-control"
                   value="2" min="1" max="10" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Czas rundy (min)</label>
            <input type="number" name="round_duration_min" class="form-control"
                   value="5" min="1" max="60" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Przerwa (min)</label>
            <input type="number" name="break_duration_min" class="form-control"
                   value="3" min="0" max="30" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Start 1. rundy</label>
            <input type="datetime-local" name="start_datetime"
                   class="form-control" required>
          </div>
          <div class="col-md-8">
            <label class="form-label">Tapeta dyplomu <span class="text-muted small">(JPG/PNG/WEBP, max 5MB)</span></label>
            <input type="file" name="tapeta" class="form-control"
                   accept="image/jpeg,image/png,image/webp">
            <div class="form-text">Jeśli nie wybierzesz, dyplom będzie bez tapety.</div>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox"
                     name="require_peer_qso_no" id="require_peer_qso_no" value="1">
              <label class="form-check-label" for="require_peer_qso_no">
                Wymagaj numeru QSO korespondenta (dla zaawansowanych)
              </label>
            </div>
          </div>
          <div class="col-12">
            <button type="submit" name="save_contest" class="btn"
                    style="background-color:#A6CE39; border-color:#7FA32C; color:#000;">
              Zapisz zawody
            </button>
          </div>
        </form>

        <!-- Start ad-hoc -->
        <div class="mt-3 p-3 border rounded" style="background-color:#fff3cd;">
          <strong>Szybki start</strong> – 2 rundy x 5 min, przerwa 3 min, start natychmiast
          <form method="post" class="mt-2">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox"
                     name="require_peer_qso_no_adhoc" id="require_peer_qso_no_adhoc" value="1">
              <label class="form-check-label" for="require_peer_qso_no_adhoc">
                Wymagaj numeru QSO korespondenta
              </label>
            </div>
            <button type="submit" name="start_now" class="btn btn-warning btn-sm"
                    onclick="return confirm('Wystartować zawody ad-hoc teraz?')">
              ⚡ Wystartuj teraz
            </button>
          </form>
        </div>

        <?php if ($contests_list && $contests_list->num_rows > 0): ?>
          <h5 class="mt-4">Ostatnie zawody</h5>
          <table class="table table-sm table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Nazwa</th>
                <th>Rundy</th>
                <th>Czas rundy</th>
                <th>Przerwa</th>
                <th>Start</th>
                <th>Nr QSO</th>
                <th>Tapeta</th>
                <th>Akcja</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($c = $contests_list->fetch_assoc()): ?>
                <tr>
                  <td><?= $c['id'] ?></td>
                  <td><?= htmlspecialchars($c['name']) ?></td>
                  <td><?= $c['rounds'] ?></td>
                  <td><?= ($c['round_duration_sec'] / 60) ?> min</td>
                  <td><?= ($c['break_duration_sec'] / 60) ?> min</td>
                  <td><?= htmlspecialchars($c['start_datetime']) ?></td>
                  <td>
                    <?php if (!empty($c['require_peer_qso_no'])): ?>
                      <span class="badge bg-warning text-dark">wymagany</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">opcjonalny</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($c['tapeta'])): ?>
                      <img src="pliki/tapety/<?= htmlspecialchars($c['tapeta']) ?>"
                           style="height:40px; border-radius:4px;" alt="tapeta">
                    <?php else: ?>
                      <span class="text-muted small">brak</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="contest_id" value="<?= $c['id'] ?>">
                      <button type="submit" name="delete_contest"
                              class="btn btn-sm btn-danger"
                              onclick="return confirm('Usunąć te zawody?')">
                        Usuń
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          <div class="text-muted small">
            Aktywne są zawody z najwyższym ID (ostatnio dodane).
          </div>
        <?php else: ?>
          <p class="mt-3 text-muted">Brak zdefiniowanych zawodów.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Reset logu -->
    <div class="card shadow-sm mb-3">
      <div class="card-body" style="background-color:#F5F5F5; color:#000;">
        <h3>Reset wyników</h3>
        <div class="alert alert-danger">
          Ta operacja kasuje <strong>wszystkie</strong> wpisy z logu, resetuje ranking
          oraz <strong>kasuje nazwy drużyn</strong>!
        </div>
        <form method="post" class="mb-3">
          <p>Żeby potwierdzić, wpisz <strong>TAK</strong> i kliknij reset.</p>
          <input type="text" name="confirm" class="form-control mb-3" required>
          <button type="submit" name="reset_log" class="btn btn-danger">
            Resetuj log
          </button>
        </form>
        <div class="mt-3">
          <a href="ranking.php" class="btn btn-sm"
             style="background-color:#A6CE39; border-color:#7FA32C; color:#000;">
            Przejdź do rankingu
          </a>
          <a href="index.php" class="btn btn-sm btn-outline-secondary ms-1">
            Powrót do loga
          </a>
        </div>
      </div>
    </div>

  <?php endif; ?>

  <?php include 'footer.php'; ?>

</div>
</body>
</html>
<?php
$conn->close();
?>
