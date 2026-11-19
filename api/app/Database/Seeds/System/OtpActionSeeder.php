<?php

namespace App\Database\Seeds\System;

use CodeIgniter\Database\Seeder;

class OtpActionSeeder extends Seeder {
    public function run(): void {
        $builder = $this->db->table('sys_otp_action');
        $time = date('Y-m-d H:i:s');
        $inserted = 0;
        $updated = 0;

        $list = array_merge($this->getBaseData(), $this->getCustomData());
        foreach ($list as $row) {
            $existing = $builder->select('id')
                ->where('code', $row['code'])
                ->limit(1)
                ->get()
                ->getRow();

            if ($existing) {
                $row['updated_at'] = $time;
                $builder->where('id', $existing->id)->update($row);
                $updated++;
            } else {
                $row['created_at'] = $time;
                $builder->insert($row);
                $inserted++;
            }
        }

        echo "  Acciones OTP insertadas: {$inserted}, actualizadas: {$updated}" . PHP_EOL;
    }

    private function getBaseData(): array {
        return [
            [
                'code'                => 'password_recovery',
                'expiration_minute'   => 10,
                'max_attempt'         => 3,
                'resend_margin_minute'=> 2,
            ],
            [
                'code'                => 'email_verification',
                'expiration_minute'   => 10,
                'max_attempt'         => 3,
                'resend_margin_minute'=> 3,
            ],
        ];
    }

    private function getCustomData(): array {
        return [];
    }
}
