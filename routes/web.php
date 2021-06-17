<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use App\LoadData;

Route::get('/', function () {
    return view('welcome');
})->name('hello');

Route::get('/learn',function (){
    (new LoadData())->startLearn(0,2000);
	return 'trueeee';
});

Route::get('/validate', function () {
    return (new LoadData())->testClassification(2001,2226);
});

Route::post('/classify',function (\Illuminate\Http\Request $request){
    $response = (new \App\Bayes())->categorize($request->text);
    return view('result')->with(['category' => $response]);
})->name('classify');
