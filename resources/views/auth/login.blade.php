@extends('layouts.main')

@section('title', 'Log In')
@section('main_class', 'bg-white')

@section('content')
    <div class="mx-auto max-w-[440px] px-6 py-10">
        <header class="mb-5">
            <h1 class="ui-page-title">Log In</h1>
        </header>

        @include('auth.partials.login_form', ['pendingUser' => $pendingUser])
    </div>
@endsection
