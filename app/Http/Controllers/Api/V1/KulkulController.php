<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use EasyRdf\Exception as EasyRdfException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

class KulkulController extends Controller
{
    public function index(){
        try {
            $result_kabupaten = $this->sparql->query('
                SELECT ?kabupaten
                WHERE {
                    ?kabupaten rdf:type thk:Kabupaten
                } 
                ORDER BY ?kabupaten
            ');

            $kulkul = [];

            foreach ($result_kabupaten as $item) {
                $kabupaten = $this->parseData($item->kabupaten->getUri());

                $result = $this->sparql->query('
                    SELECT 
                        (MIN(?kulkulName) AS ?id) 
                        (MIN(?kulkulUrl) AS ?image) 
                        (MIN(?kabupaten) AS ?location) 
                    WHERE {
                        ?parent thk:hasKulkul ?kulkulName .
                        ?kulkulName rdf:type ?kulkulLabel .
                        OPTIONAL{ 
                            ?kulkulName thk:hasImageFile ?kulkulImage .
                            ?kulkulImage thk:hasUrl ?kulkulUrl
                        }
                        FILTER (?parent NOT IN (owl:NamedIndividual)) 
                        ?parent thk:isPartOf* thk:'.$kabupaten.' 
                    } 
                    GROUP BY ?parent
                    ORDER BY ?id
                ');

                $kulkul[strtolower($kabupaten)] = [];
                foreach ($result as $data) {
                    if (method_exists($data->id, 'getValue')) {
                        $name = $data->id->getValue();
                    } else {
                        $name = $this->parseData($data->id->getUri());
                    }
                    array_push($kulkul[strtolower(($kabupaten))], [
                        'id' => $this->parseData($data->id->getUri(), true),
                        'name' => $name,
                        'image' => isset($data->image) ? $this->parseUrl($data->image->getValue()) : '',
                        'location' => $kabupaten
                    ]);
                }
            }

            return response()->json([
                'status'  => 'success',
                'data'    => $kulkul
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function indexByLocation(Request $request, $id){
        $validator = Validator::make($request->all(), [
            'page'        => 'int'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'    => 'validator',
                'message'   => $validator->errors()->all()
            ]);
        }
        try{
            $result = $this->sparql->query('
                SELECT 
                    (MIN(?kulkulName) AS ?id) 
                    (MIN(?kulkulUrl) AS ?image) 
                    (MIN(?kabupaten) AS ?location) 
                WHERE {
                    ?parent thk:hasKulkul ?kulkulName .
                    ?parent rdf:type thk:'.ucfirst($id).' .
                    ?kulkulName rdf:type ?kulkulLabel .
                    OPTIONAL{ 
                        ?kulkulName thk:hasImageFile ?kulkulImage .
                        ?kulkulImage thk:hasUrl ?kulkulUrl
                    }
                    FILTER (?parent NOT IN (owl:NamedIndividual)) 
                        ?parent thk:isPartOf* ?kabupaten . 
                        ?kabupaten rdf:type thk:Kabupaten
                } 
                GROUP BY ?parent
                ORDER BY ?id
            ');
            
            $kulkul = [];
            foreach ($result as $data) {
                if(method_exists($data->id, 'getValue')){
                    $name = $data->id->getValue();
                }else{
                    $name = $this->parseData($data->id->getUri());
                }
                array_push($kulkul, [
                    'id' => $this->parseData($data->id->getUri(), true),
                    'name' => $name,
                    'image' => isset($data->image) ? $this->parseUrl($data->image->getValue()) : '',
                    'location' => isset($data->location) ? $this->parseData($data->location->getUri()) : ''
                ]);
            }
            return response()->json([
                'status'  => 'success',
                'data'    => $kulkul
            ]);
        }catch(Throwable $e){
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function showByDesa($id)
    {
        try {
            // detail kulkul
            $result = $this->sparql->query('
                SELECT DISTINCT 
                    ?kulkulName
                    ?kulkulLabel 
                    ?jumlah 
                    ?dimension 
                    ?ukuran 
                    ?bahan_baku
                    ?pengangge 
                    ?direction
                WHERE {
                    thk:'.$id.' rdf:type thk:Desa;
                                thk:hasKulkul ?kulkulName.
                    OPTIONAL { ?kulkulName rdfs:label ?kulkulLabel. }
                    OPTIONAL { ?kulkulName thk:numberKulkul ?jumlah . }
                    OPTIONAL {	
                        ?kulkulName thk:hasDimension ?dimension .
                        ?dimension rdfs:label ?ukuran .
                    }
                    OPTIONAL { ?kulkulName thk:hasRawMaterial ?bahan_baku . }
                    OPTIONAL { ?kulkulName thk:hasPengangge ?pengangge . }
                    OPTIONAL { ?kulkulName thk:hasDirection ?direction . }
                }
            ');

            $kulkul = null;
            if ($result->numRows() > 0) {
                $name = collect();
                $jumlah = collect();
                $bahan_baku = collect();
                $ukuran = collect();
                $pengangge = collect();
                $arah = collect();

                foreach($result as $item){
                    // nama
                    if (isset($item->kulkulLabel)) {
                        $name->push([
                            'id'    => $this->parseData($item->kulkulName->getUri(), true),
                            'value' => $item->kulkulLabel->getValue()
                        ]);
                    } else {
                        $name->push([
                            'id'    => $this->parseData($item->kulkulName->getUri(), true),
                            'value' => $this->parseData($item->kulkulName->getUri())
                        ]);
                    }

                    // jumlah
                    if (isset($item->jumlah)) {
                        $jumlah->push([
                            'id'    => (string) $item->jumlah->getValue(),
                            'value' => (string) $item->jumlah->getValue()
                        ]);
                    }

                    // bahan baku
                    if(isset($item->bahan_baku)){
                        $bahan_baku->push([
                            'id'    => $this->parseData($item->bahan_baku->getUri(), true),
                            'value' => $this->parseData($item->bahan_baku->getUri())
                        ]);
                    }

                    // ukuran
                    if (isset($item->ukuran)) {
                        $ukuran->push([
                            'id'    => $this->parseData($item->dimension->getUri(), true),
                            'value' => $item->ukuran->getValue()
                        ]);
                    }

                    // penggangge
                    if (isset($item->pengangge)) {
                        $pengangge->push([
                            'id'    => $this->parseData($item->pengangge->getUri(), true),
                            'value' => $this->parseData($item->pengangge->getUri()),
                        ]);
                    }

                    // arah
                    if (isset($item->direction)) {
                        $arah->push([
                            'id'    => isset($item->direction) ? $this->parseData($item->direction->getUri(), true) : 'TidakTahu',
                            'value' => isset($item->direction) ? $this->parseData($item->direction->getUri()) : 'Tidak tahu'
                        ]);
                    }
                }

                $kulkul['names'] = $name->unique('value')->values();
                $kulkul['numbers'] = $jumlah->unique('id')->values();
                $kulkul['rawMaterials'] = $bahan_baku->unique('id')->values();
                $kulkul['dimensions'] = $ukuran->unique('id')->values();
                $kulkul['pengangges'] = $pengangge->unique('id')->values();
                $kulkul['directions'] = $arah->unique('id')->values();

                if (count($kulkul['directions']) === 0) {
                    $kulkul['directions'] = [[
                        'id'    => '',
                        'value' => 'Tidak Tahu'
                    ]];
                }

                // gambar
                $result = $this->sparql->query('
                    SELECT DISTINCT
                        ?kulkulUrl
                    WHERE {
                        thk:' . $id . ' rdf:type thk:Desa;
                                        thk:hasKulkul ?kulkulName.
                        ?kulkulName thk:hasImageFile ?kulkulImage .
                        ?kulkulImage thk:hasUrl ?kulkulUrl .
                        FILTER REGEX(?kulkulUrl, "files/kulkul/kulkuldesa/images", "i")
                    }
                ');

                $images = [];
                if ($result->numRows() > 0) {
                    foreach ($result as $item) {
                        array_push($images, isset($item->kulkulUrl) ? $this->parseUrl($item->kulkulUrl->getValue()) : '');
                    }

                    $kulkul['image'] = $images[0];
                } else {
                    $kulkul['image'] = null;
                }
                $kulkul['imageGallery'] = $images;

                // suara kulkul
                $result = $this->sparql->query('
                    SELECT DISTINCT 
                        ?sound 
                        ?activity 
                        ?soundUrl 
                        ?resourceType 
                        ?soundlabel
                    WHERE{
                        thk:' . $id . ' rdf:type thk:Desa;
                                        thk:hasKulkul ?kulkulName ;
                                        thk:hasActivity ?activity .
                        ?kulkulName thk:hasSound ?sound .
                        ?sound	rdfs:label ?soundlabel .
                        ?sound thk:isSoundFor ?activity .
                        ?kulkulName thk:isUsedFor ?activity .
                        OPTIONAL {
                            ?sound thk:hasSoundFile ?soundFile .
                            ?kulkulName thk:hasSoundFile ?soundFile .
                            ?soundFile thk:hasUrl ?soundUrl .
                            ?soundFile thk:hasResourceType ?resourceType .
                        }
                    }
                    ORDER BY ?sound
                ');

                $sounds = [];
                if ($result->numRows() > 0) {
                    foreach ($result as $item) {
                        array_push($sounds, [
                            'activity'  => [
                                'id'        => $this->parseData($item->activity->getUri(), true),
                                'value'     => $this->parseData($item->activity->getUri())
                            ],
                            'sound'     => $item->soundlabel->getValue(),
                            'type'      => isset($item->resourceType) ? $this->parseData($item->resourceType->getUri()) : null,
                            'file'      => isset($item->soundUrl) ? $this->parseUrl($item->soundUrl->getValue()) : null
                        ]);
                    }
                }
                $kulkul['sounds'] = collect($sounds)->unique('sound')->values();
            }
            
            // banjar
            $result = $this->sparql->query('
                SELECT 
                    ?banjar
                    (MIN(?kulkulUrl) AS ?image)
                WHERE {
                    ?banjar rdf:type thk:Banjar;
                        thk:isPartOf thk:' . $id . ' .
                    ?parent thk:hasKulkul ?kulkul;
                            thk:isPartOf* ?banjar .  
                    OPTIONAL {
                        ?kulkul thk:hasImageFile ?kulkulImage .
                        ?kulkulImage thk:hasUrl ?kulkulUrl
                    }
                }
                GROUP BY ?banjar
                ORDER BY ?banjar
            ');

            $data_banjar = [];

            foreach ($result as $value) {
                $id_banjar = $this->parseData($value->banjar->getUri(), true);
                $banjar = $this->parseData($value->banjar->getUri());
                $banjar = explode(' ', $banjar);
                $banjar[0] = 'Banjar';
                $banjar = implode(' ', $banjar);
                $image = isset($value->image) ? $this->parseUrl($value->image->getValue()) : '';

                array_push($data_banjar, [
                    'id'    => $id_banjar,
                    'name'  => $banjar,
                    'image' => $image
                ]);
            }

            return response()->json([
                'status'  => 'success',
                'data'    => [
                    'kulkul' => $kulkul,
                    'banjars' => $data_banjar
                ]
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage().$e->getLine()
            ]);
        }
    }

    public function showByBanjar($id)
    {
        try {
            // detail kulkul
            $result = $this->sparql->query('
                SELECT DISTINCT 
                    ?kulkulName
                    ?kulkulLabel 
                    ?jumlah 
                    ?dimension 
                    ?ukuran 
                    ?bahan_baku
                    ?pengangge 
                    ?direction
                WHERE {
                    thk:' . $id . ' rdf:type thk:Banjar;
                                thk:hasKulkul ?kulkulName.
                    OPTIONAL { ?kulkulName rdfs:label ?kulkulLabel. }
                    OPTIONAL { ?kulkulName thk:numberKulkul ?jumlah . }
                    OPTIONAL {	
                        ?kulkulName thk:hasDimension ?dimension .
                        ?dimension rdfs:label ?ukuran .
                    }
                    OPTIONAL { ?kulkulName thk:hasRawMaterial ?bahan_baku . }
                    OPTIONAL { ?kulkulName thk:hasPengangge ?pengangge . }
                    OPTIONAL { ?kulkulName thk:hasDirection ?direction . }
                }
            ');

            $kulkul = null;
            if ($result->numRows() > 0) {
                $name = collect();
                $jumlah = collect();
                $bahan_baku = collect();
                $ukuran = collect();
                $pengangge = collect();
                $arah = collect();

                foreach ($result as $item) {
                    // nama
                    if (isset($item->kulkulLabel)) {
                        $name->push([
                            'id'    => $this->parseData($item->kulkulName->getUri(), true),
                            'value' => $item->kulkulLabel->getValue()
                        ]);
                    } else {
                        $name->push([
                            'id'    => $this->parseData($item->kulkulName->getUri(), true),
                            'value' => $this->parseData($item->kulkulName->getUri())
                        ]);
                    }

                    // jumlah
                    if (isset($item->jumlah)) {
                        $jumlah->push([
                            'id'    => (string) $item->jumlah->getValue(),
                            'value' => (string) $item->jumlah->getValue()
                        ]);
                    }

                    // bahan baku
                    if(isset($item->bahan_baku)){
                        $bahan_baku->push([
                            'id'    => $this->parseData($item->bahan_baku->getUri(), true),
                            'value' => $this->parseData($item->bahan_baku->getUri())
                        ]);
                    }

                    // ukuran
                    if (isset($item->ukuran)) {
                        $ukuran->push([
                            'id'    => $this->parseData($item->dimension->getUri(), true),
                            'value' => $item->ukuran->getValue()
                        ]);
                    }

                    // penggangge
                    if (isset($item->pengangge)) {
                        $pengangge->push([
                            'id'    => $this->parseData($item->pengangge->getUri(), true),
                            'value' => $this->parseData($item->pengangge->getUri()),
                        ]);
                    }

                    // arah
                    if (isset($item->direction)) {
                        $arah->push([
                            'id'    => isset($item->direction) ? $this->parseData($item->direction->getUri(), true) : 'TidakTahu',
                            'value' => isset($item->direction) ? $this->parseData($item->direction->getUri()) : 'Tidak tahu'
                        ]);
                    }
                }

                $kulkul['names'] = $name->unique('value')->values();
                $kulkul['numbers'] = $jumlah->unique('id')->values();
                $kulkul['rawMaterials'] = $bahan_baku->unique('id')->values();
                $kulkul['dimensions'] = $ukuran->unique('id')->values();
                $kulkul['pengangges'] = $pengangge->unique('id')->values();
                $kulkul['directions'] = $arah->unique('id')->values();

                if (count($kulkul['directions']) === 0) {
                    $kulkul['directions'] = [[
                        'id'    => '',
                        'value' => 'Tidak Tahu'
                    ]];
                }

                // gambar
                $resultChild = $this->sparql->query('
                    SELECT DISTINCT
                        ?kulkulUrl
                    WHERE {
                        thk:' . $id . ' rdf:type thk:Banjar;
                                        thk:hasKulkul ?kulkulName .
                        ?kulkulName thk:hasImageFile ?kulkulImage .
                        ?kulkulImage thk:hasUrl ?kulkulUrl .
                        FILTER REGEX(?kulkulUrl, "files/kulkul/kulkulbanjar/images", "i")
                    }
                ');

                $images = [];
                if ($resultChild->numRows() > 0) {
                    foreach ($resultChild as $item) {
                        array_push($images, $this->parseUrl($item->kulkulUrl->getValue()));
                    }

                    $kulkul['image'] = $images[0];
                } else {
                    $kulkul['image'] = null;
                }
                $kulkul['imageGallery'] = $images;

                // suara kulkul
                $result = $this->sparql->query('
                    SELECT DISTINCT 
                        ?sound 
                        ?activity 
                        ?soundUrl 
                        ?resourceType 
                        ?soundlabel
                    WHERE{
                        thk:' . $id . ' rdf:type thk:Banjar;
                        thk:hasKulkul ?kulkulName ;
                        thk:hasActivity ?activity .
                        ?kulkulName thk:hasSound ?sound .
                        ?sound rdfs:label ?soundlabel .
                        ?sound thk:isSoundFor ?activity .
                        ?kulkulName thk:isUsedFor ?activity .
                        OPTIONAL {
                            ?sound thk:hasSoundFile ?soundFile .
                            ?kulkulName thk:hasSoundFile ?soundFile .
                            ?soundFile thk:hasUrl ?soundUrl .
                            ?soundFile thk:hasResourceType ?resourceType .
                        }
                    }
                    ORDER BY ?sound
                ');

                $sounds = [];
                if ($result->numRows() > 0) {
                    foreach ($result as $item) {
                        array_push($sounds, [
                            'activity'  => [
                                'id'        => $this->parseData($item->activity->getUri(), true),
                                'value'     => $this->parseData($item->activity->getUri())
                            ],
                            'sound'     => $item->soundlabel->getValue(),
                            'type'      => isset($item->resourceType) ? $this->parseData($item->resourceType->getUri()) : null,
                            'file'      => isset($item->soundUrl) ? $this->parseUrl($item->soundUrl->getValue()) : null
                        ]);
                    }
                }
                $kulkul['sounds'] = $sounds;
            }

            return response()->json([
                'status'  => 'success',
                'data'    => $kulkul,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage() . $e->getLine()
            ]);
        }
    }

    public function showByPura($id)
    {
        try {
            $kulkul = [
                'puraDesa' => $this->kulkulPura($id, 'PuraDesa'),
                'puraPuseh' => $this->kulkulPura($id, 'PuraPuseh'),
                'puraDalem' => $this->kulkulPura($id, 'PuraDalem')
            ];

            return response()->json([
                'status'  => 'success',
                'data'    => $kulkul,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage() . $e->getLine()
            ]);
        }
    }

    private function kulkulPura($id, $type)
    {
        // detail kulkul            
        $result = $this->sparql->query('
            SELECT DISTINCT 
                ?kulkulName
                ?jumlah 
                ?dimension 
                ?ukuran 
                ?bahan_baku
                ?pengangge 
                ?direction
            WHERE {
                thk:' . $id . ' rdf:type thk:Desa .
                ?pura thk:hasKulkul ?kulkulName .
                ?pura rdf:type thk:'.$type.';
                        thk:isPartOf thk:' . $id . ' .
                OPTIONAL { ?kulkulName rdfs:label ?kulkulLabel. }
                OPTIONAL { ?kulkulName thk:numberKulkul ?jumlah . }
                OPTIONAL {	
                    ?kulkulName thk:hasDimension ?dimension .
                    ?dimension rdfs:label ?ukuran .
                }
                OPTIONAL { ?kulkulName thk:hasRawMaterial ?bahan_baku . }
                OPTIONAL { ?kulkulName thk:hasPengangge ?pengangge . }
                OPTIONAL { ?kulkulName thk:hasDirection ?direction . }
            }
        ');

        $kulkul = null;
        if ($result->numRows() > 0) {
            $name = collect();
            $jumlah = collect();
            $bahan_baku = collect();
            $ukuran = collect();
            $pengangge = collect();
            $arah = collect();

            foreach ($result as $item) {
                // nama
                $name->push([
                    'id'    => $this->parseData($item->kulkulName->getUri(), true),
                    'value' => $this->parseData($item->kulkulName->getUri())
                ]);

                // jumlah
                if (isset($item->jumlah)) {
                    $jumlah->push([
                        'id'    => (string) $item->jumlah->getValue(),
                        'value' => (string) $item->jumlah->getValue()
                    ]);
                }

                // bahan baku
                if (isset($item->bahan_baku)) {
                    $bahan_baku->push([
                        'id'    => $this->parseData($item->bahan_baku->getUri(), true),
                        'value' => $this->parseData($item->bahan_baku->getUri())
                    ]);
                }

                // ukuran
                if (isset($item->ukuran)) {
                    $ukuran->push([
                        'id'    => $this->parseData($item->dimension->getUri(), true),
                        'value' => $item->ukuran->getValue()
                    ]);
                }

                // penggangge
                if (isset($item->pengangge)) {
                    $pengangge->push([
                        'id'    => $this->parseData($item->pengangge->getUri(), true),
                        'value' => $this->parseData($item->pengangge->getUri()),
                    ]);
                }

                // arah
                if (isset($item->direction)) {
                    $arah->push([
                        'id'    => isset($item->direction) ? $this->parseData($item->direction->getUri(), true) : 'TidakTahu',
                        'value' => isset($item->direction) ? $this->parseData($item->direction->getUri()) : 'Tidak tahu'
                    ]);
                }
            }

            $kulkul['names'] = $name->unique('value')->values();
            $kulkul['numbers'] = $jumlah->unique('id')->values();
            $kulkul['rawMaterials'] = $bahan_baku->unique('id')->values();
            $kulkul['dimensions'] = $ukuran->unique('id')->values();
            $kulkul['pengangges'] = $pengangge->unique('id')->values();
            $kulkul['directions'] = $arah->unique('id')->values();

            if(count($kulkul['directions']) === 0){
                $kulkul['directions'] = [[
                    'id'    => '',
                    'value' => 'Tidak Tahu'
                ]];
            }

            if ($type == "PuraDesa") {
                $filterImage = "kulkulpuradesa";
            } else if ($type == "PuraPuseh") {
                $filterImage = "kulkulpurapuseh";
            } else {
                $filterImage = "kulkulpuradalem";
            }

            // gambar
            $resultChild = $this->sparql->query('
                SELECT DISTINCT
                    ?kulkulUrl
                WHERE {
                    thk:' . $id . ' rdf:type thk:Desa.
                    ?pura thk:hasKulkul ?kulkulName.
                    ?pura rdf:type thk:'.$type.';
                            thk:isPartOf thk:' . $id . ' .
                    ?kulkulName thk:hasImageFile ?kulkulImage .
                    ?kulkulImage thk:hasUrl ?kulkulUrl .
                    FILTER REGEX(?kulkulUrl, "files/kulkul/'.$filterImage.'/images", "i")
                }
            ');

            $images = [];
            if ($resultChild->numRows() > 0) {
                foreach ($resultChild as $item) {
                    array_push($images, $this->parseUrl($item->kulkulUrl->getValue()));
                }

                $kulkul['image'] = $images[0];
            } else {
                $kulkul['image'] = null;
            }
            $kulkul['imageGallery'] = $images;

            // suara kulkul
            $result = $this->sparql->query('
                SELECT DISTINCT 
                    ?sound 
                    ?activity 
                    ?soundUrl 
                    ?resourceType 
                    ?soundlabel
                WHERE{
                    thk:' . $id . ' rdf:type thk:Desa.
                    ?pura thk:hasKulkul ?kulkulName.
                    ?pura rdf:type thk:' . $type . ';
                            thk:isPartOf thk:' . $id . ';
                            thk:hasActivity ?activity .
                    ?kulkulName thk:hasSound ?sound .
                    ?sound rdfs:label ?soundlabel .
                    ?sound thk:isSoundFor ?activity .
                    ?kulkulName thk:isUsedFor ?activity .
                    OPTIONAL {
                        ?sound thk:hasSoundFile ?soundFile .
                        ?kulkulName thk:hasSoundFile ?soundFile .
                        ?soundFile thk:hasUrl ?soundUrl .
                        ?soundFile thk:hasResourceType ?resourceType .
                    }
                }
                ORDER BY ?sound
            ');

            $sounds = [];
            if ($result->numRows() > 0) {
                foreach ($result as $item) {
                    array_push($sounds, [
                        'activity'  => [
                            'id'        => $this->parseData($item->activity->getUri(), true),
                            'value'     => $this->parseData($item->activity->getUri())
                        ],
                        'sound'     => $item->soundlabel->getValue(),
                        'type'      => isset($item->resourceType) ? $this->parseData($item->resourceType->getUri()) : null,
                        'file'      => isset($item->soundUrl) ? $this->parseUrl($item->soundUrl->getValue()) : null
                    ]);
                }
            }
            $kulkul['sounds'] = $sounds;
        }

        return $kulkul;
    }

    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'kabupaten'         => 'required',
            'kecamatan'         => 'required',
            'desa'              => 'required',
            'banjar'            => 'required',
            'puraDesa'          => 'required',
            'puraPuseh'         => 'required',
            'puraDalem'         => 'required',
            'kulkulDesa'        => 'required',
            'kulkulBanjar'      => 'required',
            'kulkulPuraDesa'    => 'required',
            'kulkulPuraPuseh'   => 'required',
            'kulkulPuraDalem'   => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'    => 'validator',
                'message'   => $validator->errors()->all()
            ]);
        }
        try {

            // return response()->json($request->all());
            $desaLabel       = trim(preg_replace('/(?<!\ )[A-Z]/', ' $0', $request->desa));
            $banjarLabel     = trim(preg_replace('/(?<!\ )[A-Z]/', ' $0', $request->banjar));
            $puraDesaLabel   = trim(preg_replace('/(?<!\ )[A-Z]/', ' $0', $request->puraDesa));
            $puraPusehLabel  = trim(preg_replace('/(?<!\ )[A-Z]/', ' $0', $request->puraPuseh));

            $kabupaten = str_replace(' ', '', $request->kabupaten);
            $kecamatan = str_replace(' ', '', $request->kecamatan);
            $desa = str_replace(' ', '', $request->desa);
            $banjar = str_replace(' ', '', $request->banjar);
            $puraDesa = str_replace(' ', '', $request->puraDesa);
            $puraPuseh = str_replace(' ', '', $request->puraPuseh);

            // add query insert banjar
            $queryInsertBanjar = '';
            if (!empty($banjar)) {
                $queryInsertBanjar = <<<EOT
                    thk:$banjar rdf:type thk:Banjar .
                    thk:$banjar thk:isPartOf thk:$desa ;
                        rdfs:label '$banjarLabel' .
                EOT;
            }

            $queryInsertPuraDalem = '';
            if (is_array($request->puraDalem)) {
                foreach ($request->puraDalem as $puraDalemRequest) {
                    if (!empty($puraDalemRequest)) {
                        $puraDalemLabel = trim(preg_replace('/(?<!\ )[A-Z]/', ' $0', $puraDalemRequest['data']));
                        $puraDalem = str_replace(' ', '', $puraDalemRequest['data']);
                        
                        $queryInsertPuraDalem .= <<<EOT
                            thk:$puraDalem rdf:type thk:PuraDalem;
                                            thk:isPartOf thk:$desa;
                                            rdfs:label '$puraDalemLabel'.
                            thk:$desa thk:hasTemple thk:$puraDalem .
                        EOT;
                    }
                }
            }

            $puraDalem = 'PuraDalem' . $desa;

            $query = <<<EOT
                INSERT DATA
                {
                    thk:$desa rdf:type thk:Desa ;
                            thk:isPartOf thk:$kabupaten ;
                            thk:isPartOf thk:$kecamatan ;
                            rdfs:label '$desaLabel'.

                    $queryInsertBanjar
                    
                    thk:$puraDesa rdf:type thk:PuraDesa ;
                                thk:isPartOf thk:$desa ;
                                rdfs:label '$puraDesaLabel' .
                    thk:$desa thk:hasTemple thk:$puraDesa .

                    thk:$puraPuseh rdf:type thk:PuraPuseh;
                                rdfs:label '$puraPusehLabel';
                                thk:isPartOf thk:$desa .
                    thk:$desa thk:hasTemple thk:$puraPuseh .

                    $queryInsertPuraDalem
                } 
            EOT;
            
            Log::info($query);
            $res = $this->sparql->update($query);

            if($res->isSuccessful()){
                $this->storeDetailKulkul('KulkulDesa', $desa, $request->kulkulDesa);
                $this->storeDetailKulkul('KulkulBanjar', $banjar, $request->kulkulBanjar);
                $this->storeDetailKulkul('KulkulPuraDesa', $puraDesa, $request->kulkulPuraDesa);
                $this->storeDetailKulkul('KulkulPuraPuseh', $puraPuseh, $request->kulkulPuraPuseh);
                $this->storeDetailKulkul('KulkulPuraDalem', $puraDalem, $request->kulkulPuraDalem);
            }else{
                throw new Exception('Gagal menambahkan data kulkul');
            }
            
            return response()->json(['status' => 'success', 'message' => 'Berhasil menambahkan data kulkul']);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage() . $e->getLine()
            ]);
        }
    }

    public function storeDetailKulkul($kulkulTypeLocation, $location, $data){
        $user = Auth::user();
        $currentTime = Carbon::now()->format('Y-m-d H:i:s');
        
        $kulkulType = 'Kulkul';
        $kulkul = str_replace(' ', '', $kulkulType . $location);
        $kulkulId = $kulkul . '-' . $user->username;
        $kulkulLabel = trim(preg_replace('/(?<!\ )[A-Z]/', ' $0', $kulkul));

        if (
            !empty($data['number']) && 
            !empty($data['pengangge']['name']) && 
            !empty($data['dimensions']) && 
            !empty($data['rawMaterials']) && 
            !empty($data['direction'])
        ) {
            $jumlah = $data['number'];
            $pengangge = str_replace(' ', '', $data['pengangge']['name']);
            $penganggeLabel = "'" . $pengangge . "'@" . $data['pengangge']['lang'];

            $direction = str_replace(' ', '', $data['direction']);

            $queryInsertRawMaterial = '';
            foreach ($data['rawMaterials'] as $item) {
                $rawMaterial = str_replace(' ', '', $item['name']);
                $rawMaterialLabel = "'".trim(preg_replace('/(?<!\ )[A-Z]/', ' $0', $item['name'])) . "'@" . $item['lang'];

                $queryInsertRawMaterial .= <<<EOT
                    thk:$rawMaterial rdf:type thk:BahanBakuKulkul;
                                    rdfs:label $rawMaterialLabel .

                    thk:$kulkulId thk:hasRawMaterial thk:$rawMaterial .
                EOT;
            }

            $queryInsertDimensions = '';
            foreach ($data['dimensions'] as $item) {
                $queryInsertDimensions .= <<<EOT
                    thk:$kulkulId thk:hasDimension thk:{$item['key']} .
                EOT;
            }

            $queryInsertImage = '';
            if (isset($data['images'])) { 
                foreach ($data['images'] as $file) {
                    $dir = "files/kulkul/".strtolower($kulkulTypeLocation)."/images";
                    $storeFile = $file['data']->store($dir, 'public');
                    $fileType = pathinfo($storeFile, PATHINFO_EXTENSION);
                    $fileName = pathinfo($storeFile, PATHINFO_FILENAME);

                    $imageUrl = $storeFile;
                    $imageFile = $fileName . "." . $fileType;

                    $queryInsertImage .= <<<EOT
                        thk:$imageFile rdf:type thk:Image ;
                            thk:hasUrl '$imageUrl' ;
                            thk:addDate '$currentTime' ;
                            thk:updatedBy thk:{$user->username} .
                            thk:{$user->username} rdf:type thk:uid .
                        thk:$kulkulId thk:hasImageFile thk:$imageFile .
                    EOT;
                }
            }

            $query = <<<EOT
                INSERT DATA
                {
                    thk:$kulkulId rdf:type thk:$kulkulTypeLocation ;
                                rdfs:label '$kulkulLabel' .

                    thk:$location thk:hasKulkul thk:$kulkulId .

                    thk:$kulkulId thk:numberKulkul $jumlah .

                    $queryInsertRawMaterial

                    thk:$kulkulId thk:hasDirection thk:$direction .

                    $queryInsertImage

                    $queryInsertDimensions

                    thk:$pengangge rdf:type thk:PakaianKulkul ;
                                rdfs:label $penganggeLabel .
                                
                    thk:$kulkulId thk:hasPengangge thk:$pengangge .

                    thk:{$user->username} rdf:type thk:uid .
                } 
            EOT;

            Log::info($query);
            $this->sparql->update($query);
        }

        if(!empty($data['sounds'])){
            foreach($data['sounds'] as $item){
                $sound = str_replace(' ', '', $item['name']);
                $soundLabel = "'".$item['name']."'@".$item['lang'];
                $soundId = $sound . "-" . $location;

                $queryInsertKegiatan = "";
                $countActivities = 0;
                if (isset($item['activities']) && is_array($item['activities'])) {
                    foreach ($item['activities'] as $itemChild) {
                        $activity = str_replace(' ', '', $itemChild['name']);
                        $activityLabel = "'" . $activity . "'@" . $itemChild['lang'];
                        $activityGroup = str_replace(' ', '', $itemChild['group']);

                        $queryInsertKegiatan .= <<<EOT
                            thk:$soundId rdf:type thk:SuaraKulkul ;
                                        rdfs:label $soundLabel ;
                                        thk:isSoundFor thk:$activity .

                            thk:$activity rdf:type thk:$activityGroup ;
                                    thk:isUsing thk:$soundId ;
                                    rdfs:label $activityLabel .

                            thk:$location thk:hasActivity thk:$activity .

                            thk:$kulkulId thk:isUsedFor thk:$activity ;
                                        thk:hasSound thk:$soundId .
                        EOT;
                    }
                }else{
                    // break jika tidak ada aktivitas pada suara kulkul
                    return;
                }

                $queryInsertSound = "";
                if($countActivities){
                    foreach($item['file'] as $file){
                        $dir = "files/kulkul/" . strtolower($kulkulTypeLocation) . "/sounds";
                        $storeFile = $file['data']->store($dir, 'public');
                        $fileType = pathinfo($storeFile, PATHINFO_EXTENSION);
                        $fileName = pathinfo($storeFile, PATHINFO_FILENAME);

                        $soundUrl = $storeFile;
                        $soundFile = $fileName . "." . $fileType;

                        $queryInsertSound .= <<<EOT
                            thk:$soundFile rdf:type thk:Audio ;
                                    thk:hasUrl '$soundUrl' ;
                                    thk:addDate '$currentTime' ;
                                    thk:hasResourceType thk:{$item['type']};
                                    thk:updatedBy thk:{$user->username} .

                            thk:{$user->username} rdf:type thk:uid .

                            thk:$soundId thk:hasSoundFile thk:$soundFile .

                            thk:$kulkulId thk:hasSoundFile thk:$soundFile .
                        EOT;
                    }
                }
                
                $query = <<<EOT
                    INSERT DATA
                    {
                        $queryInsertKegiatan
                        $queryInsertSound
                    }
                EOT;

                Log::info($query);
                $this->sparql->update($query);
            }
        }
    }
}
