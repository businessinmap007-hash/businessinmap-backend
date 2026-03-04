$(function(){
    //select
    if($('.country-select').length){
        $(".country-select").msDropDown();
    }
    if($('.currency-select').length){
        $('.currency-select').msDropDown();
    }
    //search input
    $('button#search').on('click',function(){
        //alert('ok');
        $(".searchInput").animate({width:'toggle'},350);
    });
    $('.navbar-nav').on('click',function(e){
        if ( e.target == this ){
            $(".searchInput").animate({width:'toggle'},350);
        }
    });
    //owl carousel
    if($('.owl-carousel').length){
        $('.owl-carousel').owlCarousel({
            loop:false,
            margin:10,
            responsiveClass:true,
            autoPlay: 1000,
            responsive:{
                0:{
                    items:2,
                    nav:true
                },
                600:{
                    items:3,
                    nav:true
                },
                1000:{
                    items:4,
                    nav:true
                },
                1200:{
                    items:6,
                    nav:true
                }
            }
        });
    }
    //timer
    if($('.offer-mnu').length){
        $('.offer-mnu').each(function(){
            var daysHtml = $(this).find('#days');
            var hoursHtml= $(this).find('#hours');
            var minsHtml = $(this).find('#mins');
            var secHtml = $(this).find('#secs');
            var days = Number(daysHtml.html());
            var hours = Number(hoursHtml.html());
            var minutes = Number(minsHtml.html());
            var seconds = Number(secHtml.html());
            //alert(seconds);
            function calculate() {
                setTimeout(calculate, 1000);
                daysHtml.html(days);
                hoursHtml.html(hours);
                minsHtml.html(minutes);
                secHtml.html(seconds);
                seconds--;
                if (seconds < 0) {
                    seconds = 59;
                    minutes--;
                    if (minutes < 0) {
                        hours--;
                        minutes = 59;
                        if (hours < 0) {
                            days--;
                            hours = 23;
                            if (days < 0) {
                                days = 0;
                                hours = 0;
                                minutes = 0;
                                seconds = 0;
                            }
                        }
                    }
                }
            }
            calculate();
        });
    }
    //rating
    if($('.kv-rtl-theme-fas-star').length){
        $('.kv-rtl-theme-fas-star').rating({
            hoverOnClear: false,
            theme: 'krajee-fas',
            showCaption:'false',
            containerClass: 'is-star'
        });
        $('.kv-rtl-theme-fas-star').on('rating:change', function(event, value, caption) {
            console.log(value);
        });
    }
    //price slider
    if($('#price').length){
        $("#price").slider({});
    }
    //lightSlider
    // $("#lightSlider").lightSlider({
    //     gallery: true,
    //     item: 1,
    //     loop: true,
    //     slideMargin: 0,
    //     thumbItem: 3
    // });
    //increase item numbers
    $(".increase").bind('keyup mouseup', function () {
        var getVal = $(this).val();
        $(this).attr('value',getVal);
        var changed = $(this).parents('tr').find('.increased');
        var getCurVal = $(this).parents('tr').find('.curPrice').html();
        var finalVal = Number(getCurVal) * Number(getVal);
        changed.html(finalVal);
        var getAll = 0;
        $('.table .increased').each(function(){
            getAll+=Number($(this).html());
        });
        $('.totalVal').html(getAll);
        $('.totalVal2').html(getAll);

    });

});
//map 
function initMap() {
    if($('#map').length){
        // The location of Uluru
        var uluru = {lat: 29.9883297, lng: 31.1635441};
        // The map, centered at Uluru
        var map = new google.maps.Map(document.getElementById('map'), {
            center: uluru,
            zoom: 20
                
        });
        // The marker, positioned at Uluru
        var marker = new google.maps.Marker({
            position: uluru,
            map: map,
            title: 'Position',
            icon: 'assets/imgs/contactUs/marker@2x.png'
        });
    }
    //cart map
    else if($('#map2').length){
        // The location of Uluru
        var uluru = {lat: 29.9883297, lng: 31.1635441};
        // The map, centered at Uluru
        var map2 = new google.maps.Map(document.getElementById('map2'), {
            center: uluru,
            zoom: 20
                
        });
        // The marker, positioned at Uluru
        var marker = new google.maps.Marker({
            position: uluru,
            map: map2,
            draggable: true,
            title: 'Position',
            icon: 'assets/imgs/contactUs/marker@2x.png'
        });
    }
    else{
        return;
    }
}