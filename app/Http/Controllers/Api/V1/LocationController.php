<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Throwable;

class LocationController extends Controller
{
    public function index(){
        try {
            $result = $this->sparql->query('
                SELECT ?kabupaten
                WHERE {
                    ?kabupaten rdf:type thk:Kabupaten
                } 
                ORDER BY ?kabupaten
            ');

            $data = [];

            foreach ($result as $item) {
                $id = $this->parseData($item->kabupaten->getUri(), true);
                $kabupaten = $this->parseData($item->kabupaten->getUri());

                $result_kecamatan = $this->sparql->query('
                    SELECT 
                        ?kecamatan
                        (MIN(?kulkulUrl) AS ?image)
                    WHERE {
                        ?kecamatan rdf:type thk:Kecamatan;
                                    thk:isPartOf thk:'.$id. ' .
                        ?parent thk:hasKulkul ?kulkul;
                                thk:isPartOf* ?kecamatan .  
                        ?kulkul thk:hasImageFile ?kulkulImage .
                        ?kulkulImage thk:hasUrl ?kulkulUrl
                    }
                    GROUP BY ?kecamatan
                    ORDER BY ?kecamatan
                ');

                $data_kecamatan = [];

                foreach ($result_kecamatan as $value) {
                    $id_kecamatan = $this->parseData($value->kecamatan->getUri(), true);
                    $kecamatan = $this->parseData($value->kecamatan->getUri());
                    $kecamatan = explode(' ', $kecamatan);
                    $kecamatan[0] = 'Kecamatan';
                    $kecamatan = implode(' ', $kecamatan);
                    $image = isset($value->image) ? $this->parseUrl($value->image->getValue()) : '';

                    array_push($data_kecamatan, [
                        'id'    => $id_kecamatan,
                        'name'  => $kecamatan,
                        'image' => $image
                    ]);
                }

                array_push($data, [
                    'id'    => $id,
                    'name'  => $kabupaten,
                    'kecamatan' => $data_kecamatan
                ]);
            }

            return response()->json([
                'status'  => 'success',
                'data'    => $data
            ]);
        }catch(Throwable $e){
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function indexDesa($id)
    {
        try {
            $result = $this->sparql->query('
                SELECT 
                    ?desa
                    (MIN(?kulkulUrl) AS ?image)
                WHERE {
                    ?desa rdf:type thk:Desa;
                        thk:isPartOf thk:'.$id. ' .
                    ?parent thk:hasKulkul ?kulkul;
                            thk:isPartOf* ?desa .  
                    ?kulkul thk:hasImageFile ?kulkulImage .
                    ?kulkulImage thk:hasUrl ?kulkulUrl
                }
                GROUP BY ?desa
                ORDER BY ?desa
            ');

            $data = [];

            foreach ($result as $item) {
                $id_desa = $this->parseData($item->desa->getUri(), true);
                $desa = $this->parseData($item->desa->getUri());
                $image = isset($item->image) ? $this->parseUrl($item->image->getValue()) : '';

                array_push($data, [
                    'id'    => $id_desa,
                    'name'  => $desa,
                    'image' => $image
                ]);
            }

            return response()->json([
                'status'  => 'success',
                'data'    => $data
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function indexBanjar($id){
        try {
            $result = $this->sparql->query('
                SELECT 
                    ?kulkulNameOnly 
                    ?jumlahkulkul 
                    ?dimensionLabel 
                    ?rawMaterial 
                    ?pengangge 
                    ?direction
                WHERE {
                    thk:'.$id.' rdf:type thk:Desa .
                    thk:'.$id.' thk:hasKulkul ?kulkulName .
                    OPTIONAL {
                        ?kulkulName rdfs:label ?kulkulNameOnly
                    }
                    OPTIONAL {
                        ?kulkulName thk:numberKulkul ?jumlahkulkul
                    }
                    OPTIONAL {	
                        ?kulkulName thk:hasDimension ?dimension .
                        ?dimension rdfs:label ?dimensionLabel
                    }
                    OPTIONAL {
                        ?kulkulName thk:hasRawMaterial ?rawMaterial
                    }
                    OPTIONAL {
                        ?kulkulName thk:hasPengangge ?pengangge
                    }
                    OPTIONAL { 
                        ?kulkulName thk:hasDirection ?direction
                    }
                }
            ');

            $data = [];

            foreach ($result as $item) {
                $id_desa = $this->parseData($item->desa->getUri(), true);
                $desa = $this->parseData($item->desa->getUri());
                $image = isset($item->image) ? $this->parseUrl($item->image->getValue()) : '';

                array_push($data, [
                    'id'    => $id_desa,
                    'name'  => $desa,
                    'image' => $image
                ]);
            }

            return response()->json([
                'status'  => 'success',
                'data'    => $data
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function indexById($type, $id){
		if($type === "jumlah"){
			$parameter = "?kulkul thk:numberKulkul $id .";
        }else if ($type === "ukuran"){
			$parameter = "?kulkul thk:hasDimension thk:$id .";
        }else if ($type === "bahan_baku"){
			$parameter = "?kulkul thk:hasRawMaterial thk:$id .";
        }else if ($type === "pengangge"){
			$parameter = "?kulkul thk:hasPengangge thk:$id .";
        }else if ($type === "aktivitas"){
			$parameter = "?kulkul thk:isUsedFor thk:$id .";
        }else if ($type === "suara"){
			$parameter = "?kulkul thk:hasSound ?sound .
						?sound rdfs:label ?label .
						?sound thk:isSoundFor ?activity .
						?kulkul thk:isUsedFor ?activity .
						?lokasi thk:hasActivity ?activity .
						FILTER (CONTAINS (?label, '$id'))";
        }else if ($type === "arah"){
            if($id === 'TidakTahu'){
                $parameter = "?kulkul thk:hasDirection thk: .";
            }else{
                $parameter = "?kulkul thk:hasDirection thk:$id .";
            }
        }else if ($type === "tipe_suara"){
			$parameter = "?kulkul thk:hasSoundFile ?soundFile .
					?soundFile thk:hasUrl ?soundUrl .
					?soundFile thk:hasResourceType thk:$id .";
        }else{
            $parameter = "?lokasi rdf:type thk:$id .";
        }

        try {
            $result = $this->sparql->query('
                SELECT DISTINCT 
                    ?kabupaten 
                    ?kelompok 
                    ?lokasi
				WHERE {
                    '.$parameter. '
					?lokasi thk:hasKulkul ?kulkul .
					?lokasi rdf:type ?kelompok .
					FILTER (?kelompok NOT IN (owl:NamedIndividual)) .
					?lokasi thk:isPartOf* ?kabupaten .
					?kabupaten rdf:type thk:Kabupaten . 
                }
                ORDER BY ?kabupaten asc (?kelompok) asc (?lokasi)
            ');

            $data = [];

            foreach ($result as $item) {
                $id_location    = $this->parseData($item->lokasi->getUri(), true);
                $location       = $this->parseData($item->lokasi->getUri());
                $group          = $this->parseData($item->kelompok->getUri(), true);
                $group          = $group === 'Desa' ? 'Desa' : ($group === 'Banjar' ? 'Banjar' : 'Pura');
                $kabupaten      = $this->parseData($item->kabupaten->getUri());
                // $image = isset($item->image) ? $this->parseUrl($item->image->getValue()) : '';

                $data[$group][$kabupaten][] = [ 
                    'id'           => $id_location,
                    'value'        => $location,
                    'type'         => $group,
                ];

                // dd($item);
            }

            return response()->json([
                'status'  => 'success',
                'data'    => $data
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage()
            ]);
        }
    }
}
