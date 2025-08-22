<?php
error_log("Admin Dashboard: Script started."); // Added for debugging log path
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/permissions.php';

// Check admin access
if (!isAdmin()) {
    header('Location: /dashboard');
    exit;
}

// Get dashboard stats
$stats = getStats();

// Get recent applications
$applications = getApplications(null, 10);
if (!is_array($applications)) {
    $applications = [];
}

// Calculate application statistics using PDD status
$stmt = $pdo->prepare("SELECT 
  COUNT(*) as total,
  COUNT(CASE WHEN LOWER(TRIM(pdd_status)) = 'rto-wip' THEN 1 END) as rto_wip,
  COUNT(CASE WHEN LOWER(TRIM(pdd_status)) = 'pending' THEN 1 END) as pending,
  COUNT(CASE WHEN LOWER(TRIM(pdd_status)) IN ('completed','complete','closed','close') THEN 1 END) as completed
FROM applications");
$stmt->execute();
$pddStats = $stmt->fetch();

$totalApplications = $pddStats['total'];

// Get team performance stats
$teamStats = [];
$stmt = $pdo->query("
    SELECT 
        t.team_name,
        p.name as manager_name,
        COUNT(a.id) as total_applications,
        SUM(a.loan_amount) as total_loan_amount
    FROM teams t
    JOIN profiles p ON t.manager_id = p.user_id
    LEFT JOIN team_members tm ON t.id = tm.team_id
    LEFT JOIN applications a ON tm.user_id = a.user_id
    GROUP BY t.id
");
$teamStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div>
    <!-- Header -->
    <div class="bg-white rounded-2xl p-8 text-gray-900 shadow-lg border border-gray-200">
        <h1 class="text-3xl font-bold mb-2 text-gray-800">Admin Dashboard</h1>
        <p class="text-gray-600 text-lg">
            Welcome, Admin. Here's a complete overview of the system.
        </p>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-6">
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Applications</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1"><?= $totalApplications ?></p>
                </div>
                <div class="p-3 rounded-lg bg-blue-100">
                    <i class="fas fa-file-alt text-blue-600 text-lg"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Active Users</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1"><?= $stats['approvedUsers'] ?></p>
                </div>
                <div class="p-3 rounded-lg bg-green-100">
                    <i class="fas fa-users text-green-600 text-lg"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Loan Value</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1">₹<?= number_format($stats['totalLoanAmount']) ?></p>
                </div>
                <div class="p-3 rounded-lg bg-purple-100">
                    <i class="fas fa-rupee-sign text-purple-600 text-lg"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Application Status Grid -->
    <div class="mt-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Application Status Overview</h2>
        <div class="grid grid-cols-3 gap-6">
            <a href="/admin/applications?pdd_status=pending" class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:bg-gray-50 transition-colors">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Pending Applications</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1"><?= $pddStats['pending'] ?? 0 ?></p>
                    </div>
                    <div class="p-3 rounded-lg bg-yellow-100">
                        <i class="fas fa-hourglass-half text-yellow-600 text-lg"></i>
                    </div>
                </div>
            </a>
            <a href="/admin/applications?pdd_status=rto-wip" class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:bg-gray-50 transition-colors">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">RTO Work In Progress</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1"><?= $pddStats['rto_wip'] ?? 0 ?></p>
                    </div>
                    <div class="p-3 rounded-lg bg-indigo-100">
                        <i class="fas fa-cogs text-indigo-600 text-lg"></i>
                    </div>
                </div>
            </a>
            <a href="/admin/applications?pdd_status=completed" class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:bg-gray-50 transition-colors">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Completed Applications</p>
                        <p class="text-3xl font-bold text-gray-900 mt-1"><?= $pddStats['completed'] ?? 0 ?></p>
                    </div>
                    <div class="p-3 rounded-lg bg-green-100">
                        <i class="fas fa-check-circle text-green-600 text-lg"></i>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Team Performance -->
    <div class="grid grid-cols-1 lg:grid-cols-1 gap-8 mt-6">
        <div class="lg:col-span-1 bg-white p-4 rounded-2xl shadow h-full">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Team Performance</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Team Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Manager</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Applications</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Loan Amount</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($teamStats as $team): ?>
                            <tr class="hover:bg-gray-50 transition-colors text-sm">
                                <td class="px-4 py-3 font-medium text-gray-900"><?= htmlspecialchars($team['team_name']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($team['manager_name']) ?></td>
                                <td class="px-4 py-3"><?= $team['total_applications'] ?></td>
                                <td class="px-4 py-3">₹<?= number_format($team['total_loan_amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
