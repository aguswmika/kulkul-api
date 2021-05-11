<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use EasyRdf\Exception as EasyRdfException;
use Exception;
use Illuminate\Http\Request;
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
                    OFFSET 0
                    LIMIT 10
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
                OFFSET 0
                LIMIT 10
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
                        $name->push($item->kulkulLabel->getValue());
                    } else {
                        $name->push($this->parseData($item->kulkulName->getUri()));
                    }

                    // jumlah
                    if (isset($item->jumlah)) {
                        $jumlah->push($item->jumlah->getValue());
                    }

                    // bahan baku
                    if(isset($item->bahan_baku)){
                        $bahan_baku->push($this->parseData($item->bahan_baku->getUri()));
                    }

                    // ukuran
                    if (isset($item->ukuran)) {
                        $ukuran->push($item->ukuran->getValue());
                    }

                    // penggangge
                    if (isset($item->pengangge)) {
                        $pengangge->push($this->parseData($item->pengangge->getUri()));
                    }

                    // arah
                    if (isset($item->direction)) {
                        $arah->push(isset($item->direction) ? $this->parseData($item->direction->getUri()) : 'Tidak tahu');
                    }
                }

                $kulkul['names'] = $name->unique()->values();
                $kulkul['numbers'] = $jumlah->unique()->values();
                $kulkul['rawMaterials'] = $bahan_baku->unique()->values();
                $kulkul['dimensions'] = $ukuran->unique()->values();
                $kulkul['pengangges'] = $pengangge->unique()->values();
                $kulkul['directions'] = $arah->unique()->values();

                // gambar
                $resultChild = $this->sparql->query('
                    SELECT DISTINCT
                        ?kulkulUrl
                    WHERE {
                        thk:' . $id . ' rdf:type thk:Desa;
                                        thk:hasKulkul ?kulkulName .
                        ?kulkulName thk:hasImageFile ?kulkulImage .
                        ?kulkulImage thk:hasUrl ?kulkulUrl .
                        FILTER REGEX(?kulkulUrl, "files/kulkul/kulkuldesa/images", "i")
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
            }

            $result = $this->sparql->query('
                SELECT 
                    ?banjar
                    (MIN(?kulkulUrl) AS ?image)
                WHERE {
                    ?banjar rdf:type thk:Banjar;
                        thk:isPartOf thk:' . $id . ' .
                    ?parent thk:hasKulkul ?kulkul;
                            thk:isPartOf* ?banjar .  
                    ?kulkul thk:hasImageFile ?kulkulImage .
                    ?kulkulImage thk:hasUrl ?kulkulUrl
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
                        $name->push($item->kulkulLabel->getValue());
                    } else {
                        $name->push($this->parseData($item->kulkulName->getUri()));
                    }

                    // jumlah
                    if (isset($item->jumlah)) {
                        $jumlah->push($item->jumlah->getValue());
                    }

                    // bahan baku
                    if (isset($item->bahan_baku)) {
                        $bahan_baku->push($this->parseData($item->bahan_baku->getUri()));
                    }

                    // ukuran
                    if (isset($item->ukuran)) {
                        $ukuran->push($item->ukuran->getValue());
                    }

                    // penggangge
                    if (isset($item->pengangge)) {
                        $pengangge->push($this->parseData($item->pengangge->getUri()));
                    }

                    // arah
                    if (isset($item->direction)) {
                        $arah->push(isset($item->direction) ? $this->parseData($item->direction->getUri()) : 'Tidak tahu');
                    }
                }

                $kulkul['names'] = $name->unique()->values();
                $kulkul['numbers'] = $jumlah->unique()->values();
                $kulkul['rawMaterials'] = $bahan_baku->unique()->values();
                $kulkul['dimensions'] = $ukuran->unique()->values();
                $kulkul['pengangges'] = $pengangge->unique()->values();
                $kulkul['directions'] = $arah->unique()->values();

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

    // public function showByDesa($id){
    //     try {
    //         // detail kulkul
    //         $result = $this->sparql->query('
    //             SELECT 
    //                 *
    //             WHERE {
    //                 thk:'.$id.' rdf:type thk:Desa;
    //                             thk:hasKulkul ?kulkulName .
    //                 OPTIONAL {
    //                     ?kulkulName rdfs:label ?kulkulLabel
    //                 }
    //             }
    //         ');

    //         $kulkul = null;
    //         if($result->numRows() > 0){
    //             if (isset($result[0]->kulkulLabel)) {
    //                 $kulkul['name'] = $this->parseData($result[0]->kulkulLabel->getValue());
    //             } else {
    //                 $kulkul['name'] = $this->parseData($result[0]->kulkulName->getUri());
    //             }

    //             // jumlah kulkul
    //             $resultChild = $this->sparql->query('
    //                 SELECT DISTINCT
    //                     ?jumlah
    //                 WHERE {
    //                     thk:' . $id . ' rdf:type thk:Desa;
    //                                     thk:hasKulkul ?kulkulName .
    //                     ?kulkulName thk:numberKulkul ?jumlah
    //                 }
    //             ');

    //             $jumlah = [];
    //             if ($resultChild->numRows() > 0) {
    //                 foreach ($resultChild as $item) {
    //                     array_push($jumlah, $item->jumlah->getValue());
    //                 }
    //             }

    //             $kulkul['numbers'] = $jumlah;

    //             // bahan baku 
    //             $resultChild = $this->sparql->query('
    //                 SELECT DISTINCT
    //                     ?bahan_baku
    //                 WHERE {
    //                     thk:' . $id . ' rdf:type thk:Desa;
    //                                     thk:hasKulkul ?kulkulName .
    //                     ?kulkulName thk:hasRawMaterial ?bahan_baku
    //                 }
    //             ');

    //             $bahan_baku = [];
    //             if ($resultChild->numRows() > 0) {
    //                 foreach ($resultChild as $item) {
    //                     array_push($bahan_baku, $this->parseData($item->bahan_baku->getUri()));
    //                 }
    //             }

    //             $kulkul['rawMaterials'] = $bahan_baku;

    //             // ukuran kulkul
    //             $resultChild = $this->sparql->query('
    //                 SELECT DISTINCT
    //                     ?ukuran
    //                 WHERE {
    //                     thk:'.$id.' rdf:type thk:Desa;
    //                                 thk:hasKulkul ?kulkulName .
    //                     ?kulkulName thk:hasDimension ?dimension .
    //                     ?dimension rdfs:label ?ukuran
    //                 }
    //             ');

    //             $ukuran = [];
    //             if ($resultChild->numRows() > 0) {
    //                 foreach ($resultChild as $item) {
    //                     array_push($ukuran, $item->ukuran->getValue());
    //                 }
    //             }

    //             $kulkul['dimensions'] = $ukuran;

    //             // pengangge kulkul
    //             $resultChild = $this->sparql->query('
    //                 SELECT DISTINCT
    //                     ?pengangge
    //                 WHERE {
    //                     thk:'.$id.' rdf:type thk:Desa;
    //                                 thk:hasKulkul ?kulkulName .
    //                     ?kulkulName thk:hasPengangge ?pengangge
    //                 }
    //             ');

    //             $pengangge = [];
    //             if ($resultChild->numRows() > 0) {
    //                 foreach ($resultChild as $item) {
    //                     array_push($pengangge, $this->parseData($item->pengangge->getUri()));
    //                 }
    //             }

    //             $kulkul['pengangges'] = $pengangge;

    //             // gambar
    //             $resultChild = $this->sparql->query('
    //                 SELECT DISTINCT
    //                     ?kulkulUrl
    //                 WHERE {
    //                     thk:' . $id . ' rdf:type thk:Desa;
    //                                     thk:hasKulkul ?kulkulName .
    //                     ?kulkulName thk:hasImageFile ?kulkulImage .
    //                     ?kulkulImage thk:hasUrl ?kulkulUrl .
    //                     FILTER REGEX(?kulkulUrl, "files/kulkul/kulkuldesa/images", "i")
    //                 }
    //             ');

    //             $images = [];
    //             if ($resultChild->numRows() > 0) {
    //                 foreach ($resultChild as $item) {
    //                     array_push($images, $this->parseUrl($item->kulkulUrl->getValue()));
    //                 }

    //                 $kulkul['image'] = $images[0];
    //             }else{
    //                 $kulkul['image'] = null;
    //             }
    //             $kulkul['imageGallery'] = $images;
    //         }

    //         $result = $this->sparql->query('
    //             SELECT 
    //                 ?banjar
    //                 (MIN(?kulkulUrl) AS ?image)
    //             WHERE {
    //                 ?banjar rdf:type thk:Banjar;
    //                     thk:isPartOf thk:'.$id.' .
    //                 ?parent thk:hasKulkul ?kulkul;
    //                         thk:isPartOf* ?banjar .  
    //                 ?kulkul thk:hasImageFile ?kulkulImage .
    //                 ?kulkulImage thk:hasUrl ?kulkulUrl
    //             }
    //             GROUP BY ?banjar
    //             ORDER BY ?banjar
    //         ');

    //         $data_banjar = [];

    //         foreach ($result as $value) {
    //             $id_banjar = $this->parseData($value->banjar->getUri(), true);
    //             $banjar = $this->parseData($value->banjar->getUri());
    //             $banjar = explode(' ', $banjar);
    //             $banjar[0] = 'Banjar';
    //             $banjar = implode(' ', $banjar);
    //             $image = isset($value->image) ? $this->parseUrl($value->image->getValue()) : '';

    //             array_push($data_banjar, [
    //                 'id'    => $id_banjar,
    //                 'name'  => $banjar,
    //                 'image' => $image
    //             ]);
    //         }

    //         return response()->json([
    //             'status'  => 'success',
    //             'data'    => [
    //                 'kulkul' => $kulkul,
    //                 'banjars' => $data_banjar
    //             ]
    //         ]);
    //     } catch (Throwable $e) {
    //         return response()->json([
    //             'status'  => 'fail',
    //             'message' => $e->getMessage()
    //         ]);
    //     }
    // }

    // public function showByBanjar($id)
    // {
    //     try {
    //         // detail kulkul
    //         $result = $this->sparql->query('
    //             SELECT 
    //                 *
    //             WHERE {
    //                 thk:' . $id . ' rdf:type thk:Desa;
    //                             thk:hasKulkul ?kulkulName .
    //                 OPTIONAL {
    //                     ?kulkulName rdfs:label ?kulkulLabel
    //                 }
    //             }
    //         ');

    //         $kulkul = null;
    //         if ($result->numRows() > 0) {
    //             if (isset($result[0]->kulkulLabel)) {
    //                 $kulkul['name'] = $this->parseData($result[0]->kulkulLabel->getValue());
    //             } else {
    //                 $kulkul['name'] = $this->parseData($result[0]->kulkulName->getUri());
    //             }

    //             // jumlah kulkul
    //             $resultChild = $this->sparql->query('
    //                 SELECT DISTINCT
    //                     ?jumlah
    //                 WHERE {
    //                     thk:' . $id . ' rdf:type thk:Desa;
    //                                     thk:hasKulkul ?kulkulName .
    //                     ?kulkulName thk:numberKulkul ?jumlah
    //                 }
    //             ');

    //             $jumlah = [];
    //             if ($resultChild->numRows() > 0) {
    //                 foreach ($resultChild as $item) {
    //                     array_push($jumlah, $item->jumlah->getValue());
    //                 }
    //             }

    //             $kulkul['numbers'] = $jumlah;

    //             // bahan baku 
    //             $resultChild = $this->sparql->query('
    //                 SELECT DISTINCT
    //                     ?bahan_baku
    //                 WHERE {
    //                     thk:' . $id . ' rdf:type thk:Desa;
    //                                     thk:hasKulkul ?kulkulName .
    //                     ?kulkulName thk:hasRawMaterial ?bahan_baku
    //                 }
    //             ');

    //             $bahan_baku = [];
    //             if ($resultChild->numRows() > 0) {
    //                 foreach ($resultChild as $item) {
    //                     array_push($bahan_baku, $this->parseData($item->bahan_baku->getUri()));
    //                 }
    //             }

    //             $kulkul['rawMaterials'] = $bahan_baku;

    //             // ukuran kulkul
    //             $resultChild = $this->sparql->query('
    //                 SELECT DISTINCT
    //                     ?ukuran
    //                 WHERE {
    //                     thk:' . $id . ' rdf:type thk:Desa;
    //                                 thk:hasKulkul ?kulkulName .
    //                     ?kulkulName thk:hasDimension ?dimension .
    //                     ?dimension rdfs:label ?ukuran
    //                 }
    //             ');

    //             $ukuran = [];
    //             if ($resultChild->numRows() > 0) {
    //                 foreach ($resultChild as $item) {
    //                     array_push($ukuran, $item->ukuran->getValue());
    //                 }
    //             }

    //             $kulkul['dimensions'] = $ukuran;

    //             // pengangge kulkul
    //             $resultChild = $this->sparql->query('
    //                 SELECT DISTINCT
    //                     ?pengangge
    //                 WHERE {
    //                     thk:' . $id . ' rdf:type thk:Desa;
    //                                 thk:hasKulkul ?kulkulName .
    //                     ?kulkulName thk:hasPengangge ?pengangge
    //                 }
    //             ');

    //             $pengangge = [];
    //             if ($resultChild->numRows() > 0) {
    //                 foreach ($resultChild as $item) {
    //                     array_push($pengangge, $this->parseData($item->pengangge->getUri()));
    //                 }
    //             }

    //             $kulkul['pengangges'] = $pengangge;

    //             // gambar
    //             $resultChild = $this->sparql->query('
    //                 SELECT DISTINCT
    //                     ?kulkulUrl
    //                 WHERE {
    //                     thk:' . $id . ' rdf:type thk:Desa;
    //                                     thk:hasKulkul ?kulkulName .
    //                     ?kulkulName thk:hasImageFile ?kulkulImage .
    //                     ?kulkulImage thk:hasUrl ?kulkulUrl .
    //                     FILTER REGEX(?kulkulUrl, "files/kulkul/kulkuldesa/images", "i")
    //                 }
    //             ');

    //             $images = [];
    //             if ($resultChild->numRows() > 0) {
    //                 foreach ($resultChild as $item) {
    //                     array_push($images, $this->parseUrl($item->kulkulUrl->getValue()));
    //                 }

    //                 $kulkul['image'] = $images[0];
    //             } else {
    //                 $kulkul['image'] = null;
    //             }
    //             $kulkul['imageGallery'] = $images;
    //         }

    //         $result = $this->sparql->query('
    //             SELECT 
    //                 ?banjar
    //                 (MIN(?kulkulUrl) AS ?image)
    //             WHERE {
    //                 ?banjar rdf:type thk:Banjar;
    //                     thk:isPartOf thk:' . $id . ' .
    //                 ?parent thk:hasKulkul ?kulkul;
    //                         thk:isPartOf* ?banjar .  
    //                 ?kulkul thk:hasImageFile ?kulkulImage .
    //                 ?kulkulImage thk:hasUrl ?kulkulUrl
    //             }
    //             GROUP BY ?banjar
    //             ORDER BY ?banjar
    //         ');

    //         $data_banjar = [];

    //         foreach ($result as $value) {
    //             $id_banjar = $this->parseData($value->banjar->getUri(), true);
    //             $banjar = $this->parseData($value->banjar->getUri());
    //             $banjar = explode(' ', $banjar);
    //             $banjar[0] = 'Banjar';
    //             $banjar = implode(' ', $banjar);
    //             $image = isset($value->image) ? $this->parseUrl($value->image->getValue()) : '';

    //             array_push($data_banjar, [
    //                 'id'    => $id_banjar,
    //                 'name'  => $banjar,
    //                 'image' => $image
    //             ]);
    //         }

    //         return response()->json([
    //             'status'  => 'success',
    //             'data'    => [
    //                 'kulkul' => $kulkul,
    //                 'banjars' => $data_banjar
    //             ]
    //         ]);
    //     } catch (Throwable $e) {
    //         return response()->json([
    //             'status'  => 'fail',
    //             'message' => $e->getMessage()
    //         ]);
    //     }
    // }
}
