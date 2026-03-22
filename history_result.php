<?php
session_start();
require 'db.php';

$contest_id = (int)($_GET['contest_id'] ?? 0);
if ($contest_id === 0) {
    header("Location: history.php");
    exit;
}

// Pobierz dane zawodów
$contest = null;
$stmt = $conn->prepare("SELECT * FROM contests WHERE id = ?");
$stmt->bind_param("i", $contest_id);
$stmt->execute();
$rc = $stmt->get_result();
if ($rc && $rc->num_rows > 0) {
    $contest = $rc->fetch_assoc();
}
$stmt->close();

if (!$contest) {
    header("Location: history.php");
    exit;
}

$max_rounds = (int)$contest['rounds'];

// Pobierz wyniki z widoku dla tych zawodów
$scores       = [];
$totals       = [];
$rounds_found = [];

$stmt = $conn->prepare(
    "SELECT kursant, round_no, punkty FROM ranking_view WHERE contest_id = ? ORDER BY kursant ASC, round_no ASC"
);
$stmt->bind_param("i", $contest_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $k = $row['kursant'];
        $r = (int)$row['round_no'];
        $p = (int)$row['punkty'];

        $scores[$k][$r]   = $p;
        $totals[$k]       = ($totals[$k] ?? 0) + $p;
        $rounds_found[$r] = true;
    }
}
$stmt->close();

$all_rounds = [];
for ($i = 1; $i <= $max_rounds; $i++) {
    $all_rounds[] = $i;
}
foreach (array_keys($rounds_found ?? []) as $r) {
    if (!in_array($r, $all_rounds)) {
        $all_rounds[] = $r;
    }
}
sort($all_rounds);

arsort($totals);
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <title>Wyniki – <?= htmlspecialchars($contest['name']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="layout.css">
</head>
<body class="p-page" style="background-color:#2F4F2F; color:#ffffff;">
<div class="container">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Wyniki – <?= htmlspecialchars($contest['name']) ?></h1>
    <a href="history.php" class="btn btn-sm"
       style="background-color:#A6CE39; border-color:#7FA32C; color:#000;">
      Powrót do archiwum
    </a>
  </div>

  <div class="alert alert-secondary mb-3">
    <strong><?= htmlspecialchars($contest['name']) ?></strong>
    &nbsp;|&nbsp; Rund: <?= $contest['rounds'] ?>
    &nbsp;|&nbsp; Czas rundy: <?= ($contest['round_duration_sec'] / 60) ?> min
    &nbsp;|&nbsp; Przerwa: <?= ($contest['break_duration_sec'] / 60) ?> min
    &nbsp;|&nbsp; Start: <?= htmlspecialchars($contest['start_datetime']) ?>
    <?php if (!empty($contest['require_peer_qso_no'])): ?>
      &nbsp;|&nbsp; <span class="badge bg-warning text-dark">Nr QSO wymagany</span>
    <?php endif; ?>
  </div>

  <div class="card shadow-sm">
    <div class="card-body" style="background-color:#F5F5F5; color:#000;">
      <table class="table table-striped table-sm mb-0">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>Kursant</th>
            <?php foreach ($all_rounds as $r): ?>
              <th class="text-center">Runda <?= $r ?></th>
            <?php endforeach; ?>
            <th class="text-center fw-bold">Razem</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($totals)): ?>


<?php $lp = 1; foreach ($totals as $kursant => $total): ?>
  <tr>
    <td>
      <?php if ($lp <= 3): ?>
        <?= ['', '🥇', '🥈', '🥉'][$lp] ?>
      <?php else: ?>
        <?= $lp ?>
      <?php endif; ?>
    </td>
    <td>
      <strong><?= htmlspecialchars($kursant) ?></strong>
      <?php if ($lp <= 3): ?>
        <a href="diploma.php?contest_id=<?= $contest_id ?>&kursant=<?= urlencode($kursant) ?>"
           class="btn btn-sm ms-2"
           style="background-color:#A6CE39; border-color:#7FA32C; color:#000;"
           target="_blank">
          Dyplom
        </a>
      <?php endif; ?>
    </td>
    <?php foreach ($all_rounds as $r): ?>
      <td class="text-center">
        <?= isset($scores[$kursant][$r]) ? $scores[$kursant][$r] : '–' ?>
      </td>
    <?php endforeach; ?>
    <td class="text-center fw-bold" style="background-color:#e8f5e9;">
      <?= $total ?>
    </td>
  </tr>
<?php $lp++; endforeach; ?>


          <?php else: ?>
            <tr>
              <td colspan="<?= 3 + count($all_rounds) ?>" class="text-center text-muted">
                Brak przyznanych punktów dla tych zawodów.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php include 'footer.php'; ?>
</body>
</html>
<?php
$conn->close();
?>
