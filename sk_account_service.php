<?php
// Account creation rules shared by the admin page and its regression tests.
function createSkChairpersonAccount(PDO $pdo, array $input, array $session, array $barangays): array
{
    $text = static function ($value): string {
        return is_string($value) ? trim($value) : '';
    };
    $values = [
        'full_name' => $text($input['full_name'] ?? ''),
        'barangay' => $text($input['barangay'] ?? ''),
        'username' => $text($input['username'] ?? ''),
    ];
    $fail = static function (string $error) use ($values): array {
        return ['created' => false, 'errors' => [$error], 'values' => $values];
    };
    if (($session['admin_logged_in'] ?? false) !== true || ($session['role'] ?? '') !== 'admin') {
        return $fail('Only a municipal administrator can create SK accounts.');
    }
    $token = $input['csrf_token'] ?? null;
    $expected = $session['sk_account_csrf'] ?? null;
    if (!is_string($token) || !is_string($expected) || $expected === '' || !hash_equals($expected, $token)) {
        return $fail('Your form has expired. Refresh the page and try again.');
    }

    $errors = [];
    if ($values['full_name'] === '' || strlen($values['full_name']) > 150) {
        $errors[] = 'Enter the chairperson’s full name (up to 150 characters).';
    }
    if (!in_array($values['barangay'], $barangays, true)) {
        $errors[] = 'Choose a valid Tumauini barangay.';
    }
    if (!preg_match('/\A[A-Za-z0-9._-]{3,50}\z/', $values['username'])) {
        $errors[] = 'Use 3–50 letters, numbers, periods, underscores, or hyphens for the username.';
    }
    $password = is_string($input['password'] ?? null) ? $input['password'] : '';
    $confirmation = is_string($input['confirm_password'] ?? null) ? $input['confirm_password'] : '';
    if (strlen($password) < 8 || strlen($password) > 72 || strpos($password, "\0") !== false) {
        $errors[] = 'Use a password between 8 and 72 characters.';
    }
    if (!hash_equals($password, $confirmation)) {
        $errors[] = 'The password confirmation does not match.';
    }
    if ($errors) {
        return ['created' => false, 'errors' => $errors, 'values' => $values];
    }

    try {
        $check = $pdo->prepare('SELECT id FROM admins WHERE LOWER(username) = LOWER(?) LIMIT 1');
        $check->execute([$values['username']]);
        if ($check->fetchColumn() !== false) {
            return $fail('That username is already taken. Choose another username.');
        }
        // The role, position, and approval state are fixed server-side.
        $statement = $pdo->prepare("INSERT INTO admins
            (full_name, username, password_hash, role, barangay_assigned, sk_position, status, push_token)
            VALUES (?, ?, ?, 'sub-admin', ?, 'SK Chairperson', 'approved', '')");
        $statement->execute([
            $values['full_name'], $values['username'],
            password_hash($password, PASSWORD_DEFAULT), $values['barangay'],
        ]);
        return ['created' => true, 'errors' => [], 'values' => $values];
    } catch (PDOException $exception) {
        // Also handle a competing request taking the username after our check.
        if (in_array((string)$exception->getCode(), ['23000', '23505'], true)) {
            return $fail('That username is already taken. Choose another username.');
        }
        error_log('SK account creation failed: ' . $exception->getCode());
        return $fail('The account could not be created. Please try again.');
    }
}
