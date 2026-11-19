<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mock payment checkout</title>
    <link rel="stylesheet" href="<?= base_url('css/checkout.css') ?>">
    <link rel="stylesheet" href="<?= base_url('css/gateway.css') ?>">
</head>
<body>
<main>
    <h1><?= $payment['payment_method'] === 'gcash' ? 'GCash' : ($payment['payment_method'] === 'card' ? 'Credit / Debit Card' : 'Bank Transfer') ?><?= $payment['payment_method'] === 'gcash' ? '' : ' (Simulation)' ?></h1>
    <p class="notice"><?= $payment['payment_method'] === 'gcash' ? 'Demo payment — no actual charge. No SMS is sent.' : 'TEST MODE / SIMULATED PAYMENT. No real money, wallet credentials, PIN, OTP, or bank details are used.' ?></p>
    <dl>
        <dt>Reservation reference</dt><dd><?= esc($payment['reservation_reference']) ?></dd>
        <dt>Amount</dt><dd><?= esc($payment['currency']) ?> <?= esc($payment['amount']) ?></dd>
        <dt>Transaction reference</dt><dd><?= esc($payment['transaction_reference']) ?></dd>
        <dt>Status</dt><dd><?= esc(ucfirst($payment['payment_status'])) ?></dd>
    </dl>
    <?php if ($payment['payment_status'] === 'pending' && $payment['payment_method'] === 'card'): ?>
        <form method="post" action="<?= site_url('checkout/' . rawurlencode($payment['transaction_reference']) . '/' . rawurlencode($token) . '/card') ?>">
            <?= csrf_field() ?>
            <p><label>Fictional cardholder name <input name="cardholder_name" maxlength="100" autocomplete="off" required></label></p>
            <p><label>Test card number <input name="card_number" inputmode="numeric" autocomplete="off" placeholder="4242 4242 4242 4242" required></label></p>
            <p><label>Expiration (MM/YY) <input name="card_expiry" inputmode="numeric" autocomplete="off" placeholder="12/99" required></label></p>
            <p><label>Demo CVV <input name="card_cvv" inputmode="numeric" autocomplete="off" placeholder="123" required></label></p>
            <p><label>Simulated result <select name="simulated_outcome"><option value="paid">Successful payment</option><option value="failed">Failed payment</option></select></label></p>
            <p><small>Use test card 4242 4242 4242 4242 and demo CVV 123. The decline test card is 4000 0000 0000 0002. These values are validated but never stored.</small></p>
            <button type="submit">Confirm simulated payment</button>
        </form>
    <?php elseif ($payment['payment_status'] === 'pending' && $payment['payment_method'] === 'gcash' && !isset($gcashStage)): ?>
        <form method="post" action="<?= site_url('checkout/' . rawurlencode($payment['transaction_reference']) . '/' . rawurlencode($token) . '/gcash') ?>">
            <?= csrf_field() ?>
            <p><label>Philippine mobile number <input name="mobile_number" inputmode="tel" autocomplete="tel" placeholder="09XXXXXXXXX" required></label></p>
            <p><small>We validate the number format only; it is not checked against a real GCash account.</small></p>
            <button type="submit">Send demo code</button>
        </form>
    <?php elseif ($payment['payment_status'] === 'pending' && $payment['payment_method'] === 'gcash' && ($gcashStage ?? '') === 'verify'): ?>
        <form method="post" action="<?= site_url('checkout/' . rawurlencode($payment['transaction_reference']) . '/' . rawurlencode($token) . '/gcash/verify') ?>">
            <?= csrf_field() ?>
            <p><strong>Demo verification code: <?= esc($demoOtp ?? 'Request a new code') ?></strong></p>
            <p><label>Verification code <input name="verification_code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required></label></p>
            <p><small>This demo code expires in five minutes. It is not sent by SMS.</small></p>
            <button type="submit">Verify code</button>
        </form>
        <form method="post" action="<?= site_url('checkout/' . rawurlencode($payment['transaction_reference']) . '/' . rawurlencode($token) . '/gcash/resend') ?>"><?= csrf_field() ?><p><button class="secondary" type="submit">Generate a new demo code</button></p></form>
    <?php elseif ($payment['payment_status'] === 'pending' && $payment['payment_method'] === 'gcash' && ($gcashStage ?? '') === 'confirm'): ?>
        <p>Verified mobile number: <?= esc($payment['gcash_mobile_masked'] ?? '') ?></p>
        <p><strong>Review payment: <?= esc($payment['currency']) ?> <?= esc($payment['amount']) ?></strong></p>
        <form method="post" action="<?= site_url('checkout/' . rawurlencode($payment['transaction_reference']) . '/' . rawurlencode($token) . '/gcash/confirm') ?>"><?= csrf_field() ?><button type="submit">Confirm Payment</button></form>
    <?php elseif ($payment['payment_status'] === 'pending'): ?>
        <div class="actions">
            <form method="post" action="<?= site_url('checkout/' . rawurlencode($payment['transaction_reference']) . '/' . rawurlencode($token) . '/success') ?>">
                <?= csrf_field() ?>
                <button type="submit"><?= $payment['payment_method'] === 'gcash' ? 'Simulate Successful Payment' : 'Simulate Successful Transfer' ?></button>
            </form>
            <form method="post" action="<?= site_url('checkout/' . rawurlencode($payment['transaction_reference']) . '/' . rawurlencode($token) . '/failure') ?>">
                <?= csrf_field() ?>
                <button class="secondary" type="submit"><?= $payment['payment_method'] === 'gcash' ? 'Simulate Declined Payment' : 'Simulate Failed Transfer' ?></button>
            </form>
        </div>
    <?php else: ?>
        <p class="result">This transaction is <?= esc($payment['payment_status']) ?>. Terminal results cannot be changed.</p>
        <p><a href="<?= site_url('checkout/' . rawurlencode($payment['transaction_reference']) . '/' . rawurlencode($token) . '/receipt') ?>">View printable receipt</a> · <a href="<?= site_url('checkout/' . rawurlencode($payment['transaction_reference']) . '/' . rawurlencode($token) . '/receipt/download') ?>">Download HTML receipt</a></p>
    <?php endif ?>
    <?php if ($payment['payment_status'] === 'pending'): ?><form method="post" action="<?= site_url('checkout/' . rawurlencode($payment['transaction_reference']) . '/' . rawurlencode($token) . '/cancel') ?>"><?= csrf_field() ?><p><button class="secondary" type="submit">Cancel simulated payment</button></p></form><?php endif ?>
    <?php $returnSeparator = strpos($returnUrl, '?') === false ? '?' : '&'; ?>
    <p><a href="<?= esc($returnUrl . $returnSeparator . 'payment_reference=' . rawurlencode($payment['transaction_reference']), 'attr') ?>">Return to TechReserve and verify payment</a></p>
</main>
</body>
</html>
