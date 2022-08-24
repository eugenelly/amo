<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Amo</title>

        <!-- Fonts -->
        <link href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">

        <!-- Styles -->
        <link rel="stylesheet" href={{ URL::asset('css/app.css') }} >

        <style>
            body {
                font-family: 'Nunito', sans-serif;
            }
        </style>
    </head>
    <body class="antialiased">
        <p>Все записи:</p>
        <ol>
            @foreach($leads as $lead)
                <li>{{ $lead->name }}</li>
            @endforeach
        </ol>
        <p>были успешно добавлены в БД</p>
    </body>
</html>
