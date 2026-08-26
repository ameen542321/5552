<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->guardAgainstRealDatabaseTesting();

        parent::setUp();
    }

    private function guardAgainstRealDatabaseTesting(): void
    {
        $connection = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? $_SERVER['DB_CONNECTION'] ?? null);
        $database = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? null);
        $allowRealDatabase = filter_var(
            getenv('ALLOW_REAL_DATABASE_TESTS') ?: ($_ENV['ALLOW_REAL_DATABASE_TESTS'] ?? $_SERVER['ALLOW_REAL_DATABASE_TESTS'] ?? false),
            FILTER_VALIDATE_BOOL
        );

        $message = sprintf(
            '[اختبارات] قاعدة البيانات المستخدمة: DB_CONNECTION=%s, DB_DATABASE=%s%s',
            $connection ?: 'غير محدد',
            $database ?: 'غير محدد',
            PHP_EOL
        );

        fwrite(STDERR, $message);

        if ($allowRealDatabase) {
            fwrite(STDERR, '[اختبارات] تم تجاوز حماية قاعدة البيانات عبر ALLOW_REAL_DATABASE_TESTS=true.' . PHP_EOL);

            return;
        }

        if ($connection === 'sqlite' && $database === ':memory:') {
            fwrite(STDERR, '[اختبارات] الوضع آمن: سيتم استخدام قاعدة SQLite داخل الذاكرة فقط.' . PHP_EOL);

            return;
        }

        $details = implode(PHP_EOL, [
            'تم إيقاف الاختبارات لحماية قاعدة البيانات الحقيقية.',
            'شغّل الاختبارات عبر php artisan test أو vendor/bin/phpunit مع phpunit.xml الحالي.',
            'يجب أن تكون إعدادات الاختبار: DB_CONNECTION=sqlite و DB_DATABASE=:memory:',
            'إذا كنت متأكدًا أنك تستخدم قاعدة اختبار غير حقيقية، يمكن التجاوز مؤقتًا بإضافة ALLOW_REAL_DATABASE_TESTS=true.',
        ]);

        throw new \RuntimeException($details);
    }
}
