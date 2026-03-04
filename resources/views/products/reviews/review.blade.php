<div class="review">
    <div class="time">
        <i class="far fa-calendar-alt"></i><span>{{ $rating->created_at->diffForHumans() }}</span>
    </div>
    <div class="row">
        <div class="block-img" style="padding: 5px; border-radius: 40px;">
            <img src="{{ asset('public/'.getUserInfo($rating->user_id)->image) }}">
        </div>
        <div class="block-details">
            @if($user = getUserInfo($rating->user_id) != null)
                <h5>
                    {{ getUserInfo($rating->user_id)->first_name . ' ' . getUserInfo($rating->user_id)->last_name }}
                </h5>
            @endif
            <div class="rating-stars">
                @for($i = 1; $i <= 5; $i++)
                    <i class="{{ $i > $rating->rating ? "far": "fas" }} fa-star"></i>
                @endfor
            </div>
        </div>
    </div>
    <p class="rate">{{ $rating->comment }}</p>
</div>

