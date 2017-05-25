<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\ApiController;
use App\Product;
use App\Seller;
use App\Transaction;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ProductBuyerTransactionController extends ApiController
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param Product $product
     * @param User $buyer
     * @return \Illuminate\Http\Response
     * @internal param Seller $seller
     */
    public function store(Request $request,Product $product, User $buyer)
    {
        $rules = [
            'quantity' =>'required|integer|min:1'
        ];
        if($buyer->id == $product->seller_id){
            return $this->errorResponse('The buyer must be different from the seller',409);
        }

        if(!$buyer->isVerified()){
            return $this->errorResponse('The buyer must be a verified user',409);
        }

        if($product->seller->isVerfified()){
            return $this->errorResponse('The seller must be a verified user',409);
        }

        if($product->isAvaiable()){
            return $this->errorResponse('the product is not available',409);
        }

        if($product->quantity < $request->quantity){
            return $this->errorResponse('THe product doest not have a maximum quantity',409);
        }

        return DB::transaction(function() use($request,$product,$buyer){
           $product->quantity =  $request->quantity;
           $product->save();

           $transaction = Transaction::create([
               'quantity' => $request->quantity,
               'buyer_id' =>$buyer->id,
               'product_id' =>$product->id

           ]);
           return $this->showOne($transaction,201);
        });


    }


}
