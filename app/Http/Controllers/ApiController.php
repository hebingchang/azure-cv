<?php

namespace App\Http\Controllers;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Response;

class ApiController extends Controller
{
    public function uploadPhoto(Request $request)
    {
        $path = $request->file('picture')->store('uploads');

        $api_url = env("AZURE_CV_BASE_URL") . "analyze";


        $request = new \HTTP_Request2($api_url);
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

        // Request body parameters
        $body = json_encode(array('url' => $imageUrl));

        // Request body
        $request->setBody($body);

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
