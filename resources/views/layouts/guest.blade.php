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

  <!-- Tailwindカスタムテーマ：モノトーン＋アクセントカラー -->
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
            left: 2rem;      /* ← ここで左寄せ位置を調整 */
            top: 6rem;       /* ← 上下位置。task_listの少し上/横などに合わせて */
            transform:scale(0.75);
            transform-origin: left top; /* ← 左上を基点に縮小 */
            transition: all .4s ease;
            z-index: 10;     /* 他要素に埋もれないように */
        }

        /* 小さくなっても横並びのまま */
        #mood-panel.small ul{
            flex-direction: row;  /* ← columnにしていたのをrowに戻す */
            gap: .75rem;
        }

        /* 小さくなった時はキャプションを隠す */
        #mood-panel.small .cat-caption{
            display:none;
        }
    </style>

</head>

<body class="bg-dark text-graylight min-h-screen flex flex-col justify-between font-sans">
  @yield('content')
</body>
</html>
