<?php

namespace App\Http\Controllers\API\V1;

use App\Mail\OtpMail;
use App\Models\Client;
use App\Models\UserOtp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class OtpController extends BaseController
{
    private const OTP_TTL_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    public function sendOtp(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id'  => 'required|string|max:255',
                'otp_type' => 'required|in:mobile_no,email',
            ]);

            $client = $this->resolveClient($validated['user_id']);

            if (!$client) {
                return $this->sendError('This contact is not registered in our system.', null, 400);
            }

            $otpType = $validated['otp_type'];

            $destination = $this->pickDestination($client, $validated['user_id'], $otpType);

            if (empty($destination)) {
                return $this->sendError(
                    $otpType === 'mobile_no'
                        ? 'No mobile number registered for this user.'
                        : 'No email address registered for this user.',
                    null,
                    400
                );
            }

            $otp = (string) random_int(100000, 999999);

            // Invalidate any previous unused OTPs for this user/type
            UserOtp::where('user_id', $client->id)
                ->where('otp_type', $otpType)
                ->whereNull('verified_at')
                ->update(['verified_at' => now()]);

            $record = UserOtp::create([
                'user_id'    => $client->id,
                'otp_type'   => $otpType,
                'otp'        => $otp,
                'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            ]);

            $sent = $otpType === 'mobile_no'
                ? $this->sendWhatsAppOtp($client, $destination, $otp)
                : $this->sendEmailOtp($destination, $otp);

            if (!$sent) {
                $record->update(['verified_at' => now()]);
                return $this->sendError('Failed to send OTP. Please try again.', null, 500);
            }

            return $this->sendResponse(
                ['otp_type' => $otpType],
                $otpType === 'mobile_no'
                    ? 'OTP sent to your registered mobile number.'
                    : 'OTP sent to your registered email address.'
            );

        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 400);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Failed to send OTP.');
        }
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id'  => 'required|string|max:255',
                'otp_type' => 'required|in:mobile_no,email',
                'otp'      => 'required|string|size:6',
            ]);

            $client = $this->resolveClient($validated['user_id']);

            if (!$client) {
                return $this->sendError('This contact is not registered in our system.', null, 400);
            }

            $record = UserOtp::where('user_id', $client->id)
                ->where('otp_type', $validated['otp_type'])
                ->whereNull('verified_at')
                ->latest('id')
                ->first();

            if (!$record) {
                return $this->sendError('No OTP found. Please request a new OTP.', null, 400);
            }

            if ($record->expires_at->isPast()) {
                return $this->sendError('OTP has expired. Please request a new OTP.', null, 400);
            }

            if ($record->attempts >= self::MAX_ATTEMPTS) {
                return $this->sendError('Too many attempts. Please request a new OTP.', null, 400);
            }

            $record->increment('attempts');

            if ($record->otp !== $validated['otp']) {
                return $this->sendError('Invalid OTP.', null, 400);
            }

            $record->update(['verified_at' => now()]);

            return $this->sendResponse(
                [
                    'otp_type'    => $validated['otp_type'],
                    'id'          => $client->id,
                    'server_flag' => $client->server_flag,
                    'username'    => $client->username,
                    'password'    => $client->password,
                    'phone'       => $client->phone,
                    'email'       => $client->email,
                ],
                'OTP verified successfully.'
            );

        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 400);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Failed to verify OTP.');
        }
    }

    private function sendWhatsAppOtp(Client $client, string $phone, string $otp): bool
    {
        $authToken    = config('services.elevenza.auth_token');
        $templateName = config('services.elevenza.otp_template');

        if (empty($authToken) || empty($templateName)) {
            Log::error('WhatsApp OTP not sent: 11za config missing.');
            return false;
        }

        try {
            $response = Http::withOptions(['verify' => false, 'timeout' => 8])
                ->post(config('services.elevenza.url'), [
                    'authToken'     => $authToken,
                    'name'          => $client->name ?? $client->username ?? 'User',
                    'sendto'        => $this->normalizePhone($phone),
                    'originWebsite' => 'https://qbitsenergy.com/',
                    'templateName'  => $templateName,
                    'language'      => 'en',
                    'data'          => [
                        $client->name ?? $client->username ?? 'User',
                        $otp,
                    ],
                ]);

            if (!$response->ok()) {
                Log::warning('WhatsApp OTP failed', [
                    'user_id' => $client->id,
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                ]);
                return false;
            }

            $data = $response->json();

            if (empty($data['IsSuccess']) || empty($data['Data']['messageId'])) {
                Log::warning('WhatsApp OTP failed', [
                    'user_id'   => $client->id,
                    'IsSuccess' => $data['IsSuccess'] ?? null,
                    'message'   => $data['Message'] ?? null,
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error("WhatsApp OTP User {$client->id}: {$e->getMessage()}");
            return false;
        }
    }

    private function sendEmailOtp(string $email, string $otp): bool
    {
        try {
            Mail::to($email)->send(new OtpMail($otp));
            return true;
        } catch (\Throwable $e) {
            Log::error("Email OTP to {$email}: {$e->getMessage()}");
            return false;
        }
    }

    private function resolveClient(string $contact): ?Client
    {
        $contact = trim($contact);

        $client = Client::where('username', $contact)
            // ->orWhere('email', $contact)
            // ->orWhere('name', $contact)
            ->first();

        if ($client) {
            return $client;
        }

        $phone = preg_replace('/\D/', '', $contact);

        if ($phone === '') {
            return null;
        }

        return Client::where('phone', $phone)
            ->orWhere('phone', '91' . ltrim($phone, '0'))
            ->orWhere('phone', 'like', '%' . $phone)
            ->orWhere('username', $phone)
            ->orWhere('username', '91' . ltrim($phone, '0'))
            ->orWhere('username', 'like', '%' . $phone)
            ->first();
    }

    private function pickDestination(Client $client, string $contact, string $otpType): ?string
    {
        $contact = trim($contact);

        if ($otpType === 'email') {
            foreach ([$contact, $client->email, $client->username] as $candidate) {
                if ($candidate && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                    return $candidate;
                }
            }
            return null;
        }

        $contactPhone = filter_var($contact, FILTER_VALIDATE_EMAIL)
            ? ''
            : preg_replace('/\D/', '', $contact);

        foreach ([$contactPhone, $client->phone, $client->username] as $candidate) {
            if ($candidate && preg_replace('/\D/', '', $candidate) !== '') {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        return strlen($phone) === 10 ? '91' . $phone : $phone;
    }
}
