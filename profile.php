<?php
session_start();
require 'db.php';

if (!isset($_SESSION["kursant_id"]) || !isset($_SESSION["kursant_login"])) {
    header("Location: login.php");
    exit;
}

$kursant_id    = $_SESSION["kursant_id"];
$kursant_login = strtoupper($_SESSION["kursant_login"]);
$info          = "";

// Pobierz aktualną nazwę
$stmt = $conn->prepare("SELECT display_name FROM kursanci WHERE id = ?");
$stmt->bind_param("i", $kursant_id);
$stmt->execute();
$stmt->bind_result($display_name);
$stmt->fetch();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_name = trim($_POST["display_name"] ?? "");
    if (mb_strlen($new_name) > 100) {
        $info = "Nazwa może mieć maksymalnie 100 znaków.";
    } else {
        $stmt = $conn->prepare("UPDATE kursanci SET display_name = ? WHERE id = ?");
        $stmt->bind_param("si", $new_name, $kursant_id);
        $stmt->execute();
        $stmt->close();
        $display_name = $new_name;
        $info = "Nazwa została zapisana!";

        // jeśli przyszliśmy z redirect po logowaniu
        if (!empty($_POST["redirect"])) {
            header("Location: index.php");
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <title>Nazwa drużyny – INO2026</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="layout.css">
</head>
<body class="p-page" style="background-color:#2F4F2F; color:#ffffff;">
<div class="container">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Nazwa drużyny</h1>
    <a href="index.php" class="btn btn-sm"
       style="background-color:#A6CE39; border-color:#7FA32C; color:#000;">
      Powrót do loga
    </a>
  </div>

  <?php if ($info): ?>
    <div class="alert <?= str_contains($info, '!') ? 'alert-success' : 'alert-warning' ?>">
      <?= htmlspecialchars($info) ?>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body" style="background-color:#F5F5F5; color:#000;">
      <p>Zalogowany jako: <strong><?= htmlspecialchars($kursant_login) ?></strong></p>
      <p class="text-muted">Podaj nazwę swojej drużyny lub imię i nazwisko — pojawi się na dyplomie.
         Możesz zostawić puste — wtedy użyjemy znaku <strong><?= htmlspecialchars($kursant_login) ?></strong>.</p>

      <form method="post">
        <input type="hidden" name="redirect" value="<?= isset($_GET['redirect']) ? '1' : '' ?>">
        <div class="mb-3">
          <label class="form-label">Nazwa drużyny / Imię i nazwisko</label>
          <input type="text" name="display_name" class="form-control"
                 placeholder="np. Głodne Wilczki, Jan Kowalski"
                 value="<?= htmlspecialchars($display_name ?? '') ?>"
                 maxlength="100">
          <div class="form-text">Maksymalnie 100 znaków. Możesz zostawić puste.</div>
        </div>
        <button type="submit" class="btn me-2"
                style="background-color:#A6CE39; border-color:#7FA32C; color:#000;">
          Zapisz nazwę
        </button>
        <a href="index.php" class="btn btn-outline-secondary">
          Pomiń
        </a>
      </form>
    </div>
  </div>

</div>
<?php include 'footer.php'; ?>
</body>
</html>
<?php $conn->close(); ?>
