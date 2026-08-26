<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * هجرة محفوظة للتوافق مع قواعد البيانات التي نُفذت عليها دورة الجرد يدويًا
     * قبل إضافتها إلى سجل هجرات المشروع. تتم مطابقة الأعمدة بأمان في الهجرة
     * التالية 2026_08_05_000002 بدل محاولة إعادة إنشاء أعمدة موجودة.
     */
    public function up(): void
    {
    }

    public function down(): void
    {
    }
};
