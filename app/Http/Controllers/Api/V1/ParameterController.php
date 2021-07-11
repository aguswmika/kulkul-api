<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ParameterController extends Controller
{
    public function index(){
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
                    'id'    => 'lokasi',
                    'value' => 'Lokasi'
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
                        'category' => 'lokasi',
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
                    $id = $data->soundlabel->getValue();
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
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function indexKabupaten(){
        try {
            $result = $this->sparql->query('
                SELECT ?kabupaten
                WHERE {
                    ?kabupaten rdf:type thk:Kabupaten
                } 
                ORDER BY ?kabupaten
            ');

            $data = [];

            if ($result->numRows() > 0) {
                foreach ($result as $item) {
                    $uri = $item->kabupaten->getUri();

                    $id = $this->parseData($uri, true);
                    $value = $this->parseData($uri);

                    array_push($data, [
                        'id' => $id,
                        'value' => $value
                    ]);
                }
            }

            return response()->json([
                'status'  => 'success',
                'data'    => $data
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function indexKecamatan($id)
    {
        try {
            $result = $this->sparql->query('
                SELECT ?kecamatan
                WHERE {
                    ?kecamatan rdf:type thk:Kecamatan;
                                thk:isPartOf thk:'.$id.'
                } 
                ORDER BY ?kecamatan
            ');

            $data = [];

            if ($result->numRows() > 0) {
                foreach ($result as $item) {
                    $uri = $item->kecamatan->getUri();

                    $id = $this->parseData($uri, true);
                    $value = str_replace('Kec', 'Kecamatan', $this->parseData($uri));

                    array_push($data, [
                        'id' => $id,
                        'value' => $value
                    ]);
                }
            }

            return response()->json([
                'status'  => 'success',
                'data'    => $data
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function indexDesa(Request $request, $id)
    {
        $validator = Validator ::make($request->all(), [
            'keyword'    => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'    => 'validator',
                'message'   => $validator->errors()->all()
            ]);
        }
        try {
            $result = $this->sparql->query('
                SELECT DISTINCT * {
                    ?desa rdf:type thk:Desa;
                        thk:isPartOf thk:'.$id.';
                        rdfs:label ?desaLabel.
                }
                ORDER BY ?desa
            ');

            $data = [];

            if ($result->numRows() > 0) {
                foreach ($result as $item) {
                    $uri = $item->kecamatan->getUri();

                    $id = $this->parseData($uri, true);
                    $value = str_replace('Kec', 'Kecamatan', $this->parseData($uri));

                    array_push($data, [
                        'id' => $id,
                        'value' => $value
                    ]);
                }
            }

            return response()->json([
                'status'  => 'success',
                'data'    => $data
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function indexDimension()
    {
        try {
            $result = $this->sparql->query('
                SELECT DISTINCT ?ukuran ?labelukuran {
                    ?ukuran a thk:DimensiKulkul;
                            rdfs:label ?labelukuran
                }
                ORDER BY ?ukuran
            ');

            $data = [];

            if ($result->numRows() > 0) {
                foreach ($result as $item) {
                    $uri = $item->ukuran->getUri();

                    $id = $this->parseData($uri, true);
                    $value = $item->labelukuran->getValue();

                    array_push($data, [
                        'id'    => $id,
                        'value' => $value
                    ]);
                }
            }

            return response()->json([
                'status'  => 'success',
                'data'    => $data
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function indexDirection()
    {
        try {
            $result = $this->sparql->query('
                SELECT DISTINCT ?direction {
                    ?kulkul thk:hasDirection ?direction .
                }
                ORDER BY ?direction
            ');

            $data = [];

            if ($result->numRows() > 0) {
                foreach ($result as $item) {
                    $uri = $item->direction->getUri();

                    $id = $this->parseData($uri, true);
                    $value = $this->parseData($uri);

                    array_push($data, [
                        'id'    => $id,
                        'value' => $value
                    ]);
                }
            }

            return response()->json([
                'status'  => 'success',
                'data'    => $data
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function indexPengangge(Request $request){
        try {
            $result = $this->sparql->query('
                SELECT DISTINCT ?pengangge {
                    ?kulkul thk:hasPengangge ?pengangge .
                    FILTER REGEX(str(?pengangge), "'.str_replace(' ', '', $request->keyword).'", "i")
                }
                ORDER BY ?pengangge
            ');

            $data = [];

            if ($result->numRows() > 0) {
                foreach ($result as $item) {
                    $uri = $item->pengangge->getUri();

                    $id = $this->parseData($uri, true);
                    $value =  $this->parseData($uri);

                    array_push($data, [
                        'id'    => $id,
                        'value' => $value
                    ]);
                }
            }

            return response()->json([
                'status'  => 'success',
                'data'    => $data
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function indexActivity(){
        try {
            $result = $this->sparql->query('
                SELECT DISTINCT ?activity {
                    ?activity rdfs:subClassOf thk:Keadaan .
                }
            ');

            $data = [];

            if ($result->numRows() > 0) {
                foreach ($result as $item) {
                    $uri = $item->activity->getUri();

                    $id = $this->parseData($uri, true);
                    $value =  $this->parseData($uri);

                    array_push($data, [
                        'id'    => $id,
                        'value' => $value
                    ]);

                    $resultChild = $this->sparql->query('
                        SELECT DISTINCT ?aktivitas {
                            ?aktivitas rdfs:subClassOf thk:' . $id . ' .
                        }
                        ORDER BY ?aktivitas
                    ');

                    if ($resultChild->numRows() > 0) {
                        foreach ($resultChild as $dataChild) {
                            $uriChild = $dataChild->aktivitas->getUri();
                            $idChild = $this->parseData($uriChild, true);

                            $populate = [
                                'id'    => $idChild,
                                'value' => $this->parseData($uriChild)
                            ];

                            array_push($data, $populate);
                        }
                    }

                }
            }

            return response()->json([
                'status'  => 'success',
                'data'    => $data
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage()
            ]);
        }
    }
}
