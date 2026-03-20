<?php
// logout.php – wylogowanie kursanta
session_start();

// wyczyść wszystkie zmienne sesji
$_SESSION = [];

// usuń cookie sesji (opcjonalnie, ale warto)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// zniszcz sesję
session_destroy();

// przekierowanie na stronę logowania
header("Location: login.php");
exit;

