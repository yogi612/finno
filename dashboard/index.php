<?php
// Ensure session is started before accessing $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/header.php';

// Get dashboard data for the current user
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$profile = getProfile();
$profile = is_array($profile) ? $profile : [];

// Redirect manager users to manager dashboard
if (isset($profile['role']) && $profile['role'] === 'manager') {
    header('Location: /manager/dashboard.php');
    exit;
}

// Get application statistics (new PDD status logic)
$stats = getApplicationStats($user_id);
$stats = is_array($stats) ? $stats : [];
$totalApplications = $stats['total'] ?? 0;
$rtoWipApplications = $stats['rto_wip'] ?? 0;
$pendingApplications = $stats['pending'] ?? 0;
$completedApplications = $stats['completed'] ?? 0;

// Get recent applications (limit to 5, with all key details)
$recentApplications = function_exists('getRecentApplications')
    ? getRecentApplications($user_id, 5)
    : getApplications($user_id, 5);

// Initialize application status counters to avoid undefined variable warnings
$approvedApplications = 0;
$rejectedApplications = 0;

// Count approved and rejected applications for status overview
if (is_array($recentApplications)) {
    foreach ($recentApplications as $application) {
        if (isset($application['status'])) {
            if ($application['status'] === 'approved') $approvedApplications++;
            if ($application['status'] === 'rejected') $rejectedApplications++;
        }
    }
}

// Get KYC submissions
$stmt = $pdo->prepare("SELECT * FROM kyc_submissions WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$recentKyc = $stmt->fetchAll();

$totalKycSubmissions = count($recentKyc);

// --- Business Report Calculations ---
$firstDayThisMonth = date('Y-m-01');
$firstDayLastMonth = date('Y-m-01', strtotime('first day of last month'));
$lastDayLastMonth = date('Y-m-t', strtotime('last month'));
$today = date('Y-m-d');

// Loan Disbursement Amounts
$stmt = $pdo->prepare("SELECT SUM(loan_amount) FROM applications WHERE user_id = ? AND disbursed_date >= ? AND disbursed_date <= ?");
$stmt->execute([$user_id, $firstDayThisMonth, $today]);
$loanDisbThisMonth = $stmt->fetchColumn() ?: 0;
$stmt = $pdo->prepare("SELECT SUM(loan_amount) FROM applications WHERE user_id = ? AND disbursed_date >= ? AND disbursed_date <= ?");
$stmt->execute([$user_id, $firstDayLastMonth, $lastDayLastMonth]);
$loanDisbLastMonth = $stmt->fetchColumn() ?: 0;
$stmt = $pdo->prepare("SELECT SUM(loan_amount) FROM applications WHERE user_id = ?");
$stmt->execute([$user_id]);
$loanDisbTillDate = $stmt->fetchColumn() ?: 0;

// Units Disbursed
$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE user_id = ? AND disbursed_date >= ? AND disbursed_date <= ?");
$stmt->execute([$user_id, $firstDayThisMonth, $today]);
$unitsThisMonth = $stmt->fetchColumn() ?: 0;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE user_id = ? AND disbursed_date >= ? AND disbursed_date <= ?");
$stmt->execute([$user_id, $firstDayLastMonth, $lastDayLastMonth]);
$unitsLastMonth = $stmt->fetchColumn() ?: 0;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE user_id = ?");
$stmt->execute([$user_id]);
$unitsTillDate = $stmt->fetchColumn() ?: 0;

// PDD Status
$stmt = $pdo->prepare("SELECT 
  COUNT(CASE WHEN LOWER(TRIM(pdd_status)) = 'pending' THEN 1 END) as pending,
  COUNT(CASE WHEN LOWER(TRIM(pdd_status)) = 'rto-wip' THEN 1 END) as rto_wip
FROM applications WHERE user_id = ?");
$stmt->execute([$user_id]);
$pddStats = $stmt->fetch();
$pddPending = $pddStats['pending'] ?? 0;
$pddRtoWip = $pddStats['rto_wip'] ?? 0;

// CRITICAL: Loans where today - disbursed_date >= 30 days and pdd_status != 'Completed' or 'Closed'
$stmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE user_id = ? AND DATEDIFF(?, disbursed_date) >= 30 AND LOWER(TRIM(pdd_status)) NOT IN ('completed','complete','closed','close')");
$stmt->execute([$user_id, $today]);
$pddCritical = $stmt->fetchColumn() ?: 0;
?>
<div class="p-2 sm:p-4 md:p-6 lg:p-6 max-w-7xl mx-auto animate__animated animate__fadeIn">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2 flex items-center">
            <i class="fas fa-tachometer-alt mr-3 text-red-600"></i>
            Manager Dashboard
        </h1>
        <?php if (!empty($profile['name'])): ?>
            <div class="mt-2 flex items-center text-base text-gray-700">
                <i class="fas fa-user-circle mr-2 text-gray-400"></i>
                <span class="font-semibold"><?= htmlspecialchars($profile['name']) ?></span>
                <span class="ml-3 px-2 py-1 rounded bg-gray-100 text-xs font-mono text-gray-600">Role: <?= htmlspecialchars(ucwords(str_replace('_', ' ', $profile['role']))) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Business Report Cards and Quick Actions Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
      <div class="space-y-6 flex flex-col">
        <!-- Loan Disbursement Amount -->
        <div class="bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl shadow-sm border border-blue-100 p-6 flex flex-col justify-between text-white">
          <div>
            <p class="text-base font-medium opacity-80 mb-2">Loan Disbursement Amount</p>
            <div class="space-y-1">
              <div class="flex items-center justify-between">
                <span class="text-xs opacity-80">This Month</span>
                <span class="text-xl font-bold">₹<?= number_format($loanDisbThisMonth ?? 0) ?></span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-xs opacity-80">Last Month</span>
                <span class="text-xl font-bold">₹<?= number_format($loanDisbLastMonth ?? 0) ?></span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-xs opacity-80">Till Date</span>
                <span class="text-xl font-bold">₹<?= number_format($loanDisbTillDate ?? 0) ?></span>
              </div>
            </div>
          </div>
          <div class="mt-4 flex justify-end">
            <i class="fas fa-rupee-sign text-2xl opacity-80"></i>
          </div>
        </div>
        <!-- Units Disbursed -->
        <div class="bg-gradient-to-br from-green-500 to-green-700 rounded-xl shadow-sm border border-green-100 p-6 flex flex-col justify-between text-white">
          <div>
            <p class="text-base font-medium opacity-80 mb-2">Units Disbursed</p>
            <div class="space-y-1">
              <div class="flex items-center justify-between">
                <span class="text-xs opacity-80">This Month</span>
                <span class="text-xl font-bold"><?= $unitsThisMonth ?? 0 ?></span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-xs opacity-80">Last Month</span>
                <span class="text-xl font-bold"><?= $unitsLastMonth ?? 0 ?></span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-xs opacity-80">Till Date</span>
                <span class="text-xl font-bold"><?= $unitsTillDate ?? 0 ?></span>
              </div>
            </div>
          </div>
          <div class="mt-4 flex justify-end">
            <i class="fas fa-hand-holding-usd text-2xl opacity-80"></i>
          </div>
        </div>
      </div>
      <div class="flex flex-col gap-6">
        <!-- PDD Status -->
        <div class="bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-xl shadow-sm border border-yellow-100 p-6 flex flex-col justify-between text-white mb-6">
          <div>
            <p class="text-base font-medium opacity-80 mb-2">PDD Status</p>
            <div class="space-y-1">
              <div class="flex items-center justify-between">
                <span class="text-xs opacity-80">Pending</span>
                <span class="text-xl font-bold"><?= $pddPending ?? 0 ?></span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-xs opacity-80">RTO-WIP</span>
                <span class="text-xl font-bold"><?= $pddRtoWip ?? 0 ?></span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-xs opacity-80">CRITICAL</span>
                <span class="text-xl font-bold"><?= $pddCritical ?? 0 ?></span>
              </div>
            </div>
          </div>
          <div class="mt-4 flex justify-end">
            <i class="fas fa-exclamation-triangle text-2xl opacity-80"></i>
          </div>
        </div>
        <!-- Quick Actions -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
            <i class="fas fa-bolt mr-2 text-yellow-600"></i>
            Quick Actions
          </h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <a href="/application/new" class="flex items-center justify-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
              <i class="fas fa-plus mr-2"></i>
              New Application
            </a>
            <a href="/applications" class="flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
              <i class="fas fa-file-alt mr-2"></i>
              View Applications
            </a>
            <?php if (!$profile['kyc_completed']): ?>
              <a href="/kyc/submit.php" class="flex items-center justify-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                <i class="fas fa-id-card mr-2"></i>
                Complete KYC
              </a>
            <?php endif; ?>
            <a href="/profile" class="flex items-center justify-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
              <i class="fas fa-user mr-2"></i>
              My Profile
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Content Grid -->
    <div class="max-w-7xl mx-auto">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Quick Actions and KYC -->
        <div>
          <!-- Recent KYC Submissions -->
          <?php if (!$profile['kyc_completed']): ?>
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
              <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                <i class="fas fa-id-card mr-2 text-purple-600"></i>
                KYC Status
              </h2>
              <?php if (!$profile['kyc_completed']): ?>
              <a href="/kyc/submit.php" class="text-xs bg-purple-600 text-white px-3 py-1 rounded hover:bg-purple-700">
                Complete KYC
              </a>
              <?php endif; ?>
            </div>
            <div class="space-y-4">
              <?php if ($profile['kyc_completed']): ?>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                  <i class="fas fa-check-circle text-green-500 text-3xl mb-2"></i>
                  <h3 class="font-medium text-green-800">KYC Verification Complete</h3>
                  <p class="text-sm text-green-700 mt-1">Your identity has been verified successfully.</p>
                </div>
              <?php elseif (count($recentKyc) > 0): ?>
                <?php foreach ($recentKyc as $kyc): ?>
                  <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div class="flex-1">
                      <h3 class="font-medium text-gray-900"><?= htmlspecialchars($kyc['full_name']) ?></h3>
                      <p class="text-xs text-gray-500 mt-1"><?= date('d M Y', strtotime($kyc['submitted_at'])) ?></p>
                    </div>
                    <div class="text-right">
                      <p class="text-xs text-gray-500 mt-1"><?= date('d M Y', strtotime($kyc['submitted_at'])) ?></p>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                  <i class="fas fa-id-card mx-auto mb-4 text-gray-300 text-4xl"></i>
                  <p>No KYC submissions</p>
                  <?php if (!$profile['kyc_completed']): ?>
                    <a href="/kyc/submit.php" class="mt-4 inline-block px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                      Complete KYC Verification
                    </a>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Getting Started -->
    <?php if ($totalApplications === 0): ?>
    <div class="mt-8 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-xl shadow-lg p-8 text-white">
        <h2 class="text-xl font-bold mb-4">Welcome to the DSA Sales Portal</h2>
        <p class="mb-6">Get started by creating your first loan application or completing your KYC verification.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <a href="/application/new" class="bg-white text-indigo-600 px-4 py-2 rounded-lg font-medium hover:bg-indigo-50 transition flex items-center justify-center">
                <i class="fas fa-file-alt mr-2"></i>
                Create First Application
            </a>
            <?php if (!$profile['kyc_completed']): ?>
            <a href="/kyc/submit.php" class="bg-indigo-700 text-white px-4 py-2 rounded-lg font-medium hover:bg-indigo-800 transition flex items-center justify-center">
                <i class="fas fa-id-card mr-2"></i>
                Complete KYC Verification
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<!-- Floating Plus Button with Dropdown -->
<style>
  .fab-group {
    position: fixed;
    right: 2rem;
    bottom: 2rem;
    z-index: 50;
  }
  .fab-group .fab-menu {
    display: none;
    position: absolute;
    right: 0;
    bottom: 5rem;
    width: 16rem;
    background: #fff;
    border-radius: 0.75rem;
    box-shadow: 0 10px 40px 0 rgba(0,0,0,0.15);
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
    z-index: 50;
  }
  .fab-group:hover .fab-menu,
  .fab-group:focus-within .fab-menu {
    display: block;
  }
</style>
<div class="fab-group">
  <button
    type="button"
    class="bg-red-600 hover:bg-red-700 text-white rounded-full shadow-lg w-16 h-16 flex items-center justify-center text-3xl transition duration-200 focus:outline-none"
    title="Create New Application"
    tabindex="0"
  >
    <i class="fas fa-plus"></i>
  </button>
  <div class="fab-menu">
    <a href="/application/new"
      class="block px-4 py-2 text-gray-700 hover:bg-gray-100 hover:text-red-600 transition"
      tabindex="0">
      ➕ Create Application
    </a>
  </div>
</div>