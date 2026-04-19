<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

/**
 * Google reCAPTCHA v3 server-side verification.
 *
 * Skipped automatically when `services.recaptcha.secret` is unset — keeps
 * local/test environments usable without a real key.
 */
class Recaptcha implements ValidationRule
{
    public function __construct(private readonly float $minScore = 0.5)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('services.recaptcha.secret');

        if (! is_string($secret) || $secret === '') {
            return;
        }

        if (! is_string($value) || $value === '') {
            $fail('reCAPTCHA verification failed.');

            return;
        }

        $response = Http::asForm()
            ->timeout(5)
            ->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);

        if (! $response->successful()) {
            $fail('reCAPTCHA service is unavailable.');

            return;
        }

        $data = $response->json();

        if (! ($data['success'] ?? false)) {
            $fail('reCAPTCHA verification failed.');

            return;
        }

        if (isset($data['score']) && $data['score'] < $this->minScore) {
            $fail('reCAPTCHA score is too low.');
        }
    }
}
