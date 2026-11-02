<?php

declare(strict_types=1);

/**
 * Homepage-only, server-scoped summaries. This class does NOT authorize
 * access to the underlying list/detail routes; those need a separate RBAC
 * audit before the security issue can be closed.
 */
class RoleDashboard
{
    public static function build(string $role, int $userId): array
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('کاربر معتبر نیست.');
        }
        if (!in_array($role, ['sales', 'support', 'operations', 'finance'], true)) {
            throw new InvalidArgumentException('نقش پشتیبانی‌نشده است.');
        }

        $result = [
            'role' => $role,
            'title' => [
                'sales' => 'داشبورد فروش من',
                'support' => 'داشبورد پشتیبانی من',
                'operations' => 'داشبورد عملیات من',
                'finance' => 'داشبورد مالی',
            ][$role],
            'metrics' => [],
            'tasks' => [],
            'note' => null,
        ];

        if ($role === 'sales') {
            $stmt = db()->prepare("
                SELECT
                    COUNT(CASE WHEN d.deal_stage NOT IN ('Won','Lost') THEN 1 END) AS open_deals,
                    COALESCE(SUM(CASE WHEN d.deal_stage NOT IN ('Won','Lost') THEN d.estimated_amount ELSE 0 END),0) AS pipeline,
                    COALESCE(SUM(CASE WHEN d.deal_stage NOT IN ('Won','Lost') THEN d.weighted_amount ELSE 0 END),0) AS weighted,
                    COALESCE(SUM(CASE WHEN d.deal_stage = 'Won' THEN d.estimated_amount ELSE 0 END),0) AS won_value
                FROM deals d
                JOIN customers c ON c.id = d.customer_id
                WHERE d.owner_user_id = ? AND d.deleted_at IS NULL AND c.deleted_at IS NULL
            ");
            $stmt->execute([$userId]);
            $sales = $stmt->fetch() ?: [];
            $result['metrics'] = [
                ['label' => 'فرصت‌های باز من', 'value' => (int) ($sales['open_deals'] ?? 0), 'money' => false],
                ['label' => 'ارزش پایپ‌لاین من', 'value' => $sales['pipeline'] ?? 0, 'money' => true],
                ['label' => 'ارزش وزنی من', 'value' => $sales['weighted'] ?? 0, 'money' => true],
                ['label' => 'فروش‌های برنده من (تجمیعی)', 'value' => $sales['won_value'] ?? 0, 'money' => true],
            ];
        } elseif ($role === 'support') {
            $stmt = db()->prepare("
                SELECT COUNT(*) AS assigned_total,
                       COALESCE(SUM(CASE WHEN t.status NOT IN ('Closed','Resolved') THEN 1 ELSE 0 END),0) AS open_total
                FROM tickets t
                JOIN customers c ON c.id=t.customer_id
                JOIN contacts ct ON ct.id=t.contact_id
                WHERE t.assigned_user_id=? AND t.deleted_at IS NULL
                    AND c.deleted_at IS NULL AND ct.deleted_at IS NULL
            ");
            $stmt->execute([$userId]);
            $support = $stmt->fetch() ?: [];
            $result['metrics'] = [
                ['label' => 'تیکت‌های تخصیص‌یافته به من', 'value' => (int) ($support['assigned_total'] ?? 0), 'money' => false],
                ['label' => 'تیکت‌های باز من', 'value' => (int) ($support['open_total'] ?? 0), 'money' => false],
                ['label' => 'تیکت‌های نیازمند بررسی من', 'value' => Ticket::attentionCountForUser($userId), 'money' => false],
            ];
        } elseif ($role === 'finance') {
            // Finance users are permitted to view organization-wide finance.
            $totals = ContractPayment::totals(ContractPayment::financialRows());
            $result['metrics'] = [
                ['label' => 'کل مبلغ قراردادها و فروش‌ها', 'value' => $totals['contract_amount'], 'money' => true],
                ['label' => 'دریافتی ثبت‌شده', 'value' => $totals['received'], 'money' => true],
                ['label' => 'مانده دریافتی', 'value' => $totals['balance'], 'money' => true],
            ];
            $result['note'] = 'شاخص‌های مالی فعلاً تجمیعی هستند؛ امکان انتخاب دوره در فاز گزارش‌های مالی اضافه می‌شود.';
        } else {
            $result['note'] = 'تعهدات نصب و تحویل پس از راه‌اندازی ماژول عملیات در شاخه ماموت کانکت به این داشبورد افزوده می‌شوند.';
        }

        if ($role !== 'finance') {
            $counts = Activity::agendaCountsForOwner($userId);
            $result['metrics'][] = ['label' => 'پیگیری‌های عقب‌افتاده من', 'value' => $counts['overdue'], 'money' => false];
            $result['metrics'][] = ['label' => 'کارهای امروز من', 'value' => $counts['today'], 'money' => false];
            $result['tasks'] = array_slice(array_merge(
                Activity::agendaForOwner($userId, 'overdue'),
                Activity::agendaForOwner($userId, 'today'),
                Activity::agendaForOwner($userId, 'upcoming')
            ), 0, 8);
        }
        return $result;
    }
}
