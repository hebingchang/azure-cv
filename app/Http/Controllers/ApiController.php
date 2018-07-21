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

        $api_url = env("AZURE_CV_BASE_URL") . "analyze";


        $request = new HTTP_Request2($api_url);
        $url = $request->getUrl();

        $headers = array(
            // Request headers
            'Content-Type' => 'application/json',
            'Ocp-Apim-Subscription-Key' => env('SUBSCRIPTION_KEY')
        );
        $request->setHeader($headers);

        $parameters = array(
            // Request parameters
            'visualFeatures' => 'Categories,Description',
            'details' => '',
            'language' => 'en'
        );
        $url->setQueryVariables($parameters);

        $request->setMethod(HTTP_Request2::METHOD_POST);

        $handle = fopen($path, "rb");
        $contents = fread($handle, filesize($path));
        fclose($handle);

        // Request body
        $request->setBody($contents);

        try
        {
            $response = $request->send();
            return Response::json(array(
                "success" => true,
                "data" => json_decode($response->getBody())
            ));
        }
        catch (HttpException $ex)
        {
            return Response::json(array(
                "success" => false,
            ));
        }
    }
}
