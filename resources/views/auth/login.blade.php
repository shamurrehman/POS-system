<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login · {{ setting('store_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-900 flex items-center justify-center p-4">
<div class="w-full max-w-sm">
    <div class="text-center mb-6 text-white">
        <div class="mx-auto w-14 h-14 rounded-2xl bg-brand-600 flex items-center justify-center shadow-lg"><x-icon name="pill" class="w-7 h-7" /></div>
        <h1 class="mt-3 text-2xl font-bold">{{ setting('store_name') }}</h1>
        <p class="text-sm text-slate-300">Pharmacy Point of Sale</p>
    </div>
    <form method="POST" action="{{ url('/login') }}" class="card card-body space-y-4" autocomplete="off">
        @csrf
        <div>
            <label class="label" for="username">Username</label>
            <input id="username" name="username" value="{{ old('username') }}" class="input" required autofocus autocomplete="username">
        </div>
        <div>
            <label class="label" for="password">Password</label>
            <input id="password" type="password" name="password" class="input" required autocomplete="current-password">
        </div>
        @error('username')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        <button class="btn btn-primary w-full btn-lg">Sign in</button>
    </form>
    <p class="mt-4 text-center text-xs text-slate-400">Running locally on this computer. All data stays on this machine.</p>
</div>
</body>
</html>
