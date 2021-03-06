<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\ApiController;
use App\Mail\UserCreated;
use App\User;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\Events\AccessTokenCreated;
use Laravel\Passport\Events\RefreshTokenCreated;

class UserController extends ApiController
{

    public function __construct()
    {
        // $this->middleware('client.credentials')->only(['store','resend']);
        $this->middleware('auth:api')->except(['store', 'resend', 'verify', 'login', 'getRefreshToken']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = User::all();
        return $this->showAll($user);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed'
        ];
        $this->validate($request, $rules);

        $data = $request->all();
        $data['password'] = bcrypt($request->password);
        $data['verified'] = User::UNVERIFIED_USER;
        $data['verification_token'] = User::generateVerificationCode();
        $data['admin'] = User::REGULAR_USER;

        $user = User::create($data);
        return $this->showOne($user);
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
        //

        return $this->showOne($user);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        //

        $rules = [
            'email' => 'email|unique:users,' . $user->id,
            'password' => 'min:6|confirmed'
        ];

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('email') && $user->email != $request->email) {
            $user->verified = User::UNVERIFIED_USER;
            $user->verification_token = User::generateVerificationCode();
            $user->email = $request->email;
        }

        if ($request->has('password')) {
            $user->password = bcrypt($request->password);
        }


        if (!$user->isDirty()) {
            return $this->errorResponse('you need to specify a diffenrt value to update code', 422);
        }


        $user->save();

        return $this->showOne($user);


    }

    /**
     * Remove the specified resource from storage.
     *
     * @param User $user
     * @return \Illuminate\Http\Response
     * @internal param int $id
     */
    public function destroy(User $user)
    {

        $user->delete();

        return $this->showOne($user);
    }

    /**
     * @param $token
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function verify($token)
    {

        $user = User::where('verification_token', $token)->firstOrFail();

        $user->verified = User::VERIFIED_USER;
        $user->verification_token = null;


        return $this->showMessage('The account has been verified successfully');

    }

    public function resend(User $user)
    {
        if ($user->isVerified()) {
            return $this->errorResponse("this user is already verified", 409);
        }

        Mail::to($user)->send(new UserCreated($user));
        return $this->showMessage("The verification email send");
    }

    public function login(Request $request)
    {


        $rules = [
            'username' => 'required',
            'password' => 'required'
        ];

        $this->validate($request, $rules);
        $client = new \GuzzleHttp\Client();
        $response = $client->post(route('oauth.token'), [
            'form_params' => [
                'grant_type' => 'password',
                'client_id' => env('client_id'),
                'client_secret' => env('client_secret'),
                'username' => $request->username,
                'password' => $request->password,
            ],
            'http_errors' => false //add this to return errors in json
        ]);

        return $response;

        // return  json_decode((string) $response->getBody(), true);
    }

    public function getRefreshToken(Request $request)
    {
        $rules = [
            'token' => 'required',
        ];

        $this->validate($request, $rules);
        $client = new \GuzzleHttp\Client();
        $response = "";

        // try{
        $response = $client->post(route('oauth.token'), [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $request->token,
                'client_id' => env('client_id'),
                'client_secret' => env('client_secret'),
            ],
            'http_errors' => false //add this to return errors in json
        ]);

        return $response;
//        }catch (\Exception $e){
//
//            return \GuzzleHttp\json_decode(json_encode($e->getMessage()));
//            return $e->getMessage();
//            return json_encode(json_decode($e->getMessage()));
//        }

    }

    public function logout(Request $request)
    {

        $accessToken = Auth::user()->token();

        $refreshToken = DB::table('oauth_refresh_tokens')
            ->where('access_token_id', $accessToken->id)
            ->update([
                'revoked' => true
            ]);

        $accessToken->revoke();

       return  $this->showMessage("you are logout successfully",200);
    }

}
