<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddGatewayTransactionFields extends Migration
{
    public function up()
    {
        $this->forge->addColumn('payment_transactions', [
            'gateway_transaction_id' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'null' => true,
                'after' => 'transaction_reference',
            ],
            'request_hash' => [
                'type' => 'CHAR',
                'constraint' => 64,
                'null' => true,
                'after' => 'gateway_transaction_id',
            ],
        ]);
        $this->forge->addUniqueKey('gateway_transaction_id', 'gateway_transaction_id');
        $this->forge->processIndexes('payment_transactions');
    }

    public function down()
    {
        $this->forge->dropKey('payment_transactions', 'gateway_transaction_id');
        $this->forge->dropColumn('payment_transactions', ['gateway_transaction_id', 'request_hash']);
    }
}
