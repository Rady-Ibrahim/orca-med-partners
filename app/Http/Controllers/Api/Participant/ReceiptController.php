<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Participant;

use App\Models\Participant;
use App\Models\Settlement;
use App\Models\SettlementPayment;
use App\Services\SecurityAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class ReceiptController
{
    public function __construct(private readonly SecurityAuditService $securityAudit)
    {
    }

    public function show(Request $request, Settlement $settlement, SettlementPayment $payment): Response
    {
        $participant = $this->participant($request);

        $ownsSettlementItem = $settlement->items()->where('participant_id', $participant->id)->exists();
        $paymentBelongsToSettlement = (int) $payment->settlement_id === (int) $settlement->id;

        if (! $ownsSettlementItem || ! $paymentBelongsToSettlement) {
            $this->deny($request, $participant, $settlement, $payment);
        }

        $receipt = $payment->receipt;
        abort_unless($receipt !== null, 404, 'Receipt not found.');

        $disk = Storage::disk('local');
        $path = (string) $receipt->file_path;
        abort_unless($disk->exists($path), 404, 'Receipt file missing.');

        $stream = $disk->readStream($path);
        abort_unless(is_resource($stream), 500, 'Unable to read receipt file.');

        $this->securityAudit->log('document_downloaded', $participant, 'settlement_payment', $payment->getKey(), [
            'endpoint' => $request->path(),
            'receipt' => $payment->id,
        ]);

        $filename = $receipt->original_filename ?: 'receipt-' . $payment->id . '.' . $this->guessExtension($receipt->mime);
        $mime = $receipt->mime ?: 'application/octet-stream';

        return response()->stream(function () use ($stream): void {
            while (! feof($stream)) {
                $chunk = fread($stream, 8192);
                if ($chunk === false) {
                    break;
                }
                echo $chunk;
            }
            fclose($stream);
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function participant(Request $request): Participant
    {
        abort_unless($request->user() instanceof Participant, 403, 'Participant access required.');

        return $request->user();
    }

    private function deny(Request $request, Participant $participant, Settlement $settlement, SettlementPayment $payment): never
    {
        $this->securityAudit->log('authorization_denied', $participant, 'settlement_payment', $payment->getKey(), [
            'action' => 'view_receipt',
            'endpoint' => $request->path(),
            'settlement_id' => $settlement->getKey(),
            'reason' => 'ownership_mismatch',
        ]);

        abort(403, 'Forbidden.');
    }

    private function guessExtension(?string $mime): string
    {
        return match ($mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            default => 'bin',
        };
    }
}