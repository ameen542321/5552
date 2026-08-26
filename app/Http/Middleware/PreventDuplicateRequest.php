<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PreventDuplicateRequest
{
    private const TTL_SECONDS = 15;

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->shouldProtect($request)) {
            return $next($request);
        }

        $cacheKey = $this->makeCacheKey($request);

        if (!Cache::add($cacheKey, now()->timestamp, self::TTL_SECONDS)) {
            return $this->duplicateResponse($request);
        }

        return $next($request);
    }

    private function shouldProtect(Request $request): bool
    {
        // يملك فحص مركز الأمن قفلًا أطول خاصًا به ويعيد status=skipped بنجاح عند التكرار.
        // تركه هنا يحوّل الطلب الثاني إلى 409 قبل وصوله إلى عقد الفحص الآمن في الـController.
        if ($request->routeIs('admin.security.maintenance.check')) {
            return false;
        }

        if (!$request->isMethodSafe() && !in_array($request->method(), ['OPTIONS'], true)) {
            return true;
        }

        return false;
    }

    private function makeCacheKey(Request $request): string
    {
        $userPart = $request->user()?->getAuthIdentifier()
            ?? auth('accountant')->id()
            ?? ('guest:' . $request->session()->getId());

        // لا نحذف client_operation_id أو quick_sale_submit_token من البصمة:
        // في البيع السريع قد تتطابق كل حقول البيع فعليًا بين عمليتين شرعيتين متتاليتين،
        // وهذه المعرفات تجعل كل اعتماد جديد مميزًا بينما تبقى إعادة إرسال نفس الصفحة القديمة مرفوضة.
        $payload = $request->except(['_token', '_method']);
        ksort($payload);

        $files = collect($request->allFiles())
            ->map(function ($file) {
                if (is_array($file)) {
                    return collect($file)->map(fn ($nested) => $this->mapUploadedFile($nested))->all();
                }

                return $this->mapUploadedFile($file);
            })
            ->toArray();

        return 'duplicate_request:' . sha1(json_encode([
            'user' => $userPart,
            'method' => $request->method(),
            'route' => $request->route()?->getName() ?? $request->path(),
            'payload' => $payload,
            'files' => $files,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function mapUploadedFile($file): array
    {
        return [
            'name' => $file?->getClientOriginalName(),
            'size' => $file?->getSize(),
        ];
    }

    private function duplicateResponse(Request $request): Response
    {
        $message = 'تم تجاهل الطلب لأنه مكرر ومطابق لطلب نُفذ قبل لحظات. تحقق من النتيجة قبل إعادة الإرسال.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 409);
        }

        return back()->with('error', $message)->withInput();
    }
}
