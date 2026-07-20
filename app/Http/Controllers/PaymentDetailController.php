<?php

namespace App\Http\Controllers;

use App\Models\PaymentDetail;
use Illuminate\Http\Request;

class PaymentDetailController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $paymentDetails = PaymentDetail::all();
        return view('paymentDetails.index', [
            'paymentDetails' => $paymentDetails,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentDetail $paymentDetail)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PaymentDetail $paymentDetail)
    {
        return view('paymentDetails.form', [
            'paymentDetail' => $paymentDetail,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaymentDetail $paymentDetail)
    {
        $request = $request->all();
        $paymentDetail->description = $request['description'];
        $paymentDetail->save();

        return redirect()->route('payment-details.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentDetail $paymentDetail)
    {
        //
    }
}
