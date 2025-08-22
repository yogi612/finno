<?php
// /admin/manager_permissions.php
require_once '../includes/header.php';




// Fetch all managers
$stmt = $pdo->prepare("SELECT u.id, p.name, p.email FROM users u JOIN profiles p ON u.id = p.user_id WHERE p.role = 'manager'");
$stmt->execute();
$managers = $stmt->fetchAll();

// All editable fields in applications
$fields = [
    'customer_name' => 'Customer Name',
    'mobile_number' => 'Mobile Number',
    'rc_number' => 'RC Number',
    'engine_number' => 'Engine Number',
    'chassis_number' => 'Chassis Number',
    'existing_lender' => 'Existing Lender',
    'case_type' => 'Case Type',
    'financer_name' => 'Financer',
    'loan_amount' => 'Loan Amount',
    'rate_of_interest' => 'Interest Rate',
    'tenure_months' => 'Tenure (months)',
    'rc_collection_method' => 'RC Collection',
    'channel_name' => 'Channel Name',
    'disbursed_date' => 'Disbursed Date',
    'dealing_person_name' => 'Dealing Person',
    'channel_code' => 'Channel Code',
];

// Handle permission update
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manager_user_id'])) {
    $manager_user_id = $_POST['manager_user_id'];
    $view_allowed = $_POST['fields_view'] ?? [];
    $edit_allowed = $_POST['fields_edit'] ?? [];
    $pdo->prepare("DELETE FROM manager_permissions WHERE manager_user_id = ?")->execute([$manager_user_id]);
    foreach ($fields as $field => $label) {
        $can_view = in_array($field, $view_allowed) ? 1 : 0;
        $can_edit = in_array($field, $edit_allowed) ? 1 : 0;
        if ($can_view || $can_edit) {
            $pdo->prepare("INSERT INTO manager_permissions (manager_user_id, field_name, can_view, can_edit) VALUES (?, ?, ?, ?)")
                ->execute([$manager_user_id, $field, $can_view, $can_edit]);
        }
    }
    $success = true;
}

// Manager selection
$selectedManagerId = $_GET['manager_id'] ?? $_POST['manager_user_id'] ?? ($managers[0]['id'] ?? null);
$selectedManager = null;
foreach ($managers as $m) {
    if ($m['id'] === $selectedManagerId) {
        $selectedManager = $m;
        break;
    }
}
// Fetch permissions for selected manager
$perms = [];
if ($selectedManagerId) {
    $stmt = $pdo->prepare("SELECT field_name, can_view, can_edit FROM manager_permissions WHERE manager_user_id = ?");
    $stmt->execute([$selectedManagerId]);
    foreach ($stmt->fetchAll() as $row) {
        $perms[$row['field_name']] = $row;
    }
}
?>
<div class="w-full mx-auto mt-10 bg-white rounded-xl shadow-lg border border-gray-200 p-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-blue-900 flex items-center">
            <i class="fas fa-user-shield mr-3 text-blue-600"></i>
            Manager Field Permissions
        </h1>
    </div>
    <div style="overflow-y: auto; max-height: 70vh;">
    <table class="w-full divide-y divide-gray-200 mb-8 bg-white rounded-lg shadow">
        <thead class="bg-blue-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-bold text-blue-900 uppercase">Manager</th>
                <?php foreach ($fields as $field => $label): ?>
                    <th class="px-2 py-3 text-center text-xs font-bold text-blue-900 uppercase"> <?= htmlspecialchars($label) ?> </th>
                <?php endforeach; ?>
                <th class="px-2 py-3 text-center text-xs font-bold text-blue-900 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($managers as $m): ?>
                <tr class="hover:bg-blue-50 transition">
                    <form method="POST">
                    <input type="hidden" name="dummy" value="1">
                    <td class="px-4 py-3 font-semibold text-gray-900 align-top bg-blue-50 border-r border-blue-100">
                        <?= htmlspecialchars($m['name']) ?><br>
                        <span class="text-xs text-gray-500">(<?= htmlspecialchars($m['email']) ?>)</span>
                        <input type="hidden" name="manager_user_id" value="<?= htmlspecialchars($m['id']) ?>">
                    </td>
                    <?php
                    // Fetch permissions for this manager
                    $stmt = $pdo->prepare("SELECT field_name, can_view, can_edit FROM manager_permissions WHERE manager_user_id = ?");
                    $stmt->execute([$m['id']]);
                    $rowPerms = [];
                    foreach ($stmt->fetchAll() as $row) {
                        $rowPerms[$row['field_name']] = $row;
                    }
                    ?>
                    <?php foreach ($fields as $field => $label): ?>
                        <td class="px-2 py-2 text-center align-top border-r border-blue-50">
                            <div class="flex flex-col items-center gap-1">
                                <label class="inline-flex items-center text-xs">
                                    <input type="checkbox" name="fields_view[]" value="<?= $field ?>" <?= (!empty($rowPerms[$field]['can_view'])) ? 'checked' : '' ?>>
                                    <span class="ml-1 text-green-700">View</span>
                                </label>
                                <label class="inline-flex items-center text-xs">
                                    <input type="checkbox" name="fields_edit[]" value="<?= $field ?>" <?= (!empty($rowPerms[$field]['can_edit'])) ? 'checked' : '' ?>>
                                    <span class="ml-1 text-blue-700">Edit</span>
                                </label>
                            </div>
                        </td>
                    <?php endforeach; ?>
                    <td class="px-2 py-2 text-center align-top">
                        <button type="submit" class="px-4 py-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-xs font-semibold shadow">Save</button>
                    </td>
                    </form>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if ($success): ?>
        <div class="mb-4 p-3 bg-green-100 text-green-800 rounded shadow">Permissions updated successfully.</div>
    <?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>
