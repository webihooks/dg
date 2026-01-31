<?php 
// Check if discounts exist
if (!empty($discounts)): 
    // Get currency info from global variable or local scope
    $currency_info = $GLOBALS['currency_info'] ?? $currency_info ?? null;
    $currency_symbol = $currency_info['symbol'] ?? '₹'; // Default to ₹ if not set
?>
<div class="modal fade" id="discountsModal" tabindex="-1" aria-labelledby="discountsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg offer_popup">
        <div class="modal-content">
            <button type="button" class="btn-close btn-close-black" data-bs-dismiss="modal" aria-label="Close">X</button>
            <div class="modal-body">
                <section id="discounts_section">
                    <div class="discounts-container">
                        <?php foreach ($discounts as $discount): ?>
                            <div class="discount-card">
                                <?php if (!empty($discount['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($discount['image_path']); ?>"
                                        alt="Discount Image"
                                        class="img-fluid rounded mb-2">
                                <?php endif; ?>
                                
                                <div class="msg">
                                    <span>🎉 Your Feast Just Got Better!</span> <br>
                                    Enjoy
                                    <?php if (!empty($discount['discount_in_percent'])): ?>
                                        <?php echo htmlspecialchars(number_format($discount['discount_in_percent'], 0)); ?>% Discount
                                    <?php endif; ?>

                                    <?php if (!empty($discount['discount_in_flat'])): ?>
                                        <?php echo htmlspecialchars($currency_symbol); ?><?php echo htmlspecialchars(number_format($discount['discount_in_flat'], 0)); ?> OFF
                                    <?php endif; ?>

                                    on orders over <?php echo htmlspecialchars($currency_symbol); ?><?php echo htmlspecialchars(number_format($discount['min_cart_value'], 0, '.', '')); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var discountsModal = new bootstrap.Modal(document.getElementById('discountsModal'));
        discountsModal.show();

        // Optional: Store in localStorage that the modal has been shown
        localStorage.setItem('discountsModalShown', 'true');
    });
</script>
<?php endif; ?>