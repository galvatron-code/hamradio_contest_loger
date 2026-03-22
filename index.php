<?php
session_start();
require 'db.php';

if (!isset($_SESSION["kursant_id"]) || !isset($_SESSION["kursant_login"])) {
    header("Location: login.php");
    exit;
}

$kursant_id    = $_SESSION["kursant_id"];
$kursant_login = strtoupper($_SESSION["kursant_login"]);

$contest    = null;
$round_no   = null;
$round_info = "Brak aktywnych zawodów";
$req_peer   = false;

$stmt = $conn->prepare("SELECT * FROM contests ORDER BY id DESC LIMIT 1");
$stmt->execute();
$result_contest = $stmt->get_result();
if ($result_contest && $result_contest->num_rows > 0) {
    $contest  = $result_contest->fetch_assoc();
    $req_peer = !empty($contest['require_peer_qso_no']);

    if (!empty($contest['start_datetime'])) {
        $start   = strtotime($contest['start_datetime']);
        $now     = time();
        $elapsed = $now - $start;
        $dur     = (int)$contest['round_duration_sec'];
        $brk     = (int)$contest['break_duration_sec'];
        $cycle   = $dur + $brk;
        $rounds  = (int)$contest['rounds'];

        if ($elapsed < 0) {
            $round_info = "Start zawodów za: " . gmdate("H:i:s", abs($elapsed));
        } else {
            $cycle_index   = intdiv($elapsed, $cycle);
            $cycle_elapsed = $elapsed % $cycle;

            if ($cycle_index >= $rounds) {
                $round_no   = null;
                $round_info = "Zawody zakończone";
            } elseif ($cycle_elapsed < $dur) {
                $round_no   = $cycle_index + 1;
                $secs_left  = $dur - $cycle_elapsed;
                $round_info = "Runda $round_no z $rounds – pozostało: " . gmdate("i:s", $secs_left);
            } else {
                $round_no   = null;
                $secs_left  = $cycle - $cycle_elapsed;
                $next_round = $cycle_index + 2;
                $round_info = "Przerwa – Runda $next_round za: " . gmdate("i:s", $secs_left);
            }
        }
    }
}
$stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(MAX(my_qso_no),0) AS max_no FROM qso_log WHERE kursant = ? AND contest_id = ?");
$stmt->bind_param("si", $kursant_login, $contest['id']);
$stmt->execute();
$stmt->bind_result($max_no);
$stmt->fetch();
$stmt->close();
$next_my_qso_no = $max_no + 1;

$form_error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $znak_qso    = strtoupper(trim($_POST["znak_qso"]    ?? ""));
    $peer_qso_no = trim($_POST["peer_qso_no"] ?? "");

    // Walidacja znaku
    if ($znak_qso === "") {
        $form_error = "Pole znaku korespondenta nie może być puste.";
    } elseif (!preg_match('/[A-Z]/', $znak_qso) || !preg_match('/[0-9]/', $znak_qso)) {
        $form_error = "Nieprawidłowy znak – powinien zawierać litery i cyfry, np. SP6ZHP lub ZHP001.";
    } elseif (!preg_match('/^[A-Z0-9]+$/', $znak_qso)) {
        $form_error = "Znak może zawierać tylko litery i cyfry (bez spacji ani znaków specjalnych).";
    } elseif ($znak_qso === $kursant_login) {
        $form_error = "Nie możesz zapisać łączności sam ze sobą.";
    } elseif ($req_peer && $peer_qso_no === "") {
        $form_error = "Pole numeru QSO korespondenta nie może być puste.";
    } elseif ($req_peer && (!is_numeric($peer_qso_no) || (int)$peer_qso_no < 1)) {
        $form_error = "Numer QSO korespondenta musi być liczbą całkowitą większą od 0.";
    }

    if ($form_error === "") {
        $stmt = $conn->prepare("SELECT COALESCE(MAX(my_qso_no),0) AS max_no FROM qso_log WHERE kursant = ? AND contest_id = ?");
        $stmt->bind_param("si", $kursant_login, $contest['id']);
        $stmt->execute();
        $stmt->bind_result($max_no);
        $stmt->fetch();
        $stmt->close();
        $my_qso_no = $max_no + 1;

        $round_no_insert = null;
        if ($contest && !empty($contest['start_datetime'])) {
            $start   = strtotime($contest['start_datetime']);
            $now     = time();
            $elapsed = $now - $start;
            $dur     = (int)$contest['round_duration_sec'];
            $brk     = (int)$contest['break_duration_sec'];
            $cycle   = $dur + $brk;
            $rounds  = (int)$contest['rounds'];

            if ($elapsed >= 0) {
                $cycle_index   = intdiv($elapsed, $cycle);
                $cycle_elapsed = $elapsed % $cycle;
                if ($cycle_index < $rounds && $cycle_elapsed < $dur) {
                    $round_no_insert = $cycle_index + 1;
                }
            }
        }

        $raport_text  = '';
        $peer_qso_val = $req_peer ? (int)$peer_qso_no : null;
        $contest_id   = (int)$contest['id'];

        $stmt = $conn->prepare(
            "INSERT INTO qso_log (kursant, znak_qso, raport, my_qso_no, peer_qso_no, round_no, contest_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssiiis", $kursant_login, $znak_qso, $raport_text, $my_qso_no, $peer_qso_val, $round_no_insert, $contest_id);
        $stmt->execute();
        $stmt->close();

        header("Location: index.php");
        exit;
    }
}

$sql = "
  SELECT id, kursant, znak_qso,
         DATE(created_at) AS data_qso,
         TIME(created_at) AS czas_qso,
         raport, my_qso_no, peer_qso_no, round_no, created_at
  FROM qso_log
  WHERE kursant = ? AND contest_id = ?
  ORDER BY id DESC
  LIMIT 100
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $kursant_login, $contest['id']);
$stmt->execute();
$result = $stmt->get_result();
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <title>Log łączności – INO2026</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="layout.css">

</head>
<body class="p-page" style="background-color:#2F4F2F; color:#ffffff;">

<div class="container">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Log łączności – INO2026</h1>
    <div class="text-end">
      <div>Zalogowany: <strong><?= htmlspecialchars($kursant_login) ?></strong></div>
      <div>Twoje następne QSO będzie nr: <strong><?= htmlspecialchars($next_my_qso_no) ?></strong></div>
      <a class="btn btn-sm mt-1"
         style="background-color:#A6CE39; border-color:#7FA32C; color:#000;"
         href="logout.php">Wyloguj</a>
    </div>
  </div>

  <div id="status-bar" class="alert mb-3 <?= ($round_no !== null) ? 'alert-success' : 'alert-warning' ?>" role="alert">
    <span id="status-text">
      <strong>Status zawodów:</strong> <?= htmlspecialchars($round_info) ?>
      <?php if ($round_no !== null): ?>
        &nbsp;|&nbsp; <strong>Aktualna runda: <?= $round_no ?></strong>
      <?php endif; ?>
    </span>
  </div>

  <div class="card shadow-sm mb-4" style="background-color:#F5F5F5; color:#000;">
    <div class="card-body">
      <form method="post" class="row g-3" id="qso-form">

        <div class="col-md-3">
          <label class="form-label">Znak korespondenta</label>
          <input type="text" name="znak_qso" id="znak_qso"
                 class="form-control <?= $form_error ? 'is-invalid' : '' ?>"
                 placeholder="np. SP6ZHP, ZHP001"
                 value="<?= $form_error ? htmlspecialchars(strtoupper($_POST['znak_qso'] ?? '')) : '' ?>"
                 autocomplete="off"
                 <?= ($round_no === null) ? 'disabled' : '' ?>>
          <?php if ($form_error): ?>
            <div class="invalid-feedback"><?= htmlspecialchars($form_error) ?></div>
          <?php endif; ?>
        </div>

        <?php if ($req_peer): ?>
        <div class="col-md-3">
          <label class="form-label">Nr QSO korespondenta</label>
          <input type="number" name="peer_qso_no" id="peer_qso_no"
                 class="form-control"
                 placeholder="np. 1, 2, 3..."
                 value="<?= $form_error ? htmlspecialchars($_POST['peer_qso_no'] ?? '') : '' ?>"
                 min="1"
                 <?= ($round_no === null) ? 'disabled' : '' ?>>
        </div>
        <?php endif; ?>

        <div class="col-md-3 d-flex align-items-end">
          <button type="submit" class="btn w-100"
                  style="background-color:#A6CE39; border-color:#7FA32C; color:#000;"
                  <?= ($round_no === null) ? 'disabled' : '' ?>>
            Zapisz QSO
          </button>
        </div>

      </form>
      <?php if ($round_no === null): ?>
        <div class="mt-2 text-muted small">Formularz aktywny tylko w trakcie trwania rundy.</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body" style="background-color:#F5F5F5; color:#000;">
      <h2>Ostatnie łączności</h2>
      <div class="row g-3">
        <?php if ($result && $result->num_rows > 0): ?>
          <?php while ($row = $result->fetch_assoc()): ?>
            <div class="col-12 col-md-6 col-lg-4">
              <div class="card shadow-sm">
                <div class="card-body">
                  <div>
                    <strong><?= htmlspecialchars($row["znak_qso"]) ?></strong>
                    <?php if (!empty($row["round_no"])): ?>
                      <span class="badge" style="background-color:#A6CE39; color:#000;">
                        R<?= htmlspecialchars($row["round_no"]) ?>
                      </span>
                    <?php endif; ?>
                  </div>
                  <div class="small text-muted">
                    Data: <?= htmlspecialchars($row["data_qso"]) ?>,
                    Czas: <?= htmlspecialchars($row["czas_qso"]) ?>
                  </div>
                  <div class="small">
                    nr. mojego QSO: <?= htmlspecialchars($row["my_qso_no"]) ?>
                    <?php if ($req_peer): ?>
                      , nr. QSO korespondenta: <?= htmlspecialchars($row["peer_qso_no"]) ?>
                    <?php endif; ?>
                  </div>
                  <div class="small text-muted">
                    Dodano: <?= htmlspecialchars($row["created_at"]) ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <p>Brak łączności w logu.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<?php include 'footer.php'; ?>
<script>
document.getElementById('znak_qso').addEventListener('input', function() {
    let pos = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(pos, pos);
});

setInterval(function() {
    fetch('status.php')
        .then(r => r.json())
        .then(data => {
            document.getElementById('status-bar').className =
                'alert mb-3 ' + (data.round_no ? 'alert-success' : 'alert-warning');
            document.getElementById('status-text').innerHTML =
                '<strong>Status zawodów:</strong> ' + data.round_info +
                (data.round_no ? ' &nbsp;|&nbsp; <strong>Aktualna runda: ' + data.round_no + '</strong>' : '');
            document.querySelectorAll('form input, form button').forEach(el => {
                el.disabled = !data.round_no;
            });
        });
}, 5000);
</script>

</body>
</html>
<?php
$conn->close();
?>
