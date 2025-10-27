<!-- Cover -->
<div class="cover_photo">
    <?php if (!empty($photos['cover_photo'])): ?>
    <img src="https://deegeecard.com/uploads/cover/<?= htmlspecialchars($photos['cover_photo']) ?>" class="img-fluid" alt="Cover Photo">
    <?php endif; ?>
</div>

<!-- Profile Photo -->
<div class="profile_photo">
    <?php if (!empty($photos['profile_photo'])): ?>
    <img src="https://deegeecard.com/uploads/profile/<?= htmlspecialchars($photos['profile_photo']) ?>" class="img-fluid" alt="Profile Photo">
    <?php endif; ?>
</div>

<!-- Profile Name and Details -->
<div class="personal_info">
    <h1><?= htmlspecialchars($user['name']) ?></h1>
    <?php if ($business_info && !empty($business_info['designation'])): ?>
    <div class="designation">
        <h2><?= htmlspecialchars($business_info['designation']) ?></h2>
    </div>
    <?php endif; ?>
    
    
    <ul class="social_networks">
        <?php foreach (['facebook', 'instagram', 'linkedin', 'youtube', 'telegram'] as $social): ?>
            <?php if (!empty($social_link[$social])): ?>
            <li>
                <a href="<?= htmlspecialchars($social_link[$social]) ?>" target="_blank">
                    <i class="bi bi-<?= $social ?>"></i>
                </a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <!-- Phone -->
        <?php if (!empty($user['phone'])): ?>
        <li>
            <a href="tel:<?= htmlspecialchars($user['phone']) ?>">
                <i class="bi bi-telephone"></i>
            </a>
        </li>
        <?php endif; ?>
        
        <!-- WhatsApp -->
        <?php if (!empty($social_link['whatsapp'])): ?>
        <li>
            <a target="_blank" href="<?= htmlspecialchars($social_link['whatsapp']) ?>">
                <i class="bi bi-whatsapp"></i>
            </a>
        </li>
        <?php endif; ?>
        
        <!-- Email -->
        <?php if (!empty($user['email'])): ?>
        <li>
            <a href="mailto:<?= htmlspecialchars($user['email']) ?>">
                <i class="bi bi-envelope"></i>
            </a>
        </li>
        <?php endif; ?>
        
        <!-- Google Direction -->
        <?php if (!empty($business_info['google_direction'])): ?>
        <li>
            <a href="<?= htmlspecialchars($business_info['google_direction']) ?>" target="_blank">
                <i class="bi bi-geo-alt"></i>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</div>