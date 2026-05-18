<div class="public-list-box public-pic-b">
    <div class="public-list-div public-list-bj">
        <a target="_self" class="public-list-exp" href="{{$movie->getUrl()}}" title="{{$movie->name}}">
            <img class="gen-movie-img mask-0" referrerpolicy="no-referrer"
                 src="{{$movie->getThumbUrl()}}"
                 onerror="this.onerror=null;this.src='/themes/mtyy/ds6/img/bg_404.png';"
                 alt="{{$movie->name}} {{ $movie->origin_name }}">
            <span class="public-bg"></span>
            <span class="public-list-prb hide ft2">{{$movie->language}} {{$movie->quality}}</span>
            <span class="public-play">
                <i class="fa ds-bofang1"></i>
            </span>
        </a>
    </div>
    <div class="public-list-button">
        <a target="_self" class="time-title hide ft4" href="{{$movie->getUrl()}}" title="{{$movie->name}}">{{$movie->name}}</a>
    </div>
</div>
