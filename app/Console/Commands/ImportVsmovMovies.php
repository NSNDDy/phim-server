<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use VsMov\Core\Models\Actor;
use VsMov\Core\Models\Category;
use VsMov\Core\Models\Director;
use VsMov\Core\Models\Episode;
use VsMov\Core\Models\Movie;
use VsMov\Core\Models\Region;

class ImportVsmovMovies extends Command
{
    protected $signature = 'vsmov:api-sync
        {--limit=10 : Maximum movies to import}
        {--endpoint=https://vsmov.com/api/danh-sach/phim-moi-cap-nhat : List endpoint}
        {--base=https://vsmov.com : VSMOV base URL}
        {--start-page=1 : First list page to fetch}
        {--pages= : Number of list pages to fetch}
        {--page-size= : Optional API page size}
        {--sleep-ms=0 : Delay between detail requests in milliseconds}';

    protected $description = 'Import movies from the public VSMOV API.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $baseUrl = rtrim($this->option('base'), '/');
        $startPage = max(1, (int) $this->option('start-page'));
        $pagesOption = $this->option('pages');
        $maxPages = $pagesOption === null || $pagesOption === '' ? null : max(1, (int) $pagesOption);
        $pageSizeOption = $this->option('page-size');
        $pageSize = $pageSizeOption === null || $pageSizeOption === '' ? null : max(1, (int) $pageSizeOption);
        $sleepMs = max(0, (int) $this->option('sleep-ms'));

        $items = $this->fetchListItems($limit, $startPage, $maxPages, $pageSize);

        if ($items->isEmpty()) {
            $this->error('Cannot fetch VSMOV list API.');
            return self::FAILURE;
        }

        $bar = $this->output->createProgressBar($items->count());
        $bar->start();

        foreach ($items as $item) {
            $slug = $item['slug'] ?? null;

            if (!$slug) {
                $bar->advance();
                continue;
            }

            $detail = $this->fetchJson($baseUrl . '/api/phim/' . $slug);

            if (!$detail || !($detail['status'] ?? false) || empty($detail['movie'])) {
                $bar->advance();
                continue;
            }

            $movie = $this->upsertMovie($detail['movie']);
            $this->syncTaxonomies($movie, $detail['movie']);
            $this->syncEpisodes($movie, $detail['episodes'] ?? []);

            $bar->advance();

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info('VSMOV API sync finished.');

        return self::SUCCESS;
    }

    private function fetchListItems(int $limit, int $startPage = 1, ?int $maxPages = null, ?int $pageSize = null)
    {
        $endpoint = $this->option('endpoint');
        $items = collect();
        $page = $startPage;
        $lastPage = $maxPages ? $startPage + $maxPages - 1 : null;

        while ($items->count() < $limit) {
            if ($lastPage && $page > $lastPage) {
                break;
            }

            $query = ['page' => $page];

            if ($pageSize) {
                $query['limit'] = $pageSize;
            }

            $response = $this->fetchJson($endpoint, $query);

            if (!$response || !($response['status'] ?? false)) {
                $this->warn(sprintf('Cannot fetch list page %d.', $page));

                if (!$lastPage) {
                    break;
                }

                $page++;
                continue;
            }

            $pageItems = collect($response['items'] ?? []);

            if ($pageItems->isEmpty()) {
                break;
            }

            $items = $items->merge($pageItems);
            $this->line(sprintf('Fetched list page %d (%d items)', $page, $pageItems->count()));
            $page++;
        }

        return $items->unique('slug')->take($limit)->values();
    }

    private function fetchJson(string $url, array $query = []): ?array
    {
        $lastError = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::timeout(45)->get($url, $query);

                if ($response->ok()) {
                    return $response->json();
                }

                $lastError = sprintf('HTTP %s', $response->status());
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }

            sleep($attempt);
        }

        $this->warn(sprintf('Request failed: %s %s', $url, $lastError ?: 'unknown error'));

        return null;
    }

    private function upsertMovie(array $data): Movie
    {
        $slug = $this->stringOrNull($data['slug'] ?? null) ?: md5(json_encode($data));
        $name = $this->stringOrNull($data['name'] ?? null) ?: $slug;

        return Movie::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'origin_name' => $this->stringOrNull($data['origin_name'] ?? null) ?: $name,
                'content' => $this->stringOrNull($data['content'] ?? null) ?: '',
                'thumb_url' => $this->stringOrNull($data['thumb_url'] ?? null),
                'poster_url' => $this->stringOrNull($data['poster_url'] ?? null),
                'type' => in_array($this->stringOrNull($data['type'] ?? null), ['single', 'series'], true) ? $data['type'] : 'single',
                'status' => in_array($this->stringOrNull($data['status'] ?? null), ['trailer', 'ongoing', 'completed'], true) ? $data['status'] : 'completed',
                'trailer_url' => $this->stringOrNull($data['trailer_url'] ?? null),
                'episode_time' => $this->stringOrNull($data['time'] ?? null),
                'episode_current' => $this->stringOrNull($data['episode_current'] ?? null),
                'episode_total' => $this->stringOrNull($data['episode_total'] ?? null),
                'quality' => $this->stringOrNull($data['quality'] ?? null) ?: 'HD',
                'language' => $this->stringOrNull($data['lang'] ?? null) ?: 'Vietsub',
                'notify' => $this->stringOrNull($data['notify'] ?? null),
                'showtimes' => $this->stringOrNull($data['showtimes'] ?? null),
                'publish_year' => is_numeric($data['year'] ?? null) ? (int) $data['year'] : null,
                'is_shown_in_theater' => (bool) ($data['chieurap'] ?? false),
                'is_copyright' => (bool) ($data['is_copyright'] ?? false),
                'update_handler' => 'vsmov-api',
                'update_identity' => $this->stringOrNull($data['_id'] ?? null) ?: $slug,
                'rating_star' => is_numeric(data_get($data, 'tmdb.vote_average')) ? (float) data_get($data, 'tmdb.vote_average') : 0,
                'rating_count' => is_numeric(data_get($data, 'tmdb.vote_count')) ? (int) data_get($data, 'tmdb.vote_count') : 0,
                'view_total' => is_numeric($data['view'] ?? null) ? (int) $data['view'] : 0,
            ]
        );
    }

    private function stringOrNull($value): ?string
    {
        if (is_null($value) || is_array($value) || is_object($value)) {
            return null;
        }

        return (string) $value;
    }

    private function syncTaxonomies(Movie $movie, array $data): void
    {
        $movie->categories()->sync($this->taxonomyIds(Category::class, $data['category'] ?? []));
        $movie->regions()->sync($this->taxonomyIds(Region::class, $data['country'] ?? []));
        $movie->actors()->sync($this->personIds(Actor::class, $data['actor'] ?? []));
        $movie->directors()->sync($this->personIds(Director::class, $data['director'] ?? []));
    }

    private function taxonomyIds(string $modelClass, array $items): array
    {
        return collect($items)->map(function ($item) use ($modelClass) {
            if (!is_array($item) || empty($item['name'])) {
                return null;
            }

            return $modelClass::firstOrCreate(
                ['slug' => $item['slug'] ?? Str::slug($item['name'])],
                ['name' => $item['name']]
            )->id;
        })->filter()->values()->all();
    }

    private function personIds(string $modelClass, array $names): array
    {
        return collect($names)->map(function ($name) use ($modelClass) {
            if (!is_string($name) || trim($name) === '') {
                return null;
            }

            $person = $modelClass::where('name_md5', md5($name))->first();

            if (!$person) {
                $person = $modelClass::create([
                    'name' => $name,
                    'slug' => Str::slug($name) ?: md5($name),
                ]);
            }

            return $person->id;
        })->filter()->values()->all();
    }

    private function syncEpisodes(Movie $movie, array $servers): void
    {
        $episodeCount = 0;

        foreach ($servers as $server) {
            foreach (($server['server_data'] ?? []) as $episode) {
                $link = $episode['link_embed'] ?? $episode['link_m3u8'] ?? $episode['link'] ?? null;

                if (!$link) {
                    continue;
                }

                Episode::updateOrCreate(
                    [
                        'movie_id' => $movie->id,
                        'server' => $server['server_name'] ?? 'VSMOV',
                        'slug' => $episode['slug'] ?? Str::slug($episode['name'] ?? 'full'),
                    ],
                    [
                        'name' => $episode['name'] ?? 'Full',
                        'type' => isset($episode['link_embed']) ? 'embed' : 'm3u8',
                        'link' => $link,
                    ]
                );

                $episodeCount++;
            }
        }

        $movie->update([
            'episode_server_count' => count($servers),
            'episode_data_count' => $episodeCount,
        ]);
    }
}
