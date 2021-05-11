<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use EasyRdf\Exception;

class ParameterController extends Controller
{
    function index(){
        try {
            $output = [
                [
                    'id'    => 'arah',
                    'value' => 'Arah'
                ],
                [
                    'id'    => 'aktivitas',
                    'value' => 'Aktivitas'
                ],
                [
                    'id'    => 'jumlah',
                    'value' => 'Jumlah'
                ],
                [
                    'id'    => 'suara',
                    'value' => 'Suara'
                ],
                [
                    'id'    => 'ukuran',
                    'value' => 'Ukuran'
                ],
                [
                    'id'    => 'tempat',
                    'value' => 'Tempat'
                ],
                [
                    'id'    => 'pengangge',
                    'value' => 'Pengangge'
                ],
                [
                    'id'    => 'bahan_baku',
                    'value' => 'Bahan Baku'
                ],
                [
                    'id'    => 'tipe_suara',
                    'value' => 'Tipe Suara'
                ]
            ];
            $filter = [];


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
                        'category' => 'aktivitas',
                        'value' => $this->parseData($uri)
                    ];
                    array_push($filter, $populate);

                    $resultChild = $this->sparql->query('
                        SELECT DISTINCT ?aktivitas {
                            ?aktivitas rdfs:subClassOf thk:'.$id.' .
                        }
                        ORDER BY ?aktivitas
                    ');

                    if ($resultChild->numRows() > 0) {
                        foreach ($resultChild as $dataChild) {
                            $uriChild = $dataChild->aktivitas->getUri();

                            $idChild = $this->parseData($uriChild, true);
                            $populate = [
                                'id'    => $idChild,
                                'category' => 'aktivitas',
                                'value' => $this->parseData($uriChild)
                            ];

                            array_push($filter, $populate);
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
                    $uri = $data->ukuran->getUri();
                    $id = $this->parseData($uri, true);
                    $populate = [
                        'id'    => $id,
                        'category' => 'ukuran',
                        'value' => $data->labelukuran->getValue()
                    ];
                    array_push($filter, $populate);
                }
            }

            // tempat
            $result = $this->sparql->query("
                SELECT DISTINCT ?tempat { 
                    ?tempat rdfs:subClassOf+ thk:Tempat .
					FILTER (?tempat IN (thk:Banjar, thk:Desa, thk:PuraPuseh, thk:PuraDalem, thk:PuraDesa)) .
				}
				ORDER BY ?tempat
            ");

            if ($result->numRows() > 0) {
                foreach ($result as $data) {
                    $uri = $data->tempat->getUri();

                    $id = $this->parseData($uri, true);

                    $populate = [
                        'id'    => $id,
                        'category' => 'tempat',
                        'value' => $this->parseData($uri)
                    ];
                    array_push($filter, $populate);
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
                    $value =  (string) $data->number->getValue();

                    $populate = [
                        'id'    => $value,
                        'category' => 'jumlah',
                        'value' => $value
                    ];
                    array_push($filter, $populate);
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
                        'category' => 'pengangge',
                        'value' => $this->parseData($uri)
                    ];
                    array_push($filter, $populate);
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
                        'category' => 'arah',
                        'value' => $this->parseData($uri)
                    ];
                    array_push($filter, $populate);
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
                        'category' => 'bahan_baku',
                        'value' => $this->parseData($uri)
                    ];
                    array_push($filter, $populate);
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
                    $id = str_replace(' ', '', $data->soundlabel->getValue());
                    $value = $data->soundlabel->getValue();

                    $populate = [
                        'id'    => $id,
                        'category' => 'suara',
                        'value' => $value
                    ];
                    array_push($filter, $populate);
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
                        'category' => 'tipe_suara',
                        'value' => $this->parseData($uri)
                    ];
                    array_push($filter, $populate);
                }
            }

            return response()->json([
                'status'  => 'success',
                'data'    => [
                    'output' => $output,
                    'filter' => $filter
                ]
            ]);
        } catch (Exception | \Exception $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage()
            ]);
        }
    }
}
