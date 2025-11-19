<?php
// Basic actions for salary topics/components (minimal, intended as starter)
require_once __DIR__ . '/../../includes/session_config.php';
require_once __DIR__ . '/../../includes/db_connection.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$action = $_REQUEST['action'] ?? 'list_topics';

switch ($action) {
    case 'add_topic':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ../salary/index.php'); exit;
        }
        $name = trim($_POST['name'] ?? '');
        $type = in_array($_POST['type'] ?? '', ['earning','deduction']) ? $_POST['type'] : 'earning';
        $desc = trim($_POST['description'] ?? '');
        if ($name === '') {
            $_SESSION['flash_error'] = 'Topic name is required';
            header('Location: ../salary/index.php'); exit;
        }
        $stmt = $pdo->prepare("INSERT INTO salary_topics (name, type, description, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
        $stmt->execute([$name, $type, $desc]);
        $_SESSION['flash_success'] = 'Salary topic added';
        header('Location: ../salary/index.php');
        exit;

    case 'delete_topic':
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) { header('Location: ../salary/index.php'); exit; }
        $stmt = $pdo->prepare('DELETE FROM salary_topics WHERE id = ?');
        $stmt->execute([$id]);
        $_SESSION['flash_success'] = 'Salary topic deleted';
        header('Location: ../salary/index.php');
        exit;

    case 'list_topics':
        $rows = [];
        try {
            $stmt = $pdo->query('SELECT * FROM salary_topics ORDER BY id DESC');
            if ($stmt) { $rows = $stmt->fetchAll(PDO::FETCH_ASSOC); }
        } catch (Throwable $e) {
            $rows = [];
        }
        // Simple HTML output for starters
        echo '<h2>Salary Topics</h2>';
        echo '<a href="../salary/index.php">Back</a><br><br>';
        echo '<table class="table table-bordered"><tr><th>ID</th><th>Name</th><th>Type</th><th>Description</th><th>Action</th></tr>';
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            echo '<tr>';
            echo '<td>' . $id . '</td>';
            echo '<td>' . htmlspecialchars($r['name']) . '</td>';
            echo '<td>' . htmlspecialchars($r['type']) . '</td>';
            echo '<td>' . htmlspecialchars($r['description']) . '</td>';
            echo '<td><a href="?action=delete_topic&id=' . $id . '" onclick="return confirm(\'Delete?\')">Delete</a></td>';
            echo '</tr>';
        }
        echo '</table>';
        // Add form
        echo '<h3>Add Topic</h3>';
        echo '<form method="post" action="?action=add_topic">';
        echo 'Name: <input name="name" class="form-control" required><br>';
        echo 'Type: <select name="type" class="form-control"><option value="earning">Earning</option><option value="deduction">Deduction</option></select><br>';
        echo 'Description:<br><textarea name="description" class="form-control"></textarea><br>';
        echo '<button class="btn btn-success">Add</button>';
        echo '</form>';
        exit;

    case 'list_components':
    default:
        echo '<p>Components management coming soon. Use salary topics as a starting point.</p>';
        echo '<a href="../salary/index.php">Back</a>';
        exit;
}
