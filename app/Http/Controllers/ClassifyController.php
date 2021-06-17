<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Topic;

class ClassifyController extends Controller
{
    public function naive(Request $request){
//        return view('result');
        return route('showResult');
    }
    public function show(){
        return view('result');
    }
    public function classify(Request $request){
        $predictedCategories = (new \App\Bayes())->categorize($request->text);
		//dd($predictedCategories);
        return response($predictedCategories,200);
    }
}