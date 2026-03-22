<?php
session_start();
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $login = $_POST["login"] ?? "";
    $haslo = $_POST["haslo"] ?? "";

    $stmt = $conn->prepare("SELECT id, haslo, display_name FROM kursanci WHERE login = ?");
    $stmt->bind_param("s", $login);
    $stmt->execute();
    $stmt->bind_result($id, $hash, $display_name);
    if ($stmt->fetch() && hash('sha256', $haslo) === $hash) {
        $_SESSION["kursant_id"]    = $id;
        $_SESSION["kursant_login"] = $login;
        $stmt->close();
        // Jeśli brak nazwy drużyny — przekieruj do profilu
        if (empty($display_name)) {
            header("Location: profile.php?redirect=1");
        } else {
            header("Location: index.php");
        }
        exit;
    }
    $stmt->close();
}
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <title>Logowanie kursant</title>
  <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="layout.css">  
</head>
<body class="p-page" style="background-color:#2F4F2F; color:#ffffff;">

<center>
<div class="container">

  <h1 class="mb-4">Log QSOs – logowanie</h1>

  <div class="card shadow-sm mx-auto" style="max-width:400px; background-color:#F5F5F5; color:#000;">
    <div class="card-body">
      <form method="post">
        <div class="mb-3">
          <label class="form-label">Login (np. ZHP001)</label>
          <input type="text" name="login" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Hasło</label>
          <input type="password" name="haslo" class="form-control" required>
        </div>
        <button class="btn w-100"
                type="submit"
                style="background-color:#A6CE39; border-color:#7FA32C; color:#000;">
          Zaloguj
        </button>
      </form>
    </div>
  </div>

  <div class="mb-3 mt-4">
    <a href="https://sp6zhp.pl/">
      <img src="logo-zhp.png" alt="SP6ZHP"
           style="max-width:20%; height:auto;">
    </a>
    <br>Zapraszamy do pracy w zawodach!</br>
    <br>

    <a class="btn btn-sm mt-1 me-2"
       style="background-color:#A6CE39; border-color:#7FA32C; color:#000;"
       href="ranking.php">
      Ranking
    </a>

    <a class="btn btn-sm mt-1 me-2"
       style="background-color:#A6CE39; border-color:#7FA32C; color:#000;"
       href="history.php">
      Archiwum
    </a>

    <a class="btn btn-sm mt-1 me-2"
       style="background-color:#A6CE39; border-color:#7FA32C; color:#000;"
       href="admin.php">
      Admin
    </a>

  </div>

<?php include 'footer.php'; ?>

</div>
</center>

</body>
</html>
