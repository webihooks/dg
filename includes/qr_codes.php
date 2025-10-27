<?php if (!empty($qr_codes)): ?>
<div class="qr_code_details">
    <h6>QR Code Payment Methods</h6>
    <div class="row">
        <?php foreach ($qr_codes as $qr): ?>
        <div class="col-sm-12 mb-2">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-sm-12 text-center">
                            <img src="https://deegeecard.com/uploads/qrcodes/<?= htmlspecialchars($qr['upload_qr_code']) ?>" 
                                 class="img-fluid qr-code-img" 
                                 alt="Payment QR Code"
                                 style="max-width: 150px;">
                        </div>
                        <div class="col-sm-12">
                            <h5><?= htmlspecialchars($qr['payment_type']) ?></h5>
                            <p class="mb-1">
                                <i class="bi bi-upc-scan"></i> UPI ID: <?= htmlspecialchars($qr['upi_id']) ?>
                            </p>
                            <?php if (!empty($qr['mobile_number'])): ?>
                            <p class="mb-1">
                                <i class="bi bi-phone"></i> <?= htmlspecialchars($qr['mobile_number']) ?>
                            </p>
                            <?php endif; ?>
                            <?php if ($qr['is_default']): ?>
                            <span class="badge bg-success">Default Payment Method</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-sm-12 text-md-end mt-3 mt-md-0">
                            <button class="btn btn-outline-primary btn-sm" 
                                onclick="showQrModal('<?= htmlspecialchars($qr['payment_type']) ?>', 'https://deegeecard.com/uploads/qrcodes/<?= htmlspecialchars($qr['upload_qr_code']) ?>', '<?= htmlspecialchars($qr['upi_id']) ?>')">
                                <i class="bi bi-zoom-in"></i> Enlarge
                            </button>
                            <a href="upi://pay?pa=<?= urlencode($qr['upi_id']) ?>" 
                               class="btn btn-primary btn-sm">
                                <i class="bi bi-arrow-up-right-circle"></i> Pay Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qrModalTitle">QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalQrImage" src="" class="img-fluid" alt="QR Code">
                <div class="mt-3">
                    <p class="mb-2"><strong>UPI ID:</strong> <span id="modalUpiId"></span></p>
                    <a href="#" id="payNowLink" class="btn btn-primary">
                        <i class="bi bi-arrow-up-right-circle"></i> Pay Now
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showQrModal(paymentType, qrImageSrc, upiId) {
    document.getElementById('qrModalTitle').textContent = paymentType + ' QR Code';
    document.getElementById('modalQrImage').src = qrImageSrc;
    document.getElementById('modalUpiId').textContent = upiId;
    document.getElementById('payNowLink').href = 'upi://pay?pa=' + encodeURIComponent(upiId);
    var qrModal = new bootstrap.Modal(document.getElementById('qrModal'));
    qrModal.show();
}
</script>
<?php endif; ?>