<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="{{asset('css/app.css')}}">

    <title>Document</title>
</head>
<body>
<div class="row d-flex" align="center">
    <div class="col-8">
        <h1>Hello In Result</h1>

{{--            <h3>{{$category}}</h3>--}}
        @foreach($category as $val)
                <h3>{{$val}}</h3>
        @endforeach

    </div>
</div>
<script src="{{ asset('js/app.js') }}"></script>

</body>
</html>
