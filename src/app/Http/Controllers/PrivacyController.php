<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AccountDeletionRequest;
use App\Services\Audit\AuditLogger;
use App\Services\Privacy\StudentDataExporter;
use App\Support\PlainTextPdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivacyController extends Controller
{
    public function show(): View
    {
        $version = (string) config('edupredict.consent.current_version');
        $versions = config('edupredict.consent.versions', []);

        return view('privacy', [
            'currentVersion' => $version,
            'versions' => is_array($versions) ? $versions : [],
        ]);
    }

    public function downloadJson(Request $request, StudentDataExporter $exporter, AuditLogger $audit): StreamedResponse
    {
        $payload = $this->payload($request, $exporter, $audit, 'json');
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return response()->streamDownload(function () use ($json): void {
            echo $json === false ? '{}' : $json;
        }, 'edupredict-my-data.json', ['Content-Type' => 'application/json']);
    }

    public function downloadPdf(Request $request, StudentDataExporter $exporter, AuditLogger $audit): StreamedResponse
    {
        $payload = $this->payload($request, $exporter, $audit, 'pdf');
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $pdf = PlainTextPdf::render(
            'EduPredict personal data copy',
            $json === false ? '{}' : $json,
        );

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf;
        }, 'edupredict-my-data.pdf', ['Content-Type' => 'application/pdf']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, StudentDataExporter $exporter, AuditLogger $audit, string $format): array
    {
        $student = $request->user()?->student;
        abort_if($student === null, 404);

        $this->authorize('create', AccountDeletionRequest::class);

        $payload = $exporter->forStudent($student);
        $audit->record('data_exported', $student, ['format' => $format]);

        return $payload;
    }
}
