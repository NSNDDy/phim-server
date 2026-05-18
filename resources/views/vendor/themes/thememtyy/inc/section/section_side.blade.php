<div class="box-width tv4 wow fadeInUp wr-list">
    <div class="title top10">
        <h4 class="title-h cor4">
            <a target="_self" href="{{$item['link']}}" class="ds-line22 more">
                <span>{{$item['label']}}</span>
                <span class="this-get">
                    <i class="this-hide">Xem thêm</i>
                    <i class="fa ds-jiantouyou"></i>
                </span>
            </a>
        </h4>
    </div>
    <div class="flex wrap border-box public-r hide-b-12">
        <div class="ds-r-hide list-swiper-b">
            <div class="swiper-wrapper">
                @foreach ($item['data'] as $movie)
                <div class="public-list-box public-pic-b swiper-slide">
                    <div class="public-list-div public-list-bj">
                        <a target="_self" class="public-list-exp" href="{{$movie->getUrl()}}"
                           title="{{$movie->name}}">
                            <img class="gen-movie-img mask-0" referrerpolicy="no-referrer"
                                 src="{{$movie->getThumbUrl()}}"
                                 onerror="this.onerror=null;this.src='/themes/mtyy/ds6/img/bg_404.png';"
                                 alt="{{$movie->name}}">
                            <span class="public-bg"></span>
                            <span class="public-list-prb hide ft2">{{$movie->language}} {{$movie->quality}} </span>
                            <span class="public-play">
                                <i class="fa ds-bofang1"></i>
                            </span>
                        </a>
                    </div>
                    <div class="public-list-button">
                        <a target="_self" class="time-title hide ft4" href="{{$movie->getUrl()}}" title="{{$movie->name}}">{{$movie->name}}</a>
                    </div>
                </div>
                @endforeach
            </div>
            <a class="swiper-button-prev fa ds-fanhui" href="javascript:"></a>
            <a class="swiper-button-next fa ds-jiantouyou" href="javascript:"></a>
        </div>
    </div>
</div>
