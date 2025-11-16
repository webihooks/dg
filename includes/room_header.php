<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title><?= htmlspecialchars($business_info['business_name'] ?? 'Hotel') ?></title>

    <meta name="description" content="<?= htmlspecialchars($business_info['business_description'] ?? '') ?>">
    <link rel="icon" type="image/png" href="https://deegeecard.com/uploads/profile/<?= htmlspecialchars($photos['profile_photo'] ?? '') ?>">

    <!-- Open Graph Tags (for social media sharing) -->
    <meta property="og:title" content="<?= htmlspecialchars($business_info['business_name'] ?? 'Hotel') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($business_info['business_description'] ?? '') ?>">
    <meta property="og:image" content="https://deegeecard.com/uploads/profile/<?= htmlspecialchars($photos['profile_photo'] ?? '') ?>">
    <meta property="og:type" content="hotel">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($business_info['business_name'] ?? 'Hotel') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($business_info['business_description'] ?? '') ?>">
    <meta name="twitter:image" content="https://deegeecard.com/uploads/profile/<?= htmlspecialchars($photos['profile_photo'] ?? '') ?>">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        .primary-bg { background-color: <?php echo $primary_color; ?>; }
        .primary-text { color: <?php echo $primary_color; ?>; }
        .primary-border { border-color: <?php echo $primary_color; ?>; }
        .secondary-bg { background-color: <?php echo $secondary_color; ?>; }
        
        .amenity-icon {
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .room-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .image-loading {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        /* Custom scrollbar */
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
        
        /* Animation for rating stars */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .rating-star {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body class="bg-gray-50">