<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Participant;

use App\Actions\Participant\GetParticipantReportsAction;
use App\Exports\ParticipantReportExport;
use App\Http\Requests\ReportDownloadRequest;
use App\Models\Participant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

final class ReportController
{
    public function index(Request $request, GetParticipantReportsAction $action): JsonResponse
    {
        $participant = $this->participant($request);
        $reports = [];

        foreach (GetParticipantReportsAction::TYPES as $type => $label) {
            $reports[] = [
                'type' => $type,
                'label' => $label,
                'available_years' => $type === 'settlement_statement'
                    ? $action->settleStatementYears($participant)
                    : $action->summaryYears($participant),
            ];
        }

        return response()->json(['success' => true, 'data' => ['reports' => $reports]]);
    }

    public function download(ReportDownloadRequest $request, string $type, GetParticipantReportsAction $action): Response
    {
        $participant = $this->participant($request);
        abort_unless(array_key_exists($type, GetParticipantReportsAction::TYPES), 404, 'Unknown report type.');

        $format = $request->reportFormat();
        $year = $request->reportYear() ?? $this->latestYear($request->user() ?? $participant, $type, $action);
        $rows = $type === 'settlement_statement'
            ? $action->settleStatementRows($participant, $year)
            : $action->summaryRows($participant, $year);

        $filenamePrefix = $type === 'settlement_statement' ? 'settlement-statement' : 'annual-investment-summary';
        $filename = sprintf('%s-%s-%s', $filenamePrefix, $participant->username, (string) ($year ?? 'all'));

        if ($format === 'xlsx') {
            $headings = $type === 'settlement_statement'
                ? ['السنة', 'النسخة', 'الحالة', 'الربح السنوي', 'حصة الصندوق', 'المستحق', 'المدفوع', 'المتبقي', 'حالة الدفع']
                : ['التاريخ', 'الفئة', 'البيان', 'المبلغ', 'الحالة'];

            return Excel::download(new ParticipantReportExport($rows, $headings), $filename . '.xlsx');
        }

        if ($type === 'settlement_statement') {
            $response = Pdf::loadView('reports.participant.settlement-statement-pdf', [
                'participantName' => trim($participant->first_name . ' ' . $participant->last_name),
                'participantUsername' => $participant->username,
                'year' => $year,
                'generatedAt' => now()->format('Y-m-d H:i'),
                'rows' => $rows,
            ])->setPaper('a4', 'landscape')->download($filename . '.pdf');

            return $this->withAttachmentDisposition($response, $filename . '.pdf');
        }

        $response = Pdf::loadView('reports.participant.summary-pdf', [
            'title' => 'الملخص الاستثماري السنوي',
            'participantName' => trim($participant->first_name . ' ' . $participant->last_name),
            'participantUsername' => $participant->username,
            'year' => $year,
            'generatedAt' => now()->format('Y-m-d H:i'),
            'headings' => ['التاريخ', 'الفئة', 'البيان', 'المبلغ', 'الحالة'],
            'rows' => $rows,
        ])->setPaper('a4', 'landscape')->download($filename . '.pdf');

        return $this->withAttachmentDisposition($response, $filename . '.pdf');
    }

    private function withAttachmentDisposition(Response $response, string $filename): Response
    {
        $response->headers->set('Content-Disposition', 'attachment; filename="' . str_replace('"', '', $filename) . '"');

        return $response;
    }

    private function latestYear(Participant $participant, string $type, GetParticipantReportsAction $action): ?int
    {
        $years = $type === 'settlement_statement'
            ? $action->settleStatementYears($participant)
            : $action->summaryYears($participant);

        return $years[0] ?? null;
    }

    private function participant(Request $request): Participant
    {
        abort_unless($request->user() instanceof Participant, 403, 'Participant access required.');

        return $request->user();
    }
}