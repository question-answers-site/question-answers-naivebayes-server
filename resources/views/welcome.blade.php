<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
    <link rel="stylesheet" href="{{asset('css/app.css')}}">
</head>
<body>
<div class="row">
    <div class="offset-2 col-8">
        <h1 class="text-center py-4" >Hello In Text Classification</h1>
        <form action="{{route('classify')}}" method="Post">

            <div class="form-group">
                <label for="text">enter your text to classify it : </label>
                <input id="text" type="text" name="text" class="form-control">
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary">
            </div>
            {{csrf_field()}}

        </form>
    </div>
</div>
<br>
<hr>
<br>

<script src="{{ asset('js/app.js') }}"></script>

</body>
</html>
