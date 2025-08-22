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

// Handle form submission for creating a new team
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_team'])) {
    $team_name = trim($_POST['team_name']);
    $manager_id = trim($_POST['manager_id']);

    if (!empty($team_name) && !empty($manager_id)) {
        // Generate a UUID for the new team
        $stmt = $pdo->query("SELECT UUID() as uuid");
        $uuid = $stmt->fetch()['uuid'];

        $stmt = $pdo->prepare("INSERT INTO teams (id, team_name, manager_id) VALUES (?, ?, ?)");
        $stmt->execute([$uuid, $team_name, $manager_id]);
        header('Location: /admin/teams.php');
        exit;
    }
}

// Now that all logic is done, we can include the header
require_once '../includes/header.php';

// Get all teams and their managers
$stmt = $pdo->query("
    SELECT t.id, t.team_name, p.name as manager_name
    FROM teams t
    JOIN profiles p ON t.manager_id = p.user_id
");
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users with the 'manager' role
$stmt = $pdo->query("SELECT u.id, p.name FROM users u JOIN profiles p ON u.id = p.user_id WHERE p.role = 'manager'");
$managers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="space-y-8">
    <!-- Header -->
    <div class="bg-gradient-to-br from-blue-600 via-blue-700 to-blue-800 rounded-2xl p-8 text-white shadow-xl">
        <h1 class="text-3xl font-bold mb-2">Team Management</h1>
        <p class="text-blue-100 text-lg">
            Create, view, and manage your teams.
        </p>
    </div>

    <!-- Create Team Form -->
    <div class="bg-white p-6 rounded-2xl shadow">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Create New Team</h2>
        <form action="/admin/teams.php" method="POST" class="space-y-4">
            <div>
                <label for="team_name" class="block text-sm font-medium text-gray-700">Team Name</label>
                <input type="text" id="team_name" name="team_name" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>
            <div>
                <label for="manager_id" class="block text-sm font-medium text-gray-700">Assign Manager</label>
                <select id="manager_id" name="manager_id" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <option value="">Select a Manager</option>
                    <?php foreach ($managers as $manager): ?>
                        <option value="<?= htmlspecialchars($manager['id']) ?>"><?= htmlspecialchars($manager['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" name="create_team" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Create Team
                </button>
            </div>
        </form>
    </div>

    <!-- Teams Table -->
    <div class="bg-white p-6 rounded-2xl shadow">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Existing Teams</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Team Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Manager</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($teams as $team): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($team['team_name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($team['manager_name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="/admin/manage_team.php?id=<?= htmlspecialchars($team['id']) ?>" class="text-blue-600 hover:text-blue-900">Manage</a>
                                <a href="#" class="text-red-600 hover:text-red-900 ml-4">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
