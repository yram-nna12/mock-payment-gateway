<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddWebhookDeliveryLeaseFields extends Migration
{
    public function up()
    {
        $this->forge->addColumn('webhook_deliveries', [
            'event_id' => [
                'type'       => 'CHAR',
                'constraint' => 36,
                'null'       => true,
                'after'      => 'payment_transaction_id',
            ],
            'locked_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'next_retry_at',
            ],
            'lease_token' => [
                'type'       => 'CHAR',
                'constraint' => 36,
                'null'       => true,
                'after'      => 'locked_at',
            ],
        ]);
        $this->forge->addUniqueKey('event_id', 'webhook_delivery_event_id');
        $this->forge->addKey(
            ['delivery_status', 'next_retry_at', 'locked_at'],
            false,
            false,
            'webhook_delivery_due'
        );
        $this->forge->processIndexes('webhook_deliveries');
    }

    public function down()
    {
        $this->forge->dropKey('webhook_deliveries', 'webhook_delivery_event_id');
        $this->forge->dropKey('webhook_deliveries', 'webhook_delivery_due');
        $this->forge->dropColumn('webhook_deliveries', ['event_id', 'locked_at', 'lease_token']);
    }
}
