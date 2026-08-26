<?php

namespace App\Support;

use Illuminate\Contracts\View\Factory as ViewFactory;

class ArabicPdf
{
    /**
     * 2026-05-16: واجهة بسيطة لتوليد PDF عربي من Blade عبر mPDF.
     */
    public static function loadView(string $view, array $data = [], array $mergeData = []): MpdfDocument
    {
        /** @var ViewFactory $viewFactory */
        $viewFactory = app(ViewFactory::class);

        $html = $viewFactory->make($view, $data, $mergeData)->render();

        return new MpdfDocument($html);
    }

    public static function loadHTML(string $html): MpdfDocument
    {
        return new MpdfDocument($html);
    }
}
