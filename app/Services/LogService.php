<?php

namespace App\Services;

use App\Models\Log;
use App\Models\Store;
use App\Models\User;

/**
 * فئة LogService
 * * تقدم هذه الخدمة نظاماً مركزياً لتسجيل الأنشطة والعمليات التي تحدث داخل النظام.
 * تقوم بتحديد المتجر المرتبط بالعملية، وتحديد المستخدم المسؤول عن الإجراء تلقائياً،
 * وتوثيق البيانات الحيوية مثل عنوان الـ IP وبيانات المتصفح للرجوع إليها لاحقاً.
 */
class LogService
{
    /**
     * دالة add
     * * تقوم هذه الدالة بإنشاء سجل جديد في قاعدة البيانات لتوثيق حركة أو حدث معين.
     * * @param string $action الاسم التعريفي البرمجي للعملية (مثال: create, update, delete).
     * @param string $description وصف نصي تفصيلي باللغة العربية يوضح ماهية العملية.
     * @param mixed $model الكائن البرمجي المرتبط بالعملية (اختياري، مثل كائن المنتج أو المتجر).
     * @param mixed $details تفاصيل أو بيانات إضافية حول التعديلات التي تمت (اختياري).
     * @return Log كائن السجل الذي تم إنشاؤه في قاعدة البيانات.
     */
    public function add($action, $description, $model = null, $details = null)
    {
        // [متغير $actor]: يحمل كائن المستخدم الحالي الذي قام بالإجراء، يتم التحقق أولاً من الحارس الافتراضي ثم حارس المحاسبين
        $actorContext = app(OperationActorContext::class);
        $actor = $actorContext->operationalActor();

        // [متغير $storeId]: يحمل المعرف الرقمي للمتجر المرتبط بالعملية الحالية، ويبدأ بقيمة فارغة
        $storeId = null;
        
        // التحقق مما إذا كان الكائن الممرر هو كائن المتجر نفسه مباشرة لتحديد المعرف
        if ($model instanceof Store) {
            $storeId = (int) $model->id;
        } 
        // التحقق مما إذا كان الكائن الممرر يحتوي على عمود معرف المتجر (store_id)
        elseif ($model && isset($model->store_id)) {
            $storeId = (int) $model->store_id;
        }

        // إذا لم يعثر النظام على معرف المتجر من الموديل، يتم التحقق من حقل المتجر المرتبط بالمستخدم الحالي
        if (! $storeId && $actor && isset($actor->store_id)) {
            $storeId = (int) $actor->store_id;
        }

        // إذا لم يعثر النظام على معرف المتجر، يتم فحص حقل المتجر الحالي المفتوح للمستخدم النشط
        if (! $storeId && $actor && isset($actor->current_store_id)) {
            $storeId = (int) $actor->current_store_id;
        }

        // [متغير $ownerUserId]: يحمل معرف المستخدم الرئيسي المالك للمتجر لربط العمليات بحسابه
        $ownerUserId = null;
        
        // الأولوية البرمجية المنطقية: جلب مالك المتجر الفعلي من جدول المتاجر طالما وجد معرف المتجر
        if ($storeId) {
            $ownerUserId = (int) (Store::where('id', $storeId)->value('user_id') ?? 0);
        }
        
        // في حال عدم وجود متجر محدد وكان القائم بالإجراء مستخدماً للنظام، يتم اعتباره هو صاحب العلاقة
        if (! $ownerUserId && $actor instanceof User) {
            $ownerUserId = (int) $actor->id;
        }

        // معالجة البيانات الإضافية للتأكد من حفظها دائماً بصيغة مصفوفة برمجية صالحة
        if (is_string($details)) {
            // [متغير $decoded]: يحمل البيانات الناتجة عن محاولة فك النص البرمجي بصيغة JSON
            $decoded = json_decode($details, true);
            $details = is_array($decoded) ? $decoded : ['message' => $details];
        } elseif ($details === null) {
            $details = [];
        } elseif (! is_array($details)) {
            $details = ['data' => $details];
        }

        $details = $actorContext->auditMetadata($details);
        $actor = $actorContext->realActor();

        // إدراج السجل كاملاً في جدول العمليات بقاعدة البيانات وإرجاع الكائن الناتج
        return Log::create([
            'store_id'    => $storeId,
            'user_id'     => $ownerUserId ?: null,
            'actor_type'  => $actor ? get_class($actor) : null,
            'actor_id'    => $actor?->id,
            'action'      => (string) $action,
            'description' => (string) $description,
            'model_type'  => $model ? get_class($model) : null,
            'model_id'    => $model?->id,
            'details'     => $details,
            'ip'          => request() ? request()->ip() : null,
            'user_agent'  => request() ? request()->userAgent() : null,
        ]);
    }
}








/**
 * =====================================================================================
 * فئة خدمة تسجيل العمليات والأنشطة (LogService)
 * =====================================================================================
 *
 * أولاً: طبيعة عمل الملف (How it works):
 * -----------------------------------
 * يعمل هذا الملف كخدمة مركزية (Centralized Service) مستدامة ومؤمنة لتتبع وتوثيق الحركات
 * والأحداث البرمجية التي تجري داخل النظام. يقوم بالتقاط "الفاعل" (Actor) بشكل آلي وتحديد
 * "المتجر" المستهدف (Store) بناءً على الكائنات الممررة أو الجلسة النشطة، ثم يقوم بمعالجة 
 * تفاصيل البيانات لتخزينها في قاعدة البيانات بشكل مهيكل، مما يسهل عمليات التدقيق (Auditing).
 *
 * ثانياً: وظائف الملف الأساسية (Core Responsibilities):
 * ---------------------------------------------------
 * 1. [تحديد هوية المنفّذ]: التعرف التلقائي على الفاعل (User أو Accountant) وجلب معرفه الفرعـي.
 * 2. [عزل وربط المتاجر]: استخلاص المعرف الرقمي للمتجر المستهدف تلقائياً عبر فحص الكائن الممرر 
 * (Model Instance) أو عبر تفضيلات الحساب النشط (current_store_id / store_id).
 * 3. [تأمين وحماية ملكية البيانات]: التحقق من المالك الحقيقي والنهائي للمتجر لربط السجل بحسابه.
 * 4. [تنظيف وهيكلة البيانات الإضافية]: فحص المتغيرات النصية أو المصفوفات الممررة (Details)، 
 * وفك تشفير نصوص الـ JSON لضمان حفظها دائمًا كبنية مصفوفة (Array) سليمة داخل قاعدة البيانات.
 * 5. [توثيق البصمة الرقمية]: سحب بيانات البيئة التشغيلية للمستخدم مثل عنوان الـ IP والـ User Agent 
 * وتأمين النظام من الانهيار عند التشغيل عبر الوظائف الخلفية أو سطر الأوامر.
 *
 * ثالثاً: مخرجات الملف (Outputs):
 * ------------------------------
 * - المخرج النهائي: يعيد كائن السجل المنشأ حديثاً من موديل قاعدة البيانات (App\Models\Log) 
 * بعد إتمام عملية الحفظ (Database Insertion)، محتوياً على كافة الحقول المهيكلة والروابط العلاقية.
 *
 * =====================================================================================
 */
