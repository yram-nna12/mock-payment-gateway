<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class AddGcashOtpFields extends Migration { public function up(){ $this->forge->addColumn('payment_transactions',['gcash_mobile_masked'=>['type'=>'VARCHAR','constraint'=>20,'null'=>true],'gcash_otp_hash'=>['type'=>'VARCHAR','constraint'=>255,'null'=>true],'gcash_otp_expires_at'=>['type'=>'DATETIME','null'=>true],'gcash_otp_sent_at'=>['type'=>'DATETIME','null'=>true],'gcash_otp_attempts'=>['type'=>'INT','unsigned'=>true,'default'=>0],'gcash_otp_resends'=>['type'=>'INT','unsigned'=>true,'default'=>0],'gcash_otp_verified_at'=>['type'=>'DATETIME','null'=>true]]);} public function down(){throw new \RuntimeException('OTP migration is forward-only to preserve gateway history.');}}
