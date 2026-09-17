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
    ) {
    }

    public function create(): View
    {
        return view('admin.pages.participant-form', [
            'participant' => null,
            'title'       => 'مشارك جديد',
            'formAction'  => 'create',
        ]);
    }

    public function store(StoreParticipantRequest $request): RedirectResponse|Redirector
    {
        $data = $request->validated();

        $participant = Participant::query()->create([
            'first_name'           => $data['first_name'],
            'last_name'            => $data['last_name'],
            'username'             => $data['username'],
            'email'                => $data['email'] ?? null,
            'password'             => $data['password'],
            'status'               => $data['status'],
            'created_by_admin_id'  => $request->user()?->getKey(),
        ]);

        $this->audit->log(
            'participant_created',
            $request->user(),
            $participant::class,
            $participant->getKey(),
            [
                'new' => [
                    'username' => $participant->username,
                    'email'    => $participant->email,
                    'status'   => $participant->status,
                ],
            ],
        );

        return redirect()->route('admin.participants')->with('success', 'تم إنشاء حساب المشارك "' . $participant->username . '" بنجاح.');
    }

    public function edit(Participant $participant): View
    {
        return view('admin.pages.participant-form', [
            'participant' => $participant,
            'title'       => 'تعديل المشارك',
            'formAction'  => 'edit',
        ]);
    }

    public function update(UpdateParticipantRequest $request, Participant $participant): RedirectResponse|Redirector|JsonResponse
    {
        $method = $request->method();
        $effectiveMethod = $request->getRealMethod();

        Log::debug('[ParticipantUpdate] Incoming payload.', [
            'request_method'      => $method,
            'effective_method'    => $effectiveMethod,
            'url'                 => $request->fullUrl(),
            'path'                => $request->path(),
            'route_name'          => $request->route()?->getName(),
            'route_action'        => $request->route()?->getActionName(),
            'participant_id_route'=> $request->route('participant')?->getKey(),
            'participant_model'   => $participant->getKey(),
            'all_input'           => $request->all(),
            'input_excluding_meta'=> $request->except(['_token', '_method']),
            'session_web_admin_id'=> $request->session()->get('web_admin_id'),
            'auth_user'           => $request->user()?->getKey(),
            'ip'                  => $request->ip(),
            'user_agent'          => substr((string) $request->userAgent(), 0, 200),
        ]);

        $data = $request->validated();

        Log::debug('[ParticipantUpdate] Validated data.', [
            'validated'         => $data,
            'count'             => count($data),
            'validation_missing'=> array_diff(
                ['first_name', 'last_name', 'username', 'email', 'status'],
                array_keys($data)
            ),
        ]);

        if (empty($data)) {
            Log::warning('[ParticipantUpdate] Validated payload is empty — no fields reached update().', [
                'request_method'       => $method,
                'effective_method'     => $effectiveMethod,
                'participant_id'       => $participant->getKey(),
                'all_input'            => $request->all(),
                'validated'            => $data,
                'headers_content_type' => $request->headers->get('content-type'),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'لا توجد تغييرات.']);
            }

            return redirect()->route('admin.participants')->with('success', 'لا توجد تغييرات.');
        }

        $oldValues = [
            'first_name' => $participant->first_name,
            'last_name'  => $participant->last_name,
            'username'   => $participant->username,
            'email'      => $participant->email,
            'status'     => $participant->status,
        ];

        $updateResult = $participant->update($data);

        Log::debug('[ParticipantUpdate] DB update result.', [
            'participant_id' => $participant->getKey(),
            'update_returned'=> $updateResult,
            'old'            => $oldValues,
            'new'            => $data,
            'wasChanged'     => $participant->wasChanged(),
            'changes'        => $participant->getChanges(),
        ]);

        if ($updateResult === false) {
            Log::error('[ParticipantUpdate] update() returned FALSE — persisted data NOT changed.', [
                'participant_id' => $participant->getKey(),
                'data_sent'      => $data,
            ]);
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
                    'last_name'  => $participant->last_name,
                    'username'   => $participant->username,
                    'email'      => $participant->email,
                    'status'     => $participant->status,
                ],
            ],
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم تحديث بيانات المشارك "' . $participant->username . '" بنجاح.']);
        }

        return redirect()->route('admin.participants')->with('success', 'تم تحديث بيانات المشارك "' . $participant->username . '" بنجاح.');
    }

    public function password(Participant $participant): View
    {
        return view('admin.pages.participant-password', [
            'participant' => $participant,
            'title'       => 'تغيير كلمة المرور',
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

        return redirect()->route('admin.participants')->with('success', 'تم تغيير كلمة مرور المشارك "' . $participant->username . '" بنجاح.');
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