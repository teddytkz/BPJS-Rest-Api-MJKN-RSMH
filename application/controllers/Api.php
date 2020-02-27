<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH . '/libraries/REST_Controller.php';
// require APPPATH . '/libraries/Format.php';

use Restserver\Libraries\REST_Controller;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST,GET");

class Api extends REST_Controller {
    public function __construct() {
        parent::__construct();
        $this->load->helper(['jwt', 'authorization']); 
    }

    public function token_post(){
        $username=$this->post('username');
        $password=$this->post('password');
        $password=md5($password);
        $this->db->select('*');
        $this->db->from('_bpjs_user_');
        $this->db->where('username',$username);
        $this->db->where('password',$password);
        $uservalid=$this->db->get()->num_rows();
        if($uservalid!=0){
            $user=array(
                'username'=>$username,
                'password'=>$password
            );
            $token = AUTHORIZATION::generateToken($user);
            $status = parent::HTTP_OK;
            $response=array(
                'response'=>array(
                                    'token'=>$token
                            ),
                'metadata'=>array(
                                'message'=>'OK',
                                'code'=>200
                            )

            );
            $this->response($response, 200);
        }
        else{
            $response=array(
                'metadata'=>array(
                                'message'=>'Forbidden',
                                'code'=>403
                            )
            );
            $this->response($response, 403);
        }
    }
    
    public function token_get(){
        $response=array(
                'metadata'=>array(
                                'message'=>'RS. Mitra Husada',
                                'code'=>200
                            )
            );
        $this->response($response, 200);
    }

    public function noantrian_post(){
        $token = $this->input->get_request_header('x-token');
        $verf_token=$this->verifikasi_token($token);
        $nomorkartu=$this->post('nomorkartu');
        $nik=$this->post('nik');
        $notelp=$this->post('notelp');
        $tanggalperiksa=$this->post('tanggalperiksa');
        $kodepoli=$this->post('kodepoli');
        $nomorreferensi=$this->post('nomorreferensi');
        $jenisreferensi=$this->post('jenisreferensi');
        $jenisrequest=$this->post('jenisrequest');
        $polieksekutif=$this->post('polieksekutif');
        $data_rec=array(
            'nomor_kartu'=>$nomorkartu,
            'nik'=>$nik,
            'no_telp'=>$notelp,
            'tanggal_periksa'=>$tanggalperiksa,
            'kode_poli'=>$kodepoli,
            'nomor_ref'=>$nomorreferensi,
            'jenis_referensi'=>$jenisreferensi,
            'jenis_request'=>$jenisrequest,
            'poli_eksekutif'=>$polieksekutif,
            'created_on'=>date('Y-m-d H:i:s')
        );
        $save_data_raw=$this->db->insert('_bpjs_antrian',$data_rec);
        $check_data_bpjs=$this->get_data_bpjs($nomorkartu);
        if($verf_token!=0){
            $check_nomor_kartu=$this->checknomorkartu($nomorkartu);
            if($check_nomor_kartu['metaData']['code']==200){
                $checktanggal=$this->checktanggal($tanggalperiksa);
                if($checktanggal==TRUE){
                    $check_hari_kunjungan=FALSE;
                    if($jenisreferensi==1){
                        $check_hari_kunjungan=$this->check_hari_kunjungan($tanggalperiksa,$nomorreferensi);
                    }else if($jenisreferensi==0){
                        $check_hari_kunjungan=TRUE;
                    }else{
                        $check_hari_kunjungan=FALSE;
                        $response=array(
                                        'metadata'=>array(
                                                    'message'=>'Jenis Referensi Tidak di Ketahui',
                                                    'code'=>400
                                            )
                                       );
                        $this->response($response, 400); 
                    }
                    if($check_hari_kunjungan==TRUE){
                        $convert_poli=$this->convert_poli($kodepoli);
                        if(!empty($convert_poli)){
                            $poli_tujuan=$convert_poli[0]->id_poli;
                            $checkkuota_get=$this->checkkuota_get($poli_tujuan,$tanggalperiksa);
                            if(!empty($checkkuota_get)){
                                $check_daftar_via_bpjs=$this->check_daftar_via_bpjs($poli_tujuan,$tanggalperiksa);
                                if($checkkuota_get[0]->kuota>$check_daftar_via_bpjs){
                                    $cek_pendaftaran=$this->cek_pendaftaran($nik,$tanggalperiksa);
                                    if($cek_pendaftaran==0){
                                        if($jenisrequest==1){
                                            if($jenisreferensi==1||$jenisreferensi==0){
                                                if($polieksekutif==0){
                                                    $nomor_book='RSMH-'.$poli_tujuan.'-BPJS'.time().rand(100,300);
                                                    $data_save_to_pendaftaran_online=array(
                                                        'nomor_bpjs'=>$nomorkartu,
                                                        'nik'=>$nik,
                                                        'nomortelepon'=>$notelp,
                                                        'rencana_kunjungan'=>$tanggalperiksa,
                                                        'poli_tujuan'=>$poli_tujuan,
                                                        'nomor_referensi'=>$nomorreferensi,
                                                        'jenis_referensi'=>$jenisreferensi,
                                                        'jenis_request'=>$jenisrequest,
                                                        'poli_eksekutif'=>$polieksekutif,
                                                        'status_pasien'=>2,
                                                        'jenis_pembayaran'=>'BPJS',
                                                        'created_on'=>date('Y-m-d H:i:s'),
                                                        'nomor_book'=>$nomor_book,
                                                        
                                                    );
                                                    $this->db->insert('pendaftaran_online',$data_save_to_pendaftaran_online);
                                                    $response=array(
                                                                    'response'=>array(
                                                                                    'nomorantrean'=>'B'.$check_daftar_via_bpjs,
                                                                                    'kodebooking'=>$nomor_book,
                                                                                    'jenisantrean'=>$jenisrequest,
                                                                                    'estimasidilayani'=>strtotime($tanggalperiksa." 08:00:00"),
                                                                                    'namapoli'=>$convert_poli[0]->poli,
                                                                                    'namadokter'=>'',
                                                                                ),
                                                                    'metadata'=>array(
                                                                                    'message'=>'OK',
                                                                                    'code'=>200
                                                                                )
                                                            );
                                                    $this->response($response, 200);
                                                }else if($polieksekutif==1){
                                                    $response=array(
                                                            'metadata'=>array(
                                                                            'message'=>'Tidak Bisa Mendaftar Ke Poli Eksekutif',
                                                                            'code'=>400
                                                                        )
                                                        );
                                                    $this->response($response, 400);
                                                }else{
                                                    $response=array(
                                                            'metadata'=>array(
                                                                            'message'=>'Jenis Poli Tidak Di Kenali',
                                                                            'code'=>400
                                                                        )
                                                        );
                                                    $this->response($response, 400);
                                                }
                                            }else{
                                                $response=array(
                                                            'metadata'=>array(
                                                                            'message'=>'Jenis Referensi Tidak Di Kenali',
                                                                            'code'=>400
                                                                        )
                                                        );
                                                $this->response($response, 400);
                                            }
                                        }
                                        else if($jenisrequest==2){
                                            $response=array(
                                                        'metadata'=>array(
                                                                        'message'=>'Tidak Bisa Langsung Mendaftar ke Poli',
                                                                        'code'=>403
                                                                    )
                                                    );
                                            $this->response($response, 403);
                                        }else{
                                           $response=array(
                                                        'metadata'=>array(
                                                                        'message'=>'Jenis Request Tidak Di kenali',
                                                                        'code'=>400
                                                                    )
                                                    );
                                            $this->response($response, 400); 
                                        }
                                    }else{
                                        $response=array(
                                                    'metadata'=>array(
                                                                'message'=>"Sudah Pernah Mendaftar",
                                                                'code'=>400
                                                        )
                                        );
                                        $this->response($response, 400); 
                                    }
                                }else{
                                    $response=array(
                                                    'metadata'=>array(
                                                                'message'=>"Kuota Habis",
                                                                'code'=>400
                                                        )
                                    );
                                    $this->response($response, 400);
                                }
                            }else{
                                $response=array(
                                    'metadata'=>array(
                                            'message'=>'Poli Tidak Tersedia / Dokter Libur',
                                            'code'=>400
                                    )
                                );
                                $this->response($response, 400);
                            }
                        }else{
                            $response=array(
                                    'metadata'=>array(
                                            'message'=>'Poli Tidak Tersedia',
                                            'code'=>400
                                    )
                            );
                            $this->response($response, 400); 
                        } 
                    }else{
                        $response=array(
                            'metadata'=>array(
                                'message'=>'Rujukan Sudah Lewat 90 Hari',
                                'code'=>400
                            )
                        );
                        $this->response($response, 400);
                    }
                }else{
                    $response=array(
                    'metadata'=>array(
                                'message'=>'Tanggal Tidak Sesuai atau Backdate',
                                'code'=>400
                            )
                    );
                    $this->response($response, 400);
                }
            }else{
                $response=array(
                    'metadata'=>array(
                                'message'=>$check_nomor_kartu['metaData']['message'],
                                'code'=>$check_nomor_kartu['metaData']['code']
                            )
                );
                $this->response($response, 400);
            }
        }else{
            $response=array(
                'metadata'=>array(
                                'message'=>'Forbidden',
                                'code'=>403
                            )
            );
            $this->response($response, 403); 
        }
    }

    public function noantrian_get(){
        $response=array(
                'metadata'=>array(
                                'message'=>'RS. Mitra Husada',
                                'code'=>200
                            )
            );
        $this->response($response, 200);        
    }

    public function rekapantrean_get(){

    }

    public function rekapantrean_post(){
        $token = $this->input->get_request_header('x-token');
        $verf_token=$this->verifikasi_token($token);
        $tanggalperiksa=$this->post('tanggalperiksa');
        $kodepoli=$this->post('kodepoli');
        $polieksekutif=$this->post('polieksekutif');
        if($verf_token!=0){          
            
            $this->db->select('poli.*');
            $this->db->from('poli');
            $this->db->where('kode_poli_bpjs',$kodepoli);
            $poli_tujuan_qry=$this->db->get()->result();
            if(!empty($poli_tujuan_qry)){
                $poli_tujuan=$poli_tujuan_qry[0]->kode_poli_simrs;
                $jumlahdaftarpoli=$this->jumlah_daftar($poli_tujuan,$tanggalperiksa);
                $response=array(
                        'response'=>array(
                                        'namapoli'=>$poli_tujuan_qry[0]->poli,
                                        'totalantrean'=>$jumlahdaftarpoli,
                                        'jumlahterlayani'=>0,
                                        'lastupdate'=>time()
                                    ),
                        'metadata'=>array(
                                        'message'=>'OK',
                                        'code'=>200
                                    )
                );
                $this->response($response, 200);
            }else{
                $response=array(
                            'metadata'=>array(
                                            'message'=>'Poli Tidak Tersedia',
                                            'code'=>403
                                        )
                        );
                $this->response($response, 403); 
            }
        }else{
            $response=array(
                
                'metadata'=>array(
                                'message'=>'Forbidden',
                                'code'=>403
                            )
            );
            $this->response($response, 403);
        }  
    }

    private function check_daftar_via_bpjs($id_poli,$tanggalperiksa){
        $this->db->select('pendaftaran_online.*,dokter.id_poli,jadwal_dokter.id_dokter');
        $this->db->from('pendaftaran_online');
        $this->db->where('dokter.id_poli');
        $this->db->where('rencana_kunjungan',$tanggalperiksa);
        $this->db->join('jadwal_dokter','pendaftaran_online.dokter_tujuan=jadwal_dokter.haripraktek','left');
        $this->db->join('dokter','dokter.id_dokter=jadwal_dokter.id_dokter','left');
        $jumlah_daftar_poli=$this->db->get()->num_rows();
        return $jumlah_daftar_poli;
    }

    private function checktanggal($tanggal){
        if($tanggal==""){
            return FALSE;
        }
        else{
            $tanggal=strtotime($tanggal);
            $tanggal_sekarang=strtotime(date('Y-m-d'));
            if($tanggal>=$tanggal_sekarang){
                return TRUE;
            }
            else{
                return FALSE;
            }
        }
    }


    private function checkkuota_get($id_poli,$tanggal){
        $check_hari=$this->hari($tanggal);
        $check_poli_libur=$this->checkpolilibur_get($tanggal);
        $this->db->select('dokter.id_poli,SUM(kuota) as kuota');
        $this->db->from('jadwal_dokter');
        $this->db->join('dokter','jadwal_dokter.id_dokter=dokter.id_dokter');
        $this->db->where('jadwal_dokter.haripraktek',$check_hari);
        $this->db->where('dokter.id_poli',$id_poli);
        if(sizeof($check_poli_libur)>0){
            $this->db->where_not_in('jadwal_dokter.id_jadwal',$check_poli_libur);
        }
        $this->db->group_by('id_poli');
        $qry=$this->db->get()->result();
        return $qry;
    }

    private function checkpolilibur_get($tanggal){
        $this->db->select('jadwal_libur.*');
        $this->db->from('jadwal_libur');
        $this->db->where('jadwal_libur.tanggal',$tanggal);
        $jadwallibur=$this->db->get()->result();
        $data=array();
        $i=0;
        foreach($jadwallibur as $hasil){
            $data[$i++]=$hasil->id_jadwal;
        }
        return $data;
    }

    private function cek_pendaftaran($nik,$tanggalperiksa){
        $this->db->select('pendaftaran_online.*');
        $this->db->from('pendaftaran_online');
        $this->db->where('nik',$nik);
        $this->db->where('rencana_kunjungan',$tanggalperiksa);
        $qry=$this->db->get()->num_rows();
        return $qry;
    }

    private function jumlah_daftar($id_poli,$tanggal){
        $dbmysql = $this->load->database('sqlserver', true);
        $dbmysql->select('Reg.*');
        $dbmysql->from('Reg');
        $dbmysql->where('Reg.Poli_Id',$id_poli);
        $dbmysql->where('Flag_Batal',0);
        $dbmysql->where("Tgl_masuk BETWEEN '".$tanggal." 00:00:00' AND '".$tanggal." 23:59:00'");
        $jumlah=$dbmysql->get()->num_rows();
        return $jumlah;
    }

    private function verifikasi_token($token){
        $this->db->select('_bpjs_token.token');
        $this->db->from('_bpjs_token');
        $this->db->where('_bpjs_token.token',$token);
        $verf_token=$this->db->get()->num_rows();
        return $verf_token;
    }

    private function check_hari_kunjungan($tanggal_rencana_kunjungan,$nomorrujukan){
        $get_data_rujukan=$this->get_data_rujukan($nomorrujukan);
        $add_90_day=date('Y-m-d',strtotime($get_data_rujukan['response']['rujukan']['tglKunjungan']."+90 day"));
        if($tanggal_rencana_kunjungan<$add_90_day){
            return TRUE;
        }else{
            return FALSE;
        }
    }

    private function convert_poli($idpoli){
        $this->db->select('poli.*');
        $this->db->from('poli');
        $this->db->where('kode_poli_bpjs',$idpoli);
        $poli_tujuan_qry=$this->db->get()->result();
        return $poli_tujuan_qry;
    }

    private function hari($tanggal){
        $day=date('D',strtotime($tanggal));
        $hari_ini=0;
        switch($day){
            case 'Sun':
                $hari_ini = 1;
            break;
    
            case 'Mon':			
                $hari_ini = 2;
            break;
    
            case 'Tue':
                $hari_ini = 3;
            break;
    
            case 'Wed':
                $hari_ini = 4;
            break;
    
            case 'Thu':
                $hari_ini = 5;
            break;
    
            case 'Fri':
                $hari_ini = 6;
            break;
    
            case 'Sat':
                $hari_ini = 7;
            break;
        }
        return $hari_ini;
    }    

    private function get_data_bpjs($nomorkartu){
        $data = "5498";
        $secretKey = "5fBFF7C588";
        $tanggal=date('Y-m-d');
        $url="https://new-api.bpjs-kesehatan.go.id:8080/new-vclaim-rest/Peserta/nokartu/".$nomorkartu."/tglSEP/".$tanggal;
        date_default_timezone_set('UTC');
        $tStamp = strval(time()-strtotime('1970-01-01 00:00:00'));
        $signature = hash_hmac('sha256', $data."&".$tStamp, $secretKey, true);
        $encodedSignature = base64_encode($signature);
        $opts = array(
                    'http'=>array(
                    'method'=>"GET",
                    'header'=>"Host: new-api.bpjs-kesehatan.go.id\r\n".
                    "Connection: close\r\n".
                    "X-cons-id: ".$data."\r\n".
                    "X-timestamp: ".$tStamp."\r\n".
                    "X-signature: ".$encodedSignature."\r\n".
                    "User-Agent: Mozilla/5.0 (Windows NT 6.3; Win64; x64)\r\n".
                    "Accept: application/json;charset=utf-8\r\n"
                    )
        );
        $context = stream_context_create($opts);
        $result = file_get_contents($url, false, $context);
        return json_decode($result,true);
    }

    private function get_data_rujukan($nomorrujukan){
        $data = "5498";
        $secretKey = "5fBFF7C588";
        $tanggal=date('Y-m-d');
        $url="https://new-api.bpjs-kesehatan.go.id:8080/new-vclaim-rest/Rujukan/".$nomorrujukan;
        date_default_timezone_set('UTC');
        $tStamp = strval(time()-strtotime('1970-01-01 00:00:00'));
        $signature = hash_hmac('sha256', $data."&".$tStamp, $secretKey, true);
        $encodedSignature = base64_encode($signature);
        $opts = array(
                    'http'=>array(
                    'method'=>"GET",
                    'header'=>"Host: new-api.bpjs-kesehatan.go.id\r\n".
                    "Connection: close\r\n".
                    "X-cons-id: ".$data."\r\n".
                    "X-timestamp: ".$tStamp."\r\n".
                    "X-signature: ".$encodedSignature."\r\n".
                    "User-Agent: Mozilla/5.0 (Windows NT 6.3; Win64; x64)\r\n".
                    "Accept: application/json;charset=utf-8\r\n"
                    )
        );
        $context = stream_context_create($opts);
        $result = file_get_contents($url, false, $context);
        return json_decode($result,true);
    }

    private function checknomorkartu($nomorkartu){
        $data = "5498";
        $secretKey = "5fBFF7C588";
        $tanggal=date('Y-m-d');
        $url="https://new-api.bpjs-kesehatan.go.id:8080/new-vclaim-rest/Peserta/nokartu/".$nomorkartu."/tglSEP/".$tanggal;
        date_default_timezone_set('UTC');
        $tStamp = strval(time()-strtotime('1970-01-01 00:00:00'));
        $signature = hash_hmac('sha256', $data."&".$tStamp, $secretKey, true);
        $encodedSignature = base64_encode($signature);
        $opts = array(
                    'http'=>array(
                    'method'=>"GET",
                    'header'=>"Host: new-api.bpjs-kesehatan.go.id\r\n".
                    "Connection: close\r\n".
                    "X-cons-id: ".$data."\r\n".
                    "X-timestamp: ".$tStamp."\r\n".
                    "X-signature: ".$encodedSignature."\r\n".
                    "User-Agent: Mozilla/5.0 (Windows NT 6.3; Win64; x64)\r\n".
                    "Accept: application/json;charset=utf-8\r\n"
                    )
        );
        $context = stream_context_create($opts);
        $result = file_get_contents($url, false, $context);
        return json_decode($result,true);
    }

    
}