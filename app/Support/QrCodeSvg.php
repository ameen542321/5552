<?php

namespace App\Support;

use RuntimeException;

/**
 * غلاف واحد لتوليد QR الفاتورة عبر مكتبة حقيقية فقط.
 *
 * لا يوجد fallback وهمي هنا: إذا لم تكن مكتبة QR مثبتة سنرمي خطأ واضحًا بدل
 * إظهار باركود غير قابل للمسح أو غير مطابق لمتطلبات الفاتورة الضريبية.
 */
class QrCodeSvg
{
    private int $size = 130;

    public static function size(int $size): self
    {
        $instance = new self();
        $instance->size = max(100, $size);

        return $instance;
    }

    /**
     * يولد SVG قابلًا للمسح من بيانات ZATCA TLV Base64 باستخدام مكتبة QR مثبتة.
     */
    public function generate(?string $payload): string
    {
        $qrPayload = (string) ($payload ?? '');
        $simpleQrFacade = '\\SimpleSoftwareIO\\QrCode\\Facades\\QrCode';

        if (class_exists($simpleQrFacade)) {
            return $simpleQrFacade::format('svg')->size($this->size)->generate($qrPayload);
        }

        if (class_exists('\\BaconQrCode\\Writer')) {
            $rendererStyle = new \BaconQrCode\Renderer\RendererStyle\RendererStyle($this->size);
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                $rendererStyle,
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
            );

            return (new \BaconQrCode\Writer($renderer))->writeString($qrPayload);
        }

        throw new RuntimeException('QR package is required. Install simplesoftwareio/simple-qrcode or bacon/bacon-qr-code.');
    }

    /**
     * يحول SVG إلى data URI لاستخدامه داخل وسم img.
     *
     * السبب: بعض مولدات PDF تعرض XML/SVG الخام كرموز عند حقنه مباشرة في Blade،
     * بينما استخدام img يمنع ظهور تلك الرموز أعلى الباركود.
     */
    public function toDataUri(?string $payload): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->generate($payload));
    }
}
