# هيكل SQLite الموحّد للاختبارات

يستخدم PHPUnit قاعدة `SQLite :memory:` المحمية في `phpunit.xml`. عند استعمال `RefreshDatabase` يوجّه
`Tests\Concerns\RefreshDatabase` أمر `migrate:fresh` إلى `database/migrations/testing` بدل سجل migrations الإنتاجي.

ينفذ ملف migration الاختباري `database/testing/sqlite-schema.sql`، وهو هيكل بلا بيانات مشتق من
`database/reference/carled_schema_2026-08-09.sql` ومكمّل بجداول وحقول مركز الأمن المضافة في
`2026_08_09_000001` إلى `2026_08_09_000003`.

## ضمانات الأمان

- لا يتصل هذا الهيكل بـ MySQL ولا يقرأ إعداداتها.
- لا يحتوي أوامر `DROP DATABASE` أو بيانات إنتاجية أو `INSERT`.
- ينشأ داخل ذاكرة عملية الاختبار ويختفي بانتهائها.
- لا يستخدمه `php artisan migrate` العادي؛ مساره مفعل من `tests/Concerns/RefreshDatabase.php` للاختبارات فقط.

## التحديث عند تغيير البنية

عند إضافة migration إنتاجية جديدة يجب تحديث `sqlite-schema.sql` بالبنية النهائية المكافئة، ثم تحديث
`tests/Unit/SqliteTestingSchemaTest.php` بالأعمدة أو الجداول الحرجة الجديدة. لا تنسخ صياغة MySQL مثل
`ENGINE` و`AUTO_INCREMENT` و`ENUM` حرفيًا؛ استخدم أنواع SQLite المنطقية المكافئة.

شغّل بعد ذلك:

```bash
php artisan test --filter=SqliteTestingSchemaTest
php artisan test
```
