<?php

namespace App\Http\Controllers;

use App\Models\Tv;
use App\Models\Cast;
use App\Models\Crew;
use App\Models\Image;
use App\Models\Video;
use App\Models\CastTv;
use App\Models\CrewTv;
use Illuminate\Http\Request;
use App\ViewModels\TvViewModel;
use App\ViewModels\TvShowViewModel;
use Illuminate\Support\Facades\Http;

class TvController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $popularTv = Http::withToken(config('services.tmdb.token'))
            ->get('https://api.themoviedb.org/3/tv/popular')
            ->json()['results'];

        $topRatedTv = Http::withToken(config('services.tmdb.token'))
            ->get('https://api.themoviedb.org/3/tv/top_rated')
            ->json()['results'];

        $genres = Http::withToken(config('services.tmdb.token'))
            ->get('https://api.themoviedb.org/3/genre/tv/list')
            ->json()['genres'];

        $viewModel = new TvViewModel(
            $popularTv,
            $topRatedTv,
            $genres,
        );
        return view('tv.index', $viewModel);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {

        $tv_db = Tv::where('id', $id)->with('casts', 'crews')->first();

        if ($tv_db) {

            $tv_db = $tv_db->toArray() + ['credits' => ['cast' => []] + ['crew' => []]] + ['images' => ['backdrops' => []]] + ['videos' => ['results' => []]];

            $properties = json_decode($tv_db['created_by'], true);
            $tv_db['created_by'] = $properties;

            foreach ($tv_db['casts'] as $key => $cast) {
                $cast = Cast::where('id', $cast['cast_id'])->first();
                $cast = $cast->toArray();

                array_push($tv_db['credits']['cast'], $cast);
                if ($key == 4) {
                    break;
                }
            }

            foreach ($tv_db['crews'] as $key => $crew) {
                $crew = Crew::where('id', $crew['crew_id'])->first();
                $crew = $crew->toArray();

                array_push($tv_db['credits']['crew'], $crew);
                if ($key == 1) {
                    break;
                }
            }

            $images = Image::where('related_id', $tv_db['id'])->where('type', 'movie')->take(9)->get();
            $images = $images->toArray();
            foreach ($images as $image) {
                array_push($tv_db['images']['backdrops'], $image);
            }

            $videos = Video::where('related_id', $tv_db['id'])->where('type', 'tv')->take(1)->get();
            $videos = $videos->toArray();
            foreach ($videos as $video) {
                array_push($tv_db['videos']['results'], $video);
            }

            $viewModel = new TvShowViewModel($tv_db);
        } else {

            $tvshow = Http::withToken(config('services.tmdb.token'))
                ->get('http://api.themoviedb.org/3/tv/' . $id . '?append_to_response=credits,videos,images')
                ->json();

            $tv_db = new Tv;
            $tv_db->id = $tvshow['id'];
            $tv_db->name = $tvshow['name'];
            $tv_db->poster_path = $tvshow['poster_path'];
            $tv_db->vote_average = $tvshow['vote_average'];
            $tv_db['genres'] = json_encode($tvshow['genres']);
            $tv_db->overview = $tvshow['overview'];
            $tv_db->first_air_date = $tvshow['first_air_date'];
            $tv_db['created_by'] = json_encode($tvshow['created_by']);
            $tv_db->save();

            // Cast
            foreach ($tvshow['credits']['cast'] as $key => $cast_api) {
                $cast_db = new Cast;

                $cast_db->id = $cast_api['id'];
                $cast_db->adult = $cast_api['adult'];
                $cast_db->gender = $cast_api['gender'];
                $cast_db->known_for_department = $cast_api['known_for_department'];
                $cast_db->name = $cast_api['name'];
                $cast_db->original_name = $cast_api['original_name'];
                $cast_db->popularity = $cast_api['popularity'];
                $cast_db->profile_path = $cast_api['profile_path'];
                $cast_db->character = $cast_api['character'];
                $cast_db->credit_id = $cast_api['credit_id'];
                $cast_db->order = $cast_api['order'];

                $cast_db->save();

                $cast_tv_db = new CastTv;
                $cast_tv_db->cast_id = $cast_api['id'];
                $cast_tv_db->tv_id = $tvshow['id'];
                $cast_tv_db->save();

                if ($key == 4) {
                    break;
                }
            }

            // Crew
            $crew_db = Crew::create([
                'id' => $tvshow['credits']['crew'][0]['id'],
                'adult' => $tvshow['credits']['crew'][0]['adult'],
                'gender' => $tvshow['credits']['crew'][0]['gender'],
                'known_for_department' => $tvshow['credits']['crew'][0]['known_for_department'],
                'name' => $tvshow['credits']['crew'][0]['name'],
                'original_name' => $tvshow['credits']['crew'][0]['original_name'],
                'popularity' => $tvshow['credits']['crew'][0]['popularity'],
                'profile_path' => $tvshow['credits']['crew'][0]['profile_path'],
                'credit_id' => $tvshow['credits']['crew'][0]['credit_id'],
                'department' => $tvshow['credits']['crew'][0]['department'],
                'job' => $tvshow['credits']['crew'][0]['job'],
            ]);

            CrewTv::create([
                'crew_id' => $crew_db->id,
                'tv_id' => $tvshow['id'],
            ]);

            // images
            foreach ($tvshow['images']['backdrops'] as $key => $image_api) {
                Image::create([
                    'type' => 'tv',
                    'related_id' => $tvshow['id'],
                    'aspect_ratio' => $image_api['aspect_ratio'],
                    'file_path' => $image_api['file_path'],
                    'height' => $image_api['height'],
                    'iso_639_1' => $image_api['iso_639_1'],
                    'vote_average' => $image_api['vote_average'],
                    'vote_count' => $image_api['vote_count'],
                    'width' => $image_api['width'],
                ]);


                if ($key == 8) {
                    break;
                }
            }

            // videos
            foreach ($tvshow['videos']['results'] as $key => $video_api) {
                $video_db = new Video;

                $video_db->type = 'tv';
                $video_db->related_id = $tvshow['id'];
                $video_db->iso_639_1 = $video_api['iso_639_1'];
                $video_db->iso_3166_1 = $video_api['iso_3166_1'];
                $video_db->name = $video_api['name'];
                $video_db->key = $video_api['key'];
                $video_db->site = $video_api['site'];
                $video_db->size = $video_api['size'];
                $video_db->official = $video_api['official'];
                $video_db->published_at = $video_api['published_at'];
                $video_db->save();

                if ($key == 0) {
                    break;
                }
            }

            $viewModel = new TvShowViewModel($tvshow);
        }
        return view('tv.show', $viewModel);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
