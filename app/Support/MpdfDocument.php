<?php

namespace App\Support;

use Illuminate\Http\Response;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class MpdfDocument
{
    private string $html;

    /** @var array<string, mixed> */
    private array $options = [];

    private string $format;

    private string $orientation;

    public function __construct(string $html)
    {
        $this->html = $html;
        $this->format = (string) config('pdf.format', 'A4');
        $this->orientation = (string) config('pdf.orientation', 'P');
    }

    public function setPaper(string $paper, ?string $orientation = null): self
    {
        $this->format = strtoupper($paper);

        if ($orientation !== null) {
            $this->orientation = str_starts_with(strtolower($orientation), 'landscape') ? 'L' : 'P';
        }

        return $this;
    }

    public function setOption(string $key, mixed $value): self
    {
        $normalizedKey = str_replace('-', '_', strtolower($key));

        $this->options[$normalizedKey] = $value;

        if ($normalizedKey === 'orientation') {
            $this->orientation = str_starts_with(strtolower((string) $value), 'landscape') ? 'L' : 'P';
        }

        return $this;
    }

    public function save(string $path): string
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $this->makeMpdf()->Output($path, Destination::FILE);

        return $path;
    }

    public function download(string $filename): Response
    {
        return response($this->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->fallbackFilename($filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename),
        ]);
    }

    public function output(): string
    {
        return $this->makeMpdf()->Output('', Destination::STRING_RETURN);
    }

    private function makeMpdf(): Mpdf
    {
        if (! class_exists(Mpdf::class)) {
            throw new \RuntimeException('mPDF is not installed. Run: composer require "mpdf/mpdf:^8.2"');
        }

        $tempDir = (string) config('pdf.temp_dir', storage_path('app/mpdf'));

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $margins = (array) config('pdf.margins', []);
        $fontDir = (string) config('pdf.font_dir', public_path('fonts'));
        $customFontData = (array) config('pdf.font_data', []);

        // 2026-05-17: ندمج خطوط mPDF الافتراضية مع خطوط المشروع حتى لا تصبح available_unifonts فارغة.
        $defaultConfig = (new ConfigVariables())->getDefaults();
        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontDirs = array_values(array_filter(array_merge(
            (array) ($defaultConfig['fontDir'] ?? []),
            [$fontDir],
        )));
        $fontData = array_merge((array) ($defaultFontConfig['fontdata'] ?? []), $customFontData);
        $defaultFont = (string) config('pdf.default_font', 'amiri');

        if (! array_key_exists($defaultFont, $fontData)) {
            $defaultFont = 'dejavusans';
        }

        $mpdf = new Mpdf([
            'mode' => (string) config('pdf.mode', 'utf-8'),
            'format' => $this->format,
            'orientation' => $this->orientation,
            'default_font' => $defaultFont,
            'fontDir' => $fontDirs,
            'fontdata' => $fontData,
            'tempDir' => $tempDir,
            'margin_left' => $this->margin('left', $margins),
            'margin_right' => $this->margin('right', $margins),
            'margin_top' => $this->margin('top', $margins),
            'margin_bottom' => $this->margin('bottom', $margins),
        ]);

        $mpdf->SetDirectionality((string) config('pdf.direction', 'rtl'));
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;

        [$css, $html] = $this->extractCss($this->html);

        $stylesheet = trim($this->baseCss() . "\n" . $this->sanitizeCss($css));

        // 2026-05-16: نرسل CSS إلى mPDF كـ HEADER_CSS حتى لا يظهر كنص داخل كل ملفات PDF.
        // 2026-05-17: لا نفرض خطاً موحداً هنا حتى لا ينكسر تصميم القوالب القديمة؛
        // يظل default_font الخاص بـ mPDF متاحاً للنصوص التي لا تحدد خطاً.
        if ($stylesheet !== '') {
            $mpdf->WriteHTML($stylesheet, HTMLParserMode::HEADER_CSS);
        }

        $mpdf->WriteHTML($html, HTMLParserMode::HTML_BODY);

        return $mpdf;
    }


    private function baseCss(): string
    {
        return <<<'CSS'
html, body {
    direction: rtl;
    text-align: right;
}

table {
    border-collapse: collapse;
}
CSS;
    }


    /**
     * mPDF لا يدعم CSS variables مثل var(--line) بشكل موثوق.
     * ترك هذه القيم قد ينتج border value فارغة وينتهي بتحذير Uninitialized string offset داخل mPDF.
     */
    private function sanitizeCss(string $css): string
    {
        if ($css === '') {
            return '';
        }

        $variables = [];

        $css = preg_replace_callback('/:\s*root\s*\{([^}]*)}/i', function (array $matches) use (&$variables): string {
            if (preg_match_all('/--([A-Za-z0-9_-]+)\s*:\s*([^;]+);/', $matches[1], $varMatches, PREG_SET_ORDER)) {
                foreach ($varMatches as $variable) {
                    $variables[$variable[1]] = trim($variable[2]);
                }
            }

            return '';
        }, $css) ?? $css;

        foreach ($variables as $name => $value) {
            $css = preg_replace('/var\(\s*--' . preg_quote($name, '/') . '\s*\)/i', $value, $css) ?? $css;
        }

        // احذف أي custom property أو declaration بقي يحتوي var() بدون قيمة بديلة.
        $css = preg_replace('/--[A-Za-z0-9_-]+\s*:\s*[^;]+;/', '', $css) ?? $css;
        $css = preg_replace('/[^{};]+:\s*[^;{}]*var\([^;{}]+;?/i', '', $css) ?? $css;

        return trim($css);
    }

    /**
     * استخراج محتوى style من HTML وإزالته من body قبل الإرسال إلى mPDF.
     *
     * @return array{0: string, 1: string}
     */
    private function extractCss(string $html): array
    {
        $css = '';

        $html = preg_replace_callback('/<style\b[^>]*>(.*?)<\/style>/is', function (array $matches) use (&$css): string {
            $css .= "\n" . html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return '';
        }, $html) ?? $html;

        return [trim($css), $html];
    }

    /**
     * @param array<string, mixed> $defaults
     */
    private function margin(string $side, array $defaults): float
    {
        $key = 'margin_' . $side;
        $value = $this->options[$key] ?? $defaults[$side] ?? 10;

        if (is_string($value)) {
            $value = str_replace('mm', '', $value);
        }

        return (float) $value;
    }

    private function fallbackFilename(string $filename): string
    {
        $fallback = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $filename) ?: 'document.pdf';

        return trim($fallback, '-') ?: 'document.pdf';
    }
}
