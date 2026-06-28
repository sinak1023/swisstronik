<?php

/**
 * منطق تبدیل «تراکنش موفق» به «فروشِ کارشناس».
 * قاعده: فقط تراکنش‌هایی که زمان ثبتشان «بعد از» زمان تخصیص لید به کارشناس باشد،
 * به‌عنوان فروش همان کارشناس ثبت می‌شوند. تراکنش‌های قبل از تخصیص نادیده گرفته می‌شوند.
 * هنگام ثبت فروش جدید: پیامک به کارشناس، پیامک به مدیر، و نوتیف تلگرام به کارشناس ارسال می‌شود.
 */
class SaleService
{
    private $db;
    private $sales;
    private $settings;

    public function __construct($db)
    {
        $this->db = $db;
        $this->sales = new Sales($db);
        $this->settings = new Settings($db);
    }

    /** زمان تخصیص لید به کارشناس فعلی (آخرین رکورد تخصیص) */
    public function assignment_time($lead)
    {
        if (empty($lead['assigned_to'])) return null;
        $row = $this->db->fetch(
            "SELECT assigned_at FROM lead_numbers
             WHERE lead_id = ? AND assigned_to = ?
             ORDER BY assigned_at DESC LIMIT 1",
            [$lead['id'], $lead['assigned_to']]
        );
        if ($row && !empty($row['assigned_at'])) return $row['assigned_at'];
        // fallback: زمان آخرین به‌روزرسانی لید
        return $lead['updated_at'] ?? $lead['created_at'] ?? null;
    }

    /**
     * پردازش لیست تراکنش‌های موفق یک لید و ثبت فروش‌های واجد شرایط.
     * $sessions: آرایه‌ای از ['ref' => id, 'date' => 'Y-m-d H:i:s', 'amount' => int]
     * @return array لیست id فروش‌های جدید ثبت‌شده
     */
    public function process($lead, $sessions)
    {
        $newSales = [];
        if (empty($lead['assigned_to'])) return $newSales;

        $assignTime = $this->assignment_time($lead);
        $assignTs = $assignTime ? strtotime($assignTime) : 0;

        foreach ($sessions as $s) {
            $ref = $s['ref'] ?? null;
            if (!$ref) continue;

            // تراکنش باید بعد از زمان تخصیص باشد
            $txnTs = !empty($s['date']) ? strtotime($s['date']) : 0;
            if ($assignTs && $txnTs && $txnTs < $assignTs) {
                continue; // قبل از تخصیص — به این کارشناس ربطی ندارد
            }

            // اگر قبلاً ثبت شده، رد شو
            if ($this->sales->transaction_exists($ref)) continue;

            $saleId = $this->sales->record([
                'lead_id'          => $lead['id'],
                'phone'            => $lead['phone'],
                'user_id'          => $lead['assigned_to'],
                'project_id'       => $lead['project_id'] ?? null,
                'amount'           => $s['amount'] ?? null,
                'transaction_ref'  => $ref,
                'transaction_date' => $s['date'] ?? date('Y-m-d H:i:s'),
                'payment_type'     => 'full',
            ]);

            if ($saleId) {
                $newSales[] = $saleId;
                $this->notify($lead, $s);
            }
        }
        return $newSales;
    }

    /** ارسال اعلان فروش جدید به کارشناس و مدیر */
    private function notify($lead, $sale)
    {
        $expert = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$lead['assigned_to']]);
        if (!$expert) return;

        $amount = isset($sale['amount']) ? number_format($sale['amount'] / 10) . ' تومان' : '';
        $leadName = $lead['name'] ?? $lead['phone'];

        $sms = new Sms($this->db);
        $tg  = new Telegram($this->db);

        // پیامک به کارشناس
        if (!empty($expert['phone'])) {
            $sms->send($expert['phone'], "تبریک! شما یک فروش جدید به لید «{$leadName}» ثبت کردید. مبلغ: {$amount}");
        }
        // نوتیف تلگرام به کارشناس
        $expertChat = Telegram::resolveChatId($expert);
        if ($expertChat) {
            $tg->sendMessage($expertChat, "🎉 <b>فروش جدید</b>\n\nتبریک! شما به لید «{$leadName}» فروختید.\nمبلغ: {$amount}");
        }

        // پیامک به مدیر
        $managerPhone = $this->settings->get('manager_phone', '');
        if (!empty($managerPhone)) {
            $sms->send($managerPhone, "کارشناس «{$expert['name']}» یک فروش جدید به لید «{$leadName}» ثبت کرد. مبلغ: {$amount}");
        }
        // نوتیف تلگرام به مدیر
        $managerChat = $this->settings->get('manager_telegram_id', '');
        if (!empty($managerChat)) {
            $tg->sendMessage($managerChat, "💰 <b>فروش جدید</b>\n\nکارشناس «{$expert['name']}» به لید «{$leadName}» فروخت.\nمبلغ: {$amount}");
        }

        (new ActivityLog($this->db))->log($lead['assigned_to'], 'new_sale', 'lead', $lead['id'], ['amount' => $sale['amount'] ?? null]);
    }
}
