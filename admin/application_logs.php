<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/header.php';
require_once '../includes/functions.php';

if (!isAuthenticated() || !isAdmin()) {
    header('Location: /login');
    exit;
}

// Fetch all audit logs for application changes (all users)
$userFilter = isset($_GET['user_id']) ? $_GET['user_id'] : null;
if ($userFilter) {
    $stmt = $pdo->prepare("SELECT l.*, p.name as user_name, p.role as user_role FROM audit_logs l LEFT JOIN profiles p ON l.user_id = p.user_id WHERE l.event_type = 'application_updated' AND l.user_id = ? ORDER BY l.timestamp DESC LIMIT 200");
    $stmt->execute([$userFilter]);
} else {
    $stmt = $pdo->prepare("SELECT l.*, p.name as user_name, p.role as user_role FROM audit_logs l LEFT JOIN profiles p ON l.user_id = p.user_id WHERE l.event_type = 'application_updated' ORDER BY l.timestamp DESC LIMIT 200");
    $stmt->execute();
}
$logs = $stmt->fetchAll();

// Group logs by user and application
$groupedLogs = [];
foreach ($logs as $log) {
    $userId = $log['user_id'] ?? '';
    $appId = json_decode($log['event_data'], true)['application_id'] ?? '';
    $key = $userId . '|' . $appId;
    if (!isset($groupedLogs[$key])) {
        $groupedLogs[$key] = [
            'user_name' => $log['user_name'],
            'user_role' => $log['user_role'],
            'user_id' => $userId,
            'application_id' => $appId,
            'logs' => []
        ];
    }
    $groupedLogs[$key]['logs'][] = $log;
}
?>
<div class="max-w-6xl mx-auto mt-10 bg-white rounded shadow p-8">
    <h1 class="text-2xl font-bold mb-6 flex items-center">
        <i class="fas fa-history mr-3 text-red-600"></i>
        Application Change Logs (All Users)
    </h1>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Application ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Change Details</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php $rowIdx = 0; foreach ($groupedLogs as $group): $rowIdx++; ?>
                    <tr class="cursor-pointer hover:bg-gray-100 transition" onclick="document.getElementById('expand-<?= $rowIdx ?>').classList.toggle('hidden')">
                        <td class="px-4 py-3 text-sm text-gray-700">
                            <?= htmlspecialchars($group['logs'][0]['timestamp']) ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-blue-700 font-mono">
                            <?= htmlspecialchars($group['user_name'] ?? '-') ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700"><?= htmlspecialchars($group['user_role'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm text-blue-700 font-mono"><?= htmlspecialchars($group['application_id'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            <div class="truncate">(<?= count($group['logs']) ?> change<?= count($group['logs']) > 1 ? 's' : '' ?>)</div>
                            <div id="expand-<?= $rowIdx ?>" class="hidden mt-2">
                                <?php foreach ($group['logs'] as $log): ?>
                                    <div class="mb-2 p-2 border border-gray-200 rounded bg-gray-50">
                                        <div class="text-xs text-gray-500 mb-1">Time: <?= htmlspecialchars($log['timestamp']) ?></div>
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
                                    </div>
                                <?php endforeach; ?>
                            </div>
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
