<?php

namespace App\Http\Controllers\V1\Auth;

use Exception;
use Throwable;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;

use App\Models\Authentication\AuthMemberModel;
use App\Models\Notification\SmsModel;

class AuthController extends Controller {

    private $date, $return;

    public function __construct(){
        $this->date = date("Y-m-d H:i:s");
        $this->return = [
            "error" => "false",
            "code" => "200",
            "message" => "Berhasil",
            "data" => null
        ];
    }

    public function forget(Request $r, AuthMemberModel $auth){
        try {
            // phone data
            $req = json_decode($r->getContent());
            if(!$req){
                $_tmpres = ['error' => "true", 'code' => '200', 'message' => 'Kiriman data salah'];
                return response()->json(array_merge($this->return, $_tmpres),200);
            }

            // validate phone are existing
            if(!isset($req->handphone)){
                $_tmpres = ['error' => "true", 'code' => '200', 'message' => 'Parameter handphone harus ditambahkan'];
                return response()->json(array_merge($this->return, $_tmpres),200);
            }

            // change to 62 format
            $req->handphone = substr($req->handphone,0,1) == "0"?"62".substr($req->handphone,1):$req->handphone;
            $tmp = $auth->where(['phone' => $req->handphone, 'status' => 1, 'activated' => 1])->get();
            if($tmp->count() == 0){
                $_tmpres = ['error' => "true", 'code' => '200', 'message' => 'Nomor telepon tidak ditemukan atau tidak active'];
                return response()->json(array_merge($this->return, $_tmpres),200);
            }

            // get data user
            $auth = $tmp->first();
            $old_pass = $auth->password;
            $old_status = $auth->lost_password;
            $pass = $this->randPassword(4);

            // change password
            // use md5 encryption
            $auth->password = md5($pass);
            $auth->lost_password = 1;
            $auth->modified = $this->date;

            // save into database
            if(!$auth->save()){
                $_tmpres = ['error' => "true", 'code' => '200', 'message' => 'Gagal menyimpan ke sistem'];
                return response()->json(array_merge($this->return, $_tmpres),200);
            }

            // send to sms gateway
            $sms = new SmsModel;
            $sms->phone_number = $req->handphone;
            $sms->subject = "Lost Password";
            $sms->detail = "Sandi baru anda : ".$pass;
            // $sms->keterangan = "sms send ".date("L ddaysuf of M Y H:i:s", strtotime($this->date));
            $sms->created = $this->date;
            $sms->status = 1;
            $sms->masking = 1;
            if(!$sms->save()){
                // rollback
                $auth->password = $old_pass;
                $auth->lost_password = $old_status;
                $auth->save();

                $_tmpres = ['error' => "true", 'code' => '200', 'message' => 'Gagal mengirim sms'];
                return response()->json(array_merge($this->return, $_tmpres),200);
            }

            // success forget password
            $_tmpres = ['message' => 'Kata sandi kamu berhasil kami reset , Kami telah mengirimkan kata sandi baru ke no telepon kamu. Mohon menunggu proses ini dapat berlangsung dalam 2 menit.'];
            return response()->json(array_merge($this->return, $_tmpres),200);
        } catch(QueryException $e){
            $_tmpres = ['error' => "true", 'code' => '500', 'message' => 'Terjadi kesalahan sistem', 'data' => $e->getLine().": ".$e->getMessage()];
            return response()->json(array_merge($this->return, $_tmpres),500);
        } catch(Exception $e){
            $_tmpres = ['error' => "true", 'code' => '500', 'message' => 'Terjadi kesalahan sistem', 'data' => $e->getLine().": ".$e->getMessage()];
            return response()->json(array_merge($this->return, $_tmpres),500);
        } catch(Throwable $e){
            $_tmpres = ['error' => "true", 'code' => '500', 'message' => 'Terjadi kesalahan sistem', 'data' => $e->getLine().": ".$e->getMessage()];
            return response()->json(array_merge($this->return, $_tmpres),500);
        }
    }

    public function randPassword($len=6){
        $pass = "";
        for($i=0;$i<$len;$i++){
            $pass .= strtolower(substr(dechex(mt_rand( 0, 255 )),0,1));
        }

        return $pass;
    }

}