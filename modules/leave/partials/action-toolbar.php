<?php
if (!function_exists('is_admin')) {
    require_once '../../includes/utilities.php';
}

$leaveToolbarIsAdmin = $leaveToolbarIsAdmin ?? ($isAdmin ?? (function_exists('is_admin') ? is_admin() : false));
$currentLeavePage = basename($_SERVER['PHP_SELF'] ?? '');

// Allow pages to configure the toolbar via $leaveToolbarConfig while keeping backwards compatibility
$leaveToolbarConfig = $leaveToolbarConfig ?? [];
$leaveToolbarAddButton = $leaveToolbarConfig['addButton'] ?? ($leaveToolbarAddButton ?? null);
$leaveToolbarPrimaryLinks = $leaveToolbarConfig['primaryLinks'] ?? ($leaveToolbarPrimaryLinks ?? []);
$leaveToolbarShowDashboardButton = $leaveToolbarConfig['showDashboardButton'] ?? ($leaveToolbarShowDashboardButton ?? ($currentLeavePage !== 'index.php'));
$leaveToolbarInline = $leaveToolbarConfig['inline'] ?? ($leaveToolbarInline ?? false);
$leaveToolbarWrapperClasses = $leaveToolbarConfig['wrapperClasses'] ?? ($leaveToolbarWrapperClasses ?? null);

// Normalize the add button definition (defaulting to Request Leave modal trigger)
if ($leaveToolbarAddButton !== false) {
    if (!is_array($leaveToolbarAddButton)) {
        $leaveToolbarAddButton = null;
    }

    if ($leaveToolbarAddButton === null) {
        $leaveToolbarAddButton = [
            'type' => 'button',
            'label' => 'Request Leave',
            'icon' => 'fas fa-plus',
            'classes' => 'btn btn-primary',
            'attributes' => [
                'data-bs-toggle' => 'modal',
                'data-bs-target' => '#applyLeaveModal'
            ],
            'page' => null,
        ];
    } else {
        $leaveToolbarAddButton['type'] = $leaveToolbarAddButton['type'] ?? ($leaveToolbarAddButton['url'] ?? false ? 'link' : 'button');
        $leaveToolbarAddButton['classes'] = $leaveToolbarAddButton['classes'] ?? 'btn btn-primary';
        $leaveToolbarAddButton['attributes'] = $leaveToolbarAddButton['attributes'] ?? [];
        $leaveToolbarAddButton['page'] = $leaveToolbarAddButton['page'] ?? null;
    }
}

// Normalize any additional primary links (e.g., Holidays button on dashboard)
$normalizedPrimaryLinks = [];
foreach ($leaveToolbarPrimaryLinks as $primaryLink) {
    if (!is_array($primaryLink) || empty($primaryLink['url'])) {
        continue;
    }

    $normalizedPrimaryLinks[] = [
        'url' => $primaryLink['url'],
        'label' => $primaryLink['label'] ?? 'Link',
        'icon' => $primaryLink['icon'] ?? 'fas fa-link',
        'classes' => $primaryLink['classes'] ?? 'btn btn-outline-secondary',
        'page' => $primaryLink['page'] ?? basename(parse_url($primaryLink['url'], PHP_URL_PATH) ?? ''),
        'attributes' => $primaryLink['attributes'] ?? [],
    ];
}

$leaveToolbarPrimaryLinks = $normalizedPrimaryLinks;

// Build navigation items for the dropdown
$leaveToolbarNavItems = [
    'requests.php' => [
        'label' => 'Manage Requests',
        'icon' => 'fas fa-tasks text-success',
        'adminOnly' => true,
    ],
    'my-requests.php' => [
        'label' => 'My Requests',
        'icon' => 'fas fa-list text-primary',
        'hideForAdmin' => true,
    ],
    'balance.php' => [
        'label' => 'Leave Balance',
        'icon' => 'fas fa-chart-pie text-info',
    ],
    'calendar.php' => [
        'label' => 'Calendar',
        'icon' => 'fas fa-calendar-day text-primary',
    ],
    'holidays.php' => [
        'label' => 'Holidays',
        'icon' => 'fas fa-umbrella-beach text-warning',
    ],
    'types.php' => [
        'label' => 'Leave Settings',
        'icon' => 'fas fa-sliders-h text-secondary',
        'adminOnly' => true,
    ],
    'accrual.php' => [
        'label' => 'Accruals',
        'icon' => 'fas fa-coins text-danger',
        'adminOnly' => true,
    ],
    'reports.php' => [
        'label' => 'Reports',
        'icon' => 'fas fa-chart-line text-dark',
        'adminOnly' => true,
    ],
];

$excludePages = [$currentLeavePage];
if (!empty($leaveToolbarAddButton['page'])) {
    $excludePages[] = $leaveToolbarAddButton['page'];
}
if ($leaveToolbarShowDashboardButton) {
    $excludePages[] = 'index.php';
}
foreach ($leaveToolbarPrimaryLinks as $link) {
    if (!empty($link['page'])) {
        $excludePages[] = $link['page'];
    }
}

$dropdownItems = [];
foreach ($leaveToolbarNavItems as $file => $navItem) {
    if (!empty($navItem['adminOnly']) && !$leaveToolbarIsAdmin) {
        continue;
    }
    if (!empty($navItem['hideForAdmin']) && $leaveToolbarIsAdmin) {
        continue;
    }
    if (in_array($file, $excludePages, true)) {
        continue;
    }
    $dropdownItems[$file] = $navItem;
}

$attributeString = static function (array $attributes): string {
    $segments = [];
    foreach ($attributes as $key => $value) {
        if ($value === null) {
            continue;
        }
        $segments[] = sprintf('%s="%s"', htmlspecialchars($key, ENT_QUOTES), htmlspecialchars((string) $value, ENT_QUOTES));
    }
    return implode(' ', $segments);
};

$hasDropdownItems = !empty($dropdownItems);
$toolbarWrapperClasses = $leaveToolbarWrapperClasses
    ?: ($leaveToolbarInline
        ? 'd-flex flex-wrap gap-2 align-items-center leave-toolbar ms-auto'
        : 'd-flex flex-wrap gap-2 align-items-center mb-4 leave-toolbar');
?>
<div class="<?= htmlspecialchars($toolbarWrapperClasses, ENT_QUOTES) ?>">
    <?php if ($leaveToolbarAddButton !== false): ?>
        <?php if (($leaveToolbarAddButton['type'] ?? 'button') === 'link' && !empty($leaveToolbarAddButton['url'])): ?>
            <a href="<?= htmlspecialchars($leaveToolbarAddButton['url'], ENT_QUOTES) ?>" class="<?= htmlspecialchars($leaveToolbarAddButton['classes'], ENT_QUOTES) ?>"
               <?= $attributeString($leaveToolbarAddButton['attributes']) ?>>
                <i class="<?= htmlspecialchars($leaveToolbarAddButton['icon'], ENT_QUOTES) ?> me-1"></i><?= htmlspecialchars($leaveToolbarAddButton['label']) ?>
            </a>
        <?php else: ?>
            <button type="button" class="<?= htmlspecialchars($leaveToolbarAddButton['classes'], ENT_QUOTES) ?>"
                <?= $attributeString($leaveToolbarAddButton['attributes']) ?>>
                <i class="<?= htmlspecialchars($leaveToolbarAddButton['icon'], ENT_QUOTES) ?> me-1"></i><?= htmlspecialchars($leaveToolbarAddButton['label']) ?>
            </button>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($leaveToolbarShowDashboardButton): ?>
        <a href="index.php" class="btn btn-outline-success<?= $currentLeavePage === 'index.php' ? ' active' : '' ?>">
            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
        </a>
    <?php endif; ?>

    <?php foreach ($leaveToolbarPrimaryLinks as $primaryLink): ?>
        <a href="<?= htmlspecialchars($primaryLink['url'], ENT_QUOTES) ?>" class="<?= htmlspecialchars($primaryLink['classes'], ENT_QUOTES) ?>"
            <?= $attributeString($primaryLink['attributes']) ?>>
            <i class="<?= htmlspecialchars($primaryLink['icon'], ENT_QUOTES) ?> me-1"></i><?= htmlspecialchars($primaryLink['label']) ?>
        </a>
    <?php endforeach; ?>

    <div class="btn-group">
        <button type="button" class="btn btn-outline-secondary dropdown-toggle<?= $hasDropdownItems ? '' : ' disabled' ?>"
            <?= $hasDropdownItems ? 'data-bs-toggle="dropdown"' : '' ?> aria-expanded="false">
            <i class="fas fa-ellipsis-h me-1"></i>More
        </button>
        <div class="dropdown-menu dropdown-menu-end shadow">
            <?php if ($hasDropdownItems): ?>
                <?php foreach ($dropdownItems as $file => $item): ?>
                    <a class="dropdown-item<?= $currentLeavePage === $file ? ' active' : '' ?>" href="<?= htmlspecialchars($file, ENT_QUOTES) ?>">
                        <i class="<?= htmlspecialchars($item['icon'], ENT_QUOTES) ?> me-2"></i><?= htmlspecialchars($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <span class="dropdown-item text-muted small">No other links</span>
            <?php endif; ?>
        </div>
    </div>
</div>
