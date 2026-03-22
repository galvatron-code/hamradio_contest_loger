<?php
session_start();
require 'db.php';

$stmt = $conn->prepare("SELECT * FROM contests ORDER BY id DESC");
$stmt->execute();
$result = $stmt->get_result();
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <title>Archiwum wyników – INO2026</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="layout.css">
</head>
<body class="p-page" style="background-color:#2F4F2F; color:#ffffff;">
<div class="container">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Archiwum wyników</h1>
    <a href="index.php" class="btn btn-sm"
       style="background-color:#A6CE39; border-color:#7FA32C; color:#000;">
      Powrót do loga
    </a>
  </div>

  <div class="card shadow-sm">
    <div class="card-body" style="background-color:#F5F5F5; color:#000;">
      <?php if ($result && $result->num_rows > 0): ?>
        <table class="table table-striped table-sm mb-0">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Nazwa zawodów</th>
              <th>Rundy</th>
              <th>Czas rundy</th>
              <th>Przerwa</th>
              <th>Start</th>
              <th>Nr QSO</th>
              <th>Wyniki</th>
            </tr>
          </thead>
          <tbody>
            <?php $lp = 1; while ($c = $result->fetch_assoc()): ?>
              <tr>
                <td><?= $lp++ ?></td>
                <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
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
                  <a href="history_result.php?contest_id=<?= $c['id'] ?>"
                     class="btn btn-sm"
                     style="background-color:#A6CE39; border-color:#7FA32C; color:#000;">
                    Zobacz wyniki
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-muted">Brak zawodów w archiwum.</p>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php include 'footer.php'; ?>
</body>
</html>
<?php
$conn->close();
?>
