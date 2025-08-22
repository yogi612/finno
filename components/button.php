<?php
/**
 * Button Component
 * 
 * @param string $text Button text
 * @param string $type Button type (primary, secondary, success, danger, warning, info)
 * @param string $size Button size (sm, md, lg)
 * @param string $icon Optional Font Awesome icon class
 * @param boolean $disabled Whether the button is disabled
 * @param boolean $loading Whether to show loading state
 * @param string $href Optional URL for anchor buttons
 * @param string $onclick Optional JavaScript onclick handler
 */

function renderButton($text, $type = 'primary', $size = 'md', $icon = null, $disabled = false, $loading = false, $href = null, $onclick = null) {
    $baseClasses = "inline-flex items-center justify-center font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2";
    
    $typeClasses = [
        'primary' => "bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white focus:ring-red-500 shadow-lg hover:shadow-xl",
        'secondary' => "bg-gray-600 hover:bg-gray-700 text-white focus:ring-gray-500",
        'success' => "bg-green-600 hover:bg-green-700 text-white focus:ring-green-500",
        'danger' => "bg-red-600 hover:bg-red-700 text-white focus:ring-red-500",
        'warning' => "bg-yellow-600 hover:bg-yellow-700 text-white focus:ring-yellow-500",
        'info' => "bg-blue-600 hover:bg-blue-700 text-white focus:ring-blue-500",
        'outline' => "border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 focus:ring-gray-500",
    ];
    
    $sizeClasses = [
        'sm' => "px-3 py-1.5 text-xs rounded-lg",
        'md' => "px-4 py-2 text-sm rounded-lg",
        'lg' => "px-6 py-3 text-base rounded-lg",
    ];
    
    $classes = $baseClasses . " " . $typeClasses[$type] . " " . $sizeClasses[$size];
    
    if ($disabled) {
        $classes .= " opacity-50 cursor-not-allowed";
    }
    
    $iconHtml = '';
    if ($icon) {
        $iconHtml = '<i class="fas fa-' . $icon . ' mr-2"></i>';
    }
    
    $loadingHtml = '';
    if ($loading) {
        $loadingHtml = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>';
        $iconHtml = ''; // Remove icon during loading
    }
    
    $content = $loadingHtml . $iconHtml . $text;
    
    if ($href) {
        return '<a href="' . $href . '" class="' . $classes . '"' . ($onclick ? ' onclick="' . $onclick . '"' : '') . '>' . $content . '</a>';
    } else {
        return '<button type="button" class="' . $classes . '"' . ($disabled ? ' disabled' : '') . ($onclick ? ' onclick="' . $onclick . '"' : '') . '>' . $content . '</button>';
    }
}
?>