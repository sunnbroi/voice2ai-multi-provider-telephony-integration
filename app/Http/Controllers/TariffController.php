<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Tariff;
use Illuminate\Http\Request;

class TariffController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $setting_telegram_payment_admin_id = Setting::find(Setting::TELEGRAM_PAYMENT_ADMIN_ID_SETTING_ID);
        $tariffs = Tariff::all();
        return view('tariffs.index', [
            'tariffs' => $tariffs,
            'telegram_payment_admin_id' => $setting_telegram_payment_admin_id->value,
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
    public function show(Tariff $tariff)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tariff $tariff)
    {
        return view('tariffs.form', [
            'tariff' => $tariff,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tariff $tariff)
    {
        $request = $request->all();
        $tariff->name = $request['name'];
        $tariff->price_ru = $request['price_ru'];
        $tariff->price_ua = $request['price_ua'];
        $tariff->price_kz = $request['price_kz'];
        $tariff->save();

        return redirect()->route('tariffs.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tariff $tariff)
    {
        //
    }
}
