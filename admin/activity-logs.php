<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!can_menu($pdo, 'activity_logs', 'can_view')) {
    http_response_code(403);
    exit('Permission denied.');
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim((string)($_GET['q'] ?? ''));
$module = trim((string)($_GET['module'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        l.description LIKE :search_description
        OR l.module_name LIKE :search_module
        OR l.action LIKE :search_action
        OR l.ip_address LIKE :search_ip
        OR u.name LIKE :search_admin_name
        OR u.username LIKE :search_admin_username
    )";
    $searchValue = '%' . $search . '%';
    $params['search_description'] = $searchValue;
    $params['search_module'] = $searchValue;
    $params['search_action'] = $searchValue;
    $params['search_ip'] = $searchValue;
    $params['search_admin_name'] = $searchValue;
    $params['search_admin_username'] = $searchValue;
}

if ($module !== '') {
    $where[] = 'l.module_name = :module';
    $params['module'] = $module;
}

if ($action !== '') {
    $where[] = 'l.action = :action';
    $params['action'] = $action;
}

$whereSql = $where
    ? ' WHERE ' . implode(' AND ', $where)
    : '';

/*
|--------------------------------------------------------------------------
| Activity data - server rendered, no AJAX/API dependency
|--------------------------------------------------------------------------
*/

$countStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM activity_logs l
     LEFT JOIN admin_users u
        ON u.id = l.admin_user_id"
    . $whereSql
);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalRows / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$listSql =
    "SELECT
        l.id,
        l.admin_user_id,
        l.action,
        l.module_name,
        l.entity_type,
        l.entity_id,
        l.description,
        l.route_name,
        l.request_method,
        l.ip_address,
        l.created_at,
        COALESCE(NULLIF(u.name, ''), NULLIF(u.username, ''), 'System')
            AS admin_name
     FROM activity_logs l
     LEFT JOIN admin_users u
        ON u.id = l.admin_user_id"
    . $whereSql
    . " ORDER BY l.created_at DESC, l.id DESC
        LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;

$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$activityRows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$modules = $pdo->query(
    "SELECT DISTINCT module_name
     FROM activity_logs
     WHERE module_name IS NOT NULL
       AND module_name <> ''
     ORDER BY module_name"
)->fetchAll(PDO::FETCH_COLUMN);

$actions = $pdo->query(
    "SELECT DISTINCT action
     FROM activity_logs
     WHERE action <> ''
     ORDER BY action"
)->fetchAll(PDO::FETCH_COLUMN);

function activity_logs_page_url(array $changes = []): string
{
    $query = $_GET;

    foreach ($changes as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
            continue;
        }

        $query[$key] = (string)$value;
    }

    $url = admin_url('activity-logs.php');

    return $query
        ? $url . '?' . http_build_query($query)
        : $url;
}

$pageTitle = 'Activity Logs';
$pageScript = null;

require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div>
        <h2 class="h4 mb-1">Business Activity Logs</h2>
        <p class="text-muted mb-0">
            Track login, create, update, delete and status actions.
        </p>
    </div>

    <a
        class="btn btn-outline-secondary"
        href="<?= e(admin_url('activity-logs.php')); ?>"
    >
        <i class="fa-solid fa-rotate me-2"></i>
        Refresh
    </a>
</div>

<div class="ramki-card p-3 mb-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-lg-5 col-md-12">
            <label class="form-label" for="activitySearch">Search</label>
            <input
                type="search"
                class="form-control"
                id="activitySearch"
                name="q"
                value="<?= e($search); ?>"
                placeholder="Admin, module, description or IP"
            >
        </div>

        <div class="col-lg-2 col-md-4">
            <label class="form-label" for="activityModule">Module</label>
            <select class="form-select" id="activityModule" name="module">
                <option value="">All modules</option>
                <?php foreach ($modules as $moduleOption): ?>
                    <option
                        value="<?= e((string)$moduleOption); ?>"
                        <?= $module === (string)$moduleOption ? 'selected' : ''; ?>
                    >
                        <?= e((string)$moduleOption); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-lg-2 col-md-4">
            <label class="form-label" for="activityAction">Action</label>
            <select class="form-select" id="activityAction" name="action">
                <option value="">All actions</option>
                <?php foreach ($actions as $actionOption): ?>
                    <option
                        value="<?= e((string)$actionOption); ?>"
                        <?= $action === (string)$actionOption ? 'selected' : ''; ?>
                    >
                        <?= e(ucwords(str_replace('_', ' ', (string)$actionOption))); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-lg-3 col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-ramki flex-grow-1">
                <i class="fa-solid fa-filter me-2"></i>
                Filter
            </button>

            <?php if ($search !== '' || $module !== '' || $action !== ''): ?>
                <a
                    class="btn btn-outline-secondary"
                    href="<?= e(admin_url('activity-logs.php')); ?>"
                    title="Clear filters"
                >
                    <i class="fa-solid fa-xmark"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="ramki-card p-3">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
        <h3 class="h5 mb-0">Activity History</h3>
        <span class="text-muted small">
            <?= number_format($totalRows); ?> record<?= $totalRows === 1 ? '' : 's'; ?>
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Date</th>
                    <th scope="col">Admin</th>
                    <th scope="col">Module</th>
                    <th scope="col">Action</th>
                    <th scope="col">Description</th>
                    <th scope="col">IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$activityRows): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="fa-solid fa-clock-rotate-left d-block fs-3 mb-2"></i>
                            No activity logs found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($activityRows as $row): ?>
                        <?php
                        $createdAt = (string)($row['created_at'] ?? '');
                        $timestamp = $createdAt !== '' ? strtotime($createdAt) : false;
                        $displayDate = $timestamp !== false
                            ? date('d M Y, h:i A', $timestamp)
                            : $createdAt;

                        $actionLabel = ucwords(
                            str_replace('_', ' ', (string)($row['action'] ?? ''))
                        );
                        ?>
                        <tr>
                            <td><?= (int)$row['id']; ?></td>
                            <td class="text-nowrap"><?= e($displayDate); ?></td>
                            <td><?= e((string)$row['admin_name']); ?></td>
                            <td><?= e((string)($row['module_name'] ?? '—')); ?></td>
                            <td>
                                <span class="badge text-bg-light border">
                                    <?= e($actionLabel !== '' ? $actionLabel : '—'); ?>
                                </span>
                            </td>
                            <td style="min-width: 260px;">
                                <?= e((string)($row['description'] ?? '—')); ?>
                            </td>
                            <td class="text-nowrap">
                                <?= e((string)($row['ip_address'] ?? '—')); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-3" aria-label="Activity log pages">
            <ul class="pagination pagination-sm justify-content-end mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : ''; ?>">
                    <a
                        class="page-link"
                        href="<?= e(activity_logs_page_url([
                            'page' => max(1, $page - 1),
                        ])); ?>"
                    >
                        Previous
                    </a>
                </li>

                <li class="page-item disabled">
                    <span class="page-link">
                        Page <?= $page; ?> of <?= $totalPages; ?>
                    </span>
                </li>

                <li class="page-item <?= $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a
                        class="page-link"
                        href="<?= e(activity_logs_page_url([
                            'page' => min($totalPages, $page + 1),
                        ])); ?>"
                    >
                        Next
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
