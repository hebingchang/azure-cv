<?php

namespace App\Http\Controllers;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Response;
use HTTP_Request2;
use Illuminate\Support\Facades\Storage;

class ApiController extends Controller
{
    public function uploadPhoto(Request $request)
    {
        $path = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix() . "/" . $request->file('picture')->store('uploads');


        $client = new Client();

        $handle = fopen($path, "rb");
        $contents = fread($handle, filesize($path));
        fclose($handle);

        $headers = array(
            // Request headers
            'Content-Type' => 'application/json',
            'Ocp-Apim-Subscription-Key' => env('SUBSCRIPTION_KEY')
        );

        $parameters = array(
            // Request parameters
            'visualFeatures' => 'Categories,Description',
            'details' => '',
            'language' => 'en'
        );

        try {
            $r = $client->request('POST', env("AZURE_CV_BASE_URL") . "analyze", [
                'body' => $contents,
                'headers' => $headers,
                'query' => $parameters,
            ]);

            return Response::json(json_decode($r->getBody()));
        } catch (GuzzleException $e) {
            return Response::json(array(
                "success" => false
            ))

        }


    }
}
