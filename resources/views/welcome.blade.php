<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }}</title>
        <style>
            :root {
                --yellow: #f6d84b;
                --yellow-deep: #f3c21b;
                --ink: #1b1b18;
            }

            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            body {
                min-height: 100vh;
                display: grid;
                place-items: center;
                background: radial-gradient(circle at 30% 25%, #fff0a6, var(--yellow));
                font-family: "Trebuchet MS", "Segoe UI", sans-serif;
                color: var(--ink);
            }

            .face {
                width: min(70vw, 320px);
                aspect-ratio: 1 / 1;
                position: relative;
            }

            .eye {
                width: 18%;
                aspect-ratio: 1 / 1;
                background: var(--ink);
                border-radius: 50%;
                position: absolute;
                top: 28%;
            }

            .eye.left {
                left: 22%;
            }

            .eye.right {
                right: 22%;
            }

            .smile {
                position: absolute;
                left: 50%;
                top: 52%;
                width: 60%;
                height: 34%;
                border: 10px solid var(--ink);
                border-top: none;
                border-left: none;
                border-right: none;
                border-radius: 0 0 200px 200px;
                transform: translateX(-50%);
            }
        </style>
    </head>
    <body>
        <div class="face" role="img" aria-label="Smiley face">
            <span class="eye left"></span>
            <span class="eye right"></span>
            <span class="smile"></span>
        </div>
    </body>
</html>
