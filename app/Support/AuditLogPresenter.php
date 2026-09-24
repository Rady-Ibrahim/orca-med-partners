<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Admin;
use App\Models\Participant;

final class AuditLogPresenter
{
    private const ACTIONS = [
        'admin_login_success' => 'تسجيل دخول الإداري',
        'admin_login_failure' => 'فشل تسجيل دخول الإداري',
        'admin_refresh_success' => 'تجديد جلسة الإداري',
        'admin_refresh_failure' => 'فشل تجديد جلسة الإداري',
        'admin_logout' => 'تسجيل خروج الإداري',
        'admin_password_change' => 'تغيير كلمة مرور الإداري',
        'admin_password_change_denied' => 'رفض تغيير كلمة المرور',
        'admin_password_reset_request' => 'طلب إعادة تعيين كلمة المرور',
        'admin_password_reset_success' => 'إعادة تعيين كلمة المرور',
        'participant_login_success' => 'تسجيل دخول المشارك',
        'participant_login_failure' => 'فشل تسجيل دخول المشارك',
        'participant_refresh_success' => 'تجديد جلسة المشارك',
        'participant_refresh_failure' => 'فشل تجديد جلسة المشارك',
        'participant_logout' => 'تسجيل خروج المشارك',
        'participant_password_change' => 'تغيير كلمة مرور المشارك',
        'participant_password_change_denied' => 'رفض تغيير كلمة المرور',
        'participant_password_reset_request' => 'طلب إعادة تعيين كلمة المرور',
        'participant_password_reset_success' => 'إعادة تعيين كلمة المرور',
        'inactive_account_attempt' => 'محاولة دخول بحساب غير نشط',
        'authorization_denied' => 'رفض صلاحية الوصول',
        'monthly_profit_created' => 'إنشاء أرباح شهرية',
        'monthly_profit_revision_created' => 'إنشاء نسخة مراجعة للأرباح',
        'monthly_profit_approved' => 'اعتماد الأرباح الشهرية',
        'capital_snapshot_created' => 'إنشاء لقطة رأس المال',
        'capital_snapshot_updated' => 'تحديث لقطة رأس المال',
        'depreciation_created' => 'إنشاء مخصص إهلاك',
        'depreciation_updated' => 'تحديث مخصص الإهلاك',
        'distribution_rule_created' => 'إنشاء قاعدة توزيع',
        'distribution_rule_updated' => 'تحديث قاعدة التوزيع',
        'investment_created' => 'إنشاء استثمار',
        'investment_updated' => 'تحديث استثمار',
        'investment_approved' => 'اعتماد استثمار',
        'fund_created' => 'إنشاء صندوق',
        'fund_updated' => 'تحديث صندوق',
        'fund_deposit' => 'إيداع في صندوق',
        'fund_withdrawal' => 'سحب من صندوق',
        'fund_adjustment' => 'تسوية رصيد صندوق',
        'fund_transaction_created' => 'إنشاء حركة صندوق',
        'fund_balance_changed' => 'تغير رصيد الصندوق',
        'fund_balance_recalculated' => 'إعادة حساب رصيد الصندوق',
        'settlement_created' => 'إنشاء تسوية سنوية',
        'settlement_revised' => 'مراجعة تسوية سنوية',
        'settlement_approved' => 'اعتماد تسوية سنوية',
        'settlement_cancelled' => 'إلغاء تسوية سنوية',
        'settlement_adjustment_created' => 'إضافة تعديل تسوية',
        'settlement_payment_recorded' => 'تسجيل دفعة تسوية',
        'settlement_paid' => 'استكمال دفع التسوية',
        'role_assigned' => 'تعيين دور',
        'permission_granted' => 'منح صلاحية',
        'security_event' => 'حدث أمني',
    ];

    private const ENTITIES = [
        'admin' => 'إداري',
        Admin::class => 'إداري',
        'participant' => 'مشارك',
        Participant::class => 'مشارك',
        'capital_snapshot' => 'لقطة رأس المال',
        'depreciation_note' => 'مخصص إهلاك',
        'distribution_rule' => 'قاعدة توزيع',
        'investment' => 'استثمار',
        'monthly_profit' => 'أرباح شهرية',
        'fund' => 'صندوق',
        'fund_transaction' => 'حركة صندوق',
        'settlement' => 'تسوية سنوية',
        'settlement_adjustment' => 'تعديل تسوية',
        'settlement_payment' => 'دفعة تسوية',
        'security_event' => 'حدث أمني',
        'system' => 'النظام',
    ];

    private const FIELDS = [
        'id' => 'المعرف',
        'participant_id' => 'المشارك',
        'fund_id' => 'الصندوق',
        'transaction_id' => 'الحركة',
        'capital_snapshot_id' => 'لقطة رأس المال',
        'distribution_rule_id' => 'قاعدة التوزيع',
        'monthly_profit_id' => 'الأرباح الشهرية',
        'parent_id' => 'السجل الأصلي',
        'settlement_id' => 'التسوية',
        'created_by_admin_id' => 'أنشأ بواسطة',
        'amount' => 'المبلغ',
        'gross_profit' => 'إجمالي الربح',
        'management_amount' => 'نسبة الإدارة',
        'depreciation_amount' => 'مخصص الإهلاك',
        'growth_amount' => 'حصص النمو',
        'incentive_amount' => 'حصص الحافز',
        'distributed_amount' => 'المبالغ الموزعة',
        'net_payable' => 'صافي المستحق',
        'paid_amount' => 'المدفوع',
        'profit_share' => 'حصة الربح',
        'fund_share' => 'حصة الصندوق',
        'total_capital' => 'إجمالي رأس المال',
        'current_balance' => 'الرصيد الحالي',
        'old_balance' => 'الرصيد السابق',
        'new_balance' => 'الرصيد الجديد',
        'year' => 'السنة',
        'month' => 'الشهر',
        'version' => 'الإصدار',
        'status' => 'الحالة',
        'payment_status' => 'حالة الدفع',
        'transaction_type' => 'نوع الحركة',
        'transaction_date' => 'تاريخ الحركة',
        'invested_at' => 'تاريخ الاستثمار',
        'approved_at' => 'تاريخ الاعتماد',
        'snapshot_date' => 'تاريخ اللقطة',
        'effective_from' => 'ساري من',
        'effective_to' => 'ساري إلى',
        'management_fee_rate' => 'نسبة الإدارة',
        'depreciation_fund_rate' => 'نسبة صندوق الإهلاك',
        'growth_fund_rate' => 'نسبة صندوق النمو',
        'incentive_fund_rate' => 'نسبة صندوق الحافز',
        'distributed_share_rate' => 'نسبة التوزيع',
        'rate' => 'المعدل',
        'description' => 'الوصف',
        'admin_note' => 'ملاحظة',
        'notes' => 'ملاحظات',
        'reference' => 'المرجع',
        'first_name' => 'الاسم الأول',
        'last_name' => 'اسم العائلة',
        'username' => 'اسم المستخدم',
        'email' => 'البريد الإلكتروني',
        'code' => 'الرمز',
        'role' => 'الدور',
        'permission' => 'الصلاحية',
        'ip_address' => 'عنوان IP',
        'user_agent' => 'المتصفح',
        'is_compounded' => 'مركّب',
    ];

    private const STATUS_LABELS = [
        'draft' => 'مسودة',
        'active' => 'نشط',
        'inactive' => 'غير نشط',
        'locked' => 'مقفل',
        'approved' => 'معتمد',
        'pending' => 'قيد الانتظار',
        'rejected' => 'مرفوض',
        'superseded' => 'مستبدل',
        'paid' => 'مدفوع',
        'partially_paid' => 'مدفوع جزئياً',
        'partial' => 'مدفوع جزئياً',
        'cancelled' => 'ملغي',
        'canceled' => 'ملغي',
    ];

    private const TRANSACTION_TYPES = [
        'deposit' => 'إيداع',
        'withdrawal' => 'سحب',
        'adjustment' => 'تسوية',
    ];

    /** @var array<string, string|null> */
    private static array $actorNames = [];

    public static function action(string $action): string
    {
        return self::ACTIONS[$action] ?? str_replace('_', ' ', $action);
    }

    public static function entity(string $type): string
    {
        return self::ENTITIES[$type] ?? (self::classBasename($type) ?? '—');
    }

    public static function actor(string $type, ?string $id): string
    {
        if ($type === 'system') {
            return 'النظام';
        }

        $label = self::entity($type);
        $name = self::actorName($type, $id);

        if ($name !== null) {
            return $name !== '' ? "{$name} ({$label} #{$id})" : "{$label} #{$id}";
        }

        return "{$label} #{$id}";
    }

    public static function values(?array $values): string
    {
        if (empty($values)) {
            return '—';
        }

        $lines = [];
        foreach ($values as $key => $value) {
            if ($key === 'created_at' || $key === 'updated_at') {
                continue;
            }

            $label = self::FIELDS[$key] ?? str_replace('_', ' ', $key);
            $lines[] = "{$label}: ".self::valueLabel($key, $value);
        }

        return implode("\n", $lines);
    }

    private static function valueLabel(string $key, mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($value === null) {
            return '—';
        }

        $string = (string) $value;

        if ($string === '1' || $string === 'true') {
            return 'نعم';
        }

        if ($string === '0' || $string === 'false' || $string === '') {
            return 'لا';
        }

        if ($key === 'status') {
            return self::STATUS_LABELS[$string] ?? $string;
        }

        if ($key === 'payment_status') {
            return self::STATUS_LABELS[$string] ?? $string;
        }

        if ($key === 'transaction_type') {
            return self::TRANSACTION_TYPES[$string] ?? $string;
        }

        if (str_ends_with($key, '_rate')) {
            return self::percentLabel($string);
        }

        return $string;
    }

    private static function percentLabel(string $value): string
    {
        $normalized = $value;

        if (bccomp($normalized, '1', 4) <= 0) {
            $normalized = bcmul($normalized, '100', 4);
        }

        $formatted = number_format((float) $normalized, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted.'%';
    }

    private static function actorName(string $type, ?string $id): ?string
    {
        if ($id === null || $id === '' || $id === '0') {
            return null;
        }

        $cacheKey = $type.'#'.$id;

        if (array_key_exists($cacheKey, self::$actorNames)) {
            return self::$actorNames[$cacheKey];
        }

        $name = match ($type) {
            Admin::class => Admin::query()->find($id)?->name,
            Participant::class => self::participantName(Participant::query()->find($id)),
            'admin' => Admin::query()->find($id)?->name,
            'participant' => self::participantName(Participant::query()->find($id)),
            default => null,
        };

        return self::$actorNames[$cacheKey] = $name;
    }

    private static function participantName(?Participant $participant): ?string
    {
        if ($participant === null) {
            return null;
        }

        return trim(($participant->first_name ?? '').' '.($participant->last_name ?? '')) ?: $participant->username;
    }

    private static function classBasename(string $class): string
    {
        $basename = substr($class, (int) strrpos($class, '\\') + 1);

        return $basename === '' ? $class : str_replace('_', ' ', $basename);
    }
}
