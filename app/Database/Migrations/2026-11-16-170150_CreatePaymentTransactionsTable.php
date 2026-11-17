<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePaymentTransactionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'transaction_reference' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'unique'     => true,
            ],
            'reservation_reference' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'student_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'student_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'account_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'rented_items' => [
                'type' => 'TEXT',
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'currency' => [
                'type'       => 'VARCHAR',
                'constraint' => 3,
                'default'    => 'PHP',
            ],
            'payment_status' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'pending',
                    'paid',
                    'failed',
                    'expired',
                    'refunded',
                ],
                'default' => 'pending',
            ],
            'payment_method' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'paid_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'expires_at' => [
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
        $this->forge->createTable('payment_transactions');
    }

    public function down()
    {
        $this->forge->dropTable('payment_transactions');
    }
}
