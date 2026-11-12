<?php
declare(strict_types=1);

// App Store shares the main HENDY website session, PDO connection and
// secure helpers. Do not create a second users table or a second login state.
require_once dirname(__DIR__, 2) . '/includes/db.php';
