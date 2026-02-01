<?php
if (!function_exists('is_admin')) {
    require_once '../../includes/utilities.php';
}

$taskToolbarIsAdmin = $taskToolbarIsAdmin ?? ($isAdmin ?? (function_exists('is_admin') ? is_admin() : false));
$currentTaskPage = basename($_SERVER['PHP_SELF'] ?? '');

$taskToolbarConfig = $taskToolbarConfig ?? [];
$taskToolbarAddButton = $taskToolbarConfig['addButton'] ?? ($taskToolbarAddButton ?? null);
$taskToolbarPrimaryLinks = $taskToolbarConfig['primaryLinks'] ?? ($taskToolbarPrimaryLinks ?? []);
$taskToolbarShowDashboardButton = $taskToolbarConfig['showDashboardButton'] ?? ($taskToolbarShowDashboardButton ?? ($currentTaskPage !== 'index.php'));
$taskToolbarInline = $taskToolbarConfig['inline'] ?? ($taskToolbarInline ?? false);
$taskToolbarWrapperClasses = $taskToolbarConfig['wrapperClasses'] ?? ($taskToolbarWrapperClasses ?? null);

if ($taskToolbarAddButton !== false) {
    if (!is_array($taskToolbarAddButton)) {
        $taskToolbarAddButton = null;
    }

    if ($taskToolbarAddButton === null) {
        $taskToolbarAddButton = [
            'type' => 'button',
            'label' => 'Create Task',
            'icon' => 'fas fa-plus',
            'classes' => 'btn btn-primary',
            'attributes' => [
                'data-bs-toggle' => 'modal',
                'data-bs-target' => '#createTaskModal'
            ],
            'page' => null,
        ];
    } else {
        $taskToolbarAddButton['type'] = $taskToolbarAddButton['type'] ?? ($taskToolbarAddButton['url'] ?? false ? 'link' : 'button');
        $taskToolbarAddButton['classes'] = $taskToolbarAddButton['classes'] ?? 'btn btn-primary';
        $taskToolbarAddButton['attributes'] = $taskToolbarAddButton['attributes'] ?? [];
        $taskToolbarAddButton['page'] = $taskToolbarAddButton['page'] ?? null;
        $taskToolbarAddButton['icon'] = $taskToolbarAddButton['icon'] ?? 'fas fa-plus';
        $taskToolbarAddButton['label'] = $taskToolbarAddButton['label'] ?? 'Create Task';
    }
}

$normalizedPrimaryLinks = [];
foreach ($taskToolbarPrimaryLinks as $primaryLink) {
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
$taskToolbarPrimaryLinks = $normalizedPrimaryLinks;

$taskToolbarNavItems = [
    'all-tasks.php' => [
        'label' => 'All Tasks',
        'icon' => 'fas fa-list text-info',
    ],
    'my-tasks.php' => [
        'label' => 'My Tasks',
        'icon' => 'fas fa-user-check text-primary',
    ],
    'team-tasks.php' => [
        'label' => 'Team Tasks',
        'icon' => 'fas fa-users text-success',
    ],
    'task-categories.php' => [
        'label' => 'Task Categories',
        'icon' => 'fas fa-tags text-secondary',
        'adminOnly' => true,
    ],
    'assign_task.php' => [
        'label' => 'Assign Task',
        'icon' => 'fas fa-user-plus text-warning',
        'adminOnly' => true,
    ],
    'tasks.php' => [
        'label' => 'Legacy View',
        'icon' => 'fas fa-history text-muted',
        'adminOnly' => true,
    ],
];

$excludePages = [$currentTaskPage];
if (!empty($taskToolbarAddButton['page'])) {
    $excludePages[] = $taskToolbarAddButton['page'];
}
if ($taskToolbarShowDashboardButton) {
    $excludePages[] = 'index.php';
}
foreach ($taskToolbarPrimaryLinks as $link) {
    if (!empty($link['page'])) {
        $excludePages[] = $link['page'];
    }
}

$dropdownItems = [];
foreach ($taskToolbarNavItems as $file => $item) {
    if (!empty($item['adminOnly']) && !$taskToolbarIsAdmin) {
        continue;
    }
    if (in_array($file, $excludePages, true)) {
        continue;
    }
    $dropdownItems[$file] = $item;
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
$toolbarWrapperClasses = $taskToolbarWrapperClasses
    ?: ($taskToolbarInline
        ? 'd-flex flex-wrap gap-2 align-items-center task-toolbar ms-auto'
        : 'd-flex flex-wrap gap-2 align-items-center mb-4 task-toolbar');
?>
<div class="<?= htmlspecialchars($toolbarWrapperClasses, ENT_QUOTES) ?>">
    <?php if ($taskToolbarAddButton !== false): ?>
        <?php if (($taskToolbarAddButton['type'] ?? 'button') === 'link' && !empty($taskToolbarAddButton['url'])): ?>
            <a href="<?= htmlspecialchars($taskToolbarAddButton['url'], ENT_QUOTES) ?>" class="<?= htmlspecialchars($taskToolbarAddButton['classes'], ENT_QUOTES) ?>"
               <?= $attributeString($taskToolbarAddButton['attributes']) ?>>
                <i class="<?= htmlspecialchars($taskToolbarAddButton['icon'], ENT_QUOTES) ?> me-1"></i><?= htmlspecialchars($taskToolbarAddButton['label']) ?>
            </a>
        <?php else: ?>
            <button type="button" class="<?= htmlspecialchars($taskToolbarAddButton['classes'], ENT_QUOTES) ?>"
                <?= $attributeString($taskToolbarAddButton['attributes']) ?>>
                <i class="<?= htmlspecialchars($taskToolbarAddButton['icon'], ENT_QUOTES) ?> me-1"></i><?= htmlspecialchars($taskToolbarAddButton['label']) ?>
            </button>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($taskToolbarShowDashboardButton): ?>
        <a href="index.php" class="btn btn-outline-success<?= $currentTaskPage === 'index.php' ? ' active' : '' ?>">
            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
        </a>
    <?php endif; ?>

    <?php foreach ($taskToolbarPrimaryLinks as $primaryLink): ?>
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
                    <a class="dropdown-item<?= $currentTaskPage === $file ? ' active' : '' ?>" href="<?= htmlspecialchars($file, ENT_QUOTES) ?>">
                        <i class="<?= htmlspecialchars($item['icon'], ENT_QUOTES) ?> me-2"></i><?= htmlspecialchars($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <span class="dropdown-item text-muted small">No other links</span>
            <?php endif; ?>
        </div>
    </div>
</div>
