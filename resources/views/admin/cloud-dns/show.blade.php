@extends('layouts.admin')
@section('title', 'Cloud DNS · '.$zone->name)
@section('content')
<div class="px-4 py-6 md:px-8 lg:px-10">@include('cloud-dns.zone')</div>
@endsection
