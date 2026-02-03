<?php
if (!isset($page)) {
    $page = '';
}

$assetToolbarInline = $assetToolbarInline ?? false;
$assetToolbarWrapperClasses = $assetToolbarWrapperClasses
  ?? ($assetToolbarInline ? 'd-flex flex-wrap gap-2 align-items-center assets-toolbar ms-auto' : 'd-flex flex-wrap gap-2 align-items-center mb-4 assets-toolbar');
$assetToolbarButtons = $assetToolbarButtons ?? null;

$assetNavItems = [
    'assets.php' => [
        'label' => 'Overview',
        'icon' => 'fas fa-tachometer-alt',
        'pages' => ['Assets Management'],
        'classes' => 'btn btn-outline-success',
    ],
    'manage_assets.php' => [
        'label' => 'Fixed Assets',
        'icon' => 'fas fa-boxes',
        'pages' => ['Manage Assets'],
        'classes' => 'btn btn-outline-primary',
    ],
    'manage_categories.php' => [
        'label' => 'Categories',
        'icon' => 'fas fa-tags',
        'pages' => ['Asset Categories'],
        'classes' => 'btn btn-outline-secondary',
    ],
    'manage_assignments.php' => [
        'label' => 'Assignments',
        'icon' => 'fas fa-people-carry',
        'pages' => ['Asset Assignments'],
        'classes' => 'btn btn-outline-info',
    ],
    'manage_maintenance.php' => [
        'label' => 'Maintenance',
        'icon' => 'fas fa-tools',
        'pages' => ['Maintenance Records'],
        'classes' => 'btn btn-outline-warning',
    ],
];

$primaryKeys = ['assets.php', 'manage_assets.php', 'manage_assignments.php', 'manage_maintenance.php'];
$dropdownItems = [];
foreach ($assetNavItems as $route => $item) {
    if (!in_array($route, $primaryKeys, true)) {
        $dropdownItems[$route] = $item;
    }
}

$isActivePage = static function (array $item) use ($page): bool {
    return in_array($page, $item['pages'], true);
};

$hasDropdownItems = !empty($dropdownItems);

$renderAttributes = static function (array $attributes = []): string {
    $chunks = [];
    foreach ($attributes as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $chunks[] = sprintf('%s="%s"', htmlspecialchars($key, ENT_QUOTES), htmlspecialchars((string) $value, ENT_QUOTES));
    }
    return $chunks ? ' ' . implode(' ', $chunks) : '';
};

$isActiveToolbarItem = static function (array $item) use ($page): bool {
    if (!empty($item['pages']) && in_array($page, (array) $item['pages'], true)) {
        return true;
    }
    if (!empty($item['href'])) {
        return basename($_SERVER['PHP_SELF']) === basename($item['href']);
    }
    return false;
};

$renderIcon = static function (?string $icon): string {
    if (!$icon) {
        return '';
    }
    return '<i class="' . htmlspecialchars($icon) . ' me-1"></i>';
};
?>

<div class="<?php echo htmlspecialchars($assetToolbarWrapperClasses, ENT_QUOTES); ?>">
  <?php if (!empty($assetToolbarButtons)): ?>
    <?php foreach ($assetToolbarButtons as $button): ?>
      <?php $type = $button['type'] ?? 'link'; ?>
      <?php if ($type === 'dropdown'): ?>
        <?php $items = $button['items'] ?? []; ?>
        <div class="btn-group">
          <button type="button" class="<?php echo htmlspecialchars($button['classes'] ?? 'btn btn-outline-secondary', ENT_QUOTES); ?> dropdown-toggle<?php echo empty($items) ? ' disabled' : ''; ?>"
            <?php echo empty($items) ? '' : 'data-bs-toggle="dropdown"'; ?> aria-expanded="false">
            <?php echo $renderIcon($button['icon'] ?? null); ?>
            <?php echo htmlspecialchars($button['label'] ?? 'More'); ?>
          </button>
          <div class="dropdown-menu dropdown-menu-end shadow">
            <?php if (!empty($items)): ?>
              <?php foreach ($items as $item): ?>
                <?php $active = $isActiveToolbarItem($item); ?>
                <a class="dropdown-item<?php echo $active ? ' active' : ''; ?>" href="<?php echo htmlspecialchars($item['href'] ?? '#'); ?>">
                  <?php echo $renderIcon($item['icon'] ?? null); ?>
                  <?php echo htmlspecialchars($item['label'] ?? ''); ?>
                </a>
              <?php endforeach; ?>
            <?php else: ?>
              <span class="dropdown-item text-muted small">No other links</span>
            <?php endif; ?>
          </div>
        </div>
      <?php elseif ($type === 'button'): ?>
        <?php $attrs = $button['attributes'] ?? []; ?>
        <button class="<?php echo htmlspecialchars($button['classes'] ?? 'btn btn-primary', ENT_QUOTES); ?>"<?php echo $renderAttributes($attrs); ?>>
          <?php echo $renderIcon($button['icon'] ?? null); ?>
          <?php echo htmlspecialchars($button['label'] ?? 'Action'); ?>
        </button>
      <?php else: ?>
        <a href="<?php echo htmlspecialchars($button['href'] ?? '#'); ?>" class="<?php echo htmlspecialchars($button['classes'] ?? 'btn btn-outline-secondary', ENT_QUOTES); ?>">
          <?php echo $renderIcon($button['icon'] ?? null); ?>
          <?php echo htmlspecialchars($button['label'] ?? 'Link'); ?>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  <?php else: ?>
    <?php foreach ($primaryKeys as $route): ?>
      <?php if (!isset($assetNavItems[$route])) { continue; }
        $item = $assetNavItems[$route];
        $active = $isActivePage($item);
        $classes = $active ? 'btn btn-primary' : ($item['classes'] ?? 'btn btn-outline-secondary');
      ?>
      <a href="<?php echo htmlspecialchars($route); ?>" class="<?php echo htmlspecialchars($classes, ENT_QUOTES); ?>">
        <i class="<?php echo htmlspecialchars($item['icon']); ?> me-1"></i><?php echo htmlspecialchars($item['label']); ?>
      </a>
    <?php endforeach; ?>

    <div class="btn-group">
      <button type="button" class="btn btn-outline-secondary dropdown-toggle<?php echo $hasDropdownItems ? '' : ' disabled'; ?>"
        <?php echo $hasDropdownItems ? 'data-bs-toggle="dropdown"' : ''; ?> aria-expanded="false">
        <i class="fas fa-ellipsis-h me-1"></i>More
      </button>
      <div class="dropdown-menu dropdown-menu-end shadow">
        <?php if ($hasDropdownItems): ?>
          <?php foreach ($dropdownItems as $route => $item): ?>
            <?php $active = $isActivePage($item); ?>
            <a class="dropdown-item<?php echo $active ? ' active' : ''; ?>" href="<?php echo htmlspecialchars($route); ?>">
              <i class="<?php echo htmlspecialchars($item['icon']); ?> me-2"></i><?php echo htmlspecialchars($item['label']); ?>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <span class="dropdown-item text-muted small">No other links</span>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
