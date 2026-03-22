<?php
session_start();
require 'db.php';

$contest_id = (int)($_GET['contest_id'] ?? 0);
$kursant    = strtoupper(trim($_GET['kursant'] ?? ''));

if ($contest_id === 0 || $kursant === '') {
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

// Pobierz display_name kursanta
$stmt = $conn->prepare("SELECT display_name FROM kursanci WHERE login = ?");
$stmt->bind_param("s", $kursant);
$stmt->execute();
$stmt->bind_result($display_name);
$stmt->fetch();
$stmt->close();
$display_name = !empty($display_name) ? $display_name : $kursant;

// Pobierz ranking dla tych zawodów
$scores = [];
$totals = [];
$stmt = $conn->prepare(
    "SELECT kursant, round_no, punkty FROM ranking_view WHERE contest_id = ? ORDER BY kursant ASC, round_no ASC"
);
$stmt->bind_param("i", $contest_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $k = $row['kursant'];
    $r = (int)$row['round_no'];
    $p = (int)$row['punkty'];
    $scores[$k][$r] = $p;
    $totals[$k]     = ($totals[$k] ?? 0) + $p;
}
$stmt->close();
arsort($totals);

// Ustal miejsce kursanta
$miejsce     = 0;
$total_pts   = 0;
$lp          = 1;
foreach ($totals as $k => $pts) {
    if ($k === $kursant) {
        $miejsce   = $lp;
        $total_pts = $pts;
        break;
    }
    $lp++;
}

if ($miejsce === 0 || $miejsce > 3) {
    header("Location: history_result.php?contest_id=$contest_id");
    exit;
}

$miejsce_txt = ['', 'I miejsce', 'II miejsce', 'III miejsce'];
$miejsce_en  = ['', '1st Place', '2nd Place', '3rd Place'];
$medal_color = ['', '#FFD700', '#C0C0C0', '#CD7F32']; // złoty, srebrny, brązowy
$medal_emoji = ['', '🥇', '🥈', '🥉'];

$data_zawodow = date('d.m.Y', strtotime($contest['start_datetime']));
// Tapeta dyplomu
$tapeta_url = '';
if (!empty($contest['tapeta'])) {
    $tapeta_url = 'pliki/tapety/' . htmlspecialchars($contest['tapeta']);
}

?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <title>Dyplom – <?= htmlspecialchars($display_name) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="layout.css">
  <style>
    body {
      background-color: #2F4F2F;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 20px;
    }

    .no-print {
      margin-bottom: 20px;
      display: flex;
      gap: 10px;
    }

    @media print {
      body {
        background-color: #fff;
        padding: 0;
      }
      .no-print {
        display: none !important;
      }
      .diploma {
        box-shadow: none !important;
        border: 4px double #2F4F2F !important;
      }
    }

    .diploma {
      background: #fff;
      <?php if ($tapeta_url): ?>
      background-image: url('<?= $tapeta_url ?>');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      <?php endif; ?>
      width: 210mm;
      min-height: 148mm;
      padding: 15mm 15mm;
      box-shadow: 0 0 30px rgba(0,0,0,0.4);
      border: 4px double #2F4F2F;
      border-radius: 8px;
      text-align: center;
      font-family: Georgia, serif;
      position: relative;
    }

    .diploma-overlay {
    position: relative;
    z-index: 1;
    background: rgba(255,255,255,0.82);
    border-radius: 6px;
    padding: 10mm 10mm;
    }

    .diploma-header {
      font-size: 13px;
      color: #555;
      letter-spacing: 2px;
      text-transform: uppercase;
      margin-bottom: 5px;
    }

    .diploma-title {
      font-size: 42px;
      font-weight: bold;
      color: #2F4F2F;
      margin: 10px 0 5px;
      letter-spacing: 3px;
    }

    .diploma-subtitle {
      font-size: 16px;
      color: #555;
      margin-bottom: 20px;
    }

    .diploma-medal {
      font-size: 80px;
      line-height: 1;
      margin: 10px 0;
    }

    .diploma-miejsce {
      font-size: 32px;
      font-weight: bold;
      color: <?= $medal_color[$miejsce] ?>;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
      margin: 5px 0;
      letter-spacing: 2px;
    }

    .diploma-name {
      font-size: 36px;
      font-weight: bold;
      color: #1a1a1a;
      margin: 15px 0 5px;
      border-bottom: 2px solid #2F4F2F;
      display: inline-block;
      padding: 0 20px 5px;
    }

    .diploma-callsign {
      font-size: 18px;
      color: #555;
      margin-bottom: 15px;
    }

    .diploma-contest {
      font-size: 16px;
      color: #333;
      margin: 10px 0 5px;
    }

    .diploma-points {
      font-size: 15px;
      color: #555;
      margin-bottom: 20px;
    }

    .diploma-footer {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      margin-top: 20px;
      padding-top: 10px;
      border-top: 1px solid #ccc;
    }

    .diploma-footer-left {
      text-align: left;
      font-size: 12px;
      color: #777;
    }

    .diploma-footer-right {
      text-align: right;
      font-size: 12px;
      color: #777;
    }

    .diploma-logo {
      max-height: 60px;
      margin-bottom: 10px;
    }

    .diploma-sign {
      border-top: 1px solid #333;
      padding-top: 5px;
      margin-top: 30px;
      font-size: 12px;
      color: #555;
      width: 150px;
      display: inline-block;
    }
  </style>
</head>
<body>

  <!-- Przyciski nad dyplomem — ukryte przy druku -->
  <div class="no-print">
    <button onclick="window.print()" class="btn"
            style="background-color:#A6CE39; border-color:#7FA32C; color:#000;">
      Drukuj / Zapisz PDF
    </button>
    <a href="history_result.php?contest_id=<?= $contest_id ?>" class="btn btn-outline-light">
      Powrót do wyników
    </a>
  </div>

  <!-- Dyplom -->
  <div class="diploma">

    <div class="diploma-header">Klub Krótkofalarski SP6ZHP</div>

    <img src="logo-zhp.png" alt="SP6ZHP" class="diploma-logo"><br>

    <div class="diploma-title">DYPLOM</div>
    <div class="diploma-subtitle">za udział w zawodach krótkofalarskich</div>

    <div class="diploma-medal"><?= $medal_emoji[$miejsce] ?></div>
    <div class="diploma-miejsce"><?= $miejsce_txt[$miejsce] ?></div>

    <div class="diploma-name"><?= htmlspecialchars($display_name) ?></div>
    <?php if ($display_name !== $kursant): ?>
      <div class="diploma-callsign"><?= htmlspecialchars($kursant) ?></div>
    <?php endif; ?>

    <div class="diploma-contest">
      <strong><?= htmlspecialchars($contest['name']) ?></strong>
    </div>
    <div class="diploma-points">
      Liczba łączności: <strong><?= $total_pts ?></strong>
      &nbsp;|&nbsp; Rundy: <?= $contest['rounds'] ?>
      &nbsp;|&nbsp; <?= $data_zawodow ?>
    </div>

    <div class="diploma-footer">
      <div class="diploma-footer-left">
        zawody.sp6zhp.pl<br>
        <small>Wyniki wygenerowane automatycznie</small>
      </div>
      <div>
        <div class="diploma-sign">Organizator zawodów</div>
      </div>
      <div class="diploma-footer-right">
        <?= $data_zawodow ?><br>
        <small>SP6ZHP</small>
      </div>
    </div>

  </div>

</body>
</html>
<?php $conn->close(); ?>
