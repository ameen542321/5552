<?php

return [
    /*
    |--------------------------------------------------------------------------
    | إعدادات mPDF للغة العربية
    |--------------------------------------------------------------------------
    |
    | 2026-05-16: إعدادات مركزية لتوليد ملفات PDF عربية عبر mPDF بعد حذف Snappy.
    | نضبط UTF-8، الاتجاه من اليمين لليسار، واعتماد Amiri أولاً ثم Cairo، والهوامش الافتراضية.
    |
    */

    'mode' => 'utf-8',
    'format' => 'A4',
    'orientation' => 'P',
    'direction' => 'rtl',
    'default_font' => 'amiri',

    'font_dir' => public_path('fonts'),
    'temp_dir' => storage_path('app/mpdf'),

    'font_data' => [
        'cairo' => [
            'R' => 'Cairo-Regular.ttf',
            'B' => 'Cairo-Bold.ttf',
            'useOTL' => 0xFF,
            'useKashida' => 75,
        ],
        'amiri' => [
            'R' => 'Amiri-Regular.ttf',
            'B' => 'Amiri-Bold.ttf',
            'I' => 'Amiri-Italic.ttf',
            'BI' => 'Amiri-BoldItalic.ttf',
            // 2026-05-17: لا نفعّل OTL لخط Amiri لأن mPDF يرمي FontException
            // لبعض جداول GPOS في هذا الخط: GPOS Lookup Type 5, Format 3 not supported.
            'useOTL' => 0,
            'useKashida' => 0,
        ],
    ],

    'margins' => [
        'left' => 10,
        'right' => 10,
        'top' => 10,
        'bottom' => 10,
    ],
];
