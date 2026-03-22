<?php
require 'db.php';

header('Content-Type: application/xml; charset=utf-8');

$base = 'https://zawody.sp6zhp.pl';

// Pobierz wszystkie zawody
$result = $conn->query("SELECT id FROM contests ORDER BY id ASC");

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

  <url>
    oc><?= $base ?>/</loc>
    hangefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>

  <url>
    oc><?= $base ?>/ranking.php</loc>
    hangefreq>always</changefreq>
    <priority>0.9</priority>
  </url>

  <url>
    oc><?= $base ?>/history.php</loc>
    hangefreq>weekly</changefreq>
    <priority>0.7</priority>
  </url>

  <url>
    oc><?= $base ?>/login.php</loc>
    hangefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>

<?php if ($result && $result->num_rows > 0): ?>
<?php while ($row = $result->fetch_assoc()): ?>
  <url>
    oc><?= $base ?>/history_result.php?contest_id=<?= $row['id'] ?></loc>
    hangefreq>monthly</changefreq>
    <priority>0.6</priority>
  </url>
<?php endwhile; ?>
<?php endif; ?>

</urlset>
<?php
$conn->close();
?>
