<?php 
// Add APK Download Section after business info
if ($apk_data && file_exists($apk_data['file_path'])) {
    $base_domain = 'https://deegeecard.com';
    $file_path = ltrim($apk_data['file_path'], '/');
    $full_apk_url = $base_domain . '/' . $file_path;
    echo '<a href="' . htmlspecialchars($full_apk_url) . '" download class="btn btn-success btn-lg download_btn">';
    echo '<i class="bi bi-download me-2"></i> Download Our Android App';
    echo '</a>';
}
?>