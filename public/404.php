<?php

http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Not Found</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="shortcut icon" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center text-slate-800">
<div class="text-center px-4">
    <p class="text-6xl font-bold text-slate-300 mb-4">404</p>
    <h1 class="text-xl font-semibold mb-2">Page not found</h1>
    <p class="text-sm text-slate-500 mb-6">The page you are looking for does not exist.</p>
    <a href="/" class="inline-block rounded-lg bg-slate-800 text-white text-sm font-medium px-4 py-2 hover:bg-slate-700">Back to dashboard</a>
</div>
</body>
</html>
