<?php
/**
 * Create the first (or an additional) staff account.
 *
 * The seed data deliberately ships no admin row, because a password hash
 * committed to a public repository is a password everybody has. This
 * script prompts instead and stores the result with password_hash().
 *
 * Usage:
 *     php scripts/create_admin.php
 *     php scripts/create_admin.php --username=alice --role=manager --branch=2
 *
 * The password is always prompted for; it is never taken from a command
 * line argument, because arguments show up in shell history and in `ps`.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script may only be run from the command line.\n");
}

require_once __DIR__ . '/../config.php';

/** Read a line from stdin, optionally with the terminal echo turned off. */
function prompt(string $label, bool $hidden = false): string
{
    fwrite(STDOUT, $label);

    if (!$hidden) {
        return trim((string) fgets(STDIN));
    }

    // stty is not available everywhere (Windows, some containers); fall
    // back to a visible prompt rather than silently echoing the password
    // when the caller believes it is hidden.
    $sttyPath = trim((string) @shell_exec('command -v stty'));
    if ($sttyPath === '') {
        fwrite(STDOUT, "\n[warning] cannot hide input on this system; the password will be visible.\n");
        return trim((string) fgets(STDIN));
    }

    $original = trim((string) shell_exec('stty -g'));
    shell_exec('stty -echo');
    $value = trim((string) fgets(STDIN));
    shell_exec('stty ' . escapeshellarg($original));
    fwrite(STDOUT, "\n");

    return $value;
}

/** Parse --key=value arguments. */
function option(array $argv, string $name): ?string
{
    foreach ($argv as $arg) {
        if (strpos($arg, "--{$name}=") === 0) {
            return substr($arg, strlen($name) + 3);
        }
    }
    return null;
}

$username = option($argv, 'username') ?? prompt('Username: ');
if ($username === '') {
    exit("A username is required.\n");
}

$existing = db_one($conn, "SELECT id FROM admins WHERE username = ?", [$username]);
if ($existing) {
    exit("An admin named '{$username}' already exists (id {$existing['id']}).\n");
}

$role = option($argv, 'role');
if ($role === null) {
    fwrite(STDOUT, "Roles: superadmin, manager, support\n");
    $role = prompt('Role [superadmin]: ');
    if ($role === '') {
        $role = 'superadmin';
    }
}
if (!in_array($role, ['superadmin', 'manager', 'support'], true)) {
    exit("Invalid role '{$role}'. Use superadmin, manager or support.\n");
}

$branchArg = option($argv, 'branch');
$branchId  = null;
if ($role !== 'superadmin') {
    // includes/auth.php refuses to start a session for a non-superadmin
    // without a branch, so require one here rather than creating an
    // account that can never log in.
    if ($branchArg === null) {
        $branches = db_all($conn, "SELECT id, name FROM branches ORDER BY id");
        if (!$branches) {
            exit("No branches exist yet. Create one before adding a {$role}.\n");
        }
        foreach ($branches as $b) {
            fwrite(STDOUT, "  {$b['id']}: {$b['name']}\n");
        }
        $branchArg = prompt('Branch id: ');
    }
    $branchId = (int) $branchArg;
    if (!db_one($conn, "SELECT id FROM branches WHERE id = ?", [$branchId])) {
        exit("Branch {$branchId} does not exist.\n");
    }
} elseif ($branchArg !== null && $branchArg !== '') {
    $branchId = (int) $branchArg;
}

$password = prompt('Password: ', true);
if (strlen($password) < 12) {
    exit("Password must be at least 12 characters.\n");
}
if ($password !== prompt('Confirm password: ', true)) {
    exit("Passwords did not match.\n");
}

db_exec(
    $conn,
    "INSERT INTO admins (username, password, role, branch_id, active) VALUES (?, ?, ?, ?, 1)",
    [$username, password_hash($password, PASSWORD_DEFAULT), $role, $branchId]
);

fwrite(STDOUT, "Created {$role} '{$username}'"
    . ($branchId !== null ? " in branch {$branchId}" : '') . ".\n");
