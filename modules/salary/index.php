<?php
// Minimal Salary module entry page
require_once __DIR__ . '/../../includes/session_config.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/db_connection.php';

// Simple permission check (reuse existing session user check pattern)
if (empty($_SESSION['user_id'])) {
    header('Location: ../signout.php');
    exit;
}

?>
<div class="container">
    <h1>Salary Module</h1>
    <p>Manage salary topics, components and employee salary assignments.</p>

    <div class="mb-3">
        <a href="modules/salary/actions.php?action=list_topics" class="btn btn-primary">Manage Salary Topics</a>
        <a href="modules/salary/actions.php?action=list_components" class="btn btn-secondary">Manage Components</a>
    </div>

</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
