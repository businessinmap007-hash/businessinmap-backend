<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        /*
         * «419 Page Expired» — صفحةٌ عارية تبتلع ما كتبه المستخدم.
         *
         * انتهاءُ الجلسة ليس خطأً من المستخدم ولا عطلًا فى المنصّة: النموذج
         * فُتح، ثم دارت الجلسة تحته — لأنه بقى مفتوحًا أطول من
         * SESSION_LIFETIME، أو لأنه فُتح على بوّابةٍ وأُرسل من أخرى (عند
         * المطوّر بوّابتان حيّتان: `php artisan serve` و Apache، وهما أصلان
         * مختلفان فى المتصفّح فلا يتبادلان ترميزًا)، أو لأن زرّ الرجوع أعاد
         * نموذجًا من ذاكرة المتصفّح.
         *
         * فى الحالات الثلاث الجوابُ واحد: أعِده إلى نموذجه بترميزٍ جديد، ومعه
         * ما كتبه، ورسالةٌ تقول ما جرى. تسجيلُ نشاطٍ فيه اسمٌ وهاتفٌ وتصنيف
         * كان يضيع كلُّه ليُعاد كتابته من الصفر.
         *
         * وكلمات المرور لا تُعاد: `$dontFlash` أعلاه هى نفسها المستعملة هنا،
         * فما لا يُحفظ عند فشل التحقّق لا يُحفظ هنا أيضًا.
         *
         * ولا يمسّ الـ API: العميلُ البرمجىٌّ ينتظر رمزًا لا تحويلًا.
         *
         * ── ولماذا HttpException لا TokenMismatchException ───────────────────
         *
         * `Handler::render()` يستدعى `prepareException()` **قبل**
         * `renderViaCallbacks()`، وهناك يُحوَّل TokenMismatchException إلى
         * `HttpException(419)`. فمُعالِجٌ مكتوبٌ على النوع الأصلىّ لا يُستدعى
         * أبدًا — جرّبتُه فبقيت الاستجابة ٤١٩ عارية. ٤١٩ لا تصدر إلا من هنا،
         * فالتقاطُها بالرمز دقيقٌ لا فضفاض؛ والسببُ الأصلىّ محفوظٌ فى
         * `getPrevious()` للتأكيد.
         */
        $this->renderable(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || ! $e->getPrevious() instanceof TokenMismatchException) {
                return null;
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            return redirect()->back()
                ->withInput($request->except($this->dontFlash))
                ->with('error', __('انتهت صلاحية الصفحة لطول فتحها. بياناتك محفوظة — أعد الإرسال.'))
                ->withErrors(['_token' => __('انتهت صلاحية الصفحة لطول فتحها. بياناتك محفوظة — أعد الإرسال.')]);
        });
    }
}
