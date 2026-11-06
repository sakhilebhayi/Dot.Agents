@extends('errors._ecosystem-layout', ['code' => 404, 'state' => 'stop'])

@section('title', "We couldn't find that page")
@section('message', 'The link may be old or mistyped.')

@section('action')
    <a class="button solid" href="/">Go home</a>
@endsection
