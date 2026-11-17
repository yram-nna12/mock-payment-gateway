<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWebhookDeliveriesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'payment_transaction_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'callback_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
            ],
            'event_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'payload' => [
                'type' => 'LONGTEXT',
            ],
            'delivery_status' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'pending',
                    'delivered',
                    'failed',
                ],
                'default' => 'pending',
            ],
            'attempts' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 0,
            ],
            'max_attempts' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 3,
            ],
            'response_code' => [
                'type' => 'INT',
                'null' => true,
            ],
            'last_error' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'next_retry_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'delivered_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('payment_transaction_id');

        $this->forge->addForeignKey(
            'payment_transaction_id',
            'payment_transactions',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->forge->createTable('webhook_deliveries');
    }

    public function down()
    {
        $this->forge->dropTable('webhook_deliveries');
    }
}
