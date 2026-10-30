<?php
// CLI-only import of the roster transcribed from the user's four reference images.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(); }
chdir(dirname(__DIR__));
$allow_offline_mode = true;
require_once 'db_connect.php';
if (!$pdo) { fwrite(STDERR, "A database connection is required.\n"); exit(1); }
$roster = json_decode(file_get_contents('data/sk-chairpersons-2026.json'), true, 512, JSON_THROW_ON_ERROR);
if (count($roster) !== 46 || count(array_unique(array_column($roster, 'barangay'))) !== 46) {
    throw new RuntimeException('The import must contain exactly 46 distinct barangays.');
}
$apply = in_array('--apply', $argv, true);
if ($apply) $pdo->beginTransaction();
try {
    $existing = $pdo->query("SELECT id, full_name, username, barangay_assigned FROM admins
        WHERE role = 'sub-admin' AND sk_position = 'SK Chairperson'" . ($apply ? ' FOR UPDATE' : ''))->fetchAll(PDO::FETCH_ASSOC);
    $usernames = $pdo->query('SELECT username FROM admins')->fetchAll(PDO::FETCH_COLUMN);
    $usedNames = array_map('strtolower', $usernames);
    $plan = [];
    foreach ($roster as $official) {
        $photo = realpath($official['profile_picture']);
        $photoRoot = realpath('uploads/sk_profiles/chairpersons-2026');
        if (!$photo || !$photoRoot || strpos($photo, $photoRoot . DIRECTORY_SEPARATOR) !== 0 || !getimagesize($photo)) {
            throw new RuntimeException('Missing or invalid portrait: ' . $official['full_name']);
        }
        $matches = array_values(array_filter($existing, static fn($row) => $row['barangay_assigned'] === $official['barangay']));
        if (count($matches) > 1) throw new RuntimeException('Multiple existing chairpersons for ' . $official['barangay']);
        if ($matches) {
            $official['id'] = (int)$matches[0]['id'];
            $official['username'] = $matches[0]['username'];
            $official['operation'] = 'update';
        } else {
            if (in_array(strtolower($official['username']), $usedNames, true)) {
                throw new RuntimeException('An unrelated account already uses username ' . $official['username']);
            }
            $usedNames[] = strtolower($official['username']);
            $official['operation'] = 'create';
        }
        $plan[] = $official;
    }
    $summary = array_count_values(array_column($plan, 'operation'));
    if (!$apply) {
        echo json_encode(['mode' => 'preview', 'counts' => $summary, 'accounts' => array_map(static fn($row) => [
            'full_name' => $row['full_name'], 'barangay' => $row['barangay_label'],
            'username' => $row['username'], 'operation' => $row['operation'],
        ], $plan)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        exit();
    }

    // Generated only for this run; plaintext is never written to the repository or export.
    $password = 'Tala-' . rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=') . '!';
    $update = $pdo->prepare("UPDATE admins SET full_name = ?, profile_picture = ?, password_hash = ?,
        status = 'approved' WHERE id = ? AND role = 'sub-admin' AND sk_position = 'SK Chairperson'");
    $insert = $pdo->prepare("INSERT INTO admins
        (full_name, username, password_hash, role, barangay_assigned, sk_position, status, profile_picture, push_token)
        VALUES (?, ?, ?, 'sub-admin', ?, 'SK Chairperson', 'approved', ?, '')");
    $check = $pdo->prepare('SELECT id, full_name, username, password_hash, role, barangay_assigned, sk_position, status, profile_picture FROM admins WHERE username = ?');
    $export = [];
    foreach ($plan as $official) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($official['operation'] === 'update') {
            $update->execute([$official['full_name'], $official['profile_picture'], $hash, $official['id']]);
        } else {
            $insert->execute([$official['full_name'], $official['username'], $hash, $official['barangay'], $official['profile_picture']]);
        }
        $check->execute([$official['username']]);
        $saved = $check->fetch(PDO::FETCH_ASSOC);
        if (!$saved || $saved['full_name'] !== $official['full_name'] || $saved['barangay_assigned'] !== $official['barangay'] ||
            $saved['role'] !== 'sub-admin' || $saved['sk_position'] !== 'SK Chairperson' || $saved['status'] !== 'approved' ||
            $saved['profile_picture'] !== $official['profile_picture'] || !password_verify($password, $saved['password_hash'])) {
            throw new RuntimeException('Account verification failed for ' . $official['full_name']);
        }
        $export[] = [
            'id' => (int)$saved['id'], 'full_name' => $saved['full_name'], 'barangay' => $official['barangay_label'],
            'username' => $saved['username'], 'status' => $saved['status'], 'profile_picture' => $saved['profile_picture'],
        ];
    }
    $pdo->commit();
    if (!is_dir('output')) mkdir('output', 0775, true);
    $file = fopen('output/sk-chairperson-accounts.csv', 'w');
    fputcsv($file, ['Name', 'Barangay', 'Username', 'Status', 'Profile image']);
    foreach ($export as $official) fputcsv($file, [$official['full_name'], $official['barangay'], $official['username'], $official['status'], $official['profile_picture']]);
    fclose($file);
    echo json_encode([
        'mode' => 'completed', 'verified_accounts' => count($export), 'counts' => $summary,
        'shared_password' => $password, 'usernames_csv' => 'output/sk-chairperson-accounts.csv',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
