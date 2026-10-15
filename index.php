<?php
// index.php — GameVault home page (public).
// Hero carousel (top 5 discounts) + Featured grid + Special Offers + Browse by Genre.
// Procedural PHP + PDO prepared statements. All output escaped with htmlspecialchars().

require_once __DIR__ . '/includes/db.php';

$page_title  = 'Home';
$active_page = 'index';

// ---------------------------------------------------------------- helpers ---
/**
 * Final price after discount.
 */
function final_price($price, $disc) {
    $price = (float)$price;
    $disc  = (int)$disc;
    if ($disc < 0)  $disc = 0;
    if ($disc > 100) $disc = 100;
    return round($price * (1 - $disc / 100), 2);
}

/**
 * CSS cover variant g0-g5 derived from game id (no images needed).
 */
function cover_class($id) {
    return 'g' . (((int)$id) % 6);
}

/**
 * Turn "PC, PS5, Xbox" csv into escaped badge spans.
 */
function platform_badges($platform) {
    $out = '';
    foreach (explode(',', (string)$platform) as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $out .= '<span class="platform-badge">' . htmlspecialchars($p) . '</span>';
    }
    return $out;
}

/**
 * Format INR: ₹1,499.00
 */
function inr($amount) {
    return '₹' . number_format((float)$amount, 2);
}

// ------------------------------------------------------------- data fetch ---
$hero_games = [];   // top 5 by discount
$all_games  = [];   // featured grid (everything, or filtered by ?category=slug)
$offers     = [];   // discounted only
$genres     = [];   // distinct genres (fallback chips)
$categories = [];   // categories with game counts for the cards grid
$active_category = null; // matched category row when ?category=slug is valid
$db_error   = '';

// Whitelisted category filter from URL: index.php?category=action
// is_string() guard: ?category[]=x sends an array, and trim(array) is a
// fatal TypeError on PHP 8 (would 500 the whole page).
$raw_slug    = $_GET['category'] ?? '';
$filter_slug = is_string($raw_slug) ? trim($raw_slug) : '';
if ($filter_slug !== '' && !preg_match('/^[a-z0-9-]{1,50}$/', $filter_slug)) {
    $filter_slug = ''; // ignore invalid slugs
}

try {
    // Categories + counts for the "Browse by Category" cards.
    $stmt = $conn->prepare(
        'SELECT c.category_id, c.name, c.slug, c.icon, c.description, COUNT(g.game_id) AS total ' .
        'FROM categories c LEFT JOIN games g ON g.category_id = c.category_id ' .
        'GROUP BY c.category_id ORDER BY c.name ASC'
    );
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $categories[] = $row;
        // Remember the active one if slug matches the URL filter.
        if ($filter_slug !== '' && $row['slug'] === $filter_slug) {
            $active_category = $row;
        }
    }
    // Hero: biggest discounts first.
    $stmt = $conn->prepare('SELECT * FROM games ORDER BY discount_percent DESC LIMIT 5');
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $hero_games[] = $row;
    }

    // Featured: full catalogue, or filtered to one category (prepared statement).
    if ($filter_slug !== '' && $active_category !== null) {
        $stmt = $conn->prepare(
            'SELECT g.* FROM games g JOIN categories c ON c.category_id = g.category_id ' .
            'WHERE c.slug = ? ORDER BY g.discount_percent DESC, g.game_id ASC'
        );
        $stmt->execute([$filter_slug]);
    } else {
        // Unknown slug? Ignore the filter and show everything.
        if ($filter_slug !== '') {
            $filter_slug = '';
        }
        $stmt = $conn->prepare('SELECT * FROM games ORDER BY discount_percent DESC, game_id ASC');
        $stmt->execute();
    }
    while ($row = $stmt->fetch()) {
        $all_games[] = $row;
        if ((int)$row['discount_percent'] > 0) {
            $offers[] = $row;
        }
    }

    // Genre chips.
    $stmt = $conn->prepare('SELECT DISTINCT genre FROM games ORDER BY genre ASC');
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $genres[] = $row['genre'];
    }
} catch (PDOException $e) {
    // Friendly message; never leak raw SQL errors.
    $db_error = 'Could not load games right now. Please try again later.';
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($db_error !== ''): ?>
  <p class="msg-error"><?php echo htmlspecialchars($db_error); ?></p>
<?php endif; ?>

<!-- ================= HERO CAROUSEL ================= -->
<section class="hero reveal">
  <div class="slides">
    <?php if (empty($hero_games)): ?>
      <div class="slide active">
        <div class="game-cover big g0"><span>G</span></div>
        <div class="slide-info">
          <h2>No games yet</h2>
          <p>Import database.sql to load the catalogue.</p>
        </div>
      </div>
    <?php else: ?>
      <?php foreach ($hero_games as $i => $g): ?>
        <?php
          $gid   = (int)$g['game_id'];
          $final = final_price($g['price'], $g['discount_percent']);
          $initial = strtoupper(substr($g['title'], 0, 1));
        ?>
        <div class="slide<?php echo $i === 0 ? ' active' : ''; ?>">
          <div class="game-cover big <?php echo htmlspecialchars(cover_class($gid)); ?>"><span><?php echo htmlspecialchars($initial); ?></span></div>
          <div class="slide-info">
            <span class="genre-tag"><?php echo htmlspecialchars($g['genre']); ?></span>
            <h2><?php echo htmlspecialchars($g['title']); ?></h2>
            <p><?php echo htmlspecialchars($g['description'] ?? ''); ?></p>
            <div class="price-row">
              <?php if ((int)$g['discount_percent'] > 0): ?>
                <span class="old-price"><?php echo htmlspecialchars(inr($g['price'])); ?></span>
                <span class="new-price"><?php echo htmlspecialchars(inr($final)); ?></span>
                <span class="discount-badge">-<?php echo (int)$g['discount_percent']; ?>%</span>
              <?php else: ?>
                <span class="new-price"><?php echo htmlspecialchars(inr($g['price'])); ?></span>
              <?php endif; ?>
            </div>
            <a class="btn btn-green" href="booking.php?game_id=<?php echo $gid; ?>">Reserve</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <button id="prevBtn" class="carousel-btn prev" aria-label="Previous slide">&#10094;</button>
  <button id="nextBtn" class="carousel-btn next" aria-label="Next slide">&#10095;</button>
  <div id="dots" class="dots"></div>
</section>

<!-- ================= BROWSE BY CATEGORY ================= -->
<h2 class="section-title reveal" id="categories">Browse by Category</h2>
<?php if (!empty($categories)): ?>
<div class="categories-grid reveal">
  <a href="index.php" class="category-card<?php echo $filter_slug === '' ? ' selected' : ''; ?>">
    <span class="category-icon">🎮</span>
    <span class="category-name">All Games</span>
    <span class="category-count"><?php echo (int)array_sum(array_column($categories, 'total')); ?> titles</span>
  </a>
  <?php foreach ($categories as $c): ?>
    <a href="index.php?category=<?php echo urlencode($c['slug']); ?>#categories"
       class="category-card<?php echo ($filter_slug === $c['slug']) ? ' selected' : ''; ?>">
      <span class="category-icon"><?php echo htmlspecialchars($c['icon']); ?></span>
      <span class="category-name"><?php echo htmlspecialchars($c['name']); ?></span>
      <span class="category-count"><?php echo (int)$c['total']; ?> title<?php echo ((int)$c['total'] === 1) ? '' : 's'; ?></span>
      <span class="category-desc"><?php echo htmlspecialchars($c['description']); ?></span>
    </a>
  <?php endforeach; ?>
</div>
<div class="category-actions">
  <a class="btn btn-blue" href="categories.php">View all categories</a>
  <?php if ($active_category !== null): ?>
    <a class="btn" href="index.php">Clear filter ✕</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ================= FEATURED ================= -->
<h2 class="section-title reveal">
  <?php echo $active_category !== null
    ? 'Category: ' . htmlspecialchars($active_category['icon'] . ' ' . $active_category['name'])
    : 'Featured &amp; Recommended'; ?>
</h2>
<div class="games-grid reveal">
  <?php foreach ($all_games as $g): ?>
    <?php
      $gid     = (int)$g['game_id'];
      $final   = final_price($g['price'], $g['discount_percent']);
      $initial = strtoupper(substr($g['title'], 0, 1));
    ?>
    <article class="game-card" data-genre="<?php echo htmlspecialchars($g['genre']); ?>">
      <div class="game-cover <?php echo htmlspecialchars(cover_class($gid)); ?>"><span><?php echo htmlspecialchars($initial); ?></span></div>
      <div class="game-body">
        <h3><?php echo htmlspecialchars($g['title']); ?></h3>
        <div>
          <span class="genre-tag"><?php echo htmlspecialchars($g['genre']); ?></span>
          <?php echo platform_badges($g['platform']); ?>
        </div>
        <div class="price-row">
          <?php if ((int)$g['discount_percent'] > 0): ?>
            <span class="old-price"><?php echo htmlspecialchars(inr($g['price'])); ?></span>
            <span class="new-price"><?php echo htmlspecialchars(inr($final)); ?></span>
            <span class="discount-badge">-<?php echo (int)$g['discount_percent']; ?>%</span>
          <?php else: ?>
            <span class="new-price"><?php echo htmlspecialchars(inr($g['price'])); ?></span>
          <?php endif; ?>
        </div>
        <a class="btn btn-green" href="booking.php?game_id=<?php echo $gid; ?>">Reserve</a>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<!-- ================= SPECIAL OFFERS ================= -->
<h2 class="section-title reveal">Special Offers</h2>
<div class="games-grid reveal">
  <?php if (empty($offers)): ?>
    <p>No special offers right now. Check back soon.</p>
  <?php else: ?>
    <?php foreach ($offers as $g): ?>
      <?php
        $gid     = (int)$g['game_id'];
        $final   = final_price($g['price'], $g['discount_percent']);
        $initial = strtoupper(substr($g['title'], 0, 1));
      ?>
      <article class="game-card" data-genre="<?php echo htmlspecialchars($g['genre']); ?>">
        <div class="game-cover <?php echo htmlspecialchars(cover_class($gid)); ?>"><span><?php echo htmlspecialchars($initial); ?></span></div>
        <div class="game-body">
          <h3><?php echo htmlspecialchars($g['title']); ?></h3>
          <div>
            <span class="genre-tag"><?php echo htmlspecialchars($g['genre']); ?></span>
            <?php echo platform_badges($g['platform']); ?>
          </div>
          <div class="price-row">
            <span class="old-price"><?php echo htmlspecialchars(inr($g['price'])); ?></span>
            <span class="new-price"><?php echo htmlspecialchars(inr($final)); ?></span>
            <span class="discount-badge">-<?php echo (int)$g['discount_percent']; ?>%</span>
          </div>
          <a class="btn btn-green" href="booking.php?game_id=<?php echo $gid; ?>">Reserve</a>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- ================= BROWSE BY GENRE (quick JS filter) ================= -->
<h2 class="section-title reveal">Quick Filter by Genre</h2>
<div class="genre-chips reveal">
  <button class="chip active" data-genre="all" type="button">All</button>
  <?php foreach ($genres as $genre): ?>
    <button class="chip" data-genre="<?php echo htmlspecialchars($genre); ?>" type="button"><?php echo htmlspecialchars($genre); ?></button>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
