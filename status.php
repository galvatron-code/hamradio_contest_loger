<?php
require 'db.php';
date_default_timezone_set('Europe/Warsaw');

$round_no   = null;
$round_info = "Brak aktywnych zawodów";

$stmt = $conn->prepare("SELECT * FROM contests ORDER BY id DESC LIMIT 1");
$stmt->execute();
$rc = $stmt->get_result();
if ($rc && $rc->num_rows > 0) {
    $contest = $rc->fetch_assoc();
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
                $round_info = "Zawody zakończone";
            } elseif ($cycle_elapsed < $dur) {
                $round_no   = $cycle_index + 1;
                $secs_left  = $dur - $cycle_elapsed;
                $round_info = "Runda $round_no z $rounds – pozostało: " . gmdate("i:s", $secs_left);
            } else {
                $secs_left  = $cycle - $cycle_elapsed;
                $next_round = $cycle_index + 2;
                $round_info = "Przerwa – Runda $next_round za: " . gmdate("i:s", $secs_left);
            }
        }
    }
}
$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode(['round_no' => $round_no, 'round_info' => $round_info]);
?>
