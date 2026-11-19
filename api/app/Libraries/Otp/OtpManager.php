<?php

namespace App\Libraries\Otp;

use App\Models\System\OtpActionModel;
use App\Models\System\OtpVerificationModel;

class OtpManager {
    private OtpActionModel $actionModel;
    private OtpVerificationModel $verificationModel;

    public function __construct() {
        $this->actionModel = new OtpActionModel();
        $this->verificationModel = new OtpVerificationModel();
    }

    public function request(int $userId, string $actionCode): array {
        $action = $this->loadAction($actionCode);

        $existing = $this->findPending($userId, $action['id']);

        if ($existing !== null) {
            $now = time();
            $expiresAt = strtotime($existing['expires_at']);
            $createdAt = strtotime($existing['created_at']);
            $marginSec = $action['resend_margin_minute'] * 60;

            if ($expiresAt > $now) {
                $elapsed = $now - $createdAt;

                if ($elapsed < $marginSec) {
                    $remaining = $marginSec - $elapsed;
                    throw new OtpPendingException($remaining);
                }

                $code = $this->generateCode();
                $hash = password_hash($code, PASSWORD_BCRYPT);
                $expiresAtDate = date('Y-m-d H:i:s', $now + ($action['expiration_minute'] * 60));

                $this->verificationModel->skipValidation(true)->protect(false)
                    ->set('otp_hash', $hash)
                    ->set('expires_at', $expiresAtDate)
                    ->set('failed_attempt_count', 0)
                    ->where('id', $existing['id'])
                    ->limit(1)
                    ->update();

                return $this->buildResponse($existing['id'], $code, $expiresAtDate, $action);
            }
        }

        $code = $this->generateCode();
        $hash = password_hash($code, PASSWORD_BCRYPT);
        $now = time();
        $expiresAtDate = date('Y-m-d H:i:s', $now + ($action['expiration_minute'] * 60));

        $id = $this->verificationModel->skipValidation(true)->protect(false)->insert([
            'otp_action_id'       => $action['id'],
            'user_id'             => $userId,
            'otp_hash'            => $hash,
            'expires_at'          => $expiresAtDate,
            'status'              => '0',
            'failed_attempt_count'=> 0,
            'created_at'          => date('Y-m-d H:i:s', $now),
        ], true);

        return $this->buildResponse((int) $id, $code, $expiresAtDate, $action);
    }

    public function verify(int $verificationId, string $otpCode, string $actionCode): array {
        $record = $this->verificationModel
            ->where('id', $verificationId)
            ->where('deleted_at', null)
            ->limit(1)
            ->get()
            ->getResultArray()[0] ?? null;

        if (empty($record)) {
            throw new \RuntimeException(lang('Otp.record_not_found'));
        }

        if ($record['status'] !== '0') {
            $message = match ($record['status']) {
                '1' => lang('Otp.already_used'),
                '2' => lang('Otp.attempts_exceeded'),
                default => lang('Otp.already_used'),
            };
            throw new \RuntimeException($message);
        }

        $action = $this->actionModel
            ->select('code, max_attempt')
            ->where('id', $record['otp_action_id'])
            ->where('deleted_at', null)
            ->limit(1)
            ->get()
            ->getResultArray()[0] ?? null;

        if (empty($action) || $action['code'] !== $actionCode) {
            throw new \RuntimeException(lang('Otp.action_mismatch'));
        }

        if (strtotime($record['expires_at']) < time()) {
            $this->setStatus($verificationId, '3');
            throw new \RuntimeException(lang('Otp.expired'));
        }

        if ((int) $record['failed_attempt_count'] >= (int) $action['max_attempt']) {
            $this->setStatus($verificationId, '2');
            throw new \RuntimeException(lang('Otp.attempts_exceeded'));
        }

        if (!password_verify($otpCode, $record['otp_hash'])) {
            $newCount = (int) $record['failed_attempt_count'] + 1;
            $isExhausted = $newCount >= (int) $action['max_attempt'];

            $this->verificationModel->skipValidation(true)->protect(false)
                ->set('failed_attempt_count', $newCount)
                ->set('status', $isExhausted ? '2' : '0')
                ->set('updated_at', date('Y-m-d H:i:s'))
                ->where('id', $verificationId)
                ->limit(1)
                ->update();

            throw new \RuntimeException($isExhausted ? lang('Otp.attempts_exceeded') : lang('Otp.invalid_code'));
        }

        return [
            'verified'        => true,
            'verification_id' => $verificationId,
            'user_id'         => (int) $record['user_id'],
            'action_code'     => $actionCode,
        ];
    }

    public function invalidate(int $verificationId): void {
        $this->setStatus($verificationId, '1');
    }

    public function getRecord(int $verificationId): ?array {
        $record = $this->verificationModel
            ->select('id, user_id, otp_action_id, status')
            ->where('id', $verificationId)
            ->where('deleted_at', null)
            ->limit(1)
            ->get()
            ->getResultArray()[0] ?? null;

        if (empty($record)) {
            return null;
        }

        return [
            'verification_id' => (int) $record['id'],
            'user_id'         => (int) $record['user_id'],
            'otp_action_id'   => (int) $record['otp_action_id'],
            'status'          => $record['status'],
        ];
    }

    private function setStatus(int $verificationId, string $status): void {
        $this->verificationModel->skipValidation(true)->protect(false)
            ->set('status', $status)
            ->set('updated_at', date('Y-m-d H:i:s'))
            ->where('id', $verificationId)
            ->limit(1)
            ->update();
    }

    private function loadAction(string $code): array {
        $action = $this->actionModel
            ->where('code', $code)
            ->where('is_disabled', '0')
            ->where('deleted_at', null)
            ->limit(1)
            ->get()
            ->getResultArray()[0] ?? null;

        if (empty($action)) {
            throw new \RuntimeException(lang('Otp.action_not_found'));
        }

        return $action;
    }

    private function findPending(int $userId, int $actionId): ?array {
        $builder = $this->verificationModel
            ->where('user_id', $userId)
            ->where('otp_action_id', $actionId)
            ->where('status', '0')
            ->where('deleted_at', null);

        return $builder->limit(1)->orderBy('id', 'DESC')->get()->getResultArray()[0] ?? null;
    }

    private function generateCode(): string {
        return (string) random_int(100000, 999999);
    }

    private function buildResponse(int $verificationId, string $code, string $expiresAt, array $action): array {
        $now = time();
        $marginSec = $action['resend_margin_minute'] * 60;

        return [
            'verification_id' => $verificationId,
            'plain_code'      => $code,
            'expires_at'      => $expiresAt,
            'refresh_at'      => date('Y-m-d H:i:s', $now + $marginSec),
        ];
    }
}
