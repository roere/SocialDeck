<?php
declare(strict_types=1);

require dirname(__DIR__).'/api/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Dieser Befehl darf nur über die Kommandozeile ausgeführt werden.\n");
    exit(2);
}

$lockAcquired = false;
$pdo = null;

try {
    $username = trim((string) envValue('INITIAL_ADMIN_USERNAME', ''));
    $email = trim((string) envValue('INITIAL_ADMIN_EMAIL', ''));
    $password = (string) envValue('INITIAL_ADMIN_PASSWORD', '');

    if ($username === '' || strlen($username) > 100 || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
        throw new RuntimeException('INITIAL_ADMIN_USERNAME fehlt oder ist ungültig.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        throw new RuntimeException('INITIAL_ADMIN_EMAIL fehlt oder ist ungültig.');
    }
    if (strlen($password) < 12 || strlen($password) > 1024) {
        throw new RuntimeException('INITIAL_ADMIN_PASSWORD muss 12 bis 1024 Zeichen lang sein.');
    }

    $pdo = db();
    $lockAcquired = (int) $pdo->query("SELECT GET_LOCK('socialdeck_create_initial_admin', 10)")->fetchColumn() === 1;
    if (!$lockAcquired) {
        throw new RuntimeException('Initialer Admin konnte nicht angelegt werden: Sperre nicht verfügbar.');
    }

    $pdo->beginTransaction();
    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($userCount !== 0) {
        $pdo->commit();
        $pdo->query("SELECT RELEASE_LOCK('socialdeck_create_initial_admin')");
        $lockAcquired = false;
        echo "Initialer Admin wurde nicht angelegt: users-Tabelle ist nicht leer.\n";
        exit(0);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($passwordHash)) {
        throw new RuntimeException('Initialer Admin konnte nicht angelegt werden.');
    }

    $now = date('Y-m-d H:i:s');
    $statement = $pdo->prepare(
        'INSERT INTO users(username,email,password_hash,role,is_active,created_at,updated_at) VALUES(?,?,?,?,?,?,?)'
    );
    $statement->execute([$username, $email, $passwordHash, 'admin', 1, $now, $now]);
    $pdo->commit();
    $pdo->query("SELECT RELEASE_LOCK('socialdeck_create_initial_admin')");
    $lockAcquired = false;
    echo "Initialer Admin wurde angelegt.\n";
} catch (Throwable $exception) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($pdo instanceof PDO && $lockAcquired) {
        try {
            $pdo->query("SELECT RELEASE_LOCK('socialdeck_create_initial_admin')");
        } catch (Throwable) {
        }
    }
    fwrite(STDERR, $exception instanceof RuntimeException
        ? $exception->getMessage()."\n"
        : "Initialer Admin konnte nicht angelegt werden.\n");
    exit(1);
}
