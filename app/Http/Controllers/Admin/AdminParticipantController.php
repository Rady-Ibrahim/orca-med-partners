<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ChangeParticipantPasswordRequest;
use App\Http\Requests\Admin\StoreParticipantRequest;
use App\Http\Requests\Admin\UpdateParticipantRequest;
use App\Models\Participant;
use App\Services\SecurityAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

final class AdminParticipantController
{
    public function __construct(
        private readonly SecurityAuditService $audit,
    ) {}

    public function create(): View
    {
        return view('admin.pages.participant-form', [
            'participant' => null,
            'title' => 'مشارك جديد',
            'formAction' => 'create',
        ]);
    }

    public function store(StoreParticipantRequest $request): RedirectResponse|Redirector
    {
        $data = $request->validated();

        $participant = Participant::query()->create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
            'status' => $data['status'],
            'created_by_admin_id' => $request->user()?->getKey(),
        ]);

        $this->audit->log(
            'participant_created',
            $request->user(),
            $participant::class,
            $participant->getKey(),
            [
                'new' => [
                    'username' => $participant->username,
                    'email' => $participant->email,
                    'status' => $participant->status,
                ],
            ],
        );

        return redirect()->route('admin.participants')->with('success', 'تم إنشاء حساب المشارك "'.$participant->username.'" بنجاح.');
    }

    public function edit(Participant $participant): View
    {
        return view('admin.pages.participant-form', [
            'participant' => $participant,
            'title' => 'تعديل المشارك',
            'formAction' => 'edit',
        ]);
    }

    public function update(UpdateParticipantRequest $request, Participant $participant): RedirectResponse|Redirector|JsonResponse
    {
        $data = $request->validated();

        $password = $data['password'] ?? null;
        unset($data['password'], $data['password_confirmation']);

        if (empty($data) && ! $password) {
            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'لا توجد تغييرات.']);
            }

            return redirect()->route('admin.participants')->with('success', 'لا توجد تغييرات.');
        }

        $oldValues = [
            'first_name' => $participant->first_name,
            'last_name' => $participant->last_name,
            'username' => $participant->username,
            'email' => $participant->email,
            'status' => $participant->status,
        ];

        if (! empty($data)) {
            $participant->update($data);
        }

        $stored = $participant->refresh()->only(['first_name', 'last_name', 'username', 'email', 'status']);
        $storedSent = array_intersect_key($stored, $data);
        $expected = array_intersect_key($data, $stored);

        if ($storedSent != $expected) {
            Log::error('[ParticipantUpdate] Persistence check failed — record not saved as sent.', [
                'participant_id' => $participant->getKey(),
                'expected' => $expected,
                'stored' => $stored,
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'تعذّر حفظ التعديلات. لم يتم تحديث البيانات فعلياً، راجع السجلات.',
                ], 500);
            }

            return redirect()
                ->route('admin.participants')
                ->with('error', 'تعذّر حفظ التعديلات. لم يتم تحديث البيانات فعلياً، راجع السجلات.');
        }

        if (! empty($password)) {
            $participant->update(['password' => $password]);
            $participant->tokens()->delete();

            $this->audit->log(
                'participant_password_changed',
                $request->user(),
                $participant::class,
                $participant->getKey(),
                ['new' => ['password' => '[redacted]']],
            );
        }

        $this->audit->log(
            'participant_updated',
            $request->user(),
            $participant::class,
            $participant->getKey(),
            [
                'old' => $oldValues,
                'new' => [
                    'first_name' => $participant->first_name,
                    'last_name' => $participant->last_name,
                    'username' => $participant->username,
                    'email' => $participant->email,
                    'status' => $participant->status,
                ],
            ],
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم تحديث بيانات المشارك "'.$participant->username.'" بنجاح.']);
        }

        return redirect()->route('admin.participants')->with('success', 'تم تحديث بيانات المشارك "'.$participant->username.'" بنجاح.');
    }

    public function password(Participant $participant): View
    {
        return view('admin.pages.participant-password', [
            'participant' => $participant,
            'title' => 'تغيير كلمة المرور',
        ]);
    }

    public function passwordUpdate(ChangeParticipantPasswordRequest $request, Participant $participant): RedirectResponse|Redirector
    {
        $participant->update([
            'password' => $request->validated()['password'],
        ]);

        $participant->tokens()->delete();

        $this->audit->log(
            'participant_password_changed',
            $request->user(),
            $participant::class,
            $participant->getKey(),
            ['new' => ['password' => '[redacted]']],
        );

        return redirect()->route('admin.participants')->with('success', 'تم تغيير كلمة مرور المشارك "'.$participant->username.'" بنجاح.');
    }

    public function revokeTokens(Request $request, Participant $participant): JsonResponse
    {
        abort_if($request->user()->cannot('update', $participant), 403, 'Forbidden.');

        $count = $participant->tokens()->delete();

        $this->audit->log(
            'participant_tokens_revoked',
            $request->user(),
            $participant::class,
            $participant->getKey(),
            ['new' => ['revoked_tokens_count' => $count]],
        );

        return response()->json([
            'success' => true,
            'message' => "تم إلغاء {$count} رمز وصول للمشارك \"{$participant->username}\".",
        ]);
    }
}
