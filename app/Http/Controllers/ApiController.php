<?php

namespace App\Http\Controllers;

use App\Question;
use App\Record;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\Request;
use GuzzleHttp;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class ApiController extends Controller
{
    public function getWechatAccessToken()
    {
        $client = new GuzzleHttp\Client();
        $appid = env("WECHAT_APPID");
        $secret = env("WECHAT_SECRET");

        try {
            $r = $client->request('GET', "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$secret}");
            return json_decode($r->getBody())->access_token;
        } catch (GuzzleHttp\Exception\GuzzleException $e) {
            return false;
        }

    }

    public function apiOpenid(Request $request)
    {
        $code = $request->code;
        $client = new GuzzleHttp\Client();
        $appid = env("WECHAT_APPID");
        $secret = env("WECHAT_SECRET");

        try {
            $r = $client->get("https://api.weixin.qq.com/sns/jscode2session?appid=$appid&secret=$secret&js_code=$code&grant_type=authorization_code");
            return Response::json(array(
                "success" => true,
                "openid" => json_decode($r->getBody())->openid
            ));
        } catch (GuzzleHttp\Exception\GuzzleException $e) {
            return Response::json(array(
                "success" => false,
            ));
        }
    }

    public function getQuestion()
    {
        $question = Question::where("enabled", true)->inRandomOrder()->first();
        return Response::json([
            "success" => true,
            "word" => $question->word,
        ]);
    }

    public function uploadPhoto(Request $request)
    {
        $path = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix() . "/" . $request->file('picture')->store('uploads');
        $filename = basename($path);
        $word_correct = \Illuminate\Support\Facades\Request::header('word');
        $openid = \Illuminate\Support\Facades\Request::header('openid');

        $client = new GuzzleHttp\Client();

        $handle = fopen($path, "rb");
        $contents = fread($handle, filesize($path));
        fclose($handle);

        $headers = array(
            // Request headers
            'Content-Type' => 'application/octet-stream',
            'Ocp-Apim-Subscription-Key' => env('SUBSCRIPTION_KEY')
        );


        try {
            $r = $client->request('POST', env("AZURE_CV_BASE_URL") . "analyze?visualFeatures=Categories%2CDescription%2CColor", [
                'headers' => $headers,
                'body' => $contents
            ]);
            $data = json_decode($r->getBody());
            $tags = array_slice($data->description->tags, 0, 5);

            foreach ($tags as $word) {
                $question = Question::firstOrNew(["word" => $word]);
                $question->count += 1;
                if ($question->count >= env("WORD_ENABLE_THRESHOLD"))
                {
                    $question->enabled = true;
                }
                $question->save();
            }

            $record = new Record;
            $record->word = $word_correct;
            $record->correct = in_array($word_correct, array_slice($data->description->tags, 0, env("TAG_SLICE_THRESHOLD")));
            $record->picture = $filename;
            $record->openid = $openid;
            $record->save();

            return Response::json([
                "success" => true,
                "data" => [
                    "description" => $data->description->captions[0],
                    "tags" => $data->description->tags,
                    "answer" => $word_correct,
                    "correct" => in_array($word_correct, array_slice($data->description->tags, 0, env("TAG_SLICE_THRESHOLD"))) ? true : false
                ]
            ]);

        } catch (GuzzleException $e) {
            return Response::json([
                "success" => false,
            ]);

        }



    }


    public function apiHistory(Request $request)
    {
        $offset = $request->input('offset');
        $limit = $request->input('limit');
        $is_correct = $request->input('is_correct');

        $openid = \Illuminate\Support\Facades\Request::header('openid');

        if (!isset($offset)) {
            $offset = 0;
        }

        if (!isset($limit)) {
            $limit = 10;
        }

        if ($is_correct == true) {
            $count = Record::where("openid", $openid)->where("correct", true)->count();
            $is_end = (($offset + $limit) >= $count);

            return Response::json([
                "success" => true,
                "is_end" => $is_end,
                "data" => Record::where("openid", $openid)->where("correct", true)
                    ->offset($offset)
                    ->limit($limit)
                    ->get()
            ]);
        } else {
            $count = Record::where("openid", $openid)->count();
            $is_end = (($offset + $limit) >= $count);

            return Response::json([
                "success" => true,
                "is_end" => $is_end,
                "data" => Record::where("openid", $openid)
                    ->offset($offset)
                    ->limit($limit)
                    ->get()
            ]);
        }
    }

    public function apiPicture(Request $request)
    {
        $picture_id = $request->picture_id;

        $path = storage_path("app/uploads/" . $picture_id);

        return Image::make($path)->response();

    }

    public function apiAudio(Request $request)
    {
        $client = new GuzzleHttp\Client();
        $words = $request->input("words");

        $response = $client->get('http://dict.youdao.com/dictvoice?audio=' . urlencode($words));

        return response($response->getBody())
            ->header('Content-Type', $response->getHeader("Content-Type"));

    }
}
