<?php
require_once __DIR__ . '/../includes/header.php';

// Get dashboard data
$profile = getProfile();
$user_id = $_SESSION['user_id'];

// Get recent applications
$recentApplications = getApplications($user_id, 10);

// Calculate application statistics
$stmt = $pdo->prepare("SELECT 
  COUNT(*) as total,
  SUM(loan_amount) as total_loan_amount
FROM applications
WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

$totalApplications = $stats['total'];
$totalLoanAmount = $stats['total_loan_amount'];
?>

<div class="space-y-8">
    <!-- Header -->
    <div class="bg-gradient-to-br from-green-600 via-green-700 to-green-800 rounded-2xl p-8 text-white shadow-xl">
        <h1 class="text-3xl font-bold mb-2">Employee Dashboard</h1>
        <p class="text-green-100 text-lg">
            Welcome, <?= htmlspecialchars($profile['name']) ?>. Here's an overview of your performance.
        </p>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                    <p class="text-sm font-medium text-gray-600">Total Loan Amount</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1">₹<?= number_format($totalLoanAmount) ?></p>
                </div>
                <div class="p-3 rounded-lg bg-green-100">
                    <i class="fas fa-rupee-sign text-green-600 text-lg"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Applications -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">My Recent Applications</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Loan Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($recentApplications as $app): ?>
                        <tr class="hover:bg-gray-50 transition-colors text-sm">
                            <td class="px-4 py-3"><?= htmlspecialchars($app['customer_name']) ?></td>
                            <td class="px-4 py-3">₹<?= number_format($app['loan_amount']) ?></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php 
                                    if (($app['pdd_status'] ?? '') === 'Completed') echo 'bg-green-100 text-green-800';
                                    elseif (($app['pdd_status'] ?? '') === 'RTO-WIP') echo 'bg-blue-100 text-blue-800';
                                    elseif (($app['pdd_status'] ?? '') === 'Pending') echo 'bg-yellow-100 text-yellow-800';
                                    else echo 'bg-gray-100 text-gray-800';
                                    ?>">
                                    <?= htmlspecialchars($app['pdd_status'] ?? '-') ?>
                                </span>
                            </td>
                            <td class="px-4 py-3"><?= date('d/m/Y', strtotime($app['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
