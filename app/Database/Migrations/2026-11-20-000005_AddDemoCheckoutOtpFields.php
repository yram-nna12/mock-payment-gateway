<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDemoCheckoutOtpFields extends Migration
{
    public function up()
    {
        $this->forge->addColumn('payment_transactions', [
            'payment_identifier_masked' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'demo_otp_hash' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'demo_otp_expires_at' => ['type' => 'DATETIME', 'null' => true],
            'demo_otp_attempts' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'demo_otp_verified_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
    }

    public function down()
    {
        throw new \RuntimeException('Demo checkout migration is forward-only to preserve gateway history.');
    }
}
