<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Check if user is authenticated and is an admin
if (!isAuthenticated() || !isAdmin()) {
    header('Location: /login');
    exit;
}


$search = $_GET['search'] ?? '';
$query = "SELECT rc_lookups.*, profiles.name as user_name FROM rc_lookups JOIN profiles ON rc_lookups.user_id = profiles.user_id";
$params = [];

if ($search) {
    $query .= " WHERE rc_lookups.rc_number LIKE ? OR profiles.name LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY rc_lookups.created_at DESC";
$lookups = db_query($query, $params);

?>

<div class="w-full px-4">
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-semibold text-gray-800">RC Lookup History</h2>
            <form method="get" class="flex items-center">
                <input type="text" name="search" placeholder="Search by RC number or owner" class="border rounded-l-md py-2 px-4" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                <button type="submit" class="bg-blue-500 text-white py-2 px-4 rounded-r-md">Search</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="py-3 px-4 uppercase font-semibold text-sm">RC No</th>
                        <th class="py-3 px-4 uppercase font-semibold text-sm">Owner Name</th>
                        <th class="py-3 px-4 uppercase font-semibold text-sm">Vehicle Model</th>
                        <th class="py-3 px-4 uppercase font-semibold text-sm">Registration Date</th>
                        <th class="py-3 px-4 uppercase font-semibold text-sm">Lenders</th>
                        <th class="py-3 px-4 uppercase font-semibold text-sm">Insurance Upto</th>
                        <th class="py-3 px-4 uppercase font-semibold text-sm">Insurance Provider</th>
                        <th class="py-3 px-4 uppercase font-semibold text-sm">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700">
                    <?php if (empty($lookups)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">No RC lookups found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lookups as $lookup): ?>
                            <?php
                            $response_data = json_decode($lookup['api_response'], true);
                            $details = null;
                            if (json_last_error() === JSON_ERROR_NONE && is_array($response_data)) {
                                if (isset($response_data['data']) && is_array($response_data['data'])) {
                                    $details = $response_data['data'];
                                } else {
                                    $details = $response_data;
                                }
                            }
                            $owner_name = $details['owner'] ?? 'N/A';
                            $vehicle_model = $details['makerModel'] ?? 'N/A';
                            $registration_date = $details['registered'] ?? 'N/A';
                            $lenders = $details['lender'] ?? 'N/A';
                            $insurance_upto = $details['insuranceUpto'] ?? 'N/A';
                            $insurance_provider = $details['insuranceProvider'] ?? 'N/A';
                            ?>
                            <tr>
                                <td class="py-3 px-4"><?= htmlspecialchars($lookup['rc_number']) ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($owner_name) ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($vehicle_model) ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($registration_date) ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($lenders) ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($insurance_upto) ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($insurance_provider) ?></td>
                                <td class="py-3 px-4">
                                    <button class="bg-blue-500 text-white px-3 py-1 rounded-md view-btn" data-response='<?= htmlspecialchars($lookup['api_response']) ?>'>View</button>
                                    <a href="/admin/download_rc_certificate.php?id=<?= $lookup['id'] ?>" class="bg-green-500 text-white px-3 py-1 rounded-md">Download</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="responseModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
    <div class="relative top-20 mx-auto p-5 border w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Full API Response</h3>
            <div class="mt-2 px-7 py-3">
                <pre id="modal-content" class="bg-gray-100 p-2 rounded-md text-xs whitespace-pre-wrap text-left"></pre>
            </div>
            <div class="items-center px-4 py-3">
                <button id="closeModal" class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('responseModal');
    const closeModal = document.getElementById('closeModal');
    const modalContent = document.getElementById('modal-content');

    document.querySelectorAll('.view-btn').forEach(button => {
        button.addEventListener('click', function() {
            const response = this.getAttribute('data-response');
            try {
                const formattedResponse = JSON.stringify(JSON.parse(response), null, 2);
                modalContent.textContent = formattedResponse;
            } catch (e) {
                modalContent.textContent = response;
            }
            modal.classList.remove('hidden');
        });
    });

    closeModal.addEventListener('click', function() {
        modal.classList.add('hidden');
    });

    window.addEventListener('click', function(event) {
        if (event.target == modal) {
            modal.classList.add('hidden');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
