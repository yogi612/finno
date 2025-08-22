<?php
/**
 * Loading Spinner Component
 * 
 * @param string $size Size of the spinner (sm, md, lg, xl)
 * @param string $color Color of the spinner (primary, secondary, white, gray)
 * @param string $text Optional text to display
 * @param boolean $overlay Whether to display the spinner with an overlay
 */

function renderLoadingSpinner($size = 'md', $color = 'primary', $text = '', $overlay = false) {
    $sizeClasses = [
        'sm' => 'h-4 w-4',
        'md' => 'h-6 w-6',
        'lg' => 'h-8 w-8',
        'xl' => 'h-12 w-12'
    ];
    
    $colorClasses = [
        'primary' => 'text-red-600',
        'secondary' => 'text-blue-600',
        'white' => 'text-white',
        'gray' => 'text-gray-600'
    ];
    
    $textSizeClasses = [
        'sm' => 'text-sm',
        'md' => 'text-base',
        'lg' => 'text-lg',
        'xl' => 'text-xl'
    ];
    
    $spinner = '<div class="flex flex-col items-center justify-center">
        <div class="' . $sizeClasses[$size] . ' ' . $colorClasses[$color] . ' animate-spin rounded-full border-2 border-current border-r-transparent"></div>';
    
    if (!empty($text)) {
        $spinner .= '<p class="mt-2 ' . $textSizeClasses[$size] . ' ' . $colorClasses[$color] . ' animate-pulse">' . $text . '</p>';
    }
    
    $spinner .= '</div>';
    
    if ($overlay) {
        $spinner = '<div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-lg p-6 shadow-xl">
                ' . $spinner . '
            </div>
        </div>';
    }
    
    return $spinner;
}
?>