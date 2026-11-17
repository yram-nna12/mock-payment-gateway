<?php

namespace App\Commands;

use App\Services\WebhookDeliveryWorker;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

final class DeliverPaymentWebhooks extends BaseCommand
{
    protected $group = 'Payments';
    protected $name = 'payments:deliver-webhooks';
    protected $description = 'Deliver a bounded batch of queued TechReserve payment events.';
    protected $usage = 'payments:deliver-webhooks [limit]';

    public function run(array $params)
    {
        $limit = filter_var($params[0] ?? 25, FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 100) {
            CLI::error('The delivery limit must be an integer between 1 and 100.');
            return EXIT_ERROR;
        }

        try {
            $report = (new WebhookDeliveryWorker())->run($limit);
        } catch (Throwable $exception) {
            log_message('error', 'Payment webhook worker could not run ({exceptionClass}).', [
                'exceptionClass' => $exception::class,
            ]);
            CLI::error('Payment webhook delivery could not run. Check configuration and service logs.');
            return EXIT_ERROR;
        }

        CLI::write(sprintf(
            'Processed %d; delivered %d; retrying %d; permanently failed %d.',
            $report['processed'],
            $report['delivered'],
            $report['retrying'],
            $report['failed']
        ));

        return EXIT_SUCCESS;
    }
}
