@extends('layouts.main')

@section('title', 'Forgot Password')
@section('main_class', 'bg-white')

@section('content')
    <div class="mx-auto max-w-[440px] px-6 py-10">
        <header class="mb-5">
            <h1 class="ui-page-title">Forgot Password</h1>
            <p class="ui-page-subtitle mt-2">Enter your email address and we'll send you a link to reset your password.</p>
        </header>

        @include('auth.partials.password_request_form')
    </div>
@endsection
