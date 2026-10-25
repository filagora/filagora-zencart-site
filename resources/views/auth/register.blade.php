@extends('layouts.main')

@section('title', 'Register')
@section('main_class', 'bg-white')

@section('content')
    <div class="mx-auto max-w-[440px] px-6 py-10">
        <header class="mb-5">
            <h1 class="ui-page-title">Register</h1>
        </header>

        @include('auth.partials.register_form', ['turnstileEnabled' => $turnstileEnabled])
    </div>
@endsection
