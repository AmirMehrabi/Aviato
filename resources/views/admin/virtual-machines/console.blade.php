@extends('layouts.admin')

@section('title', 'کنسول')

@section('content')
    <div class="px-4 py-6 md:px-8 lg:px-10">
        @include('console.workspace', ['adminConsole' => true])
    </div>
@endsection
