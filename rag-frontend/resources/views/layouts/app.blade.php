<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ChatPDF | Quanti Axionix</title>
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        /* Fixes prose colors to match our theme */
        .prose strong { color: inherit; font-weight: 700; }
        .prose ul { list-style-type: disc; margin-left: 1.25rem; margin-bottom: 1rem; }
        .prose ol { list-style-type: decimal; margin-left: 1.25rem; margin-bottom: 1rem; }
        .prose p { margin-bottom: 0.75rem; }
        .prose p:last-child { margin-bottom: 0; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased font-sans">
    @yield('content')
</body>
</html>
