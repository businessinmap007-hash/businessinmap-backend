<!-- JS -->

<script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>

<script src="{{ asset('assets/front/js/jquery.dd.min.js') }}"></script>
<script src="{{ asset('assets/front/js/owl.carousel.min.js') }}"></script>
<script src="{{ asset('assets/front/js/star-rating.min.js') }}"></script>
<script src="{{ asset('assets/front/js/theme.min.js') }}"></script>
<script src="{{ asset('assets/front/js/lightslider.js') }}"></script>

<script src="{{ asset('assets/general/functions.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-slider/10.6.1/bootstrap-slider.min.js"></script>

<script src="{{ asset('assets/front/js/vue.min.js') }}"></script>
<script src="{{ asset('assets/front/js/vue-carousel-3d.min.js') }}"></script>
<script src="{{ asset('assets/front/js/slick.js') }}"></script>

<script src="{{ asset('assets/front/js/bootstrap.min.js') }}"></script>
<script src="{{ asset('assets/general/confirm/libs/bundled.js') }}"></script>
<script src="{{ asset('assets/general/confirm/jquery-confirm.min.js') }}"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/parsley.js/2.9.1/parsley.min.js"></script>

<!-- Toastr js -->
<script src="{{ asset('assets/general/toastr/toastr.min.js') }}"></script>
<script src="{{ asset('assets/general/carts/shopping_cart.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.2.5/jquery.fancybox.min.js"></script>

<script>
    $(".dropdown-menu").click(function (e) { e.stopPropagation(); });
</script>

<script>
    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });

    var mobile = (!/Android|webOS|iPhone|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent));

    new Vue({
        el: '#example',
        data: { slides: 5 },
        components: {
            'carousel-3d': Carousel3d.Carousel3d,
            'slide': Carousel3d.Slide
        }
    });
</script>

<script>
    $("#filterFormBtn").on('click', function () {
        $(".inputs-filter").each((i, e) => {
            if (e.value == "") {
                $(e).removeAttr('name');
            }
        });
        $("#filterForm").submit();
        return true;
    });

    $(document).ready(function () {
        $('.search a').click(function () {
            $(this).parent().find('.input-group').css({'top': '-10px', 'z-index': '1', 'opacity': '1'});
            $(document).mouseup(function (e) {
                var container = $(".search .input-group");
                if (!container.is(e.target) && container.has(e.target).length === 0) {
                    container.css({'opacity': '0', 'z-index': '-1', 'top': '30px'});
                }
            });
        });
    });
</script>

<script>
    $('.addToCart').on('click', function (e) {
        e.preventDefault();
        var $this = $(this);
        var currentBtnContent = $this.html();
        var productId = $this.data('id');
        $this.html('<i class="fas fa-circle-notch fa-spin" style="color:#fff"></i>').attr('disabled', true);

        $.post('{{ route('add.to.cart') }}', { productId: productId }, function (data) {
            setTimeout(function () {
                $this.html(currentBtnContent).attr('disabled', false);
                showToastrMessage(data.in_cart ? 'warning' : 'success', data.message);
            }, 500);
        });
    });

    function showToastrMessage(type, message) {
        toastr.options = { positionClass: 'toast-top-left', preventDuplicates: true };
        toastr[type](message, '');
    }
</script>

<script>
    if ($(window).width() > 767) {
        $("li.dropdown").hover(function () {
            $(this).toggleClass("open show");
            $(this).find('.dropdown-menu').toggleClass("show");
        });
    } else {
        $("li.dropdown").click(function () {
            $(this).toggleClass("open show");
            $(this).find('.dropdown-menu').toggleClass("show");
        });
    }
</script>

@yield('scripts')
