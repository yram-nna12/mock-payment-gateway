<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mock payment checkout</title>
    <link rel="stylesheet" href="<?= base_url('css/checkout.css') ?>">
</head>
<body>
<main>
    <h1><?= $payment['payment_method'] === 'gcash' ? 'GCash' : 'Bank Transfer' ?> (Simulation)</h1>
    <p class="notice">Simulation only. No real money, wallet credentials, PIN, OTP, or bank details are used.</p>
    <dl>
        <dt>Reservation reference</dt><dd><?= esc($payment['reservation_reference']) ?></dd>
        <dt>Amount</dt><dd><?= esc($payment['currency']) ?> <?= esc($payment['amount']) ?></dd>
        <dt>Transaction reference</dt><dd><?= esc($payment['transaction_reference']) ?></dd>
        <dt>Status</dt><dd><?= esc(ucfirst($payment['payment_status'])) ?></dd>
    </dl>
    <?php if ($payment['payment_status'] === 'pending'): ?>
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
    <?php endif ?>
    <?php $returnSeparator = strpos($returnUrl, '?') === false ? '?' : '&'; ?>
    <p><a href="<?= esc($returnUrl . $returnSeparator . 'payment_reference=' . rawurlencode($payment['transaction_reference']), 'attr') ?>">Return to TechReserve and verify payment</a></p>
</main>
</body>
</html>
