<?php
// Render the real admin view with an empty, in-memory registry for browser tests.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(); }
class SkViewStatement extends PDOStatement {
    public function __construct() {}
    public function execute(?array $params = null): bool { return true; }
    public function fetchColumn(int $column = 0): mixed { return 0; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return []; }
}
class SkViewDatabase extends PDO {
    public function __construct() {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false { return new SkViewStatement(); }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new SkViewStatement(); }
}
chdir(dirname(__DIR__));
$pdo = new SkViewDatabase();
$_SESSION = ['admin_username' => 'Municipal Admin', 'role' => 'admin', 'sk_account_csrf' => str_repeat('a', 64)];
$_SERVER['PHP_SELF'] = '/admin_sk_accounts.php';
$pending_count = $approved_count = $total_sk_count = $current_filtered_count = 0;
$account_notice = $message = $search = $brgy_filter = $filterQuery = '';
$msg_type = 'success';
$status_filter = 'pending';
$accounts = $create_errors = [];
$create_values = ['full_name' => '', 'barangay' => '', 'username' => ''];
$source = file_get_contents('admin_sk_accounts.php');
preg_match('/\$all_barangays = \[.*?\];/s', $source, $matches);
eval($matches[0]);
$view = substr($source, strpos($source, '<!DOCTYPE html>'));
$view = str_replace("<?php include_once 'genesis_widget.php'; ?>", '', $view);
eval('?>' . $view);
