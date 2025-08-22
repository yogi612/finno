<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/header.php';
require_once '../includes/functions.php';

if (!isAuthenticated()) {
    header('Location: /login');
    exit;
}
$profile = getProfile();
if ($profile['role'] !== 'manager') {
    header('Location: /dashboard');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch audit logs for application changes by this manager
$stmt = $pdo->prepare("SELECT * FROM audit_logs WHERE user_id = ? AND event_type = 'application_updated' ORDER BY timestamp DESC LIMIT 100");
$stmt->execute([$user_id]);
$logs = $stmt->fetchAll();

?>
<div class="max-w-4xl mx-auto mt-10 bg-white rounded shadow p-8">
    <h1 class="text-2xl font-bold mb-6 flex items-center">
        <i class="fas fa-history mr-3 text-blue-600"></i>
        Application Change Logs (You: <?= htmlspecialchars($profile['name']) ?>)
    </h1>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Change Details</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars($log['timestamp']) ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            <?php $data = json_decode($log['event_data'], true); ?>
                            <?php if (!empty($data['changes'])): ?>
                                <ul class="list-disc pl-4">
                                    <?php foreach ($data['changes'] as $field => $change): ?>
                                        <li><b><?= htmlspecialchars($field) ?>:</b> <?= htmlspecialchars($change['old']) ?> → <?= htmlspecialchars($change['new']) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <span>No details</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($logs)): ?>
            <div class="text-center text-gray-500 py-8">No application changes recorded yet.</div>
        <?php endif; ?>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
