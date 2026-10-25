@extends('layouts.main')

@section('content')
    <section class="py-10">
        <div class="mx-auto max-w-5xl px-4">
            @include('cms.partials.block', ['block' => $block])
        </div>
    </section>
@endsection
