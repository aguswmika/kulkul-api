<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use EasyRdf\Exception;
use EasyRdf\Graph;
use EasyRdf\Parser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'output'    => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'    => 'validator',
                'message'   => $validator->errors()->all()
            ]);
        }

        try {
            $query_output = $this->findQueryOutput($request->output);

            $query_filter = $this->findQueryFilter($request->filter);

            $genered_query = '
                SELECT DISTINCT (?' . $request->output . ' as ?output) ?ukuranKey
                {
                    ' . $query_output['arah'] . '
                    ' . $query_output['aktivitas'] . '
                    ' . $query_output['jumlah'] . '
                    ' . $query_output['suara'] . '
                    ' . $query_output['ukuran'] . '
                    ' . $query_output['pengangge'] . '
                    ' . $query_output['bahan_baku'] . '
                    ' . $query_output['tipe_suara'] . '

                    ' . $query_filter['lokasi'] . '
                    ' . $query_filter['jumlah'] . '
                    ' . $query_filter['ukuran'] . '
                    ' . $query_filter['pengangge'] . '
                    ' . $query_filter['aktivitas'] . '
                    ' . $query_filter['arah'] . '
                    ' . $query_filter['bahan_baku'] . '
                    ' . $query_filter['suara'] . '
                    ' . $query_filter['tipe_suara'] . '
                } ORDER BY ?output
            ';
            $result = $this->sparql->query($genered_query);

            $datas = [];
            $totalData = $result->numRows();
            if($totalData > 0){
                foreach($result as $data){
                    if(property_exists($data, 'output')){
                        if(method_exists($data->output, 'getUri')){
                            $uri = $data->output->getUri();

                            if ($request->output === 'lokasi') {
                                $id = $this->parseData($this->parsePura($uri), true);
                            } else {
                                $id = $this->parseData($uri, true);
                            }
                            
                            $output = [
                                'id'    => (string) $id,
                                'value' => (string) $this->parseData($uri)
                            ];
                        }else{
                            if($request->output === 'ukuran'){
                                $id = $this->parseData($data->ukuranKey->getUri(), true);
                            }else{
                                $id = $data->output->getValue();
                            }

                            $value = $data->output->getValue();
                            
                            $output = [
                                'id'    => (string) $id,
                                'value' => (string) $value
                            ];
                        }
                        array_push($datas, $output);
                    }else{
                        $totalData = 0;
                    }
                }
            }

            return response()->json([
                'status'  => 'success',
                'data'    => $datas,
                'total'   => $totalData,
                'query'   => trim(preg_replace('/\s\s+/', ' ', $genered_query))
            ]);
        } catch (Exception | \Exception $e) {
            return response()->json([
                'status'  => 'fail',
                'message' => $e->getMessage().' '.$e->getLine()
            ]);
        }        
    }

    function findQueryOutput($output){
        $query = [
            'arah'       => '',
            'aktivitas'  => '',
            'jumlah'     => '',
            'suara'      => '',
            'ukuran'     => '',
            'pengangge'  => '',
            'bahan_baku' => '',
            'tipe_suara' => ''
        ];

        if($output === 'arah'){
            $query['arah'] = '?kulkulName thk:hasDirection ?arah .
                            ?lokasi thk:hasKulkul ?kulkulName .';
        }
        if ($output === 'aktivitas'){
            $query['aktivitas'] = '?kulkulName thk:isUsedFor ?aktivitas .
                            ?aktivitas a ?groupactivity .
                            ?lokasi thk:hasKulkul ?kulkulName .';
        }
        if ($output === 'jumlah'){
            $query['jumlah'] = '?kulkulName thk:numberKulkul ?jumlah .
                            ?lokasi thk:hasKulkul ?kulkulName .';
        }
        if ($output === 'suara'){
            $query['suara'] = '?kulkulName thk:hasSound ?sound01 .
                            ?sound01 rdfs:label ?suara .
                            ?lokasi thk:hasKulkul ?kulkulName .
                            ?sound01 thk:isSoundFor ?aktivitas .';
        }
        if ($output === 'ukuran'){
            $query['ukuran'] = '?kulkulName thk:hasDimension ?ukuranKey .
                            ?ukuranKey rdfs:label ?ukuran .
                            ?lokasi thk:hasKulkul ?kulkulName .';
        }
        if ($output === 'pengangge'){
            $query['pengangge'] = '?kulkulName thk:hasPengangge ?pengangge .
                                ?lokasi thk:hasKulkul ?kulkulName .';
        }
        if ($output === 'bahan_baku'){
            $query['bahan_baku'] = '?kulkulName thk:hasRawMaterial ?bahan_baku .
                                ?lokasi thk:hasKulkul ?kulkulName .';
        }
        if ($output === 'tipe_suara'){
            $query['tipe_suara'] = '?kulkulName thk:hasSound ?sound01 .
                                ?sound01 thk:hasSoundFile ?soundFile .
                                        ?kulkulName thk:hasSoundFile ?soundFile .
                                        ?soundFile thk:hasUrl ?soundUrl .
                                        ?soundFile thk:hasResourceType ?tipe_suara .
                                ?lokasi thk:hasKulkul ?kulkulName .';
        }

        return $query;
    }

    public function findQueryFilter($filter){
        $query = [
            'arah'       => '',
            'aktivitas'  => '',
            'jumlah'     => '',
            'suara'      => '',
            'ukuran'     => '',
            'pengangge'  => '',
            'bahan_baku' => '',
            'tipe_suara' => '',
            'lokasi'     => ''
        ];

        if (!empty($filter['arah'])) {
            $query['arah'] = '?kulkulName thk:hasDirection thk:' . $filter['arah'] . ' .
                                        ?lokasi thk:hasKulkul ?kulkulName .';
        }
        if (!empty($filter['aktivitas'])) {
            $query['aktivitas'] = '?kulkulName thk:isUsedFor ?aktivitas .
                                        ?aktivitas a thk:' . $filter['aktivitas'] . ' .
                                        ?lokasi thk:hasKulkul ?kulkulName .';
        }
        if (!empty($filter['jumlah'])) {
            $query['jumlah'] = '?kulkulName thk:numberKulkul ' . $filter['jumlah'] . ' .
                                        ?lokasi thk:hasKulkul ?kulkulName .';
        }
        if (!empty($filter['suara'])) {
            $query['suara'] = '?kulkulName thk:hasSound ?sound01 .
                                    ?sound01 rdfs:label ?sound .
                                    ?lokasi thk:hasKulkul ?kulkulName .
                                    ?sound01 thk:isSoundFor ?aktivitas .
                                    FILTER (CONTAINS (?sound, "' . $filter['suara'] . '"))';
        }
        if (!empty($filter['ukuran'])) {
            $query['ukuran'] = '?kulkulName thk:hasDimension thk:' . $filter['ukuran'] . ' .
                                        thk:' . $filter['ukuran'] . ' rdfs:label ?dimension02 .
                                        ?lokasi thk:hasKulkul ?kulkulName .';
        }
        if (!empty($filter['pengangge'])) {
            $query['pengangge'] = '?kulkulName thk:hasPengangge thk:' . $filter['pengangge'] . ' .
                                        ?lokasi thk:hasKulkul ?kulkulName .';
        }
        if (!empty($filter['bahan_baku'])) {
            $query['bahan_baku'] = '?kulkulName thk:hasRawMaterial thk:' . $filter['bahan_baku'] . ' .
                                            ?lokasi thk:hasKulkul ?kulkulName .';
        }
        if (!empty($filter['tipe_suara'])) {
            $query['tipe_suara'] = '?kulkulName thk:hasSound ?sound01 .
                                            ?sound01 thk:hasSoundFile ?soundFile .
                                                    ?kulkulName thk:hasSoundFile ?soundFile .
                                                    ?soundFile thk:hasUrl ?soundUrl .
                                                    ?soundFile thk:hasResourceType thk:' . $filter['tipe_suara'] . ' .
                                            ?lokasi thk:hasKulkul ?kulkulName .';
        }
        if (!empty($filter['lokasi'])) {
            $query['lokasi'] = '?lokasi thk:hasKulkul ?kulkulName .
                                                ?lokasi a thk:' . $filter['lokasi'] . ' .
                                        FILTER (?lokasi NOT IN (owl:NamedIndividual))
                                        ?lokasi thk:isPartOf* ?kabupaten .
                                        ?kabupaten a thk:Kabupaten .';
        }

        return $query;
    }
}
