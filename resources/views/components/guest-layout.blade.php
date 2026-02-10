<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>window.IS_AUTHENTICATED = @json(auth()->check());</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Calm Cat Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        dark: '#0A0A0A',
                        graylight: '#D1D5DB',
                        accent: '#B6A7F2',
                    },
                    fontFamily: {
                        sans: ['"Noto Sans JP"', 'sans-serif'],
                    },
                },
            },
        };
    </script>
    <style>
        #mood-panel.small{
                position:absolute;
                left: 2rem;
                top: 6rem;
                transform:scale(0.75);
                transform-origin: left top;
                transition: all .4s ease;
                z-index: 10;
        }
        #mood-panel.small ul{ flex-direction: row; gap: .75rem; }
        #mood-panel.small .cat-caption{ display:none; }
    </style>
</head>
<body class="bg-dark text-graylight min-h-screen flex flex-col justify-between font-sans">
    {{ $slot }}
</body>
</html>
