<?php

namespace App\Http\Controllers;

use EasyRdf\RdfNamespace;
use EasyRdf\Sparql\Client;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public $sparql;

    function __construct()
    {
        RdfNamespace::set('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        RdfNamespace::set('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
        RdfNamespace::set('owl', 'http://www.w3.org/2002/07/owl#');
        RdfNamespace::set('thk', 'http://dpch.oss.web.id/Bali/TriHitaKarana.owl#');

        $this->sparql = new Client(env('APP_JENA', 'https://jena.balidigitalheritage.com/fuseki/kulkul/query'));
    }

    public function parseData($data, $raw = false)
    {
        $out = explode('#', $data);
        $out = $out[count($out) - 1];

        if (!$raw) {
            $out = substr(preg_replace('/(?<!\ )[A-Z]/', ' $0', $out), 1);
            $out = explode('-', $out);
            $out = $out[0];
        }

        return $out;
    }

    public function parsePura($data){
        $data = str_replace('PuraDesa', '', $data);
        $data = str_replace('PuraPuseh', '', $data);
        $data = str_replace('PuraDalem', '', $data);

        return $data;
    }

    public function parseUrl($url)
    {
        return 'https://server.aguswmika.id/storage/' . $url;
    }
}
