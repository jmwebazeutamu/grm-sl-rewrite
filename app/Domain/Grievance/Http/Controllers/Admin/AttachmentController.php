<?php

declare(strict_types=1);

namespace App\Domain\Grievance\Http\Controllers\Admin;

use App\Domain\Grievance\Actions\DownloadAttachment;
use App\Domain\Grievance\Actions\UploadAttachment;
use App\Domain\Grievance\Http\Requests\UploadAttachmentRequest;
use App\Domain\Grievance\Http\Resources\AttachmentResource;
use App\Domain\Grievance\Models\Grievance;
use App\Domain\Grievance\Models\GrievanceAttachment;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AttachmentController extends Controller
{
    public function index(Request $request, Grievance $grievance): JsonResponse
    {
        $this->authorize('view', $grievance);

        $items = $grievance->attachments()
            ->with('uploadedBy:id,name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => AttachmentResource::collection($items)->resolve(),
        ]);
    }

    public function store(UploadAttachmentRequest $request, Grievance $grievance, UploadAttachment $upload): RedirectResponse
    {
        $this->authorize('uploadAttachment', $grievance);

        try {
            $upload(
                $grievance,
                $request->file('files') ?? [],
                $request->input('description'),
                $request->user(),
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Files uploaded.');
    }

    public function download(Grievance $grievance, GrievanceAttachment $attachment, DownloadAttachment $action): Response
    {
        $this->authorize('view', $grievance);

        abort_unless($attachment->grievance_id === $grievance->id, 404);

        try {
            return $action($attachment);
        } catch (FileNotFoundException) {
            abort(404, 'File is no longer available.');
        }
    }

    public function destroy(Grievance $grievance, GrievanceAttachment $attachment): RedirectResponse
    {
        $this->authorize('delete', $grievance);

        abort_unless($attachment->grievance_id === $grievance->id, 404);

        Storage::disk($attachment->disk ?: 'local')->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'Attachment deleted.');
    }
}
