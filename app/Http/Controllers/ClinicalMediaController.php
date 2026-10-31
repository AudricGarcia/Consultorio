<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ClinicalMedia;
use App\Models\ClinicalRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ClinicalMediaController extends Controller
{
    public function store(Request $request, ClinicalRecord $clinicalRecord): RedirectResponse
    {
        $this->authorize('clinical.create');
        $this->authorize('view', $clinicalRecord->patient);
        abort_if($request->user()->hasRole('dentist') && (int) $clinicalRecord->professional_id !== (int) $request->user()->id, 403);
        $data = $request->validate(['file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:12288']]);
        $file = $data['file'];
        $mime = $file->getMimeType();
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            default => abort(422, 'Tipo de archivo no permitido.'),
        };
        $path = $file->storeAs('clinical-media/'.$clinicalRecord->patient_id, Str::uuid().'.'.$extension, 'local');

        try {
            $media = DB::transaction(function () use ($request, $clinicalRecord, $file, $mime, $path): ClinicalMedia {
                $media = ClinicalMedia::create([
                    'patient_id' => $clinicalRecord->patient_id,
                    'clinical_record_id' => $clinicalRecord->id,
                    'uploaded_by' => $request->user()->id,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $mime,
                    'size' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                ]);
                AuditLog::create(['user_id' => $request->user()->id, 'event' => 'clinical_media.uploaded', 'subject_type' => ClinicalMedia::class, 'subject_id' => $media->id, 'after' => ['clinical_record_id' => $clinicalRecord->id, 'mime_type' => $mime, 'size' => $media->size, 'sha256' => $media->sha256], 'ip_address' => $request->ip()]);

                return $media;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Archivo clínico almacenado de forma privada.');
    }

    public function show(Request $request, ClinicalMedia $clinicalMedia): StreamedResponse
    {
        $this->authorize('clinical.view');
        $this->authorize('view', $clinicalMedia->patient);
        abort_if($request->user()->hasRole('dentist') && (int) $clinicalMedia->clinicalRecord->professional_id !== (int) $request->user()->id, 403);
        abort_unless(Storage::disk($clinicalMedia->disk)->exists($clinicalMedia->path), 404);
        AuditLog::create(['user_id' => $request->user()->id, 'event' => 'clinical_media.downloaded', 'subject_type' => ClinicalMedia::class, 'subject_id' => $clinicalMedia->id, 'ip_address' => $request->ip()]);

        return Storage::disk($clinicalMedia->disk)->download($clinicalMedia->path, 'clinical-attachment.'.pathinfo($clinicalMedia->path, PATHINFO_EXTENSION));
    }
}
