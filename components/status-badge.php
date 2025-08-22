<?php
/**
 * Status Badge Component
 * 
 * @param string $status Status value (approved, rejected, pending, etc.)
 * @param string $type Optional type override to force specific styling
 */

function renderStatusBadge($status, $type = null) {
    // Determine badge type based on status if not explicitly provided
    if ($type === null) {
        switch (strtolower($status)) {
            case 'approved':
                $type = 'success';
                break;
            case 'rejected':
                $type = 'danger';
                break;
            case 'pending':
                $type = 'warning';
                break;
            case 'incomplete':
                $type = 'info';
                break;
            default:
                $type = 'default';
        }
    }
    
    // Define badge classes based on type
    $badgeClasses = [
        'success' => 'bg-green-100 text-green-800 border border-green-200',
        'danger' => 'bg-red-100 text-red-800 border border-red-200',
        'warning' => 'bg-yellow-100 text-yellow-800 border border-yellow-200',
        'info' => 'bg-blue-100 text-blue-800 border border-blue-200',
        'default' => 'bg-gray-100 text-gray-800 border border-gray-200'
    ];
    
    // Define icons for each status type
    $icons = [
        'success' => 'check-circle',
        'danger' => 'times-circle',
        'warning' => 'clock',
        'info' => 'info-circle',
        'default' => 'circle'
    ];
    
    // Format the status text
    $displayText = ucfirst(strtolower($status));
    
    return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ' . $badgeClasses[$type] . '">
        <i class="fas fa-' . $icons[$type] . ' mr-1"></i>
        ' . $displayText . '
    </span>';
}
?>