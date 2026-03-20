<?php
session_start();
require 'db.php';

if (!isset($_SESSION["kursant_id"]) || !isset($_SESSION["kursant_login"])) {
    header("Location: login.php");
    exit;
}

$kursant_id    = $_SESSION["kursant_id"];
$kursant_login = $_SESSION["kursant_login"];

$contest    = null;
$round_no   = null;
$round_info = "Brak aktywnych zawodów";

$stmt = $conn->prepare("SELECT * FROM contests ORDER BY id DESC LIMIT 1");
$stmt->execute();
$result_contest = $stmt->get_result();
if ($result_contest && $result_contest->num_rows > 0) {
    $contest = $result_contest->fetch_assoc();
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

$stmt = $conn->prepare("SELECT COALESCE(MAX(my_qso_no),0) AS max_no FROM qso_log WHERE kursant = ?");
$stmt->bind_param("s", $kursant_login);
$stmt->execute();
$stmt->bind_result($max_no);
$stmt->fetch();
$stmt->close();
$next_my_qso_no = $max_no + 1;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $znak_qso    = trim($_POST["znak_qso"]    ?? "");
    $peer_qso_no = trim($_POST["peer_qso_no"] ?? "");

    if ($znak_qso !== "" && $peer_qso_no !== "") {
        $stmt = $conn->prepare("SELECT COALESCE(MAX(my_qso_no),0) AS max_no FROM qso_log WHERE kursant = ?");
        $stmt->bind_param("s", $kursant_login);
        $stmt->execute();
        $stmt->bind_result($max_no);
        $stmt->fetch();
        $stmt->close();
        $my_qso_no = $max_no + 1;

        // przelicz rundę uwzględniając przerwę
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

        $stmt = $conn->prepare(
            "INSERT INTO qso_log (kursant, znak_qso, raport, my_qso_no, peer_qso_no, round_no)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $raport_text  = $peer_qso_no;
        $peer_qso_val = (int)$peer_qso_no;
        $stmt->bind_param("sssiis", $kursant_login, $znak_qso, $raport_text, $my_qso_no, $peer_qso_val, $round_no_insert);
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
  WHERE kursant = ?
  ORDER BY id DESC
  LIMIT 100
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $kursant_login);
$stmt->execute();
$result = $stmt->get_result();
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <title>Log łączności – kurs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="layout.css">
</head>
<body class="p-page" style="background-color:#2F4F2F; color:#ffffff;">

<div class="container">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Log łączności – kurs</h1>
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
      <form method="post" class="row g-3">
        <div class="col-md-3">
         <label class="form-label">Znak korespondenta</label>
          <input type="text" name="znak_qso" class="form-control" required
                 <?= ($round_no === null) ? 'disabled' : '' ?>>
        </div>
        <div class="col-md-3">
          <label class="form-label">Nr QSO korespondenta</label>
          <input type="number" name="peer_qso_no" class="form-control" required
                 placeholder="np. 1, 2 itd."
                 <?= ($round_no === null) ? 'disabled' : '' ?>>
        </div>
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
                    nr. mojego QSO: <?= htmlspecialchars($row["my_qso_no"]) ?>,
                    nr. QSO korespondenta: <?= htmlspecialchars($row["peer_qso_no"]) ?>
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
setInterval(function() {
    fetch('status.php')
        .then(r => r.json())
        .then(data => {
            document.getElementById('status-bar').className =
                'alert mb-3 ' + (data.round_no ? 'alert-success' : 'alert-warning');
            document.getElementById('status-text').innerHTML =
                '<strong>Status zawodów:</strong> ' + data.round_info +
                (data.round_no ? ' &nbsp;|&nbsp; <strong>Aktualna runda: ' + data.round_no + '</strong>' : '');
            // odblokuj/zablokuj formularz
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
