<?php

namespace App\Http\Controllers;

use App\Question;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use GuzzleHttp;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

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
        $question = Question::inRandomOrder()->first();
        return Response::json([
            "success" => true,
            "word" => $question->word,
        ]);
    }

    public function uploadPhoto(Request $request)
    {
        $path = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix() . "/" . $request->file('picture')->store('uploads');


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
            return Response::json([
                "success" => true,
                "data" => json_decode($r->getBody())
            ]);

        } catch (GuzzleException $e) {
            return Response::json([
                "success" => false,
            ]);

        }



    }
}
