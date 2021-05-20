<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Throwable;

class CategoryController extends Controller
{
    function index()
    {
        try {
            $category = [
                ['id' => 'aktivitas', 'nama' => 'Aktivitas', 'child' => []],
                ['id' => 'ukuran', 'nama' => 'Ukuran', 'child' => []],
                ['id' => 'lokasi', 'nama' => 'Lokasi', 'child' => []],
                ['id' => 'jumlah', 'nama' => 'Jumlah', 'child' => []],
                ['id' => 'pengangge', 'nama' => 'Pengangge', 'child' => []],
                ['id' => 'arah', 'nama' => 'Arah', 'child' => []],
                ['id' => 'bahan_baku', 'nama' => 'Bahan Baku', 'child' => []],
                ['id' => 'suara', 'nama' => 'Suara', 'child' => []],
                ['id' => 'tipe_suara', 'nama' => 'Tipe Suara', 'child' => []],
            ];
            
            // aktivitas
            $result = $this->sparql->query("
                SELECT DISTINCT ?activity {
                    ?activity rdfs:subClassOf thk:Keadaan .
                }
            ");
            if ($result->numRows() > 0) {
                foreach ($result as $data) {
                    $uri = $data->activity->getUri();

                    $id = $this->parseData($uri, true);

                    $populate = [
                        'id'    => $id,
                        'nama' => $this->parseData($uri),
                        'child' => []
                    ];
                    array_push($category[0]['child'], $populate);
                }

                foreach($category[0]['child'] as $key => $data){
                    $resultChild = $this->sparql->query('
                        SELECT DISTINCT ?aktivitas {
                            ?aktivitas rdfs:subClassOf thk:' . $data['id'] . ' .
                        }
                        ORDER BY ?aktivitas
                    ');

                    if ($resultChild->numRows() > 0) {
                        foreach ($resultChild as $dataChild) {
                            $uriChild = $dataChild->aktivitas->getUri();

                            $idChild = $this->parseData($uriChild, true);
                            $populate = [
                                'id'    => $idChild,
                                'nama' => $this->parseData($uriChild)
                            ];

                            array_push($category[0]['child'][$key]['child'], $populate);
                        }
                    }
                }
            }

            // ukuran
            $result = $this->sparql->query("
                SELECT DISTINCT ?ukuran ?labelukuran {
                    ?ukuran a thk:DimensiKulkul ;
                    rdfs:label ?labelukuran
                }
                ORDER BY ?ukuran
            ");

            if ($result->numRows() > 0) {
                foreach ($result as $data) {
                    $id = $this->parseData($data->ukuran->getUri(), true);
                    $populate = [
                        'id'    => $id,
                        'nama' => $data->labelukuran->getValue()
                    ];
                    array_push($category[1]['child'], $populate);
                }
            }

            // lokasi
            $result = $this->sparql->query("
                SELECT DISTINCT ?lokasi { 
                    ?lokasi rdfs:subClassOf+ thk:Tempat .
					FILTER (?lokasi IN (thk:Banjar, thk:Desa, thk:PuraPuseh, thk:PuraDalem, thk:PuraDesa)) .
				}
				ORDER BY ?lokasi
            ");

            if ($result->numRows() > 0) {
                foreach ($result as $data) {
                    $uri = $data->lokasi->getUri();

                    $id = $this->parseData($uri, true);

                    $populate = [
                        'id'    => $id,
                        'nama' => $this->parseData($uri)
                    ];
                    array_push($category[2]['child'], $populate);
                }
            }

            // jumlah
            $result = $this->sparql->query("
                SELECT DISTINCT ?number { 
                    ?kulkul thk:numberKulkul ?number .
                }
                ORDER BY ?number
            ");

            if ($result->numRows() > 0) {
                foreach ($result as $data) {
                    $value = $data->number->getValue();

                    $populate = [
                        'id'    => (String) $value,
                        'nama' => (String) $value
                    ];
                    array_push($category[3]['child'], $populate);
                }
            }

            // pengangge
            $result = $this->sparql->query("
                SELECT DISTINCT ?pengangge {
                    ?kulkul thk:hasPengangge ?pengangge .
                }
				ORDER BY ?pengangge
            ");

            if ($result->numRows() > 0) {
                foreach ($result as $data) {
                    $uri = $data->pengangge->getUri();

                    $id = $this->parseData($uri, true);

                    $populate = [
                        'id'    => $id,
                        'nama' => $this->parseData($uri)
                    ];
                    array_push($category[4]['child'], $populate);
                }
            }

            // arah
            $result = $this->sparql->query("
                SELECT DISTINCT ?direction {
                    ?kulkul thk:hasDirection ?direction .
                }
                ORDER BY ?direction
            ");

            if ($result->numRows() > 0) {
                foreach ($result as $data) {
                    $uri = $data->direction->getUri();

                    $id = $this->parseData($uri, true);

                    $populate = [
                        'id'    => $id,
                        'nama' => $this->parseData($uri)
                    ];
                    array_push($category[5]['child'], $populate);
                }
            }

            // bahan baku
            $result = $this->sparql->query("
                SELECT DISTINCT ?rawMaterial {
                    ?kulkul thk:hasRawMaterial ?rawMaterial . 
                }
                ORDER BY ?rawMaterial
            ");

            if ($result->numRows() > 0) {
                foreach ($result as $data) {
                    $uri = $data->rawMaterial->getUri();

                    $id = $this->parseData($uri, true);

                    $populate = [
                        'id'    => $id,
                        'nama' => $this->parseData($uri)
                    ];
                    array_push($category[6]['child'], $populate);
                }
            }

            // suara
            $result = $this->sparql->query("
                SELECT DISTINCT ?soundlabel {
                    ?kulkul thk:hasSound ?sound .
                    ?sound rdfs:label ?soundlabel . 
                }
                ORDER BY ?soundlabel 
            ");

            if ($result->numRows() > 0) {
                foreach ($result as $data) {
                    $value = $data->soundlabel->getValue();

                    $populate = [
                        'id'    => $value,
                        'nama' => $value
                    ];
                    array_push($category[7]['child'], $populate);
                }
            }

            // tipe_suara
            $result = $this->sparql->query("
                SELECT DISTINCT ?resourceType {
                    ?soundFile thk:hasResourceType ?resourceType .
                }
                ORDER BY ?resourceType
            ");

            if ($result->numRows() > 0) {
                foreach ($result as $data) {
                    $uri = $data->resourceType->getUri();

                    $id = $this->parseData($uri, true);

                    $populate = [
                        'id'    => $id,
                        'nama' => $this->parseData($uri)
                    ];
                    array_push($category[8]['child'], $populate);
                }
            }

            return response()->json([
                'status'  => 'success',
                'data'    => $category
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage().$e->getLine()
            ]);
        }
    }
}
