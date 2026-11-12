<?php
declare(strict_types=1);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    header('Location: app_store/admin.php', true, 302);
    exit;
}
header('Location: app_store/admin_edit.php?id=' . $id, true, 302);
exit;
