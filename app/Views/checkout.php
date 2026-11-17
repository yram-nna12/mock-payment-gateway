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
    <h1><?= $payment['payment_method'] === 'gcash' ? 'GCash' : ($payment['payment_method'] === 'card' ? 'Credit / Debit Card' : 'Bank Transfer') ?> (Simulation)</h1>
    <p class="notice"><strong>TEST MODE / SIMULATED PAYMENT.</strong> No real money, wallet credentials, PIN, OTP, or bank details are used.</p>
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
    <?php elseif ($payment['payment_status'] === 'pending' && $payment['payment_method'] === 'gcash' && !($gcashVerification ?? false)): ?>
        <form method="post" action="<?= site_url('checkout/' . rawurlencode($payment['transaction_reference']) . '/' . rawurlencode($token) . '/gcash') ?>">
            <?= csrf_field() ?>
            <p><label>Fictional GCash mobile number <input name="mobile_number" inputmode="numeric" autocomplete="off" placeholder="09990000001" required></label></p>
            <p><small>Use a TEST MODE number from 09990000001 to 09990000002. No real wallet is contacted.</small></p>
            <button type="submit">Simulate verification</button>
        </form>
    <?php elseif ($payment['payment_status'] === 'pending' && $payment['payment_method'] === 'gcash'): ?>
        <form method="post" action="<?= site_url('checkout/' . rawurlencode($payment['transaction_reference']) . '/' . rawurlencode($token) . '/gcash/verify') ?>">
            <?= csrf_field() ?>
            <p><label>Simulated verification code <input name="verification_code" inputmode="numeric" autocomplete="off" placeholder="000000" required></label></p>
            <p><label>Simulated result <select name="simulated_outcome"><option value="paid">Successful payment</option><option value="failed">Failed payment</option></select></label></p>
            <p><small>Use 000000. This is a demo value only and is never stored.</small></p>
            <button type="submit">Confirm simulated GCash payment</button>
        </form>
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
