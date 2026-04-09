@extends('layouts.app')
@section('content')
<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white p-10 rounded-3xl border border-gray-100 shadow-xl shadow-gray-200/50">
        <div class="text-center">
            <div class="mx-auto h-12 w-12 bg-indigo-600 rounded-xl flex items-center justify-center text-white font-bold text-2xl shadow-lg shadow-indigo-200">Q</div>
            <h2 class="mt-6 text-3xl font-extrabold text-gray-900 tracking-tight">Create account</h2>
            <p class="mt-2 text-sm text-gray-500">Join Quanti Axionix ChatPDF</p>
        </div>
        
        <form class="mt-8 space-y-6" action="{{ route('signup') }}" method="POST">
            @csrf
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <input name="username" type="text" required class="appearance-none rounded-t-xl relative block w-full px-4 py-3 border border-gray-200 placeholder-gray-400 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="Username">
                </div>
                <div>
                    <input name="password" type="password" required class="appearance-none rounded-b-xl relative block w-full px-4 py-3 border border-gray-200 placeholder-gray-400 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="Password">
                </div>
            </div>

            @if($errors->any())
                <p class="text-red-500 text-xs mt-2">{{ $errors->first() }}</p>
            @endif

            <div>
                <button type="submit" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all shadow-lg shadow-indigo-100">
                    Create Account
                </button>
            </div>

            <div class="text-center mt-4">
                <a href="{{ route('login') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                    Already have an account? Sign in
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
