<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../config/database.php';

// Check admin access first
if (!isAdmin()) {
    header('Location: /dashboard');
    exit;
}

$team_id = $_GET['id'] ?? null;
if (!$team_id) {
    header('Location: /admin/teams.php');
    exit;
}

// Handle form submissions for adding/removing team members
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_member'])) {
        $user_id = $_POST['user_id'];
        if (!empty($user_id)) {
            $stmt = $pdo->prepare("INSERT INTO team_members (team_id, user_id) VALUES (?, ?)");
            $stmt->execute([$team_id, $user_id]);
        }
    } elseif (isset($_POST['remove_member'])) {
        $user_id = $_POST['user_id'];
        $stmt = $pdo->prepare("DELETE FROM team_members WHERE team_id = ? AND user_id = ?");
        $stmt->execute([$team_id, $user_id]);
    }
    header("Location: /admin/manage_team.php?id=$team_id");
    exit;
}

// Get team details
$stmt = $pdo->prepare("SELECT t.team_name, p.name as manager_name FROM teams t JOIN profiles p ON t.manager_id = p.user_id WHERE t.id = ?");
$stmt->execute([$team_id]);
$team = $stmt->fetch();

if (!$team) {
    header('Location: /admin/teams.php');
    exit;
}

// Get current team members
$stmt = $pdo->prepare("SELECT u.id, p.name, p.email FROM users u JOIN profiles p ON u.id = p.user_id JOIN team_members tm ON u.id = tm.user_id WHERE tm.team_id = ?");
$stmt->execute([$team_id]);
$teamMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get users who are not managers and not already in a team
$stmt = $pdo->query("
    SELECT u.id, p.name 
    FROM users u 
    JOIN profiles p ON u.id = p.user_id 
    LEFT JOIN team_members tm ON u.id = tm.user_id 
    WHERE p.role != 'manager' AND p.role != 'Admin' AND tm.id IS NULL
");
$availableUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<div class="space-y-8">
    <!-- Header -->
    <div class="bg-gradient-to-br from-green-600 via-green-700 to-green-800 rounded-2xl p-8 text-white shadow-xl">
        <h1 class="text-3xl font-bold mb-2">Manage Team: <?= htmlspecialchars($team['team_name']) ?></h1>
        <p class="text-green-100 text-lg">
            Manager: <?= htmlspecialchars($team['manager_name']) ?>
        </p>
    </div>

    <!-- Add Team Member Form -->
    <div class="bg-white p-6 rounded-2xl shadow">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Add Employee to Team</h2>
        <form action="/admin/manage_team.php?id=<?= htmlspecialchars($team_id) ?>" method="POST" class="flex items-center space-x-4">
            <div class="flex-grow">
                <label for="user_id" class="sr-only">Select Employee</label>
                <select id="user_id" name="user_id" required class="block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm">
                    <option value="">Select an Employee to Add</option>
                    <?php foreach ($availableUsers as $user): ?>
                        <option value="<?= htmlspecialchars($user['id']) ?>"><?= htmlspecialchars($user['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" name="add_member" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Add Member
                </button>
            </div>
        </form>
    </div>

    <!-- Team Members Table -->
    <div class="bg-white p-6 rounded-2xl shadow">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Current Team Members</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($teamMembers)): ?>
                        <tr>
                            <td colspan="3" class="px-6 py-4 text-center text-gray-500">This team has no members yet.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($teamMembers as $member): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($member['name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($member['email']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <form action="/admin/manage_team.php?id=<?= htmlspecialchars($team_id) ?>" method="POST" class="inline">
                                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($member['id']) ?>">
                                    <button type="submit" name="remove_member" class="text-red-600 hover:text-red-900">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
