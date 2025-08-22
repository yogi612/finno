<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Check if user is authenticated
if (!isAuthenticated()) {
    header('Location: /login');
    exit;
}

$user_id = $_SESSION['user_id'];
$searchTerm = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$loading = isset($_GET['refresh']);

// Simulate loading state to match React behavior
if ($loading) {
    usleep(500000); // 500ms delay
    header('Location: /applications');
    exit;
}

require_once __DIR__ . '/../includes/header.php';

// Add helper to convert dd/mm/yyyy to Y-m-d
function parseDateDMY($dateStr) {
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateStr, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return $dateStr;
}

// Helper to convert Y-m-d to dd/mm/yyyy for display
function formatDateToDMY($dateStr) {
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateStr, $m)) {
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }
    return $dateStr;
}

// Get user applications
$profile = getProfile();
$isManager = (isset($profile['role']) && $profile['role'] === 'manager');

$allowed_user_ids = [$user_id];

if ($isManager) {
    // Fetch team member IDs for the current manager
    $stmt = $pdo->prepare("
        SELECT tm.user_id FROM team_members tm
        JOIN teams t ON tm.team_id = t.id
        WHERE t.manager_id = ?
    ");
    $stmt->execute([$user_id]);
    $teamMemberIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($teamMemberIds) {
        $allowed_user_ids = array_merge($allowed_user_ids, $teamMemberIds);
    }
}

// Build the WHERE clause for user IDs
if (count($allowed_user_ids) > 1) {
    $in_placeholders = implode(',', array_fill(0, count($allowed_user_ids), '?'));
    $where = ["user_id IN ($in_placeholders)"];
    $params = $allowed_user_ids;
} else {
    $where = ["user_id = ?"];
    $params = [$user_id];
}

if (!empty($searchTerm)) {
    $where[] = "(customer_name LIKE ? OR rc_number LIKE ? OR financer_name LIKE ?)";
    $searchPattern = '%' . $searchTerm . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}
if (!empty($statusFilter)) {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}
// Add PDD Status filter
$pddStatus = $_GET['pdd_status'] ?? '';
if (!empty($pddStatus)) {
    $where[] = "pdd_status = ?";
    $params[] = $pddStatus;
}
// Add Case Type filter (case-insensitive, partial match)
$caseType = $_GET['case_type'] ?? '';
if (!empty($caseType)) {
    $where[] = "LOWER(case_type) LIKE ?";
    $params[] = '%' . strtolower($caseType) . '%';
}
// Add Financer filter (case-insensitive, partial match)
$financerName = $_GET['financer_name'] ?? '';
if (!empty($financerName)) {
    $where[] = "LOWER(financer_name) LIKE ?";
    $params[] = '%' . strtolower($financerName) . '%';
}
// Add Date From/To filter (convert dd/mm/yyyy to Y-m-d)
$dateFrom = $_GET['date_from'] ?? '';
if (!empty($dateFrom)) {
    $dateFromSql = parseDateDMY($dateFrom);
    $where[] = "DATE(disbursed_date) >= ?";
    $params[] = $dateFromSql;
    $dateFrom = formatDateToDMY($dateFrom); // always show as dd/mm/yyyy
}
$dateTo = $_GET['date_to'] ?? '';
if (!empty($dateTo)) {
    $dateToSql = parseDateDMY($dateTo);
    $where[] = "DATE(disbursed_date) <= ?";
    $params[] = $dateToSql;
    $dateTo = formatDateToDMY($dateTo); // always show as dd/mm/yyyy
}

$customerName = $_GET['customer_name'] ?? '';
if (!empty($customerName)) {
    $where[] = "LOWER(customer_name) LIKE ?";
    $params[] = '%' . strtolower($customerName) . '%';
}
$rcNumber = $_GET['rc_number'] ?? '';
if (!empty($rcNumber)) {
    $where[] = "LOWER(rc_number) LIKE ?";
    $params[] = '%' . strtolower($rcNumber) . '%';
}

$whereSql = implode(' AND ', $where);
$stmt = $pdo->prepare("SELECT * FROM applications WHERE $whereSql ORDER BY disbursed_date DESC, created_at DESC");
$stmt->execute($params);
$applications = $stmt->fetchAll();
?>

<div class="space-y-6 sm:space-y-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center space-y-2 sm:space-y-0">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">My Disbursement</h1>
            <p class="text-gray-600 mt-1 text-sm sm:text-base">View and manage all your loan disbursements</p>
        </div>
        <div class="text-sm text-gray-500">
            Total: <?= count($applications) ?> Disbursements
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 border border-gray-100">
        <form method="GET" action="" class="flex flex-wrap gap-3 items-end">
            <div class="min-w-[120px]">
                <input
                    type="text"
                    name="date_from"
                    value="<?= htmlspecialchars($dateFrom) ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 text-sm sm:text-base datepicker"
                    placeholder="From (dd/mm/yyyy)"
                    autocomplete="off"
                />
            </div>
            <div class="min-w-[120px]">
                <input
                    type="text"
                    name="date_to"
                    value="<?= htmlspecialchars($dateTo) ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 text-sm sm:text-base datepicker"
                    placeholder="To (dd/mm/yyyy)"
                    autocomplete="off"
                />
            </div>
            <div class="min-w-[160px]">
                <input
                    type="text"
                    name="customer_name"
                    placeholder="Customer Name"
                    value="<?= htmlspecialchars($_GET['customer_name'] ?? '') ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded text-sm sm:text-base"
                />
            </div>
            <div class="min-w-[140px]">
                <input
                    type="text"
                    name="rc_number"
                    placeholder="Vehicle RC Number"
                    value="<?= htmlspecialchars($_GET['rc_number'] ?? '') ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded text-sm sm:text-base"
                />
            </div>
            <div class="min-w-[180px]">
                <input
                    type="text"
                    name="financer_name"
                    placeholder="Financer Name"
                    value="<?= htmlspecialchars($financerName) ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded text-sm sm:text-base"
                />
            </div>
            <div class="min-w-[140px]">
                <select
                    name="pdd_status"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 text-sm sm:text-base"
                >
                    <option value="">PDD Status</option>
                    <option value="Completed" <?= $pddStatus === 'Completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="RTO-WIP" <?= $pddStatus === 'RTO-WIP' ? 'selected' : '' ?>>RTO-WIP</option>
                    <option value="Pending" <?= $pddStatus === 'Pending' ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>
            <div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-filter mr-2"></i> Filter
                </button>
            </div>
            <div>
                <a href="/applications" class="flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </a>
            </div>
        </form>
    </div>

    <!-- Applications List -->
    <?php if (count($applications) === 0): ?>
        <div class="bg-white rounded-xl shadow-sm p-8 sm:p-12 text-center border border-gray-100">
            <i class="fas fa-file-alt text-gray-400 text-5xl mb-4"></i>
            <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-2">
                <?= empty($searchTerm) && empty($statusFilter) ? 'No applications yet' : 'No matching applications' ?>
            </h3>
            <p class="text-gray-600 text-sm sm:text-base">
                <?= empty($searchTerm) && empty($statusFilter) 
                    ? 'Get started by creating your first loan application'
                    : 'Try adjusting your search or filter criteria'
                ?>
            </p>
            <?php if (empty($searchTerm) && empty($statusFilter)): ?>
                <div class="mt-6">
                    <a href="/application/new" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 inline-block">
                        <i class="fas fa-plus mr-2"></i> Create Disbursement Application
                    </a>
                </div>
            <?php else: ?>
                <div class="mt-6">
                    <a href="/Disbursement" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 inline-block">
                        <i class="fas fa-times mr-2"></i> Clear Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white rounded-xl shadow-sm border border-gray-100">
                <thead>
                    <tr class="bg-gray-50 text-xs sm:text-sm text-gray-700 uppercase">
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Customer Name</th>
                        <th class="px-4 py-3 text-left">Mob. No</th>
                        <th class="px-4 py-3 text-left">Lender/Case Type</th>
                        <th class="px-4 py-3 text-left">Loan Amount</th>
                        <th class="px-4 py-3 text-left">Days</th>
                        <th class="px-4 py-3 text-left">PDD Status</th>
                        <th class="px-4 py-3 text-left">Executive</th>
                        <th class="px-4 py-3 text-left">Who We Are</th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                        <tr class="border-b last:border-b-0 hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <i class="fas fa-calendar-alt mr-1 text-gray-400"></i>
                                <?= !empty($app['disbursed_date']) ? formatDateToDMY(substr($app['disbursed_date'], 0, 10)) : '-' ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($app['customer_name'] ?? '') ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                <?= htmlspecialchars($app['mobile_number'] ?? '') ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                <div><?= htmlspecialchars($app['financer_name'] ?? '') ?></div>
                                <div class="text-xs text-gray-400"><?= htmlspecialchars($app['case_type'] ?? '') ?></div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                <div class="font-medium text-gray-900">₹<?= number_format($app['loan_amount']) ?></div>
                                <div class="text-xs text-gray-500">
                                    <?= $app['rate_of_interest'] ?>% / <?= $app['tenure_months'] ?>mo
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <?php
                                $days = '';
                                if ($app['pdd_status'] === 'Completed') {
                                    $days = '-';
                                } elseif (!empty($app['disbursed_date']) && strtotime($app['disbursed_date'])) {
                                    $days = (new DateTime())->diff(new DateTime($app['disbursed_date']))->days . ' days';
                                } else {
                                    $days = '-';
                                }
                                ?>
                                <span class="inline-block px-2 py-1 text-xs rounded bg-gray-100 text-gray-800">
                                    <?= $days ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php 
                                    if ($app['pdd_status'] === 'Completed') echo 'bg-green-100 text-green-800';
                                    elseif ($app['pdd_status'] === 'RTO-WIP') echo 'bg-blue-100 text-blue-800';
                                    elseif ($app['pdd_status'] === 'Pending') echo 'bg-yellow-100 text-yellow-800';
                                    else echo 'bg-gray-100 text-gray-800';
                                    ?>">
                                    <?= htmlspecialchars($app['pdd_status'] ?? '') ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <?php
                                // Lookup executive name and channel code if available
                                $execName = '-';
                                $channelCode = '-';
                                if (!empty($app['user_id'])) {
                                    $stmtExec = $pdo->prepare("SELECT name, channel_code, role FROM profiles WHERE user_id = ? LIMIT 1");
                                    $stmtExec->execute([$app['user_id']]);
                                    $exec = $stmtExec->fetch();
                                    if ($exec) {
                                        $execName = htmlspecialchars($exec['name'] ?? '');
                                        $channelCode = htmlspecialchars($exec['channel_code'] ?? '');
                                        $execRole = htmlspecialchars($exec['role'] ?? '');
                                    } else {
                                        $execRole = 'N/A';
                                    }
                                } else {
                                    $execRole = 'N/A';
                                }
                                ?>
                                <div class="text-gray-900"><?= $execName ?></div>
                                <div class="text-xs text-gray-500 font-mono"><?= $channelCode ?></div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <span class="inline-block px-2 py-1 text-xs rounded bg-gray-100 text-gray-800">
                                    <?= $execRole ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <a href="/application/view.php?id=<?= $app['id'] ?>" class="flex items-center px-3 py-2 text-sm text-blue-600 hover:text-blue-700 transition-colors hover:bg-blue-50 rounded-md">
                                    <i class="fas fa-eye mr-2"></i>
                                    View Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
// Simple datepicker: force dd/mm/yyyy format
function formatDateToDMY(date) {
    if (!date) return '';
    const d = new Date(date);
    if (isNaN(d)) return date;
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    return `${day}/${month}/${year}`;
}
document.querySelectorAll('input.datepicker').forEach(function(input) {
    input.addEventListener('change', function() {
        this.form.submit();
    });
    input.addEventListener('blur', function() {
        // Try to auto-format yyyy-mm-dd to dd/mm/yyyy
        if (/^\d{4}-\d{2}-\d{2}$/.test(this.value)) {
            const [y, m, d] = this.value.split('-');
            this.value = `${d}/${m}/${y}`;
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
