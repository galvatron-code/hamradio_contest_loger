<?php
session_start();
require 'db.php';

// Pobierz aktywny contest (ostatni)
$contest  = null;
$max_rounds = 1;
$stmt = $conn->prepare("SELECT * FROM contests ORDER BY id DESC LIMIT 1");
$stmt->execute();
$rc = $stmt->get_result();
if ($rc && $rc->num_rows > 0) {
    $contest    = $rc->fetch_assoc();
    $max_rounds = (int)$contest['rounds'];
}
$stmt->close();

// Pobierz sparowane QSO z podziałem na rundy
// (bez CTE – podzapytanie zagnieżdżone)
$sql = "
SELECT kursant, round_no, COUNT(*) AS punkty
FROM (
  SELECT l1.kursant AS kursant, l1.round_no AS round_no
  FROM qso_log l1
  JOIN qso_log l2
    ON l1.kursant <> l2.kursant
   AND l1.znak_qso = l2.kursant
   AND l2.znak_qso = l1.kursant
   AND l1.my_qso_no = l2.peer_qso_no
   AND l2.my_qso_no = l1.peer_qso_no
   AND l1.id < l2.id
   AND l1.round_no = l2.round_no
   AND ABS(TIMESTAMPDIFF(SECOND, l1.created_at, l2.created_at)) <= 45
  GROUP BY
    LEAST(l1.kursant, l2.kursant),
    GREATEST(l1.kursant, l2.kursant),
    l1.round_no

  UNION ALL

  SELECT l2.kursant AS kursant, l2.round_no AS round_no
  FROM qso_log l1
  JOIN qso_log l2
    ON l1.kursant <> l2.kursant
   AND l1.znak_qso = l2.kursant
   AND l2.znak_qso = l1.kursant
   AND l1.my_qso_no = l2.peer_qso_no
   AND l2.my_qso_no = l1.peer_qso_no
   AND l1.id < l2.id
   AND l1.round_no = l2.round_no
   AND ABS(TIMESTAMPDIFF(SECOND, l1.created_at, l2.created_at)) <= 45
  GROUP BY
    LEAST(l1.kursant, l2.kursant),
    GREATEST(l1.kursant, l2.kursant),
    l1.round_no
) AS wszyscy
GROUP BY kursant, round_no
ORDER BY kursant ASC, round_no ASC
";

$result = $conn->query($sql);

// Zbuduj tablicę: $scores[kursant][runda] = punkty
$scores  = [];   // $scores['ZHP001'][1] = 3
$totals  = [];   // $totals['ZHP001'] = 5
$rounds_found = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $k = $row['kursant'];
        $r = (int)$row['round_no'];
        $p = (int)$row['punkty'];

        $scores[$k][$r] = $p;
        $totals[$k]     = ($totals[$k] ?? 0) + $p;
        $rounds_found[$r] = true;
    }
}

// Jeśli są rundy w danych, użyj ich; jeśli nie – pokaż przynajmniej tyle ile w contest
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

// Posortuj kursantów malejąco po sumie
arsort($totals);
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <title>Ranking QSO – kurs</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="refresh" content="5">
  <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="layout.css">
</head>

<body class="p-page" style="background-color:#2F4F2F; color:#ffffff;">
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Ranking QSO – kurs</h1>
    <a href="index.php" class="btn btn-sm"
       style="background-color:#A6CE39; border-color:#7FA32C; color:#000;">
      Powrót do loga
    </a>
  </div>

   <?php if ($contest): ?>
    <div class="alert alert-secondary mb-3">
      <strong><?= htmlspecialchars($contest['name']) ?></strong>
      &nbsp;|&nbsp; Rund: <?= $contest['rounds'] ?>
      &nbsp;|&nbsp; Czas rundy: <?= ($contest['round_duration_sec'] / 60) ?> min
      &nbsp;|&nbsp; Przerwa: <?= ($contest['break_duration_sec'] / 60) ?> min
      &nbsp;|&nbsp; Start: <?= htmlspecialchars($contest['start_datetime']) ?>
    </div>
  <?php endif; ?>

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
                <td><?= $lp++ ?></td>
                <td><strong><?= htmlspecialchars($kursant) ?></strong></td>
                <?php foreach ($all_rounds as $r): ?>
                  <td class="text-center">
                    <?= isset($scores[$kursant][$r])
                        ? $scores[$kursant][$r]
                        : '–' ?>
                  </td>
                <?php endforeach; ?>
                <td class="text-center fw-bold"
                    style="background-color:#e8f5e9;">
                  <?= $total ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="<?= 3 + count($all_rounds) ?>">
                Brak przyznanych punktów.
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
