<!-- start footer -->

<footer>
    <div class="container">
        <div class="row">
            <div class="col-lg-4 col-sm-6">
                <img src="{{ request()->root() }}/public/assets/front/img/logo2.png">
                <p class="mt-20">هناك حقيقة مثبتة منذ زمن طويل وهي أن المحتوى المقروء لصفحة ما سيلهي القارئ عن
                    التركيز على الشكل الخارجي للنص أو شكل توضع الفقرات في الصفحة التي يقرأها</p>
            </div>
            <div class="col-lg-2 col-sm-3">
                <h5 class="the-title">حسابى</h5>
                <ul>
                    <li><a href="{{ route('terms') }}">Terms & Conditions</a></li>
                    <li><a href="{{ route('privacy') }}">Privacy Policy</a></li>
                    <li><a href="#">Faqs</a></li>
                    <li><a href="#">Careers</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-sm-3">
                <h5 class="the-title">روابط هامة</h5>
                <ul>
                    <li><a href="{{ route('aboutus') }}">@lang('trans.aboutus')</a></li>
                    <li><a href="{{ route('terms') }}">Terms & Conditions</a></li>
                    <li><a href="#">Link</a></li>
                    <li><a href="#">Link</a></li>
                </ul>
            </div>
            <div class="col-lg-4 col-sm-12">
                <h5 class="the-title">القائمة البريدية</h5>
                <form>
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="البريد الالكترونى">
                        <div class="input-group-btn">
                            <button class="btn btn-default the-btn3" type="submit">
                                اشترك الان
                            </button>
                        </div>
                    </div>
                </form>
                <div class="pay">
                    <img src="{{ request()->root() }}/public/assets/front/img/pay1.png" alt="paypal" title="paypal">
                    <img src="{{ request()->root() }}/public/assets/front/img/pay2.png" alt="mada" title="mada">
                    <img src="{{ request()->root() }}/public/assets/front/img/pay3.png" alt="visa" title="visa">
                    <img src="{{ request()->root() }}/public/assets/front/img/pay4.png" alt="master card"
                         title="master card">
                </div>
            </div>
        </div>
        <div class="col-xs-12">
            <div class="copyrights">
                <p>All copyrights are saved 2018</p>
                <ul class="social">
                    @if($setting->getBody('google_plus'))
                        <li>
                            <a target="_blank" href="{{ $setting->getBody('google_plus') }}" title="google+">
                                <img src="{{ request()->root() }}/public/assets/front/img/icon-google.png">
                            </a>
                        </li>
                    @endif
                    @if($setting->getBody('instagram'))
                        <li>
                            <a target="_blank" href="{{ $setting->getBody('instagram') }}" title="instagram">
                                <img src="{{ request()->root() }}/public/assets/front/img/icon-instagram.png">
                            </a>
                        </li>
                    @endif
                    @if($setting->getBody('twitter'))
                        <li>
                            <a target="_blank" href="{{ $setting->getBody('twitter') }}" title="twitter">
                                <img src="{{ request()->root() }}/public/assets/front/img/icon-twitter.png">
                            </a>
                        </li>
                    @endif

                    @if($setting->getBody('facebook'))
                        <li>
                            <a target="_blank" href="{{ $setting->getBody('facebook') }}" title="facebook">
                                <img src="{{ request()->root() }}/public/assets/front/img/icon-facebook.png">
                            </a>
                        </li>
                    @endif

                </ul>
            </div>
        </div>
    </div>
</footer>
<div class="footer-img"><img src="{{ request()->root() }}/public/assets/front/img/footer.png"></div>