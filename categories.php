<?php
// categories.php — GameVault category catalogue (public).
// Lists every category with its game count + sample titles.
// Procedural PHP + PDO prepared statements, htmlspecialchars on output.

require_once __DIR__ . '/includes/db.php';

$page_title  = 'Categories';
$active_page = 'categories';

$categories = [];
$db_error   = '';

try {
    // One query: categories + counts + up to 3 sample titles via GROUP_CONCAT.
    $stmt = $conn->prepare(
        'SELECT c.category_id, c.name, c.slug, c.icon, c.description, ' .
        'COUNT(g.game_id) AS total, ' .
        'SUBSTRING_INDEX(GROUP_CONCAT(g.title ORDER BY g.discount_percent DESC SEPARATOR \'|\'), \'|\', 3) AS samples ' .
        'FROM categories c LEFT JOIN games g ON g.category_id = c.category_id ' .
        'GROUP BY c.category_id ORDER BY c.name ASC'
    );
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $categories[] = $row;
    }
} catch (PDOException $e) {
    $db_error = 'Could not load categories right now. Please try again later.';
}

require_once __DIR__ . '/includes/header.php';
?>

<h2 class="section-title reveal">Game Categories</h2>
<p class="muted reveal"><?php echo (int)count($categories); ?> vault<?php echo (count($categories) === 1) ? '' : 's'; ?> &mdash; pick one to filter the store.</p>

<?php if ($db_error !== ''): ?>
  <p class="msg-error"><?php echo htmlspecialchars($db_error); ?></p>
<?php endif; ?>

<div class="categories-grid categories-page reveal">
  <?php foreach ($categories as $c): ?>
    <a href="index.php?category=<?php echo urlencode($c['slug']); ?>#categories" class="category-card">
      <span class="category-icon"><?php echo htmlspecialchars($c['icon']); ?></span>
      <span class="category-name"><?php echo htmlspecialchars($c['name']); ?></span>
      <span class="category-count"><?php echo (int)$c['total']; ?> title<?php echo ((int)$c['total'] === 1) ? '' : 's'; ?></span>
      <span class="category-desc"><?php echo htmlspecialchars($c['description']); ?></span>
      <?php if (!empty($c['samples'])): ?>
        <span class="category-samples">e.g. <?php echo htmlspecialchars(str_replace('|', ', ', $c['samples'])); ?></span>
      <?php endif; ?>
      <span class="btn btn-green btn-block">Browse <?php echo htmlspecialchars($c['name']); ?></span>
    </a>
  <?php endforeach; ?>
</div>

<?php if (empty($categories) && $db_error === ''): ?>
  <p class="msg-error">No categories found. Import database.sql to seed them.</p>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
