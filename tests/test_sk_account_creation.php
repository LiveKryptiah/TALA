<?php
// Runs only from the CLI and rolls back every test account.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(); }
require_once __DIR__ . '/../sk_account_service.php';
$allow_offline_mode = true;
require_once __DIR__ . '/../db_connect.php';
if (!$pdo) { fwrite(STDERR, "A local test database is required.\n"); exit(1); }

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$token = bin2hex(random_bytes(32));
$session = ['admin_logged_in' => true, 'role' => 'admin', 'sk_account_csrf' => $token];
$input = [
    'full_name' => 'Account Creation Regression Test',
    'barangay' => 'Annafunan',
    'username' => 'sk_test_' . bin2hex(random_bytes(8)),
    'password' => bin2hex(random_bytes(12)),
    'csrf_token' => $token,
    // Client-supplied privilege fields must never be used.
    'role' => 'admin', 'status' => 'pending', 'sk_position' => 'SK Treasurer',
];
$input['confirm_password'] = $input['password'];
$barangays = ['Annafunan', 'Balug'];
$pdo->beginTransaction();
try {
    $nonAdmin = $session;
    $nonAdmin['role'] = 'sub-admin';
    check(!createSkChairpersonAccount($pdo, $input, $nonAdmin, $barangays)['created'], 'Non-admin access was allowed');
    $notLoggedIn = $session;
    $notLoggedIn['admin_logged_in'] = false;
    check(!createSkChairpersonAccount($pdo, $input, $notLoggedIn, $barangays)['created'], 'Logged-out access was allowed');
    $invalid = $input;
    $invalid['csrf_token'] = 'wrong-token';
    check(!createSkChairpersonAccount($pdo, $invalid, $session, $barangays)['created'], 'Invalid CSRF was accepted');
    $invalid['csrf_token'] = $token;
    $invalid['barangay'] = 'Invalid Barangay';
    check(!createSkChairpersonAccount($pdo, $invalid, $session, $barangays)['created'], 'Invalid barangay was accepted');
    $invalid = $input;
    $invalid['confirm_password'] = 'different';
    check(!createSkChairpersonAccount($pdo, $invalid, $session, $barangays)['created'], 'Mismatched passwords were accepted');
    $invalid = $input;
    $invalid['password'] = $invalid['confirm_password'] = 'short';
    check(!createSkChairpersonAccount($pdo, $invalid, $session, $barangays)['created'], 'A short password was accepted');
    $invalid = $input;
    $invalid['username'] = '<script>alert(1)</script>';
    check(!createSkChairpersonAccount($pdo, $invalid, $session, $barangays)['created'], 'Invalid username was accepted');

    $result = createSkChairpersonAccount($pdo, $input, $session, $barangays);
    check($result['created'], 'Account creation failed: ' . implode('; ', $result['errors']));
    $statement = $pdo->prepare('SELECT * FROM admins WHERE username = ?');
    $statement->execute([$input['username']]);
    $account = $statement->fetch(PDO::FETCH_ASSOC);
    check($account['role'] === 'sub-admin', 'Incorrect access role');
    check($account['sk_position'] === 'SK Chairperson', 'Incorrect SK position');
    check($account['status'] === 'approved', 'Account is not approved for login');
    check($account['barangay_assigned'] === 'Annafunan', 'Incorrect barangay assignment');
    check($account['password_hash'] !== $input['password'], 'Plaintext password was stored');
    check(password_verify($input['password'], $account['password_hash']), 'Password is incompatible with login verification');
    check(!createSkChairpersonAccount($pdo, $input, $session, $barangays)['created'], 'Duplicate username was accepted');
    $caseVariant = $input;
    $caseVariant['username'] = strtoupper($input['username']);
    check(!createSkChairpersonAccount($pdo, $caseVariant, $session, $barangays)['created'], 'Case-insensitive duplicate username was accepted');
    echo "PASS: authorization, CSRF, field validation, account creation, fixed privileges, password verification, and duplicate usernames.\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
$statement = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE username = ?');
$statement->execute([$input['username']]);
check((int)$statement->fetchColumn() === 0, 'A test account was left in the database');
echo "PASS: all test accounts rolled back.\n";
