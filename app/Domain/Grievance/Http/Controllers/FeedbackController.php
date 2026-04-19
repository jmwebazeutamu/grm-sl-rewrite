<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Controllers;

use App\Domain\Grievance\Actions\RecordFeedback;
use App\Domain\Grievance\Enums\FeedbackRating;
use App\Domain\Grievance\Models\GrievanceFeedbackToken;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    /**
     * Tokenised feedback form. No auth — the token IS the credential.
     * Complainant reaches this via the signed link in the SMS/email sent
     * when their case resolved.
     */
    public function show(string $token): Response
    {
        $record = $this->resolve($token);

        return Inertia::render('Grievance/Feedback/Form', [
            'token' => $token,
            'g_number' => $record->grievance->g_number,
            'summary' => $record->grievance->summary,
            'ratings' => collect(FeedbackRating::cases())->map(fn ($r) => [
                'value' => $r->value,
                'label' => $r->label(),
            ]),
        ]);
    }

    public function store(Request $request, string $token, RecordFeedback $record): RedirectResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $tokenRecord = $this->resolve($token);

        try {
            $record(
                $tokenRecord,
                [
                    'rating' => FeedbackRating::from($data['rating']),
                    'comment' => $data['comment'] ?? null,
                    'channel' => 'web',
                ],
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('grievances.feedback.thanks')
            ->with('success', 'Thank you for your feedback.');
    }

    public function thanks(): Response
    {
        return Inertia::render('Grievance/Feedback/Thanks');
    }

    private function resolve(string $token): GrievanceFeedbackToken
    {
        $record = GrievanceFeedbackToken::with('grievance')
            ->where('token', $token)
            ->firstOrFail();

        abort_if(! $record->isUsable(), 410, 'This feedback link has expired or been used.');

        return $record;
    }
}
