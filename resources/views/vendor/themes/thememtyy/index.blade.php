@extends('themes::thememtyy.layout')

@php
    use VsMov\Core\Models\Movie;

    $home_page_slider_poster = Cache::remember('site.movies.home_page_slider_poster', setting('site_cache_ttl', 5 * 60), function () {
        $list = get_theme_option('home_page_slider_poster');
		$data = null;
        if(trim($list)) {
			$list = explode('|', $list);
            [$label, $relation, $field, $val, $sortKey, $alg, $limit] = array_merge($list, ['Phim đề cử', '', 'is_recommended', '1', 'updated_at', 'desc', 10]);
            try {
                $data = [
                    'label' => $label,
                    'data' => Movie::when($relation, function ($query) use ($relation, $field, $val) {
                            $query->whereHas($relation, function ($rel) use ($field, $val) {
                            $rel->where($field, $val);
                        });
                    })
                    ->when(!$relation, function ($query) use ($field, $val) {
                        $query->where($field, $val);
                    })
                    ->orderBy($sortKey, $alg)
                    ->limit($limit)
                    ->get()
                ];
            } catch (\Exception $e) {
            }
        }
		return $data;
    });
    	$home_page_slider_thumb = Cache::remember('site.movies.home_page_slider_thumb', setting('site_cache_ttl', 5 * 60), function () {
        $list = get_theme_option('home_page_slider_thumb');
		$data = null;
        if(trim($list)) {
			$list = explode('|', $list);
            [$label, $relation, $field, $val, $sortKey, $alg, $limit, $link] = array_merge($list, ['Phim mới cập nhật', '', 'is_copyright', '0', 'updated_at', 'desc', 20, '#']);
            try {
                $data = [
                    'label' => $label,
                    'data' => Movie::when($relation, function ($query) use ($relation, $field, $val) {
                            $query->whereHas($relation, function ($rel) use ($field, $val) {
                            $rel->where($field, $val);
                        });
                    })
                    ->when(!$relation, function ($query) use ($field, $val) {
                        $query->where($field, $val);
                    })
                    ->orderBy($sortKey, $alg)
                    ->limit($limit)
                    ->get(),
                        'link' => $link ?: '#',

                ];
            } catch (\Exception $e) {
            }
        }
		return $data;
    });
    $movies = Movie::orderBy('updated_at', 'desc')->paginate(36);
@endphp
@section('navclass', 'no-null')
@section('slider_recommended')
    @if($home_page_slider_poster && count($home_page_slider_poster['data']))
        @include('themes::thememtyy.inc.slider_recommended')
    @endif
@endsection
@section('home_page_slider_thumb')
@endsection
@section('content')
    <script>
        var body = document.body;
        body.classList.add("homepage");
    </script>
    <main id="index-main" class="wrapper">
        <div class="content">
            <div class="box-width tv4 wow fadeInUp">
                <div class="title top10">
                    <h4 class="title-h cor4">
                        <a target="_self" href="/" class="ds-line22 more">
                            <span>Tất cả phim</span>
                        </a>
                    </h4>
                </div>
                <div class="flex wrap border-box public-r hide-b-16">
                    @foreach ($movies as $movie)
                        @include('themes::thememtyy.inc.section.movie_card')
                    @endforeach
                </div>
                {{ $movies->appends(request()->all())->links("themes::thememtyy.inc.pagination") }}
            </div>
        </div>
    </main>
@endsection
