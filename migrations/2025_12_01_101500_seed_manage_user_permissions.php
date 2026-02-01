<?php
/**
 * Migration: seed_manage_user_permissions
 * Creates a 'manage_user_permissions' permission if not present and assigns it to admin role (role_id=1)
 */
return [
    'up' => function (PDO $pdo) {
        $permCode = 'manage_user_permissions';
        $permDescription = 'Allows granting or revoking permissions for individual users (per-user overrides).';

        $stmt = $pdo->prepare('SELECT id FROM permissions WHERE name = ? LIMIT 1');
        $stmt->execute([$permCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $columns = ['name', 'description'];
            $placeholders = '?, ?';

            $hasCategory = false;
            $infoStmt = $pdo->query('SHOW COLUMNS FROM permissions');
            foreach ($infoStmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
                if (($column['Field'] ?? '') === 'category') {
                    $hasCategory = true;
                    break;
                }
            }

            if ($hasCategory) {
                $columns[] = 'category';
                $placeholders .= ', ?';
                    $insert = $pdo->prepare('INSERT INTO permissions (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')');
                $insert->execute([$permCode, $permDescription, 'system']);
            } else {
                $insert = $pdo->prepare('INSERT INTO permissions (name, description) VALUES (?, ?)');
                $insert->execute([$permCode, $permDescription]);
            }

            $permId = (int) $pdo->lastInsertId();
        } else {
            $permId = (int) $row['id'];
        }

        $checkRole = $pdo->prepare('SELECT 1 FROM role_permissions WHERE role_id = 1 AND permission_id = ? LIMIT 1');
        $checkRole->execute([$permId]);
        if (!$checkRole->fetch()) {
            $assign = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (1, ?)');
            $assign->execute([$permId]);
        }
    },

    'down' => function (PDO $pdo) {
        $permCode = 'manage_user_permissions';
        $stmt = $pdo->prepare('SELECT id FROM permissions WHERE name = ? LIMIT 1');
        $stmt->execute([$permCode]);
        $permId = $stmt->fetchColumn();

        if ($permId) {
            $deleteRole = $pdo->prepare('DELETE FROM role_permissions WHERE permission_id = ?');
            $deleteRole->execute([$permId]);

            $deletePermission = $pdo->prepare('DELETE FROM permissions WHERE id = ?');
            $deletePermission->execute([$permId]);
        }
    },
];
